<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Exceptions\DataOuTipoInvalidoException;
use App\Exceptions\DescricaoVaziaException;
use App\Models\Cardapio;
use App\Services\CardapioService;

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
        $destino = $this->input('redirect') === '/cardapio' ? '/cardapio' : '/painel';

        try {
            CardapioService::salvarDia($data, $tipo, $this->input('descricao'));
        } catch (DataOuTipoInvalidoException) {
            $this->redirect('/painel');
        } catch (DescricaoVaziaException) {
            $this->flash('A descrição do cardápio não pode ser vazia.', 'error');
            $this->redirect('/painel');
        }

        $this->redirect($destino);
    }
}
