<?php

namespace App\Console\Commands;

use App\Mail\RejectedDocumentsReport;
use App\Services\RejectedDocumentsMailSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendRejectedDocumentsReport extends Command
{
    protected $signature = 'documents:send-rejected-report';
    protected $description = 'Envia un reporte diario de documentos rechazados por correo';

    public function handle(RejectedDocumentsMailSettings $settings): int
    {
        $documents = $settings->rejectedToday();

        if ($documents->isEmpty()) {
            $this->info('No hay documentos rechazados hoy. No se envia reporte.');
            return self::SUCCESS;
        }

        $recipients = $settings->recipients();

        if ($recipients === []) {
            $this->warn('No hay destinatarios configurados. No se envia reporte.');
            return self::SUCCESS;
        }

        Mail::to($recipients)->send(new RejectedDocumentsReport($documents));

        $this->info("Reporte de {$documents->count()} documentos rechazados enviado a: " . implode(', ', $recipients));

        return self::SUCCESS;
    }
}
