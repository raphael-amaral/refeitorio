<?php

namespace Tests\Integration;

use App\Core\Migrator;
use PDO;
use RuntimeException;
use Tests\Support\ProcessTestCase;

class MigratorTest extends ProcessTestCase
{
    private PDO $pdo;
    private string $migrationsDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->migrationsDirectory = $this->temporaryDirectory . '/migrations';
        mkdir($this->migrationsDirectory, 0700, true);
    }

    public function testAplicaMigrationPendenteUmaUnicaVez(): void
    {
        $this->writeMigration('20260826_create_example.sql', 'CREATE TABLE example (id INTEGER PRIMARY KEY);');
        $migrator = new Migrator($this->pdo, $this->migrationsDirectory);

        $this->assertSame(['20260826_create_example.sql'], $migrator->applyPending());
        $this->assertSame([], $migrator->applyPending());
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn());
        $this->assertSame(
            [['versao' => '20260826_create_example.sql', 'estado' => 'aplicada']],
            $migrator->status()
        );
    }

    public function testRecusaMigrationAplicadaQueFoiAlterada(): void
    {
        $this->writeMigration('20260826_create_example.sql', 'CREATE TABLE example (id INTEGER PRIMARY KEY);');
        $migrator = new Migrator($this->pdo, $this->migrationsDirectory);
        $migrator->applyPending();
        $this->writeMigration('20260826_create_example.sql', 'CREATE TABLE example (id INTEGER, nome TEXT);');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('estado alterada');
        $migrator->applyPending();
    }

    public function testRecusaHistoricoCujoArquivoFoiRemovido(): void
    {
        $path = $this->writeMigration('20260826_create_example.sql', 'CREATE TABLE example (id INTEGER PRIMARY KEY);');
        $migrator = new Migrator($this->pdo, $this->migrationsDirectory);
        $migrator->applyPending();
        unlink($path);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('estado arquivo_ausente');
        $migrator->applyPending();
    }

    public function testBaselineRegistraSemExecutarSql(): void
    {
        $version = '20260826_create_example.sql';
        $this->writeMigration($version, 'CREATE TABLE example (id INTEGER PRIMARY KEY);');
        $migrator = new Migrator($this->pdo, $this->migrationsDirectory);

        $this->assertTrue($migrator->baseline($version));
        $this->assertFalse($migrator->baseline($version));
        $this->assertSame(0, (int) $this->pdo->query(
            "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'example'"
        )->fetchColumn());
        $this->assertSame(
            [['versao' => $version, 'estado' => 'aplicada']],
            $migrator->status()
        );
    }

    public function testInitSqlRegistraChecksumDeTodasAsMigrationsIncorporadas(): void
    {
        $projectDirectory = dirname(__DIR__, 2);
        $initSql = (string) file_get_contents($projectDirectory . '/database/init.sql');
        $migrations = glob($projectDirectory . '/database/migrations/*.sql') ?: [];

        foreach ($migrations as $migration) {
            $this->assertStringContainsString(basename($migration), $initSql);
            $this->assertStringContainsString(hash_file('sha256', $migration), $initSql);
        }
    }

    private function writeMigration(string $version, string $sql): string
    {
        return $this->writeFile('migrations/' . $version, $sql);
    }
}
