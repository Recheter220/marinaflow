<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Esqueci minha senha". Reaproveita o mesmo broker do convite — daí valer a
 * pena expor a tela, já que o mecanismo teve de ser construído de qualquer forma.
 */
class PasswordResetLinkController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/EsqueciSenha', [
            'status' => session('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        // Resposta deliberadamente idêntica exista ou não a conta, para não
        // transformar a tela em um verificador de e-mails cadastrados.
        Password::sendResetLink($request->only('email'));

        return back()->with('status', 'Se houver uma conta com este e-mail, o link de redefinição foi enviado.');
    }
}
