<?php

namespace App\Services;

use App\Mail\RejectedDocumentsReport;
use App\Models\MedicalDocument;
use App\Models\SystemSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RejectedDocumentsMailSettings
{
    public const RECIPIENTS_KEY = 'rejected_documents_report_recipients';
    public const LAST_SENT_KEY = 'rejected_documents_report_last_sent_at';
    private const REPORT_TIMEZONE = 'America/Lima';

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
        [$startsAt, $endsAt] = $this->reportDayBounds();

        return MedicalDocument::with(['type', 'worker', 'creator', 'statusChangedBy', 'history'])
            ->where('status', MedicalDocument::STATUS_REJECTED)
            ->whereBetween('status_changed_at', [$startsAt, $endsAt])
            ->latest('status_changed_at')
            ->get();
    }

    public function reportSentToday(): bool
    {
        $setting = SystemSetting::where('key', self::LAST_SENT_KEY)->first();
        $value = is_array($setting?->value) ? ($setting->value['sent_at'] ?? null) : null;

        return $value
            ? Carbon::parse($value)->setTimezone(self::REPORT_TIMEZONE)->isSameDay($this->reportNow())
            : false;
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
            Log::info('Reporte de documentos rechazados omitido: no hay destinatarios configurados.', [
                'is_test' => $isTest,
            ]);

            return 0;
        }

        $documents = $this->rejectedToday();
        if ($documents->isEmpty()) {
            Log::info('Reporte de documentos rechazados omitido: no hay documentos rechazados del dia.', [
                'is_test' => $isTest,
            ]);

            return 0;
        }

        Mail::to($recipients)->send(new RejectedDocumentsReport($documents, $isTest));

        if (! $isTest) {
            $this->markReportSent();
        }

        Log::info('Reporte de documentos rechazados enviado.', [
            'is_test' => $isTest,
            'documents_count' => $documents->count(),
            'recipients_count' => count($recipients),
            'report_timezone' => self::REPORT_TIMEZONE,
        ]);

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
        return $this->sendRejectedUpdateAfterDailyReportWindow();
    }

    public function sendRejectedUpdateAfterDailyReportWindow(): int
    {
        $now = $this->reportNow();
        $windowStartsAt = $this->reportToday()->setTime(16, 30);

        if ($now->lt($windowStartsAt)) {
            Log::info('Actualizacion de documentos rechazados omitida: fuera de ventana posterior al reporte diario.', [
                'window_starts_at' => $windowStartsAt->toDateTimeString(),
                'current_time' => $now->toDateTimeString(),
                'report_timezone' => self::REPORT_TIMEZONE,
            ]);

            return 0;
        }

        return $this->sendRejectedReport();
    }

    private function reportNow(): Carbon
    {
        return now(self::REPORT_TIMEZONE);
    }

    private function reportToday(): Carbon
    {
        return Carbon::today(self::REPORT_TIMEZONE);
    }

    private function reportDayBounds(): array
    {
        $storageTimezone = config('app.timezone', 'UTC');
        $startsAt = $this->reportToday()->startOfDay();
        $endsAt = $this->reportToday()->endOfDay();

        return [
            $startsAt->copy()->setTimezone($storageTimezone),
            $endsAt->copy()->setTimezone($storageTimezone),
        ];
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
