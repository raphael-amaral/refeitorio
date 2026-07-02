<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Exceptions\SenhaAtualIncorretaException;
use App\Exceptions\SenhaCurtaException;
use App\Exceptions\SenhasNaoCoincidemException;
use App\Services\ContaService;

class ContaController extends Controller
{
    /**
     * Altera a senha do usuário logado (POST /conta/senha).
     */
    public function alterarSenha(): void
    {
        $matricula = $_SESSION['usuario']['matricula'];

        try {
            ContaService::alterarSenha(
                $matricula,
                $_POST['senha_atual'] ?? '',
                $_POST['nova_senha'] ?? '',
                $_POST['confirmar_nova_senha'] ?? ''
            );
        } catch (SenhaAtualIncorretaException) {
            $this->flash('Senha atual incorreta.', 'error');
            $this->redirect('/painel');
        } catch (SenhaCurtaException) {
            $this->flash('A nova senha deve ter pelo menos 6 caracteres.', 'error');
            $this->redirect('/painel');
        } catch (SenhasNaoCoincidemException) {
            $this->flash('A nova senha e a confirmação não coincidem.', 'error');
            $this->redirect('/painel');
        }

        $_SESSION['usuario']['senha_padrao'] = false;
        $this->flash('Senha alterada com sucesso.', 'success');
        $this->redirect('/painel');
    }
}
