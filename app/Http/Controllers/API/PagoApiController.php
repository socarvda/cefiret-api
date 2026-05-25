<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stripe\StripeClient;

class PagoApiController extends Controller
{
    private function authUser(Request $request)
    {
        return $request->attributes->get('auth_user');
    }

    private function esAdminOFisio($usuario): bool
    {
        return in_array((int) ($usuario->id_tipo_usuario ?? 0), [1, 2], true);
    }

    private function esPaciente($usuario): bool
    {
        return (int) ($usuario->id_tipo_usuario ?? 0) === 3;
    }

    private function puedeConsultarPaciente(Request $request, int $idPaciente): bool
    {
        $usuario = $this->authUser($request);

        if (!$usuario) {
            return false;
        }

        if ($this->esAdminOFisio($usuario)) {
            return true;
        }

        return $this->esPaciente($usuario) && (int) $usuario->id_usuario === $idPaciente;
    }

    public function index(Request $request)
    {
        $usuario = $this->authUser($request);

        if (!$this->esAdminOFisio($usuario)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para consultar pagos.'
            ], 403);
        }

        $pagos = DB::table('pago')
            ->join('usuario', 'pago.id_usuario', '=', 'usuario.id_usuario')
            ->select(
                'pago.id_pago',
                'pago.id_usuario',
                'pago.monto',
                'pago.fecha_pago',
                'pago.metodo_pago',
                'pago.detalle',
                'usuario.nombre',
                'usuario.apaterno',
                'usuario.amaterno',
                'usuario.correo'
            )
            ->orderByDesc('pago.id_pago')
            ->get();

        return response()->json([
            'success' => true,
            'pagos' => $pagos
        ]);
    }

    public function paciente(Request $request, $id)
    {
        $idPaciente = (int) $id;

        if (!$this->puedeConsultarPaciente($request, $idPaciente)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para consultar esta información.'
            ], 403);
        }

        $pagos = DB::table('pago')
            ->where('id_usuario', $idPaciente)
            ->orderByDesc('id_pago')
            ->get();

        return response()->json([
            'success' => true,
            'pagos' => $pagos
        ]);
    }

    public function store(Request $request)
    {
        $usuario = $this->authUser($request);

        if (!$this->esAdminOFisio($usuario)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para registrar pagos.'
            ], 403);
        }

        $request->validate([
            'id_usuario' => 'required|integer|exists:usuario,id_usuario',
            'monto' => 'required|numeric|min:1',
            'detalle' => 'required|string|max:500',
        ], [
            'id_usuario.required' => 'Selecciona un paciente.',
            'id_usuario.exists' => 'El paciente seleccionado no existe.',
            'monto.required' => 'Ingresa el monto.',
            'monto.numeric' => 'El monto debe ser numérico.',
            'monto.min' => 'El monto debe ser mayor a 0.',
            'detalle.required' => 'Ingresa el detalle del pago.',
        ]);

        $paciente = DB::table('usuario')
            ->where('id_usuario', $request->id_usuario)
            ->where('id_tipo_usuario', 3)
            ->first();

        if (!$paciente) {
            return response()->json([
                'success' => false,
                'message' => 'Solo se pueden registrar pagos a pacientes.'
            ], 422);
        }

        $idPago = DB::table('pago')->insertGetId([
            'id_usuario' => $request->id_usuario,
            'monto' => $request->monto,
            'fecha_pago' => now()->toDateString(),
            'metodo_pago' => 'Stripe pendiente',
            'detalle' => 'Pendiente Stripe | ' . $request->detalle,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pago pendiente registrado correctamente.',
            'id_pago' => $idPago
        ], 201);
    }

    public function crearCheckout(Request $request, $idPago)
    {
        $usuario = $this->authUser($request);
        $idPago = (int) $idPago;

        $pago = DB::table('pago')
            ->join('usuario', 'pago.id_usuario', '=', 'usuario.id_usuario')
            ->select(
                'pago.*',
                'usuario.nombre',
                'usuario.apaterno',
                'usuario.amaterno',
                'usuario.correo'
            )
            ->where('pago.id_pago', $idPago)
            ->first();

        if (!$pago) {
            return response()->json([
                'success' => false,
                'message' => 'Pago no encontrado.'
            ], 404);
        }

        if (!$this->esAdminOFisio($usuario)) {
            if (!$this->esPaciente($usuario) || (int) $usuario->id_usuario !== (int) $pago->id_usuario) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para pagar este registro.'
                ], 403);
            }
        }

        if (str_contains(strtolower((string) $pago->metodo_pago), 'stripe') &&
            str_contains(strtolower((string) $pago->detalle), 'pagado stripe')) {
            return response()->json([
                'success' => false,
                'message' => 'Este pago ya está marcado como pagado.'
            ], 422);
        }

        $stripeSecret = env('STRIPE_SECRET_KEY');

        if (!$stripeSecret) {
            return response()->json([
                'success' => false,
                'message' => 'Stripe no está configurado en el servidor.'
            ], 500);
        }

        $frontendUrl = rtrim(env('FRONTEND_URL', 'http://127.0.0.1:5500'), '/');
        $currency = strtolower(env('STRIPE_CURRENCY', 'mxn'));

        $stripe = new StripeClient($stripeSecret);

        $montoCentavos = (int) round(((float) $pago->monto) * 100);

        try {
            $session = $stripe->checkout->sessions->create([
                'mode' => 'payment',
                'payment_method_types' => ['card'],
                'customer_email' => $pago->correo,
                'client_reference_id' => (string) $pago->id_pago,
                'success_url' => $frontendUrl . '/views/pagos/success.html?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $frontendUrl . '/views/pagos/cancel.html?id_pago=' . $pago->id_pago,
                'metadata' => [
                    'id_pago' => (string) $pago->id_pago,
                    'id_usuario' => (string) $pago->id_usuario,
                ],
                'line_items' => [
                    [
                        'quantity' => 1,
                        'price_data' => [
                            'currency' => $currency,
                            'unit_amount' => $montoCentavos,
                            'product_data' => [
                                'name' => 'Pago CEFIRET',
                                'description' => mb_substr((string) $pago->detalle, 0, 250),
                            ],
                        ],
                    ],
                ],
            ]);

            DB::table('pago')
                ->where('id_pago', $pago->id_pago)
                ->update([
                    'metodo_pago' => 'Stripe pendiente',
                    'detalle' => $this->limpiarDetalleStripe((string) $pago->detalle) . ' | Sesión Stripe: ' . $session->id,
                ]);

            return response()->json([
                'success' => true,
                'checkout_url' => $session->url,
                'session_id' => $session->id
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear sesión de Stripe: ' . $e->getMessage()
            ], 500);
        }
    }

    public function confirmarStripe(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string'
        ], [
            'session_id.required' => 'No se recibió la sesión de Stripe.'
        ]);

        $stripeSecret = env('STRIPE_SECRET_KEY');

        if (!$stripeSecret) {
            return response()->json([
                'success' => false,
                'message' => 'Stripe no está configurado en el servidor.'
            ], 500);
        }

        $stripe = new StripeClient($stripeSecret);

        try {
            $session = $stripe->checkout->sessions->retrieve($request->session_id);

            $idPago = $session->metadata->id_pago ?? $session->client_reference_id ?? null;

            if (!$idPago) {
                return response()->json([
                    'success' => false,
                    'message' => 'La sesión de Stripe no contiene el ID del pago.'
                ], 422);
            }

            $pago = DB::table('pago')
                ->where('id_pago', $idPago)
                ->first();

            if (!$pago) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pago no encontrado.'
                ], 404);
            }

            if ($session->payment_status === 'paid') {
                DB::table('pago')
                    ->where('id_pago', $idPago)
                    ->update([
                        'fecha_pago' => now()->toDateString(),
                        'metodo_pago' => 'Stripe',
                        'detalle' => $this->limpiarDetalleStripe((string) $pago->detalle)
                            . ' | Pagado Stripe | Sesión Stripe: ' . $session->id,
                    ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Pago confirmado correctamente.',
                    'id_pago' => (int) $idPago
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'El pago todavía no aparece como pagado en Stripe.'
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al confirmar pago con Stripe: ' . $e->getMessage()
            ], 500);
        }
    }

    private function limpiarDetalleStripe(string $detalle): string
    {
        $detalle = preg_replace('/\s*\|\s*Sesión Stripe:\s*cs_[^\s|]+/i', '', $detalle);
        $detalle = str_replace('Pendiente Stripe | ', '', $detalle);
        $detalle = str_replace('Pagado Stripe | ', '', $detalle);

        return trim($detalle);
    }
}