<?php

namespace App\Services;

use App\Core\Audit;
use App\Exceptions\AgendamentoCanceladoException;
use App\Exceptions\CredenciaisInvalidasException;
use App\Exceptions\ForaDoHorarioExcecaoException;
use App\Exceptions\JaConcluidoException;
use App\Exceptions\SemAgendamentoException;
use App\Exceptions\SemCardapioHojeException;
use App\Exceptions\TipoNaoPermitidoException;
use App\Models\Cardapio;
use App\Models\Registro;
use App\Models\Usuario;

class ScannerService
{
    /**
     * Valida login do app do scanner. Só usuários snd/dev podem operar o scanner.
     *
     * @return array{matricula: string, nome: string, tipo: string}
     */
    public static function autenticar(string $matricula, string $senha): array
    {
        $usuario = Usuario::buscarPorMatricula($matricula);

        if (!$usuario || !password_verify($senha, $usuario['senha']) || !Perfil::podeScanner($usuario['tipo'])) {
            Audit::write('scanner.login_app', 'failed', $matricula !== '' ? $matricula : null, ['motivo' => 'credenciais_invalidas']);
            throw new CredenciaisInvalidasException();
        }

        Audit::write('scanner.login_app', 'success', $matricula, ['nome' => $usuario['nome']]);
        return $usuario;
    }

    /**
     * Processa a leitura do crachá: conclui a refeição do dia se o agendamento permitir.
     */
    public static function processar(string $matricula): array
    {
        $registro = Registro::buscarParaScannerHoje($matricula);

        if (!$registro) {
            Audit::write('scanner.processar', 'blocked', $matricula, ['motivo' => 'sem_agendamento']);
            throw new SemAgendamentoException();
        }

        if (($registro['status'] ?? null) === 'concluido') {
            Audit::write('scanner.processar', 'blocked', $matricula, ['motivo' => 'ja_concluido', 'nome_usuario' => $registro['nome_usuario'], 'id_registro' => $registro['id'], 'id_cardapio' => $registro['id_cardapio'], 'tipo_refeicao' => $registro['tipo_refeicao']]);
            throw new JaConcluidoException((int) $registro['id_cardapio']);
        }

        if (($registro['status'] ?? null) === 'cancelado') {
            Audit::write('scanner.processar', 'blocked', $matricula, ['motivo' => 'agendamento_cancelado', 'nome_usuario' => $registro['nome_usuario'], 'id_registro' => $registro['id'], 'id_cardapio' => $registro['id_cardapio'], 'tipo_refeicao' => $registro['tipo_refeicao']]);
            throw new AgendamentoCanceladoException((int) $registro['id_cardapio']);
        }

        Registro::concluir((int) $registro['id']);

        Audit::write('scanner.processar', 'success', $matricula, [
            'nome_usuario'  => $registro['nome_usuario'],
            'id_registro'   => $registro['id'],
            'id_cardapio'   => $registro['id_cardapio'],
            'data_cardapio' => date('Y-m-d'),
            'tipo_refeicao' => $registro['tipo_refeicao'],
        ]);

        return $registro;
    }

    /**
     * Registra uma exceção (crachá esquecido) para o almoço do dia, mediante login do próprio funcionário.
     *
     * @return array{matricula: string, nome: string, tipo: string}
     */
    public static function registrarExcecao(string $matricula, string $senha): array
    {
        $limiteHora = $_ENV['EXCECAO_LIMIT_TIME'] ?? '13:30';
        if (date('H:i') > $limiteHora) {
            Audit::write('scanner.excecao', 'blocked', $matricula, ['motivo' => 'fora_do_horario', 'limite' => $limiteHora]);
            throw new ForaDoHorarioExcecaoException($limiteHora);
        }

        $usuario = Usuario::buscarPorMatricula($matricula);

        if (!$usuario || !password_verify($senha, $usuario['senha'])) {
            Audit::write('scanner.excecao', 'blocked', $matricula, ['motivo' => 'credenciais_invalidas']);
            throw new CredenciaisInvalidasException();
        }

        if (!Perfil::ehComum($usuario['tipo'])) {
            Audit::write('scanner.excecao', 'blocked', $matricula, ['motivo' => 'tipo_nao_permitido', 'tipo' => $usuario['tipo']]);
            throw new TipoNaoPermitidoException($usuario['tipo']);
        }

        $cardapio = Cardapio::almocoDaData(date('Y-m-d'));
        if (!$cardapio) {
            Audit::write('scanner.excecao', 'blocked', $matricula, ['motivo' => 'sem_cardapio_hoje']);
            throw new SemCardapioHojeException();
        }

        if (Registro::jaConcluidoPorCardapio($matricula, (int) $cardapio['id'])) {
            Audit::write('scanner.excecao', 'blocked', $matricula, ['motivo' => 'ja_concluido', 'id_cardapio' => $cardapio['id']]);
            throw new JaConcluidoException((int) $cardapio['id']);
        }

        Registro::concluirExcecao($matricula, (int) $cardapio['id']);

        Audit::write('scanner.excecao', 'success', $matricula, [
            'nome_usuario'  => $usuario['nome'],
            'id_cardapio'   => $cardapio['id'],
            'data_cardapio' => date('Y-m-d'),
        ]);

        return $usuario;
    }

    /**
     * Lista os registros de hoje no formato esperado pelo app Android.
     */
    public static function listaHoje(): array
    {
        $hoje      = date('Y-m-d');
        $registros = Registro::buscarParaRelatorio('', $hoje, $hoje, '');

        $lista = [];
        foreach ($registros as $r) {
            $lista[] = [
                'matricula'     => $r['matricula'] ?? '',
                'nome_usuario'  => $r['nome_usuario'] ?? 'Desconhecido',
                'tipo_refeicao' => ($r['tipo_refeicao'] ?? '') === 'almoco' ? 'Almoço' : 'Jantar',
                'status'        => $r['status'] ?? 'agendado',
            ];
        }

        return $lista;
    }
}
