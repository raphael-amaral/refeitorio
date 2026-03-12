<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Cardapio;

class CardapioController extends Controller
{
    /**
     * Lista cardápios (usuário comum vê apenas os próximos; admin/copa vê todos com filtro por data).
     */
    public function index(): void
    {
        $tipo = $_SESSION['usuario']['tipo'];

        $dataInicio = trim($_GET['data_inicio'] ?? '');
        $dataFim    = trim($_GET['data_fim']    ?? '');

        // Valida formato Y-m-d para cada campo preenchido
        $reData = '/^\d{4}-\d{2}-\d{2}$/';
        $inicioOk = $dataInicio !== '' && preg_match($reData, $dataInicio);
        $fimOk    = $dataFim    !== '' && preg_match($reData, $dataFim);

        if (in_array($tipo, ['copa', 'dev'], true)) {
            if ($inicioOk && $fimOk && $dataInicio <= $dataFim) {
                // Ambas válidas e coerentes
                $cardapios = Cardapio::buscarPorPeriodo($dataInicio, $dataFim);
            } elseif ($inicioOk && !$fimOk) {
                // Só data início: a partir dessa data
                $cardapios = Cardapio::buscarPorPeriodo($dataInicio, '9999-12-31');
                $dataFim   = '';
            } elseif (!$inicioOk && $fimOk) {
                // Só data fim: até essa data
                $cardapios  = Cardapio::buscarPorPeriodo('0001-01-01', $dataFim);
                $dataInicio = '';
            } else {
                // Sem filtro válido: lista tudo
                $cardapios  = Cardapio::todos('data DESC, tipo ASC');
                $dataInicio = '';
                $dataFim    = '';
            }
        } else {
            $cardapios  = Cardapio::proximosDias(14);
            $dataInicio = '';
            $dataFim    = '';
        }

        $this->render('cardapio/index', [
            'titulo'     => 'Cardápios',
            'cardapios'  => $cardapios,
            'dataInicio' => $dataInicio,
            'dataFim'    => $dataFim,
        ]);
    }

    /**
     * Exibe o formulário de criação (admin/dev).
     */
    public function novo(): void
    {
        $this->render('cardapio/form', [
            'titulo'   => 'Novo Cardápio',
            'cardapio' => null,
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
            'descricao' => $this->input('descricao'),
        ];

        Cardapio::criar($dados);
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
            'descricao' => $this->input('descricao'),
        ];

        Cardapio::atualizar((int) $id, $dados);
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
            $this->flash('Não é possível excluir: este cardápio possui agendamentos vinculados.', 'error');
            $this->redirect('/cardapio');
        }

        try {
            Cardapio::deletar($idInt);
        } catch (\PDOException $e) {
            $this->flash('Erro ao excluir o cardápio. Verifique se há dados vinculados.', 'error');
        }

        $this->redirect('/cardapio');
    }

    /**
     * Formulário de edição de um dia/tipo a partir do painel (copa).
     */
    public function editarDia(string $data, string $tipo): void
    {
        if (!in_array($tipo, ['almoco', 'jantar'], true) || !$this->dataNaSemanaAtual($data)) {
            $this->redirect('/painel');
        }

        $cardapio = Cardapio::buscarPorDataETipo($data, $tipo);
        $labels   = ['almoco' => 'Almoço', 'jantar' => 'Jantar'];

        $this->render('cardapio/editar_dia', [
            'titulo'    => 'Editar Cardápio',
            'data'      => $data,
            'tipo'      => $tipo,
            'labelTipo' => $labels[$tipo],
            'descricao' => $cardapio['descricao'] ?? '',
            'redirect'  => $_GET['from'] === 'cardapio' ? '/cardapio' : '/painel',
        ]);
    }

    /**
     * Salva (cria ou atualiza) o cardápio de um dia/tipo (copa).
     */
    public function salvarDia(string $data, string $tipo): void
    {
        if (!in_array($tipo, ['almoco', 'jantar'], true) || !$this->dataNaSemanaAtual($data)) {
            $this->redirect('/painel');
        }

        $destino = $this->input('redirect') === '/cardapio' ? '/cardapio' : '/painel';

        $descricao = $this->input('descricao');
        Cardapio::salvarDia($data, $tipo, $descricao);
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
