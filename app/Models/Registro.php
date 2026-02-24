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
            $stmt = $this->db->prepare("INSERT IGNORE INTO registros (usuario_id, matricula, data_agendada, status) VALUES (:uid, :mat, :data, 'agendado')");
            return $stmt->execute([
                ':uid'  => $usuario_id,
                ':mat'  => $matricula,
                ':data' => $data
            ]);
        } catch (\PDOException $e) {
            return false;
        }
    }

    public function buscarMeusAgendamentos($usuario_id)
    {
        $stmt = $this->db->prepare("SELECT data_agendada, status, criado_em FROM registros WHERE usuario_id = :uid ORDER BY data_agendada ASC LIMIT 15");
        $stmt->execute([':uid' => $usuario_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
