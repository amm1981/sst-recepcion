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

    public function __construct(Collection $documents)
    {
        $this->documents = $documents;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reporte de Documentos Rechazados - ' . now()->format('d/m/Y'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.rejected-documents-report',
        );
    }
}
