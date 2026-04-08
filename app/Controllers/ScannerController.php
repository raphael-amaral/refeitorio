<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Security;
use App\Models\Registro;
use App\Models\Usuario;

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
            return;
        }

        if ($matriculaForm === '' || $senhaForm === '') {
            $this->json(['ok' => false, 'erro' => 'Preencha matrícula e senha.'], 400);
            return;
        }

        // Busca o usuário usando a função do seu Model (já filtra por ativo = 1)
        $usuario = Usuario::buscarPorMatricula($matriculaForm);

        if ($usuario && password_verify($senhaForm, $usuario['senha']) && in_array($usuario['tipo'], ['snd', 'dev'], true)) {
            Security::clear('api_login_ip', $ipKey);
            Security::clear('api_login_user', $userKey);

            $this->json([
                'ok'    => true,
                'nome'  => $usuario['nome'],
                'token' => 'Bearer ' . ($_ENV['SCANNER_TOKEN'] ?? '')
            ]);
            return;
        }

        Security::registerFailure('api_login_ip', $ipKey, $windowSeconds, $maxAttempts, $lockSeconds);
        Security::registerFailure('api_login_user', $userKey, $windowSeconds, $maxAttempts, $lockSeconds);
        usleep(random_int(150000, 350000));

        $this->json(['ok' => false, 'erro' => 'Credenciais inválidas.'], 401);
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
            return; // IMPORTANTE: Para a execução aqui
        }

        // Busca o registro do usuário para o dia de hoje.
        // Dica: Para validar se ele JÁ COMEU, essa função no seu Model precisa 
        // retornar o registro mesmo que o status dele já seja "concluido".
        $registro = Registro::buscarParaScannerHoje($matricula);

        // 1. Erro: Não agendou
        if (!$registro) {
            $this->json(['ok' => false, 'erro' => 'Nenhum agendamento encontrado para hoje.'], 404);
            return;
        }

        // 2. Erro: Já passou o crachá antes (já comeu)
        // Verifique qual é o nome exato da coluna de status no seu banco de dados
        if (isset($registro['status']) && $registro['status'] === 'concluido') {
            $this->json(['ok' => false, 'erro' => 'Refeição já registrada hoje.'], 400);
            return;
        }

        // 3. Erro: Agendamento cancelado (se o seu sistema tiver essa opção)
        if (isset($registro['status']) && $registro['status'] === 'cancelado') {
            $this->json(['ok' => false, 'erro' => 'Agendamento está cancelado.'], 400);
            return;
        }

        // Se passou por tudo, conclui a refeição
        Registro::concluir((int) $registro['id']);

        $this->json([
            'ok'       => true,
            'nome'     => $registro['nome_usuario'],
            'refeicao' => $registro['tipo_refeicao'] === 'almoco' ? 'Almoço' : 'Jantar',
        ]);
    }

    public function listaHoje(): void
    {
        $hoje = date('Y-m-d');

        // Utilizamos a mesma função do relatório web, fixando as datas para o dia atual.
        // Os campos vazios ('') indicam que não estamos filtrando por status nem por matrícula.
        $registros = Registro::buscarParaRelatorio('', $hoje, $hoje, '');

        $listaFormatada = [];

        foreach ($registros as $r) {
            $listaFormatada[] = [
                // O aplicativo Kotlin espera exatamente estas três chaves
                'matricula' => $r['matricula'] ?? '',
                'nome_usuario'  => $r['nome_usuario'] ?? 'Desconhecido',
                'tipo_refeicao' => ($r['tipo_refeicao'] ?? '') === 'almoco' ? 'Almoço' : 'Jantar',
                'status'        => $r['status'] ?? 'agendado'
            ];
        }

        $this->json([
            'ok'    => true,
            'lista' => $listaFormatada
        ]);
    }
}
