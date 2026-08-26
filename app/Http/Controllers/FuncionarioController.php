<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFuncionarioRequest;
use App\Http\Requests\UpdateFuncionarioRequest;
use App\Models\Funcionario;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** `commands/funcionario_commands.rs`. */
class FuncionarioController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function index(Request $request): Response
    {
        $termo = $request->string('busca')->toString();

        return Inertia::render('Funcionarios', [
            'funcionarios' => Funcionario::query()
                ->search($termo)
                ->with('user:id,funcionario_id,email')
                ->orderBy('nome')
                ->get()
                ->map(fn (Funcionario $f): array => [
                    'id' => $f->id,
                    'nome' => $f->nome,
                    'cargo' => $f->cargo,
                    'telefone' => $f->telefone,
                    'ativo' => $f->ativo,
                    'email' => $f->user?->email,
                ]),
            'filtros' => ['busca' => $termo],
        ]);
    }

    /**
     * Cria o funcionário e, se um e-mail for informado, o usuário correspondente
     * com convite — o equivalente seguro do usuário auto-criado com senha
     * temporária em `funcionario_service::criar`.
     */
    public function store(StoreFuncionarioRequest $request): RedirectResponse
    {
        $dados = $request->validated();
        $email = $dados['email'] ?? null;
        unset($dados['email']);

        $funcionario = Funcionario::query()->create($dados);

        if ($email === null) {
            return back()->with('success', "Funcionário {$funcionario->nome} cadastrado.");
        }

        $this->users->criar([
            'name' => $funcionario->nome,
            'email' => $email,
            'role' => User::ROLE_FUNCIONARIO,
            'funcionario_id' => $funcionario->id,
        ]);

        return back()->with('success', "Funcionário cadastrado e convite enviado para {$email}.");
    }

    public function update(UpdateFuncionarioRequest $request, Funcionario $funcionario): RedirectResponse
    {
        $funcionario->update($request->validated());

        return back()->with('success', 'Funcionário atualizado.');
    }
}
