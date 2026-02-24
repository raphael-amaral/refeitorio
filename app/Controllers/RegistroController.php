<?php

namespace App\Controllers;

use App\Models\Registro;

class RegistroController
{
    public function agendar()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();

        $usuario_id = $_SESSION['usuario_id'];
        $matricula  = $_SESSION['usuario_matricula'];
        $tipo = $_POST['tipo'] ?? null;
        $data_especifica = $_POST['data_especifica'] ?? null;

        $model = new Registro();
        $hoje = date('Y-m-d');
        $agora = date('H:i');
        $limite = "09:30";

        if ($data_especifica) {
            if ($data_especifica === $hoje && $agora >= $limite) {
                $_SESSION['aviso_erro'] = "Horário limite atingido.";
                header('Location: /dashboard');
                exit;
            }
            $model->salvar($usuario_id, $matricula, $data_especifica);
            $_SESSION['aviso_sucesso'] = "Agendado para o dia " . date('d/m', strtotime($data_especifica));
        } elseif ($tipo === 'semana') {
            $diaDaSemanaAtual = (int)date('w');
            if ($diaDaSemanaAtual <= 5) {
                $inicio = ($diaDaSemanaAtual == 0) ? 1 : 0;
                $diasAteSexta = 5 - $diaDaSemanaAtual;
                for ($i = $inicio; $i <= $diasAteSexta; $i++) {
                    $dataAlvo = date('Y-m-d', strtotime("+$i days"));
                    if ($dataAlvo === $hoje && $agora >= $limite) continue;
                    $model->salvar($usuario_id, $matricula, $dataAlvo);
                }
                $_SESSION['aviso_sucesso'] = "Semana agendada com sucesso!";
            }
        }
        header('Location: /dashboard');
        exit;
    }
}
