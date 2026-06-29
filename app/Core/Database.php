<?php

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    private function __construct() {}
    private function __clone() {}

    /** Injeta uma conexão PDO para uso nos testes (SQLite in-memory). */
    public static function setTestConnection(PDO $pdo): void
    {
        self::$instance = $pdo;
    }

    /** Remove a conexão injetada, forçando reconexão na próxima chamada. */
    public static function reset(): void
    {
        self::$instance = null;
    }

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $envPath = __DIR__ . '/../../.env';

            if (!file_exists($envPath)) {
                throw new \RuntimeException('Arquivo .env não encontrado.');
            }

            $env = parse_ini_file($envPath);

            $host = $env['DB_HOST']     ?? 'db';
            $port = $env['DB_PORT']     ?? '3306';
            $db   = $env['DB_DATABASE'] ?? 'hospital_refeitorio';
            $user = $env['DB_USERNAME'] ?? 'root';
            $pass = $env['DB_PASSWORD'] ?? '';

            $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";

            self::$instance = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ]);
        }

        return self::$instance;
    }
}
