<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Security;
use App\Exceptions\CredenciaisInvalidasException;
use App\Models\Cardapio;
use App\Services\LoginService;

class LoginController extends Controller
{
    /**
     * Exibe a tela de login (GET /).
     */
    public function index(): void
    {
        if (isset($_SESSION['usuario'])) {
            $this->redirect('/painel');
        }

        // Consome a mensagem de erro da sessão e a passa para a view
        $erro = $_SESSION['erro_login'] ?? null;
        unset($_SESSION['erro_login']);

        // Cardápios do dia para exibir sem login
        $cardapiosHoje = [];
        foreach (Cardapio::buscarPorData(date('Y-m-d')) as $c) {
            $cardapiosHoje[$c['tipo']] = $c;
        }

        $this->render('login', ['erro' => $erro, 'cardapiosHoje' => $cardapiosHoje], null);
    }

    /**
     * Processa o formulário de login (POST /login).
     */
    public function autenticar(): void
    {
        $matricula = trim($_POST['matricula'] ?? '');
        $senha     = $_POST['senha'] ?? '';

        $maxAttempts   = (int) ($_ENV['LOGIN_MAX_ATTEMPTS'] ?? 5);
        $windowSeconds = (int) ($_ENV['LOGIN_WINDOW_SECONDS'] ?? 300);
        $lockSeconds   = (int) ($_ENV['LOGIN_LOCKOUT_SECONDS'] ?? 300);

        $ipKey   = Security::clientIp();
        $userKey = strtolower($matricula !== '' ? $matricula : '_empty_');

        $retryIp = Security::isBlocked('web_login_ip', $ipKey, $windowSeconds, $maxAttempts, $lockSeconds);
        $retryUser = Security::isBlocked('web_login_user', $userKey, $windowSeconds, $maxAttempts, $lockSeconds);
        $retryAfter = max($retryIp, $retryUser);

        if ($retryAfter > 0) {
            $_SESSION['erro_login'] = 'Muitas tentativas. Tente novamente em ' . $retryAfter . ' segundos.';
            $this->redirect('/');
        }

        try {
            $usuario = LoginService::autenticar($matricula, $senha);
        } catch (CredenciaisInvalidasException) {
            Security::registerFailure('web_login_ip', $ipKey, $windowSeconds, $maxAttempts, $lockSeconds);
            Security::registerFailure('web_login_user', $userKey, $windowSeconds, $maxAttempts, $lockSeconds);
            usleep(random_int(150000, 350000));
            $_SESSION['erro_login'] = 'Credenciais inválidas.';
            $this->redirect('/');
        }

        Security::clear('web_login_ip', $ipKey);
        Security::clear('web_login_user', $userKey);

        session_regenerate_id(true);

        $_SESSION['usuario'] = [
            'matricula'    => $usuario['matricula'],
            'nome'         => $usuario['nome'],
            'tipo'         => $usuario['tipo'],
            'senha_padrao' => password_verify('stcasa' . $usuario['matricula'], $usuario['senha']),
        ];
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['_last_activity'] = time();

        $destino = $usuario['tipo'] === 'dp' ? '/questor' : '/painel';
        $this->redirect($destino);
    }

    /**
     * Encerra a sessão (GET /logout).
     */
    public function logout(): void
    {
        LoginService::registrarLogout($_SESSION['usuario']['matricula'] ?? null, $_SESSION['usuario']['nome'] ?? null);
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
        }

        session_destroy();
        $this->redirect('/');
    }
}
