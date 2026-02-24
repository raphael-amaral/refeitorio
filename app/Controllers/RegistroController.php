<?php

namespace App\Controllers;

use App\Models\Registro;

class RegistroController
{

    public function agendar()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();

        $usuario_id = $_SESSION['usuario_id'];
        $matricula  = $_SESSION['usuario_matricula'] ?? 'N/A'; // Precisamos garantir que a matricula está na sessão
        $tipo       = $_POST['tipo'] ?? 'hoje';

        $agendamentoModel = new Registro();
        $limite = "09:30";
        $agora  = date('H:i');
        $hoje   = date('Y-m-d');

        if ($tipo === 'hoje') {
            if ($agora > $limite) {
                header('Location: /dashboard?erro=horario');
                exit;
            }
            $agendamentoModel->salvar($usuario_id, $matricula, $hoje);
        } elseif ($tipo === 'semana') {
            // Agendar para hoje e os próximos 4 dias úteis (total 5 dias)
            for ($i = 0; $i < 5; $i++) {
                $dataAlvo = date('Y-m-d', strtotime("+$i days"));
                $diaSemana = date('w', strtotime($dataAlvo));

                if ($diaSemana == 0 || $diaSemana == 6) continue; // Pula finais de semana

                // Se for hoje e passou das 09:30, pula o dia de hoje mas agenda os outros
                if ($dataAlvo === $hoje && $agora > $limite) continue;

                $agendamentoModel->salvar($usuario_id, $matricula, $dataAlvo);
            }
        }

        header('Location: /dashboard?sucesso=1');
        exit;
    }
}
