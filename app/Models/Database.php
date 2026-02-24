<?php
namespace App\Models;

use PDO;
use PDOException;

class Database {
    private static $instance = null;

    public static function getConnection() {
        if (self::$instance === null) {
            // Buscando as configurações das variáveis de ambiente (.env via Docker)
            // O segundo parâmetro é um fallback (valor padrão) caso a variável não exista
            $host = getenv('DB_HOST') ?: 'db'; 
            $db   = getenv('DB_NAME') ?: 'refeitorio_db';
            $user = getenv('DB_USER') ?: 'root';
            $pass = getenv('DB_ROOT_PASSWORD'); // Puxa a senha do .env
            $port = getenv('DB_PORT') ?: '3306';

            try {
                $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
                
                self::$instance = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                // No ambiente hospitalar, o die ajuda a identificar erros de rede rapidamente
                die("Erro na conexão com o banco: " . $e->getMessage());
            }
        }
        return self::$instance;
    }
}