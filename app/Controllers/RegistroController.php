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
        $usuario   = $_SESSION['usuario'];
        $registros = Registro::buscarPorUsuario($usuario['matricula']);

        $this->render('registro/index', [
            'titulo'    => 'Meus Agendamentos',
            'registros' => $registros,
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

        // Prazo: até 09:30 do dia do cardápio
        if ($idCardapio > 0 && isset($cardapio)) {
            $agora        = new DateTime();
            $diaCardapio  = new DateTime($cardapio['data']);
            $hoje         = $agora->format('Y-m-d');
            $fimPrazo     = (clone $diaCardapio)->setTime(9, 30);

            if (
                $diaCardapio->format('Y-m-d') < $hoje ||
                ($diaCardapio->format('Y-m-d') === $hoje && $agora >= $fimPrazo)
            ) {
                $this->flash('Agendamentos encerrados para este dia. Prazo: até 09h30.', 'warning');
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
            $agora       = new DateTime();
            $diaCardapio = new DateTime($cardapio['data']);
            $hoje        = $agora->format('Y-m-d');
            $fimPrazo    = (clone $diaCardapio)->setTime(9, 30);

            if (
                $diaCardapio->format('Y-m-d') < $hoje ||
                ($diaCardapio->format('Y-m-d') === $hoje && $agora >= $fimPrazo)
            ) {
                $this->flash('Cancelamento encerrado para este dia. Prazo: até 09h30.', 'warning');
                $this->redirect('/painel');
            }
        }

        Registro::cancelarDoUsuario((int) $id, $usuario['matricula']);
        $this->redirect('/painel');
    }

    /**
     * Marca um agendamento como concluído (copa/admin/dev).
     */
    public function concluir(string $id): void
    {
        Registro::concluir((int) $id);
        $this->redirect('/registro');
    }
}
