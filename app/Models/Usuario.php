<?php
namespace App\Models;

use PDO;

class Usuario {
    private $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function buscarPorMatricula($matricula) {
        $stmt = $this->db->prepare("SELECT * FROM usuarios WHERE matricula = :matricula AND ativo = 1");
        $stmt->execute([':matricula' => $matricula]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}