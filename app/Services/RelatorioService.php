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
        string $matriculaRaw
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

        $status    = in_array($statusRaw, ['', 'agendado', 'cancelado', 'concluido'], true) ? $statusRaw : '';
        $matricula = trim($matriculaRaw);

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
