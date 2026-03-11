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
     * Filtra por status se fornecido ('agendado', 'cancelado', 'concluido').
     */
    public static function buscarPorUsuario(string $matricula, string $status = ''): array
    {
        $sql = 'SELECT r.*, c.data, c.tipo AS tipo_refeicao, c.descricao
                  FROM registros r
                  JOIN cardapios c ON r.id_cardapio = c.id
                 WHERE r.matricula_usuario = :matricula';

        $params = ['matricula' => $matricula];

        if ($status !== '') {
            $sql .= ' AND r.status = :status';
            $params['status'] = $status;
        }

        $sql .= ' ORDER BY c.data DESC, c.tipo ASC';

        $stmt = static::db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna todos os agendamentos de um cardápio (visão da copa).
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
     * Marca um agendamento como concluído (ação da copa).
     */
    public static function concluir(int $id): bool
    {
        return static::atualizar($id, ['status' => 'concluido']);
    }
}
