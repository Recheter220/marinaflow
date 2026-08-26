<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * `services/auth_service.rs::trocar_senha` — troca voluntária, com o usuário já
 * autenticado. O ramo de "primeiro acesso obrigatório" saiu: quem ainda não
 * definiu senha não consegue chegar até aqui.
 */
class PasswordController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Auth/TrocarSenha');
    }

    public function update(UpdatePasswordRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->validated('password'),
            'primeiro_acesso' => false,
        ]);

        return redirect()->route('dashboard')->with('success', 'Senha atualizada.');
    }
}
