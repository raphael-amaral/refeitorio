<?php

namespace App\Models;

use PDO;

class Registro
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function salvar($usuario_id, $matricula, $data)
    {
        try {
            // Agora salvamos ID e MATRICULA para facilitar a auditoria/relatórios
            $stmt = $this->db->prepare("INSERT IGNORE INTO registros (usuario_id, matricula, data_agendada, status) VALUES (:usuario_id, :matricula, :data, 'agendado')");
            return $stmt->execute([
                ':usuario_id'  => $usuario_id,
                ':matricula'  => $matricula,
                ':data' => $data
            ]);
        } catch (\PDOException $e) {
            return false;
        }
    }

    public function buscarMeusAgendamentos($usuario_id)
    {
        $stmt = $this->db->prepare("
        SELECT data_agendada, status, criado_em 
        FROM registros 
        WHERE usuario_id = :uid 
        ORDER BY data_agendada ASC 
        LIMIT 10
    ");
        $stmt->execute([':uid' => $usuario_id]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
