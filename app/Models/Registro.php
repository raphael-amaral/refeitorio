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
        $filtro = static::filtrosStatusEPeriodo($status, $dataInicio, $dataFim);

        $sql = 'SELECT r.*, c.data, c.tipo AS tipo_refeicao, c.descricao
                  FROM registros r
                  JOIN cardapios c ON r.id_cardapio = c.id
                 WHERE r.matricula_usuario = :matricula'
            . $filtro['sql']
            . ' ORDER BY c.data DESC, c.tipo ASC';

        $stmt = static::db()->prepare($sql);
        $stmt->execute(['matricula' => $matricula] + $filtro['params']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Monta o fragmento SQL (cláusulas AND) e os parâmetros dos filtros de status e
     * período do cardápio, comuns a buscarPorUsuario, buscarPorUsuarioPaginado,
     * contarPorUsuario e buscarParaRelatorio.
     *
     * @return array{sql: string, params: array<string, string>}
     */
    private static function filtrosStatusEPeriodo(string $status, string $dataInicio, string $dataFim): array
    {
        $sql    = '';
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

        return ['sql' => $sql, 'params' => $params];
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
        static::upsertStatus($matricula, $idCardapio, 'agendado');
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
     * Verifica se o usuário já tem refeição concluída para o cardápio informado.
     */
    public static function jaConcluidoPorCardapio(string $matricula, int $idCardapio): bool
    {
        $stmt = static::db()->prepare(
            "SELECT COUNT(*) FROM registros
              WHERE matricula_usuario = :matricula
                AND id_cardapio       = :id_cardapio
                AND status            = 'concluido'"
        );
        $stmt->execute(['matricula' => $matricula, 'id_cardapio' => $idCardapio]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Cria ou sobrescreve um registro diretamente como concluído (exceção SND).
     * Ignora regras de agendamento e horário limite.
     */
    public static function concluirExcecao(string $matricula, int $idCardapio): void
    {
        static::upsertStatus($matricula, $idCardapio, 'concluido');
    }

    /**
     * Cria um registro para o par usuário/cardápio, ou atualiza o status se já existir.
     * Portável entre MySQL e SQLite (evita ON DUPLICATE KEY UPDATE, específico do MySQL).
     */
    private static function upsertStatus(string $matricula, int $idCardapio, string $status): void
    {
        $stmt = static::db()->prepare(
            'SELECT id FROM registros WHERE matricula_usuario = :matricula AND id_cardapio = :id_cardapio'
        );
        $stmt->execute(['matricula' => $matricula, 'id_cardapio' => $idCardapio]);
        $id = $stmt->fetchColumn();

        if ($id !== false) {
            static::atualizar((int) $id, ['status' => $status]);
        } else {
            static::criar([
                'matricula_usuario' => $matricula,
                'id_cardapio'       => $idCardapio,
                'status'            => $status,
            ]);
        }
    }

    public static function buscarPorUsuarioPaginado(
        string $matricula,
        string $status,
        string $dataInicio,
        string $dataFim,
        int $offset,
        int $limite
    ): array {
        $filtro = static::filtrosStatusEPeriodo($status, $dataInicio, $dataFim);

        $sql = 'SELECT r.*, c.data, c.tipo AS tipo_refeicao, c.descricao
                  FROM registros r
                  JOIN cardapios c ON r.id_cardapio = c.id
                 WHERE r.matricula_usuario = :matricula'
            . $filtro['sql']
            . ' ORDER BY c.data DESC, c.tipo ASC LIMIT :limite OFFSET :offset';

        $stmt = static::db()->prepare($sql);
        foreach (['matricula' => $matricula] + $filtro['params'] as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue('limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function contarPorUsuario(
        string $matricula,
        string $status = '',
        string $dataInicio = '',
        string $dataFim = ''
    ): int {
        $filtro = static::filtrosStatusEPeriodo($status, $dataInicio, $dataFim);

        $sql = 'SELECT COUNT(*)
                  FROM registros r
                  JOIN cardapios c ON r.id_cardapio = c.id
                 WHERE r.matricula_usuario = :matricula'
            . $filtro['sql'];

        $stmt = static::db()->prepare($sql);
        $stmt->execute(['matricula' => $matricula] + $filtro['params']);
        return (int) $stmt->fetchColumn();
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
        $filtro = static::filtrosStatusEPeriodo($status, $dataInicio, $dataFim);
        $params = $filtro['params'];

        $sql = 'SELECT r.id, r.status, r.criado_em,
                       u.matricula, u.nome AS nome_usuario,
                       c.data AS data_cardapio, c.tipo AS tipo_refeicao, c.descricao
                  FROM registros r
                  JOIN usuarios  u ON r.matricula_usuario = u.matricula
                  JOIN cardapios c ON r.id_cardapio       = c.id
                 WHERE 1=1'
            . $filtro['sql'];

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
                AND c.data = :hoje
              ORDER BY r.status ASC
              LIMIT 1"
        );
        $stmt->execute(['matricula' => $matricula, 'hoje' => date('Y-m-d')]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
