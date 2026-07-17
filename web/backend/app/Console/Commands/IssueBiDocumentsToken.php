<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class IssueBiDocumentsToken extends Command
{
    protected $signature = 'bi:issue-documents-token
        {--user= : ID, usuario o correo del propietario del token}
        {--name=bi-documents : Nombre interno del token}
        {--days=365 : Dias de vigencia}
        {--revoke-existing : Revoca tokens existentes con el mismo nombre para el usuario}';

    protected $description = 'Genera un Bearer token de solo lectura para la API BI de documentos.';

    public function handle(): int
    {
        $user = $this->resolveUser();

        if (! $user) {
            $this->error('No se encontro un usuario activo para emitir el token.');

            return self::FAILURE;
        }

        $days = max(1, min((int) $this->option('days'), 3660));
        $name = trim((string) $this->option('name')) ?: 'bi-documents';
        $expiresAt = now()->addDays($days);

        if ($this->option('revoke-existing')) {
            $user->tokens()->where('name', $name)->delete();
        }

        $token = $user->createToken($name, ['bi.documents.read'], $expiresAt);

        $this->info('Token BI generado correctamente.');
        $this->line('Usuario: ' . $user->name . ' <' . $user->email . '>');
        $this->line('Nombre: ' . $name);
        $this->line('Vence: ' . $expiresAt->timezone('America/Lima')->format('Y-m-d H:i:s') . ' America/Lima');
        $this->newLine();
        $this->warn('Guarde este token ahora. No se podra volver a ver completo desde la base de datos.');
        $this->line($token->plainTextToken);

        return self::SUCCESS;
    }

    private function resolveUser(): ?User
    {
        $identifier = trim((string) $this->option('user'));

        if ($identifier !== '') {
            return User::query()
                ->where('is_active', true)
                ->where(function ($query) use ($identifier) {
                    $query->where('id', $identifier)
                        ->orWhere('user', $identifier)
                        ->orWhere('email', $identifier);
                })
                ->first();
        }

        return User::query()
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->where('code', 'ADMIN'))
            ->orderBy('id')
            ->first();
    }
}
