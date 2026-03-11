<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Cardapio;

class CardapioController extends Controller
{
    /**
     * Lista cardápios (usuário comum vê apenas os próximos; admin/copa vê todos recentes).
     */
    public function index(): void
    {
        $tipo = $_SESSION['usuario']['tipo'];

        if (in_array($tipo, ['admin', 'dev', 'copa'], true)) {
            $cardapios = Cardapio::todos('data DESC, tipo ASC');
        } else {
            $cardapios = Cardapio::proximosDias(14);
        }

        $this->render('cardapio/index', [
            'titulo'    => 'Cardápios',
            'cardapios' => $cardapios,
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
     */
    public function excluir(string $id): void
    {
        Cardapio::deletar((int) $id);
        $this->redirect('/cardapio');
    }

    /**
     * Formulário de edição de um dia/tipo a partir do painel (copa).
     */
    public function editarDia(string $data, string $tipo): void
    {
        if (!in_array($tipo, ['almoco', 'jantar'], true)) {
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
        ]);
    }

    /**
     * Salva (cria ou atualiza) o cardápio de um dia/tipo (copa).
     */
    public function salvarDia(string $data, string $tipo): void
    {
        if (!in_array($tipo, ['almoco', 'jantar'], true)) {
            $this->redirect('/painel');
        }

        $descricao = $this->input('descricao');
        Cardapio::salvarDia($data, $tipo, $descricao);
        $this->redirect('/painel');
    }
}
