<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\QuestorService;

class QuestorController extends Controller
{
    public function index(): void
    {
        $this->render('questor/index', ['titulo' => 'Exportação Questor']);
    }

    /**
     * Gera e envia o CSV no layout Questor (GET /questor/exportar).
     */
    public function exportar(): void
    {
        $relatorio = QuestorService::relatorio(
            $_GET['data_inicio']    ?? '',
            $_GET['data_fim']       ?? '',
            $_GET['valor_refeicao'] ?? '15'
        );

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . QuestorService::nomeArquivo($relatorio) . '"');

        echo QuestorService::csv($relatorio);

        QuestorService::gerarBackup($relatorio['data_inicio'], $relatorio['data_fim']);

        exit;
    }
}
