<?php

namespace App\Http\Controllers;

use App\Services\MeteorologiaService;
use Inertia\Inertia;
use Inertia\Response;

class MeteorologiaController extends Controller
{
    public function index(MeteorologiaService $meteorologia): Response
    {
        return Inertia::render('Meteorologia', ['previsao' => $meteorologia->previsao()]);
    }
}
