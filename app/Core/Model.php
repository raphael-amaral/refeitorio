<?php

namespace App\Core;

use PDO;

/**
 * Classe base para todos os Models.
 *
 * Subclasses devem declarar:
 *   protected static string $tabela        = 'nome_da_tabela';
 *   protected static string $chavePrimaria = 'id';  // padrão
 */
abstract class Model
{
    protected static string $tabela        = '';
    protected static string $chavePrimaria = 'id';

    // -------------------------------------------------------------------------
    // Conexão
    // -------------------------------------------------------------------------

    protected static function db(): PDO
    {
        return Database::getConnection();
    }

    // -------------------------------------------------------------------------
    // Leitura
    // -------------------------------------------------------------------------

    public static function buscarPorId(int|string $id): array|false
    {
        $sql  = 'SELECT * FROM ' . static::$tabela
            . ' WHERE ' . static::$chavePrimaria . ' = :id';
        $stmt = static::db()->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna todos os registros, com ordenação opcional.
     * Ex: Cardapio::todos('data DESC, tipo ASC')
     */
    public static function todos(string $ordem = ''): array
    {
        $sql = 'SELECT * FROM ' . static::$tabela;
        if ($ordem !== '') {
            $sql .= ' ORDER BY ' . $ordem;
        }
        return static::db()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    // -------------------------------------------------------------------------
    // Escrita
    // -------------------------------------------------------------------------

    /**
     * Insere um novo registro e retorna o ID gerado (lastInsertId).
     * Para tabelas sem auto-increment, retorna '0' — use o valor que você inseriu.
     */
    public static function criar(array $dados): string|false
    {
        $colunas    = implode(', ', array_keys($dados));
        $marcadores = ':' . implode(', :', array_keys($dados));

        $stmt = static::db()->prepare(
            "INSERT INTO " . static::$tabela . " ({$colunas}) VALUES ({$marcadores})"
        );

        if (!$stmt->execute($dados)) {
            return false;
        }

        return static::db()->lastInsertId();
    }

    /**
     * Atualiza um registro pela chave primária.
     */
    public static function atualizar(int|string $id, array $dados): bool
    {
        $sets = implode(', ', array_map(fn($col) => "{$col} = :{$col}", array_keys($dados)));

        // Usa um placeholder exclusivo para a PK para evitar conflito com colunas do $dados
        $dados['__pk_val'] = $id;

        $stmt = static::db()->prepare(
            "UPDATE " . static::$tabela
                . " SET {$sets} WHERE " . static::$chavePrimaria . " = :__pk_val"
        );

        return $stmt->execute($dados);
    }

    /**
     * Remove um registro pela chave primária.
     */
    public static function deletar(int|string $id): bool
    {
        $stmt = static::db()->prepare(
            'DELETE FROM ' . static::$tabela
                . ' WHERE ' . static::$chavePrimaria . ' = :id'
        );
        return $stmt->execute(['id' => $id]);
    }
}
