<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class QuestorExportacao extends Model
{
    protected static string $tabela        = 'questor_exportacoes';
    protected static string $chavePrimaria = 'id';

    /** Retorna a exportação mais recente (a última realizada), ou null se nunca houve nenhuma. */
    public static function ultima(): ?array
    {
        $stmt = static::db()->query(
            'SELECT * FROM ' . static::$tabela . ' ORDER BY criado_em DESC, id DESC LIMIT 1'
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
