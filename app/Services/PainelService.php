<?php

namespace App\Services;

use App\Models\Cardapio;
use App\Models\Registro;
use DateTime;

class PainelService
{
    /**
     * Monta a grade da semana atual (segunda a domingo) com os cardápios e o status
     * de agendamento do usuário para cada dia.
     *
     * @param array{matricula: string, nome: string, tipo: string} $usuario
     */
    public static function montarSemana(array $usuario): array
    {
        // Encontra a segunda-feira da semana atual
        $hoje    = new DateTime();
        $diaNum  = (int) $hoje->format('N'); // 1=Seg … 7=Dom
        $segunda = (clone $hoje)->modify('-' . $diaNum - 1 . ' days');

        $agora                 = new DateTime();
        $hojeStr               = $agora->format('Y-m-d');
        $ehComum               = $usuario['tipo'] === 'comum';
        $agendamentoHabilitado = filter_var($_ENV['AGENDAMENTO_HABILITADO']     ?? 'true',  FILTER_VALIDATE_BOOLEAN);
        $agendaSabadoDomingo   = filter_var($_ENV['AGENDAMENTO_SABADO_DOMINGO'] ?? 'false', FILTER_VALIDATE_BOOLEAN);

        // Monta a estrutura dos 7 dias com slots para almoço e jantar
        $labels = ['Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado', 'Domingo'];
        $semana = [];
        for ($i = 0; $i < 7; $i++) {
            $dia   = (clone $segunda)->modify("+{$i} days");
            $chave = $dia->format('Y-m-d');

            // Pode agendar: agendamento habilitado + usuário comum + dia permitido + prazo não encerrado
            $diaPermitido   = $i < 5 || $agendaSabadoDomingo;
            $podeAgendarDia = $agendamentoHabilitado && $ehComum && $diaPermitido && !Agendamento::foraPrazo($chave);

            $semana[$chave] = [
                'label'       => $labels[$i],
                'data'        => $dia,
                'almoco'      => null,
                'jantar'      => null,
                'podeAgendar' => $podeAgendarDia,
            ];
        }

        // Preenche os slots com os cardápios cadastrados
        foreach (Cardapio::semanaAtual($segunda) as $c) {
            if (isset($semana[$c['data']])) {
                $semana[$c['data']][$c['tipo']] = $c;
            }
        }

        // Mapa: id_cardapio => ['id', 'status'] — todos os registros da semana atual
        $registrosSemana = [];
        $domingoSemana   = (clone $segunda)->modify('+6 days');
        foreach (
            Registro::buscarPorUsuario(
                $usuario['matricula'],
                '',
                $segunda->format('Y-m-d'),
                $domingoSemana->format('Y-m-d')
            ) as $r
        ) {
            $registrosSemana[$r['id_cardapio']] = ['id' => $r['id'], 'status' => $r['status']];
        }

        return [
            'semana'                => $semana,
            'registrosSemana'       => $registrosSemana,
            'segunda'               => $segunda,
            'hoje'                  => $hojeStr,
            'agendamentoHabilitado' => $agendamentoHabilitado,
        ];
    }
}
