<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Cardapio;
use App\Models\Registro;
use DateTime;

class RegistroController extends Controller
{
    /**
     * Lista os agendamentos do usuário logado.
     */
    public function index(): void
    {
        $usuario = $_SESSION['usuario'];
        $reData  = '/^\d{4}-\d{2}-\d{2}$/';

        $status     = in_array($_GET['status'] ?? '', ['agendado', 'cancelado', 'concluido'], true)
            ? $_GET['status']
            : '';
        $dataInicio = preg_match($reData, $_GET['data_inicio'] ?? '') ? $_GET['data_inicio'] : '';
        $dataFim    = preg_match($reData, $_GET['data_fim']    ?? '') ? $_GET['data_fim']    : '';

        $registros = Registro::buscarPorUsuario($usuario['matricula'], $status, $dataInicio, $dataFim);

        $this->render('registro/index', [
            'titulo'     => 'Meus Agendamentos',
            'registros'  => $registros,
            'status'     => $status,
            'dataInicio' => $dataInicio,
            'dataFim'    => $dataFim,
        ]);
    }

    /**
     * Cria um novo agendamento para o usuário logado.
     */
    public function criar(): void
    {
        $usuario    = $_SESSION['usuario'];
        $idCardapio = (int) ($_POST['id_cardapio'] ?? 0);

        // Apenas usuários comuns podem agendar
        if ($usuario['tipo'] !== 'comum') {
            $this->redirect('/painel');
        }

        // Jantar não é agendável
        if ($idCardapio > 0) {
            $cardapio = Cardapio::buscarPorId($idCardapio);
            if ($cardapio && $cardapio['tipo'] === 'jantar') {
                $this->redirect('/painel');
            }
        }

        // Prazo: até hora limite do dia do cardápio
        if ($idCardapio > 0 && isset($cardapio)) {
            $limitTime = $_ENV['LIMIT_TIME'] ?? '09:30';
            [$horaLimit, $minutoLimit] = explode(':', $limitTime);
            $agora        = new DateTime();
            $diaCardapio  = new DateTime($cardapio['data']);
            $hoje         = $agora->format('Y-m-d');
            $fimPrazo     = (clone $diaCardapio)->setTime((int)$horaLimit, (int)$minutoLimit);

            if (
                $diaCardapio->format('Y-m-d') < $hoje ||
                ($diaCardapio->format('Y-m-d') === $hoje && $agora >= $fimPrazo)
            ) {
                $timeFormatted = str_replace(':', 'h', $limitTime);
                $this->flash("Agendamentos encerrados para este dia. Prazo: até {$timeFormatted}.", 'warning');
                $this->redirect('/painel');
            }
        }

        if ($idCardapio > 0 && !Registro::jaAgendado($usuario['matricula'], $idCardapio)) {
            Registro::upsertAgendado($usuario['matricula'], $idCardapio);
        }

        $this->redirect('/painel');
    }

    /**
     * Cancela um agendamento do próprio usuário.
     */
    public function cancelar(string $id): void
    {
        $usuario = $_SESSION['usuario'];
        $registro = Registro::buscarPorId((int) $id);

        if ($registro) {
            $cardapio    = Cardapio::buscarPorId($registro['id_cardapio']);
            $limitTime = $_ENV['LIMIT_TIME'] ?? '09:30';
            [$horaLimit, $minutoLimit] = explode(':', $limitTime);
            $agora       = new DateTime();
            $diaCardapio = new DateTime($cardapio['data']);
            $hoje        = $agora->format('Y-m-d');
            $fimPrazo    = (clone $diaCardapio)->setTime((int)$horaLimit, (int)$minutoLimit);

            if (
                $diaCardapio->format('Y-m-d') < $hoje ||
                ($diaCardapio->format('Y-m-d') === $hoje && $agora >= $fimPrazo)
            ) {
                $destino = $this->input('redirect') === '/registro' ? '/registro' : '/painel';
                $timeFormatted = str_replace(':', 'h', $limitTime);
                $this->flash("Cancelamento encerrado para este dia. Prazo: até {$timeFormatted}.", 'warning');
                $this->redirect($destino);
            }
        }

        Registro::cancelarDoUsuario((int) $id, $usuario['matricula']);

        $destino = $this->input('redirect') === '/registro' ? '/registro' : '/painel';
        $this->redirect($destino);
    }

    /**
     * Marca um agendamento como concluído (SND/admin/dev).
     */
    public function concluir(string $id): void
    {
        Registro::concluir((int) $id);
        $this->redirect('/painel');
    }
}
