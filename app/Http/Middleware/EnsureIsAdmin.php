<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Equivalente a `auth/guard.rs::require_admin`.
 *
 * `require_authenticated` é coberto pelo middleware `auth` embutido, então aqui
 * só resta a verificação de papel.
 */
class EnsureIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless(
            $user !== null && $user->isAdmin(),
            403,
            'Acesso negado. Role necessária: admin',
        );

        return $next($request);
    }
}
