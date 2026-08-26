<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Funcionario;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * `commands/auth_commands.rs` — parte administrativa. Todas as rotas passam
 * pelo middleware `admin`, que faz o papel de `guard::require_admin`.
 */
class UserController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function index(): Response
    {
        return Inertia::render('Usuarios', [
            'usuarios' => User::query()
                ->with('funcionario:id,nome')
                ->orderBy('email')
                ->get()
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => $u->role,
                    'ativo' => $u->ativo,
                    'primeiro_acesso' => $u->primeiro_acesso,
                    'convite_pendente' => $u->convitePendente(),
                    'funcionario_id' => $u->funcionario_id,
                    'funcionario_nome' => $u->funcionario?->nome,
                ]),
            'funcionarios' => Funcionario::query()
                ->ativos()
                ->orderBy('nome')
                ->get(['id', 'nome']),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = $this->users->criar($request->validated());

        return back()->with('success', "Convite enviado para {$user->email}.");
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->users->atualizar($user, $request->validated());

        return back()->with('success', 'Usuário atualizado.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $email = $user->email;

        $this->users->excluir($user, $request->user());

        return back()->with('success', "Usuário {$email} excluído.");
    }

    public function toggleAtivo(Request $request, User $user): RedirectResponse
    {
        $ativo = $request->boolean('ativo');

        $this->users->definirAtivo($user, $request->user(), $ativo);

        return back()->with('success', sprintf(
            'Usuário %s %s.',
            $user->email,
            $ativo ? 'ativado' : 'desativado',
        ));
    }

    /** Substitui `cmd_resetar_senha`: reenvia o link, sem senha em texto claro. */
    public function reenviarConvite(Request $request, User $user): RedirectResponse
    {
        $this->users->reenviarLink($user, $request->user());

        return back()->with('success', "Link de definição de senha reenviado para {$user->email}.");
    }
}
