<?php

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Controller;
use App\Models\Usuario;

class ContaController extends Controller
{
    /**
     * Altera a senha do usuário logado (POST /conta/senha).
     */
    public function alterarSenha(): void
    {
        $matricula   = $_SESSION['usuario']['matricula'];
        $senhaAtual  = $_POST['senha_atual'] ?? '';
        $novaSenha   = $_POST['nova_senha'] ?? '';
        $confirmacao = $_POST['confirmar_nova_senha'] ?? '';

        $usuario = Usuario::buscarPorMatricula($matricula);

        if (!$usuario || !password_verify($senhaAtual, $usuario['senha'])) {
            Audit::write('conta.senha', 'blocked', $matricula, ['motivo' => 'senha_atual_incorreta']);
            $this->flash('Senha atual incorreta.', 'error');
            $this->redirect('/painel');
        }

        if ($novaSenha === '' || strlen($novaSenha) < 6) {
            $this->flash('A nova senha deve ter pelo menos 6 caracteres.', 'error');
            $this->redirect('/painel');
        }

        if ($novaSenha !== $confirmacao) {
            $this->flash('A nova senha e a confirmação não coincidem.', 'error');
            $this->redirect('/painel');
        }

        Usuario::atualizarUsuario($matricula, $usuario['nome'], $usuario['tipo'], $novaSenha);
        $_SESSION['usuario']['senha_padrao'] = false;

        Audit::write('conta.senha', 'success', $matricula, [
            'era_senha_padrao' => !empty($_SESSION['usuario']['senha_padrao']),
        ]);
        $this->flash('Senha alterada com sucesso.', 'success');
        $this->redirect('/painel');
    }
}
