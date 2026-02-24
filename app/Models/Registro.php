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

    public function salvar($matricula, $data)
    {
        try {
            // Se já existir registro, volta o status para 'agendado'
            $sql = "INSERT INTO registros (matricula, data_agendada, status) 
                    VALUES (:mat, :data, 'agendado')
                    ON DUPLICATE KEY UPDATE status = 'agendado'";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([':mat' => $matricula, ':data' => $data]);
        } catch (\PDOException $e) {
            return false;
        }
    }

    public function alterarStatus($matricula, $data, $novoStatus)
    {
        try {
            $sql = "UPDATE registros 
                    SET status = :status 
                    WHERE matricula = :mat 
                    AND data_agendada = :data";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':status' => $novoStatus,
                ':mat'    => $matricula,
                ':data'   => $data
            ]);
        } catch (\PDOException $e) {
            return false;
        }
    }

    public function buscarMeusAgendamentos($matricula)
    {
        $stmt = $this->db->prepare("SELECT data_agendada, status FROM registros WHERE matricula = :mat AND status != 'cancelado' ORDER BY data_agendada ASC");
        $stmt->execute([':mat' => $matricula]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
