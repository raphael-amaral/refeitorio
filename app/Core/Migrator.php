<?php

namespace App\Core;

use PDO;
use RuntimeException;

final class Migrator
{
    private const VERSION_PATTERN = '/^\d{8}_[a-z0-9_]+\.sql$/';

    public function __construct(
        private PDO $pdo,
        private string $migrationsDirectory
    ) {}

    public function databaseName(): string
    {
        if ($this->driver() === 'mysql') {
            $database = $this->pdo->query('SELECT DATABASE()')->fetchColumn();
            if (!is_string($database) || $database === '') {
                throw new RuntimeException('Nenhum banco de dados foi selecionado.');
            }

            return $database;
        }

        return 'sqlite';
    }

    /**
     * @return list<array{versao: string, estado: string}>
     */
    public function status(): array
    {
        $files = $this->migrationFiles();
        $applied = $this->appliedMigrations();
        $status = [];

        foreach ($files as $version => $path) {
            $state = !isset($applied[$version])
                ? 'pendente'
                : (hash_file('sha256', $path) === $applied[$version] ? 'aplicada' : 'alterada');

            $status[] = ['versao' => $version, 'estado' => $state];
        }

        foreach (array_diff_key($applied, $files) as $version => $_checksum) {
            $status[] = ['versao' => $version, 'estado' => 'arquivo_ausente'];
        }

        return $status;
    }

    /** @return list<string> */
    public function applyPending(): array
    {
        $this->ensureControlTable();

        return $this->withLock(function (): array {
            $invalid = array_filter(
                $this->status(),
                fn(array $migration): bool => in_array($migration['estado'], ['alterada', 'arquivo_ausente'], true)
            );

            if ($invalid !== []) {
                $first = reset($invalid);
                throw new RuntimeException(
                    "Migration {$first['versao']} está com estado {$first['estado']}; execução cancelada."
                );
            }

            $applied = [];
            foreach ($this->status() as $migration) {
                if ($migration['estado'] !== 'pendente') {
                    continue;
                }

                $version = $migration['versao'];
                $path = $this->migrationFiles()[$version];
                $sql = trim((string) file_get_contents($path));
                if ($sql === '') {
                    throw new RuntimeException("Migration vazia: {$version}");
                }

                $this->pdo->exec($sql);
                $this->recordMigration($version, hash_file('sha256', $path));
                $applied[] = $version;
            }

            return $applied;
        });
    }

    public function baseline(string $version): bool
    {
        $files = $this->migrationFiles();
        if (!isset($files[$version])) {
            throw new RuntimeException("Migration não encontrada: {$version}");
        }

        $this->ensureControlTable();

        return $this->withLock(function () use ($version, $files): bool {
            $checksum = hash_file('sha256', $files[$version]);
            $applied = $this->appliedMigrations();

            if (isset($applied[$version])) {
                if ($applied[$version] !== $checksum) {
                    throw new RuntimeException("Checksum divergente para migration já registrada: {$version}");
                }

                return false;
            }

            $this->recordMigration($version, $checksum);
            return true;
        });
    }

    /** @return array<string,string> */
    private function migrationFiles(): array
    {
        $paths = glob(rtrim($this->migrationsDirectory, '/') . '/*.sql') ?: [];
        sort($paths, SORT_STRING);
        $files = [];

        foreach ($paths as $path) {
            $version = basename($path);
            if (!preg_match(self::VERSION_PATTERN, $version)) {
                throw new RuntimeException("Nome de migration inválido: {$version}");
            }
            $files[$version] = $path;
        }

        return $files;
    }

    /** @return array<string,string> */
    private function appliedMigrations(): array
    {
        if (!$this->controlTableExists()) {
            return [];
        }

        $rows = $this->pdo->query('SELECT versao, checksum FROM schema_migrations ORDER BY versao')->fetchAll(PDO::FETCH_ASSOC);
        $applied = [];
        foreach ($rows as $row) {
            $applied[$row['versao']] = $row['checksum'];
        }

        return $applied;
    }

    private function controlTableExists(): bool
    {
        if ($this->driver() === 'mysql') {
            $stmt = $this->pdo->query(
                "SELECT COUNT(*) FROM information_schema.TABLES"
                . " WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schema_migrations'"
            );
            return (int) $stmt->fetchColumn() === 1;
        }

        $stmt = $this->pdo->query(
            "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'schema_migrations'"
        );
        return (int) $stmt->fetchColumn() === 1;
    }

    private function ensureControlTable(): void
    {
        $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS schema_migrations (
    versao VARCHAR(255) PRIMARY KEY,
    checksum CHAR(64) NOT NULL,
    aplicada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL;
        if ($this->driver() === 'mysql') {
            $sql .= ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        }

        $this->pdo->exec($sql);
    }

    private function recordMigration(string $version, string $checksum): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO schema_migrations (versao, checksum) VALUES (:versao, :checksum)'
        );
        $stmt->execute(['versao' => $version, 'checksum' => $checksum]);
    }

    private function withLock(callable $callback): mixed
    {
        if ($this->driver() !== 'mysql') {
            return $callback();
        }

        $lock = 'refeitorio_migrations_' . substr(hash('sha256', $this->databaseName()), 0, 32);
        $stmt = $this->pdo->prepare('SELECT GET_LOCK(:lock_name, 10)');
        $stmt->execute(['lock_name' => $lock]);
        if ((int) $stmt->fetchColumn() !== 1) {
            throw new RuntimeException('Não foi possível obter o lock de migrations.');
        }

        try {
            return $callback();
        } finally {
            $stmt = $this->pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
            $stmt->execute(['lock_name' => $lock]);
        }
    }

    private function driver(): string
    {
        return (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }
}
