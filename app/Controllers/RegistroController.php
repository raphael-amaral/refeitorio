<?php

namespace App\Controllers;

use App\Models\Registro;

class RegistroController
{
    public function agendar()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();

        // CHAVE CORRIGIDA: de 'usuario_matricula' para 'matricula'
        $matricula = $_SESSION['matricula'] ?? null;
        $tipo = $_POST['tipo'] ?? null;
        $data_especifica = $_POST['data_especifica'] ?? null;

        if (!$matricula) {
            header('Location: /login');
            exit;
        }

        $model = new Registro();
        $hoje = date('Y-m-d');
        $agora = date('H:i');
        $limite = "09:30";

        if ($data_especifica) {
            if ($data_especifica === $hoje && $agora >= $limite) {
                $_SESSION['aviso_erro'] = "Horário limite atingido para hoje.";
            } else {
                $model->salvar($matricula, $data_especifica);
                $_SESSION['aviso_sucesso'] = "Agendado: " . date('d/m', strtotime($data_especifica));
            }
        } elseif ($tipo === 'semana') {
            $diaW = (int)date('w');
            if ($diaW <= 5) {
                $inicio = ($diaW == 0) ? 1 : 0;
                $diasAteSexta = 5 - $diaW;
                for ($i = $inicio; $i <= $diasAteSexta; $i++) {
                    $dataAlvo = date('Y-m-d', strtotime("+$i days"));
                    if ($dataAlvo === $hoje && $agora >= $limite) continue;
                    $model->salvar($matricula, $dataAlvo);
                }
                $_SESSION['aviso_sucesso'] = "Semana agendada com sucesso!";
            }
        }
        header('Location: /dashboard');
        exit;
    }

    public function cancelar()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();

        $matricula = $_SESSION['matricula'] ?? null;
        $data_cancelar = $_POST['data_cancelar'] ?? null;

        if ($matricula && $data_cancelar) {
            $hoje = date('Y-m-d');
            $agora = date('H:i');
            $limite = "09:30";

            if (($data_cancelar === $hoje && $agora >= $limite) || ($data_cancelar < $hoje)) {
                $_SESSION['aviso_erro'] = "Prazo de cancelamento expirado.";
            } else {
                $model = new Registro();
                if ($model->alterarStatus($matricula, $data_cancelar, 'cancelado')) {
                    $_SESSION['aviso_sucesso'] = "Cancelado com sucesso.";
                } else {
                    $_SESSION['aviso_erro'] = "Erro ao cancelar no banco.";
                }
            }
        }
        header('Location: /dashboard');
        exit;
    }
}
