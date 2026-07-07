<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\PainelService;

class PainelController extends Controller
{
    public function index(): void
    {
        $usuario = $_SESSION['usuario'];
        $dados   = PainelService::montarSemana($usuario);

        $this->render('painel/index', [
            'titulo'  => 'Painel',
            'usuario' => $usuario,
            ...$dados,
        ]);
    }
}
