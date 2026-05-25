<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Confirma tu correo</title>
</head>
<body style="margin:0;padding:0;background:#f4f6f8;font-family:Arial, Helvetica, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f8;padding:30px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;">
                    <tr>
                        <td style="background:#1e3a5f;color:#ffffff;padding:24px;text-align:center;">
                            <h1 style="margin:0;font-size:24px;">CEFIRET</h1>
                            <p style="margin:8px 0 0;">Centro de Fisioterapia y Rehabilitación</p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:30px;color:#333333;">
                            <h2 style="margin-top:0;color:#1e3a5f;">Confirma tu correo</h2>

                            <p>Hola <strong>{{ $nombreCompleto }}</strong>,</p>

                            <p>
                                Tu cuenta fue registrada correctamente en el sistema CEFIRET.
                                Para activar tu usuario, confirma tu correo dando clic en el siguiente botón:
                            </p>

                            <p style="text-align:center;margin:32px 0;">
                                <a href="{{ url('/email/confirm/' . $token) }}"
                                   style="background:#0d6efd;color:#ffffff;text-decoration:none;padding:14px 24px;border-radius:8px;display:inline-block;font-weight:bold;">
                                    Confirmar correo
                                </a>
                            </p>

                            <p>
                                Si el botón no funciona, copia y pega este enlace en tu navegador:
                            </p>

                            <p style="word-break:break-all;color:#0d6efd;">
                                {{ url('/email/confirm/' . $token) }}
                            </p>

                            <p style="margin-top:30px;color:#666666;font-size:14px;">
                                Si tú no solicitaste este registro, puedes ignorar este correo.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="background:#f1f5f9;color:#666666;text-align:center;padding:18px;font-size:13px;">
                            Este correo fue enviado automáticamente por CEFIRET.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>