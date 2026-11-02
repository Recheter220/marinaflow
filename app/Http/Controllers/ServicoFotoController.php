<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServicoFotoRequest;
use App\Models\Servico;
use App\Models\ServicoFoto;
use App\Services\ServicoFotoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** MF-04 — fotos do serviço executado. */
class ServicoFotoController extends Controller
{
    public function __construct(private readonly ServicoFotoService $fotos) {}

    public function store(StoreServicoFotoRequest $request, Servico $servico): RedirectResponse
    {
        $anexadas = $this->fotos->anexar($servico, $request->file('fotos'), $request->user());

        return back()->with('success', $anexadas->count() === 1 ? 'Foto anexada.' : "{$anexadas->count()} fotos anexadas.");
    }

    public function destroy(Request $request, ServicoFoto $foto): RedirectResponse
    {
        $this->fotos->remover($foto, $request->user());

        return back()->with('success', 'Foto removida.');
    }

    /** Link estável para a foto: cada acesso gera uma URL assinada nova. */
    public function show(Request $request, ServicoFoto $foto): RedirectResponse
    {
        return redirect()->away($this->fotos->urlTemporaria($foto, $request->user()));
    }
}
