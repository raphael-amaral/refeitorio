<?php

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Controller;
use App\Models\Cardapio;

class CardapioController extends Controller
{
    /**
     * Lista cardápios (usuário comum vê apenas os próximos; admin/SND vê todos com filtro por data).
     */
    public function index(): void
    {
        $tipo      = $_SESSION['usuario']['tipo'];
        $porPagina = 14;
        $pagina    = max(1, (int) ($_GET['pagina'] ?? 1));

        $dataInicio = trim($_GET['data_inicio'] ?? '');
        $dataFim    = trim($_GET['data_fim']    ?? '');

        $reData   = '/^\d{4}-\d{2}-\d{2}$/';
        $inicioOk = $dataInicio !== '' && preg_match($reData, $dataInicio);
        $fimOk    = $dataFim    !== '' && preg_match($reData, $dataFim);

        if (in_array($tipo, ['snd', 'dev'], true)) {
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
            $cardapios    = Cardapio::buscarPorPeriodoPaginado($qInicio, $qFim, $offset, $porPagina);
        } else {
            $cardapios       = Cardapio::proximosDias(14);
            $dataInicio      = '';
            $dataFim         = '';
            $filtroExplicito = false;
            $total           = count($cardapios);
            $totalPaginas    = 1;
            $pagina          = 1;
        }

        $this->render('cardapio/index', [
            'titulo'          => 'Cardápios',
            'cardapios'       => $cardapios,
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
     * Processa o formulário de criação (admin/dev).
     */
    public function criar(): void
    {
        $dados = [
            'data'      => $this->input('data'),
            'tipo'      => $this->input('tipo'),
            'descricao' => mb_strtoupper(trim($this->input('descricao') ?? '')),
        ];

        if (!$dados['data'] || !in_array($dados['tipo'], ['almoco', 'jantar'], true) || $dados['descricao'] === '') {
            Audit::write('cardapio.criar', 'blocked', null, [
                'motivo' => 'validacao',
                'data' => $dados['data'],
                'tipo' => $dados['tipo'],
            ]);
            $this->flash('Preencha todos os campos corretamente.', 'error');
            $this->redirect('/cardapio');
        }

        Cardapio::criar($dados);
        Audit::write('cardapio.criar', 'success', null, [
            'data' => $dados['data'],
            'tipo' => $dados['tipo'],
        ]);
        $this->flash('Cardápio criado com sucesso.', 'success');
        $this->redirect('/cardapio');
    }

    /**
     * Exibe o formulário de edição (admin/dev).
     */
    public function editar(string $id): void
    {
        $cardapio = Cardapio::buscarPorId((int) $id);

        if (!$cardapio) {
            $this->redirect('/cardapio');
        }

        $this->render('cardapio/form', [
            'titulo'   => 'Editar Cardápio',
            'cardapio' => $cardapio,
        ]);
    }

    /**
     * Processa o formulário de edição (admin/dev).
     */
    public function atualizar(string $id): void
    {
        $dados = [
            'data'      => $this->input('data'),
            'tipo'      => $this->input('tipo'),
            'descricao' => mb_strtoupper(trim($this->input('descricao') ?? '')),
        ];

        if (!$dados['data'] || !in_array($dados['tipo'], ['almoco', 'jantar'], true) || $dados['descricao'] === '') {
            Audit::write('cardapio.atualizar', 'blocked', (string) $id, [
                'motivo' => 'validacao',
                'data' => $dados['data'],
                'tipo' => $dados['tipo'],
            ]);
            $this->flash('Preencha todos os campos corretamente.', 'error');
            $this->redirect('/cardapio');
        }

        Cardapio::atualizar((int) $id, $dados);
        Audit::write('cardapio.atualizar', 'success', (string) $id, [
            'data' => $dados['data'],
            'tipo' => $dados['tipo'],
        ]);
        $this->flash('Cardápio atualizado com sucesso.', 'success');
        $this->redirect('/cardapio');
    }

    /**
     * Remove um cardápio (admin/dev).
     * Bloqueia se houver agendamentos vinculados (integridade referencial).
     */
    public function excluir(string $id): void
    {
        $idInt = (int) $id;

        if (\App\Models\Registro::contarPorCardapio($idInt) > 0) {
            Audit::write('cardapio.excluir', 'blocked', (string) $idInt, ['motivo' => 'possui_registros']);
            $this->flash('Não é possível excluir: este cardápio possui agendamentos vinculados.', 'error');
            $this->redirect('/cardapio');
        }

        try {
            Cardapio::deletar($idInt);
            Audit::write('cardapio.excluir', 'success', (string) $idInt);
        } catch (\PDOException $e) {
            Audit::write('cardapio.excluir', 'error', (string) $idInt, ['erro' => $e->getCode()]);
            $this->flash('Erro ao excluir o cardápio. Verifique se há dados vinculados.', 'error');
        }

        $this->redirect('/cardapio');
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
     * Verifica se $data (Y-m-d) está no intervalo editável: hoje até o próximo domingo.
     */
    private function dataNaSemanaAtual(string $data): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            return false;
        }

        $hoje    = (new \DateTime())->setTime(0, 0, 0);
        $diaNum  = (int) $hoje->format('N'); // 1=Seg … 7=Dom
        $domingo = (clone $hoje)->modify('+' . (7 - $diaNum) . ' days')->setTime(23, 59, 59);

        $alvo = \DateTime::createFromFormat('Y-m-d', $data);
        if (!$alvo) {
            return false;
        }

        return $alvo >= $hoje && $alvo <= $domingo;
    }
}
