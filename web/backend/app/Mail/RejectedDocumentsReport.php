<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class RejectedDocumentsReport extends Mailable
{
    use Queueable, SerializesModels;

    public Collection $documents;
    public bool $isTest;

    public function __construct(Collection $documents, bool $isTest = false)
    {
        $this->documents = $documents;
        $this->isTest = $isTest;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: ($this->isTest ? '[Prueba] ' : '') . 'Reporte de Documentos Rechazados - ' . now()->format('d/m/Y'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.rejected-documents-report',
        );
    }
}
