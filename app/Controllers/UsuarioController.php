<?php

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Controller;
use App\Models\Usuario;

class UsuarioController extends Controller
{
    /**
     * Lista todos os usuários e exibe formulário de criação.
     */
    public function index(): void
    {
        $busca     = trim($_GET['busca'] ?? '');
        $porPagina = 20;
        $pagina    = max(1, (int) ($_GET['pagina'] ?? 1));

        $ordenarPor   = ($busca !== '' && ctype_digit($busca)) ? 'matricula' : 'nome';
        $total        = Usuario::contarTotal($busca);
        $totalPaginas = max(1, (int) ceil($total / $porPagina));
        $pagina       = min($pagina, $totalPaginas);
        $offset       = ($pagina - 1) * $porPagina;

        $this->render('usuario/index', [
            'titulo'       => 'Usuários',
            'usuarios'     => Usuario::listarPaginado($busca, $offset, $porPagina, $ordenarPor),
            'busca'        => $busca,
            'pagina'       => $pagina,
            'totalPaginas' => $totalPaginas,
            'total'        => $total,
        ]);
    }

    /**
     * Cria um novo usuário (POST /usuario).
     */
    public function criar(): void
    {
        $matricula      = trim($_POST['matricula'] ?? '');
        $nome           = mb_strtoupper(trim($_POST['nome'] ?? ''));
        $senha          = $_POST['senha'] ?? '';
        $confirmarSenha = $_POST['confirmar_senha'] ?? '';
        $tipo           = $_POST['tipo'] ?? 'comum';

        $tiposPermitidos = ['comum', 'snd', 'dp', 'dev'];

        if (!$matricula || !$nome || !in_array($tipo, $tiposPermitidos, true)) {
            Audit::write('usuario.criar', 'blocked', $matricula !== '' ? $matricula : null, [
                'motivo' => 'validacao',
                'tipo' => $tipo,
            ]);
            $this->flash('Preencha todos os campos corretamente.', 'error');
            $this->redirect('/usuario');
        }

        if ($senha === '') {
            $senha = 'stcasa' . $matricula;
        } else {
            if (strlen($senha) < 6) {
                $this->flash('A senha deve ter pelo menos 6 caracteres.', 'error');
                $this->redirect('/usuario');
            }
            if ($senha !== $confirmarSenha) {
                $this->flash('As senhas não coincidem.', 'error');
                $this->redirect('/usuario');
            }
        }

        try {
            Usuario::novoUsuario($matricula, $nome, $senha, $tipo);
            Audit::write('usuario.criar', 'success', $matricula, ['tipo' => $tipo]);
            $this->flash("Usuário {$nome} criado com sucesso.", 'success');
        } catch (\PDOException $e) {
            Audit::write('usuario.criar', 'error', $matricula, [
                'tipo' => $tipo,
                'erro' => $e->getCode(),
            ]);
            if ($e->getCode() === '23000') {
                $this->flash('Matrícula já cadastrada.', 'error');
            } else {
                $this->flash('Erro ao criar usuário.', 'error');
            }
        }

        $this->redirect('/usuario');
    }

    /**
     * Importa múltiplos usuários em lote (POST /usuario/importar).
     * Formato esperado: uma linha por usuário → "matricula;nome"
     */
    public function importar(): void
    {
        $tipo = $_POST['tipo_importacao'] ?? 'comum';

        $tiposPermitidos = ['comum', 'snd', 'dp', 'dev'];
        if (!in_array($tipo, $tiposPermitidos, true)) {
            $tipo = 'comum';
        }

        // Arquivo CSV tem prioridade sobre o textarea
        $arquivo = $_FILES['arquivo'] ?? null;
        if (!empty($arquivo['tmp_name']) && is_uploaded_file($arquivo['tmp_name'])) {
            $conteudo = file_get_contents($arquivo['tmp_name']);
            $conteudo = ltrim($conteudo, "\xEF\xBB\xBF"); // remove BOM UTF-8
            $lista    = str_replace(["\r\n", "\r"], "\n", $conteudo);
        } else {
            $lista = trim($_POST['lista'] ?? '');
        }

        if ($lista === '') {
            Audit::write('usuario.importar', 'blocked', null, ['motivo' => 'lista_vazia', 'tipo' => $tipo]);
            $this->flash('Nenhum dado para importar.', 'error');
            $this->redirect('/usuario');
        }

        set_time_limit(300);

        $linhas   = array_values(array_filter(array_map('trim', explode("\n", $lista))));
        $invalidas = [];
        $entradas  = [];
        $vistos    = [];

        foreach ($linhas as $linha) {
            // Detecta separador: ; , ou espaço
            if (str_contains($linha, ';')) {
                [$matricula, $nome] = array_map('trim', explode(';', $linha, 2));
            } elseif (str_contains($linha, ',')) {
                [$matricula, $nome] = array_map('trim', explode(',', $linha, 2));
            } else {
                $partes    = preg_split('/\s+/', $linha, 2);
                $matricula = trim($partes[0] ?? '');
                $nome      = trim($partes[1] ?? '');
            }

            if ($matricula === '' || $nome === '') {
                $invalidas[] = mb_strimwidth($linha, 0, 40, '…');
                continue;
            }

            // Duplicata dentro do próprio arquivo
            if (isset($vistos[$matricula])) {
                $invalidas[] = "Matrícula {$matricula} repetida no arquivo";
                continue;
            }

            $vistos[$matricula] = true;
            $entradas[] = ['matricula' => $matricula, 'nome' => mb_strtoupper($nome)];
        }

        if ($invalidas) {
            Audit::write('usuario.importar', 'blocked', null, [
                'motivo'          => 'linhas_invalidas',
                'tipo'            => $tipo,
                'total_invalidas' => count($invalidas),
            ]);
            $_SESSION['import_invalidas'] = $invalidas;
            $this->flash(count($invalidas) . ' problema(s) encontrado(s) no arquivo. Corrija e tente novamente.', 'error');
            $this->redirect('/usuario');
        }

        // Verifica duplicatas no banco antes de inserir qualquer usuário
        $matriculas = array_column($entradas, 'matricula');
        $duplicadas = Usuario::matriculasExistentes($matriculas);

        if (!empty($duplicadas)) {
            Audit::write('usuario.importar', 'blocked', null, [
                'motivo'           => 'matriculas_duplicadas',
                'tipo'             => $tipo,
                'total_duplicadas' => count($duplicadas),
            ]);
            $_SESSION['import_duplicadas'] = $duplicadas;
            $this->flash(count($duplicadas) . ' matrícula(s) já cadastrada(s) no sistema. Corrija a lista e tente novamente.', 'error');
            $this->redirect('/usuario');
        }

        $criados = 0;
        foreach ($entradas as $entrada) {
            // Custo 8 (vs padrão 10) para viabilizar importação em lote sem travar o servidor
            Usuario::novoUsuario($entrada['matricula'], $entrada['nome'], 'stcasa' . $entrada['matricula'], $tipo, 8);
            Audit::write('usuario.criar', 'success', $entrada['matricula'], ['tipo' => $tipo, 'lote' => true]);
            $criados++;
        }

        $this->flash("{$criados} usuário(s) importado(s) com sucesso.", 'success');
        $this->redirect('/usuario');
    }

    /**
     * Atualiza nome, tipo e senha de um usuário (POST /usuario/{matricula}/editar).
     */
    public function editar(string $matricula): void
    {
        $nome  = mb_strtoupper(trim($_POST['nome'] ?? ''));
        $tipo  = $_POST['tipo'] ?? 'comum';
        $senha = $_POST['senha'] ?? '';

        if ($matricula === ($_SESSION['usuario']['matricula'] ?? '')) {
            $tipo = $_SESSION['usuario']['tipo'];
        }

        $tiposPermitidos = ['comum', 'snd', 'dp', 'dev'];
        $usuarioAtual = Usuario::buscarPorMatricula($matricula);

        if (!$nome || !in_array($tipo, $tiposPermitidos, true)) {
            Audit::write('usuario.editar', 'blocked', $matricula, [
                'motivo' => 'validacao',
                'tipo' => $tipo,
            ]);
            $this->flash('Preencha os campos corretamente.', 'error');
            $this->redirect('/usuario');
        }

        Usuario::atualizarUsuario($matricula, $nome, $tipo, $senha !== '' ? $senha : null);
        Audit::write('usuario.editar', 'success', $matricula, [
            'nome_anterior'  => $usuarioAtual['nome'] ?? null,
            'tipo_anterior'  => $usuarioAtual['tipo'] ?? null,
            'nome_novo'      => $nome,
            'tipo_novo'      => $tipo,
            'senha_alterada' => $senha !== '',
        ]);
        $this->flash('Usuário atualizado com sucesso.', 'success');
        $this->redirect('/usuario');
    }

    /**
     * Inativa um usuário (POST /usuario/{matricula}/inativar).
     */
    public function inativar(string $matricula): void
    {
        // Impede inativar a própria conta
        if ($matricula === ($_SESSION['usuario']['matricula'] ?? '')) {
            Audit::write('usuario.inativar', 'blocked', $matricula, ['motivo' => 'auto_inativacao']);
            $this->flash('Você não pode inativar sua própria conta.', 'error');
            $this->redirect('/usuario');
        }

        $nomeAlvo = Usuario::buscarPorMatricula($matricula)['nome'] ?? null;
        Usuario::alterarAtivo($matricula, 0);
        Audit::write('usuario.inativar', 'success', $matricula, ['nome' => $nomeAlvo]);
        $this->flash('Usuário inativado.', 'success');
        $this->redirect('/usuario');
    }

    /**
     * Reativa um usuário (POST /usuario/{matricula}/ativar).
     */
    public function ativar(string $matricula): void
    {
        $nomeAlvo = Usuario::buscarPorMatricula($matricula)['nome'] ?? null;
        Usuario::alterarAtivo($matricula, 1);
        Audit::write('usuario.ativar', 'success', $matricula, ['nome' => $nomeAlvo]);
        $this->flash('Usuário reativado.', 'success');
        $this->redirect('/usuario');
    }

    /**
     * Exclui permanentemente um usuário sem registros (POST /usuario/{matricula}/excluir).
     */
    public function excluir(string $matricula): void
    {
        if ($matricula === ($_SESSION['usuario']['matricula'] ?? '')) {
            Audit::write('usuario.excluir', 'blocked', $matricula, ['motivo' => 'auto_exclusao']);
            $this->flash('Você não pode excluir sua própria conta.', 'error');
            $this->redirect('/usuario');
        }

        if (Usuario::temRegistros($matricula)) {
            Audit::write('usuario.excluir', 'blocked', $matricula, ['motivo' => 'possui_registros']);
            $this->flash('Não é possível excluir: usuário possui agendamentos.', 'error');
            $this->redirect('/usuario');
        }

        $nomeAlvo = Usuario::buscarPorMatricula($matricula)['nome'] ?? null;
        Usuario::excluir($matricula);
        Audit::write('usuario.excluir', 'success', $matricula, ['nome' => $nomeAlvo]);
        $this->flash('Usuário excluído com sucesso.', 'success');
        $this->redirect('/usuario');
    }
}
