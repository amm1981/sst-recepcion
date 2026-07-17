<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBiDocumentsToken
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->is_active) {
            abort(403, 'Usuario propietario del token inactivo.');
        }

        $token = $request->user()?->currentAccessToken();
        $abilities = is_object($token) ? ($token->abilities ?? []) : [];

        if (! is_array($abilities) || ! in_array('bi.documents.read', $abilities, true)) {
            abort(403, 'Token no autorizado para la API BI de documentos.');
        }

        return $next($request);
    }
}
