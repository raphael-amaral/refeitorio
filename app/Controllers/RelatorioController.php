<?php

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Controller;
use App\Services\Perfil;
use App\Services\RelatorioService;

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

        Audit::write('relatorio.pdf', 'success', null, [
            'data_inicio'     => $dados['dataInicio'],
            'data_fim'        => $dados['dataFim'],
            'status'          => $dados['status'] !== '' ? $dados['status'] : null,
            'matricula'       => $dados['matricula'] !== '' ? $dados['matricula'] : null,
            'total_registros' => count($dados['registros']),
        ]);

        // Renderiza sem layout base ($layout = null)
        $this->render('relatorio/pdf', [
            ...$dados,
            'titulo' => 'Relatório de Agendamentos',
        ], null);
    }

    /**
     * Baixa o relatório consolidado do DP sem registrar uma exportação Questor.
     */
    public function csv(): void
    {
        if (!Perfil::ehDp($_SESSION['usuario']['tipo'] ?? '')) {
            $this->redirect('/relatorios');
        }

        $dados = $this->prepararDadosRelatorio();

        Audit::write('relatorio.csv', 'success', null, [
            'data_inicio'        => $dados['dataInicio'],
            'data_fim'           => $dados['dataFim'],
            'matricula'          => $dados['matricula'] !== '' ? $dados['matricula'] : null,
            'total_registros'    => count($dados['registros']),
            'total_funcionarios' => count($dados['funcionarios']),
        ]);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . RelatorioService::nomeArquivoCsv($dados) . '"');

        echo RelatorioService::csv($dados);
        exit;
    }

    /**
     * Extrai os filtros da query string e delega o processamento ao RelatorioService.
     */
    private function prepararDadosRelatorio(): array
    {
        return RelatorioService::preparar(
            $_GET['modo']        ?? '',
            $_GET['data_dia']    ?? '',
            $_GET['data_inicio'] ?? '',
            $_GET['data_fim']    ?? '',
            $_GET['ano']         ?? '',
            $_GET['mes']         ?? '',
            $_GET['status']      ?? '',
            $_GET['matricula']   ?? '',
            Perfil::ehDp($_SESSION['usuario']['tipo'] ?? '')
        );
    }
}
