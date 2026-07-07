<?php

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Security;
use App\Exceptions\AgendamentoCanceladoException;
use App\Exceptions\CredenciaisInvalidasException;
use App\Exceptions\ForaDoHorarioExcecaoException;
use App\Exceptions\JaConcluidoException;
use App\Exceptions\SemAgendamentoException;
use App\Exceptions\SemCardapioHojeException;
use App\Exceptions\TipoNaoPermitidoException;
use App\Services\ScannerService;

class ScannerController extends Controller
{
    /**
     * Valida o acesso enviado pelo app Android (POST /api/login).
     * Retorna o token de acesso em caso de sucesso.
     */
    public function loginApp(): void
    {
        // O app Kotlin envia o campo "usuario", que no seu sistema representa a matrícula
        $matriculaForm = trim($_POST['usuario'] ?? '');
        $senhaForm = trim($_POST['senha'] ?? '');

        $maxAttempts   = (int) ($_ENV['API_LOGIN_MAX_ATTEMPTS'] ?? 5);
        $windowSeconds = (int) ($_ENV['API_LOGIN_WINDOW_SECONDS'] ?? 300);
        $lockSeconds   = (int) ($_ENV['API_LOGIN_LOCKOUT_SECONDS'] ?? 300);

        $ipKey   = Security::clientIp();
        $userKey = strtolower($matriculaForm !== '' ? $matriculaForm : '_empty_');

        $retryIp = Security::isBlocked('api_login_ip', $ipKey, $windowSeconds, $maxAttempts, $lockSeconds);
        $retryUser = Security::isBlocked('api_login_user', $userKey, $windowSeconds, $maxAttempts, $lockSeconds);
        $retryAfter = max($retryIp, $retryUser);

        if ($retryAfter > 0) {
            $this->json(['ok' => false, 'erro' => 'Muitas tentativas. Tente novamente mais tarde.'], 429);
        }

        if ($matriculaForm === '' || $senhaForm === '') {
            $this->json(['ok' => false, 'erro' => 'Preencha matrícula e senha.'], 400);
        }

        try {
            $usuario = ScannerService::autenticar($matriculaForm, $senhaForm);
        } catch (CredenciaisInvalidasException) {
            Security::registerFailure('api_login_ip', $ipKey, $windowSeconds, $maxAttempts, $lockSeconds);
            Security::registerFailure('api_login_user', $userKey, $windowSeconds, $maxAttempts, $lockSeconds);
            usleep(random_int(150000, 350000));
            $this->json(['ok' => false, 'erro' => 'Credenciais inválidas.'], 401);
        }

        Security::clear('api_login_ip', $ipKey);
        Security::clear('api_login_user', $userKey);

        $this->json([
            'ok'    => true,
            'nome'  => $usuario['nome'],
            'token' => 'Bearer ' . ($_ENV['SCANNER_TOKEN'] ?? ''),
        ]);
    }

    /**
     * Processa uma matrícula lida pelo scanner (POST /scanner/processar).
     * Retorna JSON.
     */
    public function processar(): void
    {
        $matricula = trim($_POST['matricula'] ?? '');

        if ($matricula === '') {
            $this->json(['ok' => false, 'erro' => 'Matrícula não informada.'], 422);
        }

        try {
            $registro = ScannerService::processar($matricula);
        } catch (SemAgendamentoException) {
            $this->json(['ok' => false, 'erro' => 'Nenhum agendamento encontrado para hoje.'], 404);
        } catch (JaConcluidoException) {
            $this->json(['ok' => false, 'erro' => 'Refeição já registrada hoje.'], 400);
        } catch (AgendamentoCanceladoException) {
            $this->json(['ok' => false, 'erro' => 'Agendamento está cancelado.'], 400);
        }

        $this->json([
            'ok'       => true,
            'nome'     => $registro['nome_usuario'],
            'refeicao' => $registro['tipo_refeicao'] === 'almoco' ? 'Almoço' : 'Jantar',
        ]);
    }

    /**
     * Registra uma exceção para um funcionário sem agendamento (POST /scanner/excecao).
     * Vincula sempre ao almoço do dia. Bloqueado após EXCECAO_LIMIT_TIME (padrão 13:30).
     */
    public function excecao(): void
    {
        $matricula = trim($_POST['matricula'] ?? '');
        $senha     = trim($_POST['senha']     ?? '');

        if ($matricula === '' || $senha === '') {
            Audit::write('scanner.excecao', 'blocked', $matricula !== '' ? $matricula : null, ['motivo' => 'campos_vazios']);
            $this->json(['ok' => false, 'erro' => 'Matrícula e senha são obrigatórias.'], 422);
        }

        try {
            $usuario = ScannerService::registrarExcecao($matricula, $senha);
        } catch (ForaDoHorarioExcecaoException $e) {
            $this->json(['ok' => false, 'erro' => "Exceções encerradas após {$e->limiteHora}."], 422);
        } catch (CredenciaisInvalidasException) {
            $this->json(['ok' => false, 'erro' => 'Credenciais inválidas.'], 401);
        } catch (TipoNaoPermitidoException) {
            $this->json(['ok' => false, 'erro' => 'Exceção disponível apenas para funcionários comuns.'], 403);
        } catch (SemCardapioHojeException) {
            $this->json(['ok' => false, 'erro' => 'Nenhum cardápio de almoço cadastrado para hoje.'], 404);
        } catch (JaConcluidoException) {
            $this->json(['ok' => false, 'erro' => 'Refeição já registrada hoje.'], 400);
        }

        $this->json([
            'ok'       => true,
            'nome'     => $usuario['nome'],
            'refeicao' => 'Almoço',
        ]);
    }

    public function listaHoje(): void
    {
        $this->json([
            'ok'    => true,
            'lista' => ScannerService::listaHoje(),
        ]);
    }
}
