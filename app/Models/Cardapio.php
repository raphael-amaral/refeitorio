<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class Cardapio extends Model
{
    protected static string $tabela        = 'cardapios';
    protected static string $chavePrimaria = 'id';

    /**
     * Retorna os cardápios dos próximos N dias (incluindo hoje), ordenados por data e tipo.
     */
    public static function proximosDias(int $dias = 7): array
    {
        $stmt = static::db()->prepare(
            'SELECT * FROM cardapios
              WHERE data BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :dias DAY)
              ORDER BY data ASC, tipo ASC'
        );
        $stmt->execute(['dias' => $dias]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna todos os cardápios de uma data específica (formato Y-m-d).
     */
    public static function buscarPorData(string $data): array
    {
        $stmt = static::db()->prepare(
            'SELECT * FROM cardapios WHERE data = :data ORDER BY tipo ASC'
        );
        $stmt->execute(['data' => $data]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     */
    public static function buscarPorPeriodoPaginado(string $inicio, string $fim, int $offset, int $limite): array
    {
        $stmt = static::db()->prepare(
            'SELECT * FROM cardapios
              WHERE data BETWEEN :inicio AND :fim
              ORDER BY data DESC, tipo ASC
              LIMIT :limite OFFSET :offset'
        );
        $stmt->bindValue('inicio', $inicio);
        $stmt->bindValue('fim',    $fim);
        $stmt->bindValue('limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Conta os cardápios de um intervalo de datas.
     */
    public static function contarPorPeriodo(string $inicio, string $fim): int
    {
        $stmt = static::db()->prepare(
            'SELECT COUNT(*) FROM cardapios WHERE data BETWEEN :inicio AND :fim'
        );
        $stmt->execute(['inicio' => $inicio, 'fim' => $fim]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Retorna os cardápios da semana começando em $segunda (7 dias).
     * Usado pela dashboard para montar o grid seg–dom.
     */
    public static function semanaAtual(\DateTime $segunda): array
    {
        $domingo = (clone $segunda)->modify('+6 days');
        $stmt    = static::db()->prepare(
            'SELECT * FROM cardapios
              WHERE data BETWEEN :segunda AND :domingo
              ORDER BY data ASC, tipo ASC'
        );
        $stmt->execute([
            'segunda' => $segunda->format('Y-m-d'),
            'domingo' => $domingo->format('Y-m-d'),
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna o cardápio de almoço de uma data específica, ou null se não existir.
     */
    public static function almocoDaData(string $data): ?array
    {
        $stmt = static::db()->prepare(
            "SELECT * FROM cardapios WHERE data = :data AND tipo = 'almoco' LIMIT 1"
        );
        $stmt->execute(['data' => $data]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Cria ou atualiza o cardápio de uma data/tipo (upsert).
     */
    public static function salvarDia(string $data, string $tipo, string $descricao): void
    {
        static::db()->prepare(
            'INSERT INTO cardapios (data, tipo, descricao)
             VALUES (:data, :tipo, :descricao)
             ON DUPLICATE KEY UPDATE descricao = :descricao2, atualizado_em = NOW()'
        )->execute([
            'data'       => $data,
            'tipo'       => $tipo,
            'descricao'  => $descricao,
            'descricao2' => $descricao,
        ]);
    }
}
