<?php

namespace App\Services;

use App\Core\Audit;
use App\Exceptions\AutoAcaoNaoPermitidaException;
use App\Exceptions\ErroAoCriarUsuarioException;
use App\Exceptions\LinhasInvalidasException;
use App\Exceptions\ListaVaziaException;
use App\Exceptions\MatriculaDuplicadaException;
use App\Exceptions\MatriculasDuplicadasException;
use App\Exceptions\PossuiRegistrosException;
use App\Exceptions\SenhaCurtaException;
use App\Exceptions\SenhasNaoCoincidemException;
use App\Exceptions\ValidacaoException;
use App\Models\Usuario;
use PDOException;

class UsuarioService
{
    private const TIPOS_PERMITIDOS = ['comum', 'snd', 'dp', 'dev'];

    /**
     * Cria um novo usuário. Retorna o nome normalizado (maiúsculas), para uso em mensagens.
     */
    public static function criar(string $matricula, string $nome, string $senha, string $confirmarSenha, string $tipo): string
    {
        $nome = mb_strtoupper(trim($nome));

        if (!$matricula || !$nome || !in_array($tipo, self::TIPOS_PERMITIDOS, true)) {
            Audit::write('usuario.criar', 'blocked', $matricula !== '' ? $matricula : null, ['motivo' => 'validacao', 'tipo' => $tipo]);
            throw new ValidacaoException();
        }

        if ($senha === '') {
            $senha = 'stcasa' . $matricula;
        } else {
            if (strlen($senha) < 6) {
                throw new SenhaCurtaException();
            }
            if ($senha !== $confirmarSenha) {
                throw new SenhasNaoCoincidemException();
            }
        }

        try {
            Usuario::novoUsuario($matricula, $nome, $senha, $tipo);
            Audit::write('usuario.criar', 'success', $matricula, ['nome' => $nome, 'tipo' => $tipo]);
        } catch (PDOException $e) {
            Audit::write('usuario.criar', 'error', $matricula, ['tipo' => $tipo, 'erro' => $e->getCode()]);
            if ($e->getCode() === '23000') {
                throw new MatriculaDuplicadaException();
            }
            throw new ErroAoCriarUsuarioException();
        }

        return $nome;
    }

    /**
     * Importa múltiplos usuários em lote a partir de um texto (uma linha por usuário: "matricula;nome").
     *
     * @return int Quantidade de usuários criados.
     */
    public static function importar(string $tipo, string $lista): int
    {
        if (!in_array($tipo, self::TIPOS_PERMITIDOS, true)) {
            $tipo = 'comum';
        }

        if ($lista === '') {
            Audit::write('usuario.importar', 'blocked', null, ['motivo' => 'lista_vazia', 'tipo' => $tipo]);
            throw new ListaVaziaException();
        }

        set_time_limit(300);

        $linhas    = array_values(array_filter(array_map('trim', explode("\n", $lista))));
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

            if (isset($vistos[$matricula])) {
                $invalidas[] = "Matrícula {$matricula} repetida no arquivo";
                continue;
            }

            $vistos[$matricula] = true;
            $entradas[] = ['matricula' => $matricula, 'nome' => mb_strtoupper($nome)];
        }

        if ($invalidas) {
            Audit::write('usuario.importar', 'blocked', null, ['motivo' => 'linhas_invalidas', 'tipo' => $tipo, 'total_invalidas' => count($invalidas)]);
            throw new LinhasInvalidasException($invalidas);
        }

        $matriculas = array_column($entradas, 'matricula');
        $duplicadas = Usuario::matriculasExistentes($matriculas);

        if (!empty($duplicadas)) {
            Audit::write('usuario.importar', 'blocked', null, ['motivo' => 'matriculas_duplicadas', 'tipo' => $tipo, 'total_duplicadas' => count($duplicadas)]);
            throw new MatriculasDuplicadasException($duplicadas);
        }

        $criados = 0;
        foreach ($entradas as $entrada) {
            // Custo 8 (vs padrão 10) para viabilizar importação em lote sem travar o servidor
            Usuario::novoUsuario($entrada['matricula'], $entrada['nome'], 'stcasa' . $entrada['matricula'], $tipo, 8);
            Audit::write('usuario.criar', 'success', $entrada['matricula'], ['nome' => $entrada['nome'], 'tipo' => $tipo, 'lote' => true]);
            $criados++;
        }

        return $criados;
    }

    /**
     * Atualiza nome, tipo e opcionalmente a senha de um usuário.
     */
    public static function editar(string $matricula, string $nome, string $tipo, string $senha): void
    {
        $nome         = mb_strtoupper(trim($nome));
        $usuarioAtual = Usuario::buscarPorMatricula($matricula);

        if (!$nome || !in_array($tipo, self::TIPOS_PERMITIDOS, true)) {
            Audit::write('usuario.editar', 'blocked', $matricula, ['motivo' => 'validacao', 'tipo' => $tipo]);
            throw new ValidacaoException();
        }

        Usuario::atualizarUsuario($matricula, $nome, $tipo, $senha !== '' ? $senha : null);
        Audit::write('usuario.editar', 'success', $matricula, [
            'nome_anterior'  => $usuarioAtual['nome'] ?? null,
            'tipo_anterior'  => $usuarioAtual['tipo'] ?? null,
            'nome_novo'      => $nome,
            'tipo_novo'      => $tipo,
            'senha_alterada' => $senha !== '',
        ]);
    }

    /**
     * Inativa um usuário. $matriculaLogada é quem está executando a ação.
     */
    public static function inativar(string $matricula, string $matriculaLogada): void
    {
        if ($matricula === $matriculaLogada) {
            Audit::write('usuario.inativar', 'blocked', $matricula, ['motivo' => 'auto_inativacao']);
            throw new AutoAcaoNaoPermitidaException();
        }

        $nomeAlvo = Usuario::buscarPorMatricula($matricula)['nome'] ?? null;
        Usuario::alterarAtivo($matricula, 0);
        Audit::write('usuario.inativar', 'success', $matricula, ['nome' => $nomeAlvo]);
    }

    /**
     * Reativa um usuário.
     */
    public static function ativar(string $matricula): void
    {
        $nomeAlvo = Usuario::buscarPorMatricula($matricula)['nome'] ?? null;
        Usuario::alterarAtivo($matricula, 1);
        Audit::write('usuario.ativar', 'success', $matricula, ['nome' => $nomeAlvo]);
    }

    /**
     * Exclui permanentemente um usuário sem registros. $matriculaLogada é quem está executando a ação.
     */
    public static function excluir(string $matricula, string $matriculaLogada): void
    {
        if ($matricula === $matriculaLogada) {
            Audit::write('usuario.excluir', 'blocked', $matricula, ['motivo' => 'auto_exclusao']);
            throw new AutoAcaoNaoPermitidaException();
        }

        if (Usuario::temRegistros($matricula)) {
            Audit::write('usuario.excluir', 'blocked', $matricula, ['motivo' => 'possui_registros']);
            throw new PossuiRegistrosException();
        }

        $nomeAlvo = Usuario::buscarPorMatricula($matricula)['nome'] ?? null;
        Usuario::excluir($matricula);
        Audit::write('usuario.excluir', 'success', $matricula, ['nome' => $nomeAlvo]);
    }
}
