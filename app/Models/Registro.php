<?php

namespace App\Models;

use App\Core\Model;
use PDO;

class Registro extends Model
{
    protected static string $tabela        = 'registros';
    protected static string $chavePrimaria = 'id';

    /**
     * Retorna os agendamentos de um usuário com os dados do cardápio via JOIN.
     * Filtra por status e/ou período de datas se fornecidos.
     */
    public static function buscarPorUsuario(
        string $matricula,
        string $status = '',
        string $dataInicio = '',
        string $dataFim = ''
    ): array {
        $sql = 'SELECT r.*, c.data, c.tipo AS tipo_refeicao, c.descricao
                  FROM registros r
                  JOIN cardapios c ON r.id_cardapio = c.id
                 WHERE r.matricula_usuario = :matricula';

        $params = ['matricula' => $matricula];

        if ($status !== '') {
            $sql .= ' AND r.status = :status';
            $params['status'] = $status;
        }

        if ($dataInicio !== '') {
            $sql .= ' AND c.data >= :data_inicio';
            $params['data_inicio'] = $dataInicio;
        }

        if ($dataFim !== '') {
            $sql .= ' AND c.data <= :data_fim';
            $params['data_fim'] = $dataFim;
        }

        $sql .= ' ORDER BY c.data DESC, c.tipo ASC';

        $stmt = static::db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna todos os agendamentos de um cardápio (visão da SND).
     */
    public static function buscarPorCardapio(int $idCardapio): array
    {
        $stmt = static::db()->prepare(
            'SELECT r.*, u.nome AS nome_usuario
               FROM registros r
               JOIN usuarios u ON r.matricula_usuario = u.matricula
              WHERE r.id_cardapio = :id_cardapio
              ORDER BY u.nome ASC'
        );
        $stmt->execute(['id_cardapio' => $idCardapio]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna a quantidade de registros vinculados a um cardápio (qualquer status).
     */
    public static function contarPorCardapio(int $idCardapio): int
    {
        $stmt = static::db()->prepare(
            'SELECT COUNT(*) FROM registros WHERE id_cardapio = :id_cardapio'
        );
        $stmt->execute(['id_cardapio' => $idCardapio]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Verifica se o usuário já tem um agendamento ativo para o cardápio informado.
     */
    public static function jaAgendado(string $matricula, int $idCardapio): bool
    {
        $stmt = static::db()->prepare(
            "SELECT COUNT(*) FROM registros
              WHERE matricula_usuario = :matricula
                AND id_cardapio       = :id_cardapio
                AND status            = 'agendado'"
        );
        $stmt->execute(['matricula' => $matricula, 'id_cardapio' => $idCardapio]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Cria ou reativa (se cancelado) um agendamento para o usuário/cardápio.
     */
    public static function upsertAgendado(string $matricula, int $idCardapio): void
    {
        static::db()->prepare(
            "INSERT INTO registros (matricula_usuario, id_cardapio, status)
             VALUES (:matricula, :id_cardapio, 'agendado')
             ON DUPLICATE KEY UPDATE status = 'agendado', atualizado_em = NOW()"
        )->execute(['matricula' => $matricula, 'id_cardapio' => $idCardapio]);
    }

    /**
     * Cancela um agendamento do próprio usuário (não deixa cancelar registros alheios).
     */
    public static function cancelarDoUsuario(int $id, string $matricula): bool
    {
        $stmt = static::db()->prepare(
            "UPDATE registros
                SET status = 'cancelado'
              WHERE id               = :id
                AND matricula_usuario = :matricula
                AND status           = 'agendado'"
        );
        return $stmt->execute(['id' => $id, 'matricula' => $matricula]);
    }

    /**
     * Marca um agendamento como concluído (ação da SND).
     */
    public static function concluir(int $id): bool
    {
        return static::atualizar($id, ['status' => 'concluido']);
    }

    /**
     * Busca o agendamento ativo de hoje para uma matrícula (usado pelo scanner).
     */
    public static function buscarAgendadoHoje(string $matricula): ?array
    {
        $stmt = static::db()->prepare(
            "SELECT r.*, c.tipo AS tipo_refeicao, c.descricao, u.nome AS nome_usuario
               FROM registros r
               JOIN cardapios c ON r.id_cardapio = c.id
               JOIN usuarios  u ON r.matricula_usuario = u.matricula
              WHERE r.matricula_usuario = :matricula
                AND c.data             = CURDATE()
                AND r.status           = 'agendado'
              LIMIT 1"
        );
        $stmt->execute(['matricula' => $matricula]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Retorna todos os registros para o relatório (visão SND/dev).
     * Suporta filtro por status e intervalo de datas do cardápio.
     */
    public static function buscarParaRelatorio(
        string $status = '',
        string $dataInicio = '',
        string $dataFim = '',
        string $matricula = ''
    ): array {
        $sql = 'SELECT r.id, r.status, r.criado_em,
                       u.matricula, u.nome AS nome_usuario,
                       c.data AS data_cardapio, c.tipo AS tipo_refeicao, c.descricao
                  FROM registros r
                  JOIN usuarios  u ON r.matricula_usuario = u.matricula
                  JOIN cardapios c ON r.id_cardapio       = c.id
                 WHERE 1=1';

        $params = [];

        if ($status !== '') {
            $sql .= ' AND r.status = :status';
            $params['status'] = $status;
        }
        if ($dataInicio !== '') {
            $sql .= ' AND c.data >= :data_inicio';
            $params['data_inicio'] = $dataInicio;
        }
        if ($dataFim !== '') {
            $sql .= ' AND c.data <= :data_fim';
            $params['data_fim'] = $dataFim;
        }
        if ($matricula !== '') {
            $sql .= ' AND r.matricula_usuario LIKE :matricula';
            $params['matricula'] = '%' . $matricula . '%';
        }

        $sql .= ' ORDER BY c.data DESC, u.nome ASC';

        $stmt = static::db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function buscarParaScannerHoje(string $matricula): ?array
    {
        // Se houver dois cardápios no mesmo dia (almoço e jantar),
        // queremos o que foi agendado ou concluído.
        // O ORDER BY prioriza mostrar registros que ainda não foram cancelados.
        $stmt = static::db()->prepare(
            "SELECT r.*, c.tipo AS tipo_refeicao, u.nome AS nome_usuario
               FROM registros r
               JOIN cardapios c ON r.id_cardapio = c.id
               JOIN usuarios  u ON r.matricula_usuario = u.matricula
              WHERE r.matricula_usuario = :matricula
                AND c.data = CURDATE()
              ORDER BY r.status ASC
              LIMIT 1"
        );
        $stmt->execute(['matricula' => $matricula]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
