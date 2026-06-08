<?php

namespace App\Controllers;

use App\Core\Agendamento;
use App\Core\Audit;
use App\Core\Controller;
use App\Models\Cardapio;
use App\Models\Registro;

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

        $porPagina    = 30;
        $pagina       = max(1, (int) ($_GET['pagina'] ?? 1));
        $total        = Registro::contarPorUsuario($usuario['matricula'], $status, $dataInicio, $dataFim);
        $totalPaginas = max(1, (int) ceil($total / $porPagina));
        $pagina       = min($pagina, $totalPaginas);
        $offset       = ($pagina - 1) * $porPagina;

        $this->render('registro/index', [
            'titulo'       => 'Meus Agendamentos',
            'registros'    => Registro::buscarPorUsuarioPaginado($usuario['matricula'], $status, $dataInicio, $dataFim, $offset, $porPagina),
            'status'       => $status,
            'dataInicio'   => $dataInicio,
            'dataFim'      => $dataFim,
            'pagina'       => $pagina,
            'totalPaginas' => $totalPaginas,
            'total'        => $total,
        ]);
    }

    /**
     * Cria um novo agendamento para o usuário logado.
     */
    public function criar(): void
    {
        $usuario    = $_SESSION['usuario'];
        $idCardapio = (int) ($_POST['id_cardapio'] ?? 0);

        if (!filter_var($_ENV['AGENDAMENTO_HABILITADO'] ?? 'true', FILTER_VALIDATE_BOOLEAN)) {
            Audit::write('registro.criar', 'blocked', (string) $idCardapio, ['motivo' => 'agendamento_desabilitado']);
            $this->flash('Agendamentos estão desabilitados no momento.', 'warning');
            $this->redirect('/painel');
        }

        // Apenas usuários comuns podem agendar
        if ($usuario['tipo'] !== 'comum') {
            Audit::write('registro.criar', 'blocked', (string) $idCardapio, ['motivo' => 'tipo_nao_permitido']);
            $this->redirect('/painel');
        }

        // Jantar não é agendável
        if ($idCardapio > 0) {
            $cardapio = Cardapio::buscarPorId($idCardapio);
            if ($cardapio && $cardapio['tipo'] === 'jantar') {
                Audit::write('registro.criar', 'blocked', (string) $idCardapio, ['motivo' => 'jantar_nao_agendavel']);
                $this->redirect('/painel');
            }
        }

        if ($idCardapio > 0 && isset($cardapio)) {
            $podeAgendarFinalSemana = filter_var($_ENV['AGENDAMENTO_SABADO_DOMINGO'] ?? 'false', FILTER_VALIDATE_BOOLEAN);

            if (!$podeAgendarFinalSemana && Agendamento::fimDeSemana($cardapio['data'])) {
                Audit::write('registro.criar', 'blocked', (string) $idCardapio, ['motivo' => 'fim_de_semana']);
                $this->flash('Agendamentos não são permitidos para sábado ou domingo.', 'warning');
                $this->redirect('/painel');
            }

            if (Agendamento::foraPrazo($cardapio['data'])) {
                Audit::write('registro.criar', 'blocked', (string) $idCardapio, ['motivo' => 'fora_prazo']);
                $timeFormatted = str_replace(':', 'h', $_ENV['LIMIT_TIME'] ?? '10:00');
                $this->flash("Agendamentos encerrados para este dia. Prazo: até {$timeFormatted}.", 'warning');
                $this->redirect('/painel');
            }
        }

        if ($idCardapio > 0 && !Registro::jaAgendado($usuario['matricula'], $idCardapio)) {
            Registro::upsertAgendado($usuario['matricula'], $idCardapio);
            Audit::write('registro.criar', 'success', (string) $idCardapio, [
                'matricula_usuario' => $usuario['matricula'],
                'data_cardapio'     => $cardapio['data'] ?? null,
                'tipo_refeicao'     => $cardapio['tipo'] ?? null,
            ]);
        } else {
            Audit::write('registro.criar', 'blocked', (string) $idCardapio, ['motivo' => 'ja_agendado_ou_invalido']);
        }

        $this->redirect('/painel');
    }

    /**
     * Cancela um agendamento do próprio usuário.
     */
    public function cancelar(string $id): void
    {
        $usuario = $_SESSION['usuario'];

        if (!filter_var($_ENV['AGENDAMENTO_HABILITADO'] ?? 'true', FILTER_VALIDATE_BOOLEAN)) {
            Audit::write('registro.cancelar', 'blocked', $id, ['motivo' => 'agendamento_desabilitado']);
            $this->flash('Cancelamentos estão desabilitados no momento.', 'warning');
            $this->redirect('/painel');
        }

        $registro = Registro::buscarPorId((int) $id);

        if ($registro) {
            $cardapio = Cardapio::buscarPorId($registro['id_cardapio']);

            if (Agendamento::foraPrazo($cardapio['data'])) {
                $destino       = $this->input('redirect') === '/registro' ? '/registro' : '/painel';
                $timeFormatted = str_replace(':', 'h', $_ENV['LIMIT_TIME'] ?? '10:00');
                $this->flash("Cancelamento encerrado para este dia. Prazo: até {$timeFormatted}.", 'warning');
                $this->redirect($destino);
            }
        }

        $cancelado = Registro::cancelarDoUsuario((int) $id, $usuario['matricula']);
        Audit::write('registro.cancelar', $cancelado ? 'success' : 'blocked', (string) $id, [
            'matricula_usuario' => $usuario['matricula'],
            'data_cardapio'     => $cardapio['data'] ?? null,
            'tipo_refeicao'     => $cardapio['tipo'] ?? null,
        ]);

        $destino = $this->input('redirect') === '/registro' ? '/registro' : '/painel';
        $this->redirect($destino);
    }

    /**
     * Marca um agendamento como concluído (SND/admin/dev).
     */
    public function concluir(string $id): void
    {
        $registro = Registro::buscarPorId((int) $id);
        $cardapio = $registro ? Cardapio::buscarPorId($registro['id_cardapio']) : null;

        $ok = Registro::concluir((int) $id);
        Audit::write('registro.concluir', $ok ? 'success' : 'error', (string) $id, [
            'matricula_usuario' => $registro['matricula_usuario'] ?? null,
            'data_cardapio'     => $cardapio['data'] ?? null,
            'tipo_refeicao'     => $cardapio['tipo'] ?? null,
        ]);
        $this->redirect('/painel');
    }
}
