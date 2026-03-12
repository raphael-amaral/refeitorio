<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class Usuario extends Model
{
    protected static string $tabela        = 'usuarios';
    protected static string $chavePrimaria = 'matricula';

    /**
     * Busca um usuário ativo pela matrícula.
     * Retorna array associativo ou false se não encontrar / inativo.
     */
    public static function buscarPorMatricula(string $matricula): array|false
    {
        $stmt = static::db()->prepare(
            'SELECT matricula, nome, senha, tipo
               FROM usuarios
              WHERE matricula = :matricula
                AND ativo = 1'
        );
        $stmt->execute(['matricula' => $matricula]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna todos os usuários (ativos e inativos), ordenados por nome.
     */
    public static function listar(): array
    {
        $stmt = static::db()->query(
            "SELECT matricula, nome, tipo, ativo, criado_em
               FROM usuarios
              ORDER BY nome ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cria um novo usuário.
     */
    public static function novoUsuario(string $matricula, string $nome, string $senha, string $tipo): void
    {
        $stmt = static::db()->prepare(
            'INSERT INTO usuarios (matricula, nome, senha, tipo)
             VALUES (:matricula, :nome, :senha, :tipo)'
        );
        $stmt->execute([
            'matricula' => $matricula,
            'nome'      => $nome,
            'senha'     => password_hash($senha, PASSWORD_BCRYPT),
            'tipo'      => $tipo,
        ]);
    }

    /**
     * Atualiza nome, tipo e opcionalmente a senha de um usuário.
     */
    public static function atualizarUsuario(string $matricula, string $nome, string $tipo, ?string $senha = null): void
    {
        if ($senha !== null && $senha !== '') {
            $stmt = static::db()->prepare(
                'UPDATE usuarios SET nome = :nome, tipo = :tipo, senha = :senha WHERE matricula = :matricula'
            );
            $stmt->execute([
                'nome'      => $nome,
                'tipo'      => $tipo,
                'senha'     => password_hash($senha, PASSWORD_BCRYPT),
                'matricula' => $matricula,
            ]);
        } else {
            $stmt = static::db()->prepare(
                'UPDATE usuarios SET nome = :nome, tipo = :tipo WHERE matricula = :matricula'
            );
            $stmt->execute(['nome' => $nome, 'tipo' => $tipo, 'matricula' => $matricula]);
        }
    }

    /**
     * Ativa ou inativa um usuário.
     */
    public static function alterarAtivo(string $matricula, int $ativo): void
    {
        $stmt = static::db()->prepare(
            'UPDATE usuarios SET ativo = :ativo WHERE matricula = :matricula'
        );
        $stmt->execute(['ativo' => $ativo, 'matricula' => $matricula]);
    }

    /**
     * Verifica se o usuário possui registros (agendamentos).
     */
    public static function temRegistros(string $matricula): bool
    {
        $stmt = static::db()->prepare(
            'SELECT COUNT(*) FROM registros WHERE matricula_usuario = :matricula'
        );
        $stmt->execute(['matricula' => $matricula]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Exclui permanentemente um usuário sem registros.
     */
    public static function excluir(string $matricula): void
    {
        $stmt = static::db()->prepare('DELETE FROM usuarios WHERE matricula = :matricula');
        $stmt->execute(['matricula' => $matricula]);
    }
}
