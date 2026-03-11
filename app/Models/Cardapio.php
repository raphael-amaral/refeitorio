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
     * Retorna os cardápios de um intervalo de datas, mais recentes primeiro.
     */
    public static function buscarPorPeriodo(string $inicio, string $fim): array
    {
        $stmt = static::db()->prepare(
            'SELECT * FROM cardapios
              WHERE data BETWEEN :inicio AND :fim
              ORDER BY data DESC, tipo ASC'
        );
        $stmt->execute(['inicio' => $inicio, 'fim' => $fim]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
     * Retorna o cardápio de uma data e tipo específicos.
     */
    public static function buscarPorDataETipo(string $data, string $tipo): ?array
    {
        $stmt = static::db()->prepare(
            'SELECT * FROM cardapios WHERE data = :data AND tipo = :tipo LIMIT 1'
        );
        $stmt->execute(['data' => $data, 'tipo' => $tipo]);
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
