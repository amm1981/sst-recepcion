<?php

namespace App\Services;

use App\Mail\RejectedDocumentsReport;
use App\Models\MedicalDocument;
use App\Models\SystemSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class RejectedDocumentsMailSettings
{
    public const RECIPIENTS_KEY = 'rejected_documents_report_recipients';
    public const LAST_SENT_KEY = 'rejected_documents_report_last_sent_at';

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

    public function reportSentToday(): bool
    {
        $setting = SystemSetting::where('key', self::LAST_SENT_KEY)->first();
        $value = is_array($setting?->value) ? ($setting->value['sent_at'] ?? null) : null;

        return $value ? Carbon::parse($value)->isToday() : false;
    }

    public function markReportSent(): void
    {
        SystemSetting::updateOrCreate(
            ['key' => self::LAST_SENT_KEY],
            ['value' => ['sent_at' => now()->toISOString()]],
        );
    }

    public function sendRejectedReport(bool $isTest = false): int
    {
        $recipients = $this->recipients();
        if ($recipients === []) {
            return 0;
        }

        $documents = $this->rejectedToday();
        if ($documents->isEmpty()) {
            return 0;
        }

        Mail::to($recipients)->send(new RejectedDocumentsReport($documents, $isTest));

        if (! $isTest) {
            $this->markReportSent();
        }

        return $documents->count();
    }

    public function resendIfReportWasAlreadySentToday(): int
    {
        if (! $this->reportSentToday()) {
            return 0;
        }

        return $this->sendRejectedReport();
    }

    public function sendRejectedUpdateIfDailyReportWasSent(): int
    {
        if (! $this->reportSentToday()) {
            return 0;
        }

        if (now()->lt(today()->setTime(16, 30))) {
            return 0;
        }

        return $this->sendRejectedReport();
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
