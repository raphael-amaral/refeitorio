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
     *
     * @return array|false
     */
    public static function buscarPorMatricula(string $matricula)
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
     * Versão paginada com busca opcional por nome ou matrícula.
     */
    public static function listarPaginado(string $busca, int $offset, int $limite, string $ordenarPor = 'nome'): array
    {
        $ordem = $ordenarPor === 'matricula' ? 'CAST(matricula AS UNSIGNED) ASC' : 'nome ASC';

        if ($busca !== '') {
            $stmt = static::db()->prepare(
                "SELECT matricula, nome, tipo, ativo, criado_em
                   FROM usuarios
                  WHERE nome LIKE :busca OR matricula LIKE :busca2
                  ORDER BY {$ordem}
                  LIMIT :limite OFFSET :offset"
            );
            $like = '%' . $busca . '%';
            $stmt->bindValue('busca',  $like);
            $stmt->bindValue('busca2', $like);
        } else {
            $stmt = static::db()->prepare(
                "SELECT matricula, nome, tipo, ativo, criado_em
                   FROM usuarios
                  ORDER BY {$ordem}
                  LIMIT :limite OFFSET :offset"
            );
        }
        $stmt->bindValue('limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Conta usuários, com busca opcional por nome ou matrícula.
     */
    public static function contarTotal(string $busca = ''): int
    {
        if ($busca !== '') {
            $like = '%' . $busca . '%';
            $stmt = static::db()->prepare(
                "SELECT COUNT(*) FROM usuarios WHERE nome LIKE :busca OR matricula LIKE :busca2"
            );
            $stmt->execute(['busca' => $like, 'busca2' => $like]);
        } else {
            $stmt = static::db()->query("SELECT COUNT(*) FROM usuarios");
        }
        return (int) $stmt->fetchColumn();
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
