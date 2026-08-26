<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\EmbarcacaoController;
use App\Http\Controllers\FuncionarioController;
use App\Http\Controllers\ServicoController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);

    Route::get('/esqueci-senha', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/esqueci-senha', [PasswordResetLinkController::class, 'store'])->name('password.email');

    // Destino do link de convite e de redefinição — o mesmo broker atende os dois.
    Route::get('/definir-senha/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/definir-senha', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware(['auth', 'ativo'])->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::redirect('/', '/embarcacoes')->name('dashboard');

    Route::get('/embarcacoes', [EmbarcacaoController::class, 'index'])->name('embarcacoes.index');
    Route::get('/funcionarios', [FuncionarioController::class, 'index'])->name('funcionarios.index');

    Route::get('/servicos/novo', [ServicoController::class, 'create'])->name('servicos.create');
    Route::post('/servicos', [ServicoController::class, 'store'])->name('servicos.store');
    Route::get('/historico', [ServicoController::class, 'index'])->name('servicos.index');
    Route::put('/servicos/{servico}', [ServicoController::class, 'update'])->name('servicos.update');

    Route::get('/trocar-senha', [PasswordController::class, 'edit'])->name('senha.edit');
    Route::put('/trocar-senha', [PasswordController::class, 'update'])->name('senha.update');

    // `guard::require_admin`
    Route::middleware('admin')->group(function () {
        Route::post('/embarcacoes', [EmbarcacaoController::class, 'store'])->name('embarcacoes.store');
        Route::put('/embarcacoes/{embarcacao}', [EmbarcacaoController::class, 'update'])->name('embarcacoes.update');

        Route::post('/funcionarios', [FuncionarioController::class, 'store'])->name('funcionarios.store');
        Route::put('/funcionarios/{funcionario}', [FuncionarioController::class, 'update'])->name('funcionarios.update');

        Route::get('/usuarios', [UserController::class, 'index'])->name('usuarios.index');
        Route::post('/usuarios', [UserController::class, 'store'])->name('usuarios.store');
        Route::put('/usuarios/{user}', [UserController::class, 'update'])->name('usuarios.update');
        Route::delete('/usuarios/{user}', [UserController::class, 'destroy'])->name('usuarios.destroy');
        Route::patch('/usuarios/{user}/ativo', [UserController::class, 'toggleAtivo'])->name('usuarios.ativo');
        Route::post('/usuarios/{user}/convite', [UserController::class, 'reenviarConvite'])->name('usuarios.convite');
    });
});
