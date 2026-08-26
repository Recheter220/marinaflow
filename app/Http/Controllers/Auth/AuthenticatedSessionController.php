<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** `services/auth_service.rs::login` + o `logout` de `auth_commands.rs`. */
class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'status' => session('status'),
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->validated();

        // Um convite ainda não concluído deixa `password` nulo. Sem esta checagem
        // o hasher receberia null; e a mensagem genérica não ajudaria o usuário,
        // que precisa saber que deve procurar o e-mail de convite.
        $user = User::query()->where('email', $credentials['email'])->first();

        if ($user !== null && $user->convitePendente()) {
            throw ValidationException::withMessages([
                'email' => 'Este acesso ainda não foi ativado. Use o link enviado por e-mail para definir sua senha.',
            ]);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'E-mail ou senha inválidos.',
            ]);
        }

        // Checado depois da senha, e não antes como no Rust, para não revelar a
        // situação da conta a quem não provou ser dono dela.
        if (! $request->user()->ativo) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'Usuário desativado. Contate o administrador.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
