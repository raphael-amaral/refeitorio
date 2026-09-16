<?php

namespace App\Services;

use App\Models\Registro;
use DateTime;

class RelatorioService
{
    /**
     * Resolve o modo de filtro (dia/período/mês) e busca os registros correspondentes.
     * Não audita — quem audita é o Controller, já que esta lógica é reaproveitada tanto
     * pela tela de relatório quanto pela exportação em PDF, e só a segunda audita.
     */
    public static function preparar(
        string $modoRaw,
        string $dataDiaRaw,
        string $dataInicioRaw,
        string $dataFimRaw,
        string $anoRaw,
        string $mesRaw,
        string $statusRaw,
        string $matriculaRaw,
        bool $consolidado = false
    ): array {
        $reData = '/^\d{4}-\d{2}-\d{2}$/';

        $modo = in_array($modoRaw, ['dia', 'periodo', 'mes'], true) ? $modoRaw : 'dia';

        if ($modo === 'dia') {
            $dataInicio = preg_match($reData, $dataDiaRaw) ? $dataDiaRaw : date('Y-m-d');
            $dataFim    = $dataInicio;
        } elseif ($modo === 'periodo') {
            $hoje            = new DateTime();
            $diasParaDomingo = (int) $hoje->format('w'); // 0=Dom … 6=Sab
            $domingo         = (clone $hoje)->modify("-{$diasParaDomingo} days");
            $sabado          = (clone $domingo)->modify('+6 days');

            $dataInicio = preg_match($reData, $dataInicioRaw) ? $dataInicioRaw : $domingo->format('Y-m-d');
            $dataFim    = preg_match($reData, $dataFimRaw)    ? $dataFimRaw    : $sabado->format('Y-m-d');
        } else {
            $ano    = (int) $anoRaw;
            $mes    = (int) $mesRaw;
            $ano    = ($ano >= 2024 && $ano <= ((int) date('Y') + 1)) ? $ano : (int) date('Y');
            $mes    = ($mes >= 1   && $mes <= 12)                     ? $mes : (int) date('m');
            $mesStr = str_pad((string) $mes, 2, '0', STR_PAD_LEFT);

            $dataInicio = "{$ano}-{$mesStr}-01";
            $dataFim    = date('Y-m-t', strtotime($dataInicio));
        }

        $status    = $consolidado
            ? 'concluido'
            : (in_array($statusRaw, ['', 'agendado', 'cancelado', 'concluido'], true) ? $statusRaw : '');
        $matricula = trim($matriculaRaw);

        $registros = Registro::buscarParaRelatorio($status, $dataInicio, $dataFim, $matricula);

        $totais = ['agendado' => 0, 'cancelado' => 0, 'concluido' => 0];
        foreach ($registros as $r) {
            if (isset($totais[$r['status']])) {
                $totais[$r['status']]++;
            }
        }

        $valorRefeicao = (float) str_replace(',', '.', (string) ($_ENV['VALOR_REFEICAO'] ?? '15'));
        if ($valorRefeicao <= 0) {
            $valorRefeicao = 15.0;
        }

        $funcionarios = [];
        if ($consolidado) {
            foreach ($registros as $r) {
                $mat = $r['matricula'];
                if (!isset($funcionarios[$mat])) {
                    $funcionarios[$mat] = [
                        'nome'        => $r['nome_usuario'],
                        'quantidade'  => 0,
                        'valor_total' => 0.0,
                    ];
                }
                $funcionarios[$mat]['quantidade']++;
                $funcionarios[$mat]['valor_total'] += $valorRefeicao;
            }
        }

        return [
            'registros'      => $registros,
            'funcionarios'   => $funcionarios,
            'consolidado'    => $consolidado,
            'valorRefeicao'  => $valorRefeicao,
            'totais'         => $totais,
            'modo'           => $modo,
            'dataInicio'     => $dataInicio,
            'dataFim'        => $dataFim,
            'status'         => $status,
            'matricula'      => $matricula,
        ];
    }

    /**
     * Gera um CSV de consulta do relatório consolidado, sem efeitos no histórico Questor.
     */
    public static function csv(array $relatorio): string
    {
        $arquivo = fopen('php://temp', 'r+');
        fwrite($arquivo, "\xEF\xBB\xBF");
        fputcsv($arquivo, ['Matrícula', 'Nome', 'Quantidade', 'Valor total'], ';', '"', '', "\r\n");

        foreach ($relatorio['funcionarios'] as $matricula => $dados) {
            fputcsv($arquivo, [
                $matricula,
                $dados['nome'],
                $dados['quantidade'],
                number_format($dados['valor_total'], 2, ',', ''),
            ], ';', '"', '', "\r\n");
        }

        rewind($arquivo);
        $csv = stream_get_contents($arquivo);
        fclose($arquivo);

        return $csv;
    }

    public static function nomeArquivoCsv(array $relatorio): string
    {
        return 'relatorio_refeitorio_'
            . str_replace('-', '', $relatorio['dataInicio']) . '_'
            . str_replace('-', '', $relatorio['dataFim']) . '.csv';
    }
}
