<?php

namespace App\Services;

use App\Models\MedicalDocument;
use App\Models\SystemSetting;
use Illuminate\Support\Collection;

class RejectedDocumentsMailSettings
{
    public const RECIPIENTS_KEY = 'rejected_documents_report_recipients';

    public function recipients(): array
    {
        $setting = SystemSetting::where('key', self::RECIPIENTS_KEY)->first();
        $recipients = is_array($setting?->value) ? $setting->value : [];

        return collect($recipients)
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
    }

    public function saveRecipients(array $recipients): array
    {
        $normalized = collect($recipients)
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();

        SystemSetting::updateOrCreate(
            ['key' => self::RECIPIENTS_KEY],
            ['value' => $normalized],
        );

        return $normalized;
    }

    public function rejectedToday(): Collection
    {
        return MedicalDocument::with(['type', 'worker', 'creator', 'statusChangedBy', 'history'])
            ->where('status', MedicalDocument::STATUS_REJECTED)
            ->whereDate('status_changed_at', today())
            ->latest('status_changed_at')
            ->get();
    }

    public function sampleDocuments(): Collection
    {
        return collect([
            (object) [
                'id' => 1001,
                'type' => (object) ['name' => 'Descanso Medico'],
                'worker' => (object) [
                    'first_name' => 'Carlos',
                    'last_name' => 'Ramirez Torres',
                    'dni' => '12345678',
                ],
                'creator' => (object) ['name' => 'Usuario RRHH'],
                'statusChangedBy' => (object) ['name' => 'Usuario SST'],
                'status_changed_at' => now(),
                'history' => collect([(object) ['to_status' => MedicalDocument::STATUS_REJECTED, 'observation' => 'Documento ilegible.']]),
            ],
            (object) [
                'id' => 1002,
                'type' => (object) ['name' => 'Atencion Medica'],
                'worker' => (object) [
                    'first_name' => 'Lucia',
                    'last_name' => 'Vargas Medina',
                    'dni' => '87654321',
                ],
                'creator' => (object) ['name' => 'Usuario RRHH'],
                'statusChangedBy' => (object) ['name' => 'Usuario SST'],
                'status_changed_at' => now(),
                'history' => collect([(object) ['to_status' => MedicalDocument::STATUS_REJECTED, 'observation' => 'Falta firma del trabajador.']]),
            ],
        ]);
    }
}
