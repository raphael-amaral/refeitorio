<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Cardapio;
use App\Models\Registro;
use DateTime;

class PainelController extends Controller
{
    public function index(): void
    {
        $usuario = $_SESSION['usuario'];

        // Encontra a segunda-feira da semana atual
        $hoje    = new DateTime();
        $diaNum  = (int) $hoje->format('N'); // 1=Seg … 7=Dom
        $segunda = (clone $hoje)->modify('-' . ($diaNum - 1) . ' days');

        $agora  = new DateTime();
        $hoje   = $agora->format('Y-m-d');
        $limitTime = $_ENV['LIMIT_TIME'] ?? '09:30';
        [$horaLimit, $minutoLimit] = explode(':', $limitTime);
        $antes930 = $agora < (clone $agora)->setTime((int)$horaLimit, (int)$minutoLimit);
        $ehComum  = $usuario['tipo'] === 'comum';

        // Monta a estrutura dos 7 dias com slots para almoço e jantar
        $labels = ['Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado', 'Domingo'];
        $semana = [];
        for ($i = 0; $i < 7; $i++) {
            $dia   = (clone $segunda)->modify("+{$i} days");
            $chave = $dia->format('Y-m-d');

            // Pode agendar: usuário comum + (dia futuro OU hoje antes das 09:30)
            $podeAgendarDia = $ehComum && (
                $chave > $hoje || ($chave === $hoje && $antes930)
            );

            $semana[$chave] = [
                'label'        => $labels[$i],
                'data'         => $dia,
                'almoco'       => null,
                'jantar'       => null,
                'podeAgendar'  => $podeAgendarDia,
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

        $this->render('painel/index', [
            'titulo'          => 'Painel',
            'usuario'         => $usuario,
            'semana'          => $semana,
            'registrosSemana' => $registrosSemana,
            'segunda'         => $segunda,
            'hoje'            => $hoje,
        ]);
    }
}
