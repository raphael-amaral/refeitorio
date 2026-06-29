<?php

namespace App\Controllers;

use App\Core\Audit;
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
     * Lógica central que processa os filtros da query string e busca os registros.
     */
    private function prepararDadosRelatorio(): array
    {
        $reData = '/^\d{4}-\d{2}-\d{2}$/';

        $modo = in_array($_GET['modo'] ?? '', ['dia', 'periodo', 'mes'], true)
            ? $_GET['modo']
            : 'dia';

        if ($modo === 'dia') {
            $dataDia    = $_GET['data_dia'] ?? '';
            $dataInicio = preg_match($reData, $dataDia) ? $dataDia : date('Y-m-d');
            $dataFim    = $dataInicio;
        } elseif ($modo === 'periodo') {
            $di   = $_GET['data_inicio'] ?? '';
            $df   = $_GET['data_fim']    ?? '';

            $hoje            = new \DateTime();
            $diasParaDomingo = (int) $hoje->format('w'); // 0=Dom … 6=Sab
            $domingo         = (clone $hoje)->modify("-{$diasParaDomingo} days");
            $sabado          = (clone $domingo)->modify('+6 days');

            $dataInicio = preg_match($reData, $di) ? $di : $domingo->format('Y-m-d');
            $dataFim    = preg_match($reData, $df) ? $df : $sabado->format('Y-m-d');
        } else {
            $ano        = (int) ($_GET['ano'] ?? date('Y'));
            $mes        = (int) ($_GET['mes'] ?? date('m'));
            $ano        = ($ano >= 2024 && $ano <= ((int) date('Y') + 1)) ? $ano : (int) date('Y');
            $mes        = ($mes >= 1   && $mes <= 12)                     ? $mes : (int) date('m');
            $mesStr     = str_pad((string) $mes, 2, '0', STR_PAD_LEFT);
            $dataInicio = "{$ano}-{$mesStr}-01";
            $dataFim    = date('Y-m-t', strtotime($dataInicio));
        }

        $statusRaw = $_GET['status'] ?? '';
        $status    = in_array($statusRaw, ['', 'agendado', 'cancelado', 'concluido'], true)
            ? $statusRaw
            : '';

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
