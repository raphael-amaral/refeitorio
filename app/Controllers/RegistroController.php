<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Exceptions\AgendamentoDesabilitadoException;
use App\Exceptions\FimDeSemanaException;
use App\Exceptions\ForaDoPrazoException;
use App\Exceptions\JaAgendadoOuInvalidoException;
use App\Exceptions\JantarNaoAgendavelException;
use App\Exceptions\TipoNaoPermitidoException;
use App\Models\QuestorExportacao;
use App\Models\Registro;
use App\Services\RegistroService;

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

        $ultimaExportacao = QuestorExportacao::ultima();

        // Valor já fechado pelo Questor na última exportação.
        $qtdUltima   = $ultimaExportacao
            ? Registro::contarPorUsuario($usuario['matricula'], 'concluido', $ultimaExportacao['data_inicio'], $ultimaExportacao['data_fim'])
            : 0;
        $valorUltima = $qtdUltima * (float) ($ultimaExportacao['valor_refeicao'] ?? 0);
        $mesUltima   = $ultimaExportacao ? (int) date('n', strtotime($ultimaExportacao['data_fim'])) : null;

        // Valor estimado para a próxima exportação (período ainda em aberto).
        $referenciaDesconto = $ultimaExportacao
            ? date('Y-m-d', strtotime($ultimaExportacao['data_fim'] . ' +1 day'))
            : date('Y-m-01');
        $qtdProxima   = Registro::contarPorUsuario($usuario['matricula'], 'concluido', $referenciaDesconto, date('Y-m-d'));
        $valorProxima = $qtdProxima * (float) ($_ENV['VALOR_REFEICAO'] ?? 15);
        $mesProxima   = (int) date('n', strtotime($referenciaDesconto . ' +1 month'));

        $this->render('registro/index', [
            'titulo'           => 'Meus Agendamentos',
            'registros'        => Registro::buscarPorUsuarioPaginado($usuario['matricula'], $status, $dataInicio, $dataFim, $offset, $porPagina),
            'status'           => $status,
            'dataInicio'       => $dataInicio,
            'dataFim'          => $dataFim,
            'pagina'           => $pagina,
            'totalPaginas'     => $totalPaginas,
            'total'            => $total,
            'ultimaExportacao' => $ultimaExportacao,
            'mesUltima'        => $mesUltima,
            'valorUltima'      => $valorUltima,
            'mesProxima'       => $mesProxima,
            'valorProxima'     => $valorProxima,
        ]);
    }

    /**
     * Cria um novo agendamento para o usuário logado.
     */
    public function criar(): void
    {
        $usuario    = $_SESSION['usuario'];
        $idCardapio = (int) ($_POST['id_cardapio'] ?? 0);

        try {
            RegistroService::registrar($usuario, $idCardapio);
        } catch (AgendamentoDesabilitadoException) {
            $this->flash('Agendamentos estão desabilitados no momento.', 'warning');
            $this->redirect('/painel');
        } catch (FimDeSemanaException) {
            $this->flash('Agendamentos não são permitidos para sábado ou domingo.', 'warning');
            $this->redirect('/painel');
        } catch (ForaDoPrazoException) {
            $timeFormatted = str_replace(':', 'h', $_ENV['LIMIT_TIME'] ?? '10:00');
            $this->flash("Agendamentos encerrados para este dia. Prazo: até {$timeFormatted}.", 'warning');
            $this->redirect('/painel');
        } catch (TipoNaoPermitidoException|JantarNaoAgendavelException|JaAgendadoOuInvalidoException) {
            $this->redirect('/painel');
        }

        $this->redirect('/painel');
    }

    /**
     * Cancela um agendamento do próprio usuário.
     */
    public function cancelar(string $id): void
    {
        $usuario = $_SESSION['usuario'];

        try {
            RegistroService::cancelar($usuario, (int) $id);
        } catch (AgendamentoDesabilitadoException) {
            $this->flash('Cancelamentos estão desabilitados no momento.', 'warning');
            $this->redirect('/painel');
        } catch (ForaDoPrazoException) {
            $destino       = $this->input('redirect') === '/registro' ? '/registro' : '/painel';
            $timeFormatted = str_replace(':', 'h', $_ENV['LIMIT_TIME'] ?? '10:00');
            $this->flash("Cancelamento encerrado para este dia. Prazo: até {$timeFormatted}.", 'warning');
            $this->redirect($destino);
        }

        $destino = $this->input('redirect') === '/registro' ? '/registro' : '/painel';
        $this->redirect($destino);
    }

    /**
     * Marca um agendamento como concluído (SND/admin/dev).
     */
    public function concluir(string $id): void
    {
        RegistroService::concluir((int) $id);
        $this->redirect('/painel');
    }
}
