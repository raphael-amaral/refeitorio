<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Registro;

class ScannerController extends Controller
{
    /**
     * Página do scanner de crachá (GET /scanner).
     */
    public function index(): void
    {
        $this->render('scanner/index', ['titulo' => 'Scanner']);
    }

    /**
     * Processa uma matrícula lida pelo scanner (POST /scanner/processar).
     * Retorna JSON.
     */
    public function processar(): void
    {
        $matricula = trim($_POST['matricula'] ?? '');

        if ($matricula === '') {
            $this->json(['ok' => false, 'erro' => 'Matrícula não informada.'], 422);
        }

        $registro = Registro::buscarAgendadoHoje($matricula);

        if (!$registro) {
            $this->json(['ok' => false, 'erro' => 'Nenhum agendamento encontrado para hoje.'], 404);
        }

        Registro::concluir((int) $registro['id']);

        $this->json([
            'ok'       => true,
            'nome'     => $registro['nome_usuario'],
            'refeicao' => $registro['tipo_refeicao'] === 'almoco' ? 'Almoço' : 'Jantar',
        ]);
    }
}
