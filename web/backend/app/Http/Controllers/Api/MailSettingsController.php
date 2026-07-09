<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\RejectedDocumentsReport;
use App\Services\RejectedDocumentsMailSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class MailSettingsController extends Controller
{
    public function show(RejectedDocumentsMailSettings $settings)
    {
        return response()->json([
            'rejected_report_recipients' => $settings->recipients(),
        ]);
    }

    public function update(Request $request, RejectedDocumentsMailSettings $settings)
    {
        $data = $request->validate([
            'rejected_report_recipients' => ['array'],
            'rejected_report_recipients.*' => ['required', 'email', 'max:191'],
        ]);

        return response()->json([
            'message' => 'Configuracion de correos actualizada.',
            'rejected_report_recipients' => $settings->saveRecipients($data['rejected_report_recipients'] ?? []),
        ]);
    }

    public function sendTest(Request $request, RejectedDocumentsMailSettings $settings)
    {
        $data = $request->validate([
            'recipients' => ['nullable', 'array'],
            'recipients.*' => ['required', 'email', 'max:191'],
        ]);

        $recipients = $data['recipients'] ?? $settings->recipients();
        abort_if($recipients === [], 422, 'Debe configurar o ingresar al menos un correo destinatario.');

        $documents = $settings->rejectedToday();
        if ($documents->isEmpty()) {
            $documents = $settings->sampleDocuments();
        }

        Mail::to($recipients)->send(new RejectedDocumentsReport($documents, true));

        return response()->json([
            'message' => 'Correo de prueba enviado.',
            'recipients' => $recipients,
            'documents_count' => $documents->count(),
        ]);
    }
}
