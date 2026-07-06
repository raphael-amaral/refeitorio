<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\QuestorExportacao;
use App\Services\QuestorService;

class QuestorController extends Controller
{
    public function index(): void
    {
        $ultimaExportacao = QuestorExportacao::ultima();
        $dataInicioPadrao = $ultimaExportacao
            ? date('Y-m-d', strtotime($ultimaExportacao['data_fim'] . ' +1 day'))
            : date('Y-m-01');

        $this->render('questor/index', [
            'titulo'           => 'Exportação Questor',
            'ultimaExportacao' => $ultimaExportacao,
            'dataInicioPadrao' => $dataInicioPadrao,
        ]);
    }

    /**
     * Gera e envia o CSV no layout Questor (GET /questor/exportar).
     */
    public function exportar(): void
    {
        $relatorio = QuestorService::relatorio(
            $_GET['data_inicio'] ?? '',
            $_GET['data_fim']    ?? '',
            (string) ($_ENV['VALOR_REFEICAO'] ?? '15')
        );

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . QuestorService::nomeArquivo($relatorio) . '"');

        echo QuestorService::csv($relatorio);

        QuestorService::gerarBackup($relatorio['data_inicio'], $relatorio['data_fim']);

        QuestorExportacao::criar([
            'data_inicio'    => $relatorio['data_inicio'],
            'data_fim'       => $relatorio['data_fim'],
            'valor_refeicao' => $relatorio['valor_refeicao'],
            'exportado_por'  => $_SESSION['usuario']['matricula'] ?? null,
        ]);

        exit;
    }
}
