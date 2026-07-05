<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MedicalDocument;
use Illuminate\Support\Facades\Mail;
use App\Mail\RejectedDocumentsReport;

class SendRejectedDocumentsReport extends Command
{
    protected $signature = 'documents:send-rejected-report';
    protected $description = 'Envía un reporte diario de documentos rechazados por correo';

    public function handle()
    {
        $documents = MedicalDocument::with(['type', 'worker', 'creator', 'statusChangedBy'])
            ->where('status', MedicalDocument::STATUS_REJECTED)
            ->whereDate('status_changed_at', today())
            ->get();

        if ($documents->isEmpty()) {
            $this->info('No hay documentos rechazados hoy. No se envía reporte.');
            return;
        }

        $recipients = [
            'svillegas@lacalera.com.pe',
            'amendoza@lacalera.com.pe',
        ];

        Mail::to($recipients)->send(new RejectedDocumentsReport($documents));

        $this->info("Reporte de {$documents->count()} documentos rechazados enviado a: " . implode(', ', $recipients));
    }
}
