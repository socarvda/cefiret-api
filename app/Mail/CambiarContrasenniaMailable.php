<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CambiarContrasenniaMailable extends Mailable
{
    use Queueable, SerializesModels;

    public $nombreCompleto;
    public $token;

    public function __construct($nombreCompleto, $token)
    {
        $this->nombreCompleto = $nombreCompleto;
        $this->token = $token;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(env('MAIL_FROM_ADDRESS'), 'CEFIRET - Sistema de Rehabilitación'),
            subject: 'Cambiar contraseña',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'ResetPasswordViews.mensajecambiarcontrasennia',
            with: [
                'nombreCompleto' => $this->nombreCompleto,
                'token' => $this->token,
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
