<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Uso: ->middleware('perfil:admin,rh,dp') */
class VerificaPerfil
{
    public function handle(Request $request, Closure $next, string ...$perfis)
    {
        $user = $request->user();
        abort_unless($user && $user->temPerfil(...$perfis), 403, 'Seu perfil não tem acesso a esta área.');
        return $next($request);
    }
}
