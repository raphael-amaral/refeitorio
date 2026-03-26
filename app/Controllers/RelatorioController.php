<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Registro;

class RelatorioController extends Controller
{
    /**
     * Lista os registros baseados nos filtros da URL.
     */
    public function index(): void
    {
        $dados = $this->prepararDadosRelatorio();

        $this->render('relatorio/index', [
            ...$dados,
            'titulo' => 'Relatórios',
        ]);
    }

    /**
     * Renderiza a versão para impressão (PDF).
     */
    public function pdf(): void
    {
        $dados = $this->prepararDadosRelatorio();

        // Renderiza sem layout base ($layout = null)
        $this->render('relatorio/pdf', [
            ...$dados,
            'titulo' => 'Relatório de Agendamentos',
        ], null);
    }

    /**
     * Lógica central que processa os filtros da query string e busca os registros.
     */
    private function prepararDadosRelatorio(): array
    {
        $modo = $_GET['modo'] ?? 'dia'; // dia, periodo, mes

        $dataInicio = '';
        $dataFim    = '';

        if ($modo === 'dia') {
            $dataInicio = $_GET['data_dia'] ?? date('Y-m-d');
            $dataFim    = $dataInicio;
        } elseif ($modo === 'periodo') {
            $dataInicio = $_GET['data_inicio'] ?? date('Y-m-01');
            $dataFim    = $_GET['data_fim']    ?? date('Y-m-t');
        } else { // mes
            $ano = (int) ($_GET['ano'] ?? date('Y'));
            $mes = (int) ($_GET['mes'] ?? date('m'));
            // Padroniza com zeros à esquerda
            $mesStr = str_pad((string)$mes, 2, '0', STR_PAD_LEFT);
            $dataInicio = "{$ano}-{$mesStr}-01";
            $dataFim    = date('Y-m-t', strtotime($dataInicio));
        }

        $status    = $_GET['status'] ?? 'concluido';
        $matricula = trim($_GET['matricula'] ?? '');

        $registros = Registro::buscarParaRelatorio($status, $dataInicio, $dataFim, $matricula);

        $totais = ['agendado' => 0, 'cancelado' => 0, 'concluido' => 0];
        foreach ($registros as $r) {
            if (isset($totais[$r['status']])) {
                $totais[$r['status']]++;
            }
        }

        return [
            'registros'  => $registros,
            'totais'     => $totais,
            'modo'       => $modo,
            'dataInicio' => $dataInicio,
            'dataFim'    => $dataFim,
            'status'     => $status,
            'matricula'  => $matricula,
        ];
    }
}
