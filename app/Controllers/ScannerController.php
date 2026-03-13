<?php

namespace App\Controllers;

use App\Core\Controller;
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

        if ($matriculaForm === '' || $senhaForm === '') {
            $this->json(['ok' => false, 'erro' => 'Preencha matrícula e senha.'], 400);
            return;
        }

        // Busca o usuário usando a função do seu Model (já filtra por ativo = 1)
        $usuario = \App\Models\Usuario::buscarPorMatricula($matriculaForm);

        if (!$usuario) {
            $this->json(['ok' => false, 'erro' => 'Matrícula não encontrada ou inativa.'], 404);
            return;
        }

        // Valida a senha usando BCRYPT (padrão do seu novoUsuario)
        if (password_verify($senhaForm, $usuario['senha'])) {

            // Confere se o tipo de usuário é snd ou dev
            if (in_array($usuario['tipo'], ['snd', 'dev'], true)) {
                $this->json([
                    'ok'    => true,
                    'nome'  => $usuario['nome'],
                    'token' => 'Bearer CHAVE_SECRETA_COPA_2026'
                ]);
                return;
            } else {
                $this->json(['ok' => false, 'erro' => 'Sem permissão para usar o aplicativo.'], 403);
                return;
            }
        }

        $this->json(['ok' => false, 'erro' => 'Senha incorreta.'], 401);
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
}
