<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (! $request->user()?->canDo($permission)) {
            abort(403, 'No tiene permisos para realizar esta accion.');
        }

        return $next($request);
    }
}
