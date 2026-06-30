<?php

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Controller;
use App\Models\Cardapio;

class CardapioController extends Controller
{
    public function index(): void
    {
        $porPagina = 14;
        $pagina    = max(1, (int) ($_GET['pagina'] ?? 1));

        $dataInicio = trim($_GET['data_inicio'] ?? '');
        $dataFim    = trim($_GET['data_fim']    ?? '');

        $reData   = '/^\d{4}-\d{2}-\d{2}$/';
        $inicioOk = $dataInicio !== '' && preg_match($reData, $dataInicio);
        $fimOk    = $dataFim    !== '' && preg_match($reData, $dataFim);

        $filtroExplicito = isset($_GET['data_inicio']) || isset($_GET['data_fim']);

        if ($inicioOk && $fimOk && $dataInicio <= $dataFim) {
            $qInicio = $dataInicio;
            $qFim    = $dataFim;
        } elseif ($inicioOk && !$fimOk) {
            $qInicio = $dataInicio;
            $qFim    = '9999-12-31';
            $dataFim = '';
        } elseif (!$inicioOk && $fimOk) {
            $qInicio    = '0001-01-01';
            $qFim       = $dataFim;
            $dataInicio = '';
        } else {
            $dataInicio      = date('Y-m-01');
            $dataFim         = date('Y-m-t');
            $qInicio         = $dataInicio;
            $qFim            = $dataFim;
            $filtroExplicito = false;
        }

        $total        = Cardapio::contarPorPeriodo($qInicio, $qFim);
        $totalPaginas = max(1, (int) ceil($total / $porPagina));
        $pagina       = min($pagina, $totalPaginas);
        $offset       = ($pagina - 1) * $porPagina;

        $this->render('cardapio/index', [
            'titulo'          => 'Cardápios',
            'cardapios'       => Cardapio::buscarPorPeriodoPaginado($qInicio, $qFim, $offset, $porPagina),
            'dataInicio'      => $dataInicio,
            'dataFim'         => $dataFim,
            'filtroExplicito' => $filtroExplicito,
            'pagina'          => $pagina,
            'totalPaginas'    => $totalPaginas,
            'total'           => $total,
            'porPagina'       => $porPagina,
        ]);
    }

    /**
     * Salva (cria ou atualiza) o cardápio de um dia/tipo (SND).
     */
    public function salvarDia(string $data, string $tipo): void
    {
        if (!in_array($tipo, ['almoco', 'jantar'], true) || !$this->dataNaSemanaAtual($data)) {
            Audit::write('cardapio.salvar_dia', 'blocked', $data . ':' . $tipo, ['motivo' => 'fora_intervalo_ou_tipo']);
            $this->redirect('/painel');
        }

        $destino = $this->input('redirect') === '/cardapio' ? '/cardapio' : '/painel';

        $descricao = mb_strtoupper(trim($this->input('descricao') ?? ''));

        if ($descricao === '') {
            Audit::write('cardapio.salvar_dia', 'blocked', $data . ':' . $tipo, ['motivo' => 'descricao_vazia']);
            $_SESSION['flash'] = ['tipo' => 'error', 'mensagem' => 'A descrição do cardápio não pode ser vazia.'];
            $this->redirect('/painel');
        }

        Cardapio::salvarDia($data, $tipo, $descricao);
        Audit::write('cardapio.salvar_dia', 'success', $data . ':' . $tipo, [
            'descricao' => $descricao,
        ]);
        $this->redirect($destino);
    }

    /**
     * Verifica se $data (Y-m-d) está no intervalo editável: segunda-feira até o domingo da semana atual.
     */
    private function dataNaSemanaAtual(string $data): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            return false;
        }

        $hoje    = (new \DateTime())->setTime(0, 0, 0);
        $diaNum  = (int) $hoje->format('N'); // 1=Seg … 7=Dom
        $segunda = (clone $hoje)->modify('-' . ($diaNum - 1) . ' days')->setTime(0, 0, 0);
        $domingo = (clone $hoje)->modify('+' . (7 - $diaNum) . ' days')->setTime(23, 59, 59);

        $alvo = \DateTime::createFromFormat('Y-m-d', $data);
        if (!$alvo) {
            return false;
        }

        return $alvo >= $segunda && $alvo <= $domingo;
    }
}
