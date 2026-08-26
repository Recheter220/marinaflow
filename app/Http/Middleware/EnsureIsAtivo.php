<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Derruba a sessão de quem foi desativado pelo admin depois de já ter entrado —
 * no app Tauri isso só era checado no login, porque a sessão vivia em memória.
 */
class EnsureIsAtivo
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null && ! $request->user()->ativo) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'Usuário desativado. Contate o administrador.']);
        }

        return $next($request);
    }
}
