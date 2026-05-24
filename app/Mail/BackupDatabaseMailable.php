<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BackupDatabaseMailable extends Mailable
{
    use Queueable, SerializesModels;

    public string $filePath;
    public string $fileName;

    public function __construct(string $filePath, string $fileName)
    {
        $this->filePath = $filePath;
        $this->fileName = $fileName;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(env('MAIL_FROM_ADDRESS'), env('MAIL_FROM_NAME', 'CEFIRET')),
            subject: 'Respaldo automático de base de datos - CEFIRET',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.backup_database',
            with: [
                'fileName' => $this->fileName,
                'fecha' => now()->format('d/m/Y H:i:s'),
            ]
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromPath($this->filePath)
                ->as($this->fileName)
                ->withMime('application/sql'),
        ];
    }
}