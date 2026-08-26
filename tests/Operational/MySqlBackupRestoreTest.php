<?php

namespace Tests\Operational;

use Tests\Support\ProcessTestCase;

class MySqlBackupRestoreTest extends ProcessTestCase
{
    public function testBackupQuestorEScriptsFuncionamContraMysqlDescartavel(): void
    {
        $suffix = substr(hash('sha256', $this->temporaryDirectory), 0, 12);
        $network = 'refeitorio_test_' . $suffix;
        $container = 'refeitorio_mysql_' . $suffix;

        $networkResult = $this->runProcess(['docker', 'network', 'create', $network]);
        $this->assertSame(0, $networkResult['exitCode'], $networkResult['stderr']);

        try {
            $containerResult = $this->runProcess([
                'docker', 'run', '--detach', '--rm',
                '--name', $container,
                '--network', $network,
                '--env', 'MYSQL_ROOT_PASSWORD=root_password',
                '--env', 'MYSQL_DATABASE=refeitorio_test',
                '--env', 'MYSQL_USER=backup_user',
                '--env', 'MYSQL_PASSWORD=backup_password',
                'mysql:8.0',
            ]);
            $this->assertSame(0, $containerResult['exitCode'], $containerResult['stderr']);

            $this->waitForMysql($container);
            $this->seedDatabase($container);
            $this->runMigrations($network, $container);

            $backupDirectory = $this->temporaryDirectory . '/backups';
            mkdir($backupDirectory, 0700, true);

            $this->runQuestorBackup($network, $container, $backupDirectory);
            $this->runDailyBackup($network, $container, $backupDirectory);

            $this->executeMysql($container, 'DELETE FROM smoke_data;');
            $this->runRestore($network, $container, $backupDirectory);

            $rows = $this->queryMysql($container, 'SELECT valor FROM smoke_data;');
            $this->assertSame('registro_preservado', trim($rows));
        } finally {
            $this->runProcess(['docker', 'rm', '--force', $container]);
            $this->runProcess(['docker', 'network', 'rm', $network]);
        }
    }

    private function waitForMysql(string $container): void
    {
        for ($attempt = 0; $attempt < 60; $attempt++) {
            $result = $this->runProcess([
                'docker', 'exec', $container,
                'mysqladmin', 'ping', '--host=127.0.0.1', '--user=root', '--password=root_password', '--silent',
            ]);

            if ($result['exitCode'] === 0) {
                return;
            }

            usleep(500_000);
        }

        $logs = $this->runProcess(['docker', 'logs', $container]);
        $this->fail('MySQL descartável não ficou pronto: ' . $logs['stderr'] . $logs['stdout']);
    }

    private function seedDatabase(string $container): void
    {
        $this->executeMysql(
            $container,
            "CREATE TABLE smoke_data (id INT PRIMARY KEY, valor VARCHAR(50));"
            . " INSERT INTO smoke_data VALUES (1, 'registro_preservado');"
            . " CREATE TABLE questor_exportacoes ("
            . " id INT AUTO_INCREMENT PRIMARY KEY,"
            . " data_inicio DATE NOT NULL,"
            . " data_fim DATE NOT NULL,"
            . " valor_refeicao DECIMAL(6,2) NOT NULL,"
            . " exportado_por VARCHAR(12),"
            . " criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP"
            . ");"
        );
    }

    private function runMigrations(string $network, string $container): void
    {
        $projectDirectory = dirname(__DIR__, 2);
        $environmentFile = $this->writeFile('migration.env', implode("\n", [
            'DB_HOST=' . $container,
            'DB_PORT=3306',
            'DB_DATABASE=refeitorio_test',
            'DB_USERNAME=backup_user',
            'DB_PASSWORD=backup_password',
            '',
        ]));
        $command = [
            'docker', 'run', '--rm', '--network', $network,
            '--volume', $projectDirectory . ':/workspace:ro',
            '--volume', $environmentFile . ':/workspace/.env:ro',
            '--workdir', '/workspace',
            '--entrypoint', 'php',
            'refeitorio-entrypoint-test',
            'bin/migrate',
        ];

        $wrongDatabase = $this->runProcess(array_merge($command, ['up', '--database=refeitorio_snd']));
        $this->assertSame(1, $wrongDatabase['exitCode']);
        $this->assertStringContainsString('difere do banco conectado', $wrongDatabase['stderr']);
        $this->assertSame('0', trim($this->queryMysql(
            $container,
            "SELECT COUNT(*) FROM information_schema.TABLES"
            . " WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schema_migrations';"
        )));

        $first = $this->runProcess(array_merge($command, ['up', '--database=refeitorio_test']));
        $this->assertSame(0, $first['exitCode'], $first['stderr']);
        $this->assertStringContainsString(
            'Aplicada: 20260807_add_arquivo_backup_questor_exportacoes.sql',
            $first['stdout']
        );

        $second = $this->runProcess(array_merge($command, ['up', '--database=refeitorio_test']));
        $this->assertSame(0, $second['exitCode'], $second['stderr']);
        $this->assertStringContainsString('Nenhuma migration pendente.', $second['stdout']);

        $status = $this->runProcess(array_merge($command, ['status']));
        $this->assertSame(0, $status['exitCode'], $status['stderr']);
        $this->assertStringContainsString('[aplicada]', $status['stdout']);

        $column = $this->queryMysql(
            $container,
            "SELECT COLUMN_TYPE FROM information_schema.COLUMNS"
            . " WHERE TABLE_SCHEMA = DATABASE()"
            . " AND TABLE_NAME = 'questor_exportacoes'"
            . " AND COLUMN_NAME = 'arquivo_backup';"
        );
        $this->assertSame('varchar(255)', trim($column));
        $this->assertSame('1', trim($this->queryMysql(
            $container,
            'SELECT COUNT(*) FROM schema_migrations;'
        )));
    }

    private function runQuestorBackup(string $network, string $container, string $backupDirectory): void
    {
        $projectDirectory = dirname(__DIR__, 2);
        $php = <<<'PHP'
require 'vendor/autoload.php';
foreach (['DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE', 'BACKUP_DIR', 'AUDIT_LOG_PATH'] as $key) {
    $_ENV[$key] = getenv($key);
}
echo App\Services\QuestorService::gerarBackup('2026-08-01', '2026-08-31');
PHP;
        $result = $this->runProcess([
            'docker', 'run', '--rm', '--network', $network,
            '--user', $this->currentUser(),
            '--volume', $projectDirectory . ':/workspace:ro',
            '--volume', $backupDirectory . ':/backups',
            '--workdir', '/workspace',
            '--env', 'DB_HOST=' . $container,
            '--env', 'DB_PORT=3306',
            '--env', 'DB_USERNAME=backup_user',
            '--env', 'DB_PASSWORD=backup_password',
            '--env', 'DB_DATABASE=refeitorio_test',
            '--env', 'BACKUP_DIR=/backups',
            '--env', 'AUDIT_LOG_PATH=/tmp/audit.log',
            '--entrypoint', 'php',
            'refeitorio-entrypoint-test',
            '-r', $php,
        ]);

        $this->assertSame(0, $result['exitCode'], $result['stderr']);
        $files = glob($backupDirectory . '/questor/questor_refeitorio_test_*.sql');
        $this->assertCount(1, $files);
        $this->assertStringContainsString('registro_preservado', file_get_contents($files[0]));
    }

    private function runDailyBackup(string $network, string $container, string $backupDirectory): void
    {
        $testProject = $this->prepareScriptProject($container);
        $result = $this->runProcess([
            'docker', 'run', '--rm', '--network', $network,
            '--user', $this->currentUser(),
            '--volume', $testProject . ':/workspace:ro',
            '--volume', $backupDirectory . ':/backups',
            '--entrypoint', '/bin/bash',
            'refeitorio-entrypoint-test',
            '/workspace/scripts/backup_mysql.sh',
        ]);

        $this->assertSame(0, $result['exitCode'], $result['stderr']);
        $files = glob($backupDirectory . '/diarios/backup_refeitorio_test_*.sql');
        $this->assertCount(1, $files);
        $this->assertStringContainsString('registro_preservado', file_get_contents($files[0]));
    }

    private function runRestore(string $network, string $container, string $backupDirectory): void
    {
        $testProject = $this->temporaryDirectory . '/script-project';
        $result = $this->runProcess([
            'docker', 'run', '--rm', '--interactive', '--network', $network,
            '--user', $this->currentUser(),
            '--volume', $testProject . ':/workspace:ro',
            '--volume', $backupDirectory . ':/backups',
            '--entrypoint', '/bin/bash',
            'refeitorio-entrypoint-test',
            '/workspace/scripts/restaurar_backup.sh', 'latest',
        ], "y\n");

        $this->assertSame(0, $result['exitCode'], $result['stderr']);
        $this->assertStringContainsString('Restauracao concluida com sucesso', $result['stdout']);
        $this->assertCount(1, glob($backupDirectory . '/pre_restore/pre_restore_refeitorio_test_*.sql'));
    }

    private function prepareScriptProject(string $container): string
    {
        $testProject = $this->temporaryDirectory . '/script-project';
        mkdir($testProject . '/scripts', 0700, true);
        copy(dirname(__DIR__, 2) . '/scripts/backup_mysql.sh', $testProject . '/scripts/backup_mysql.sh');
        copy(dirname(__DIR__, 2) . '/scripts/restaurar_backup.sh', $testProject . '/scripts/restaurar_backup.sh');
        file_put_contents($testProject . '/.env', implode("\n", [
            'BACKUP_DB_HOST=' . $container,
            'BACKUP_DB_PORT=3306',
            'DB_USERNAME=backup_user',
            'DB_PASSWORD=backup_password',
            'DB_DATABASE=refeitorio_test',
            'BACKUP_DIR=/backups',
            '',
        ]));

        return $testProject;
    }

    private function executeMysql(string $container, string $sql): void
    {
        $result = $this->runProcess([
            'docker', 'exec', $container,
            'mysql', '--user=root', '--password=root_password', 'refeitorio_test', '--execute', $sql,
        ]);

        $this->assertSame(0, $result['exitCode'], $result['stderr']);
    }

    private function queryMysql(string $container, string $sql): string
    {
        $result = $this->runProcess([
            'docker', 'exec', $container,
            'mysql', '--batch', '--skip-column-names',
            '--user=root', '--password=root_password', 'refeitorio_test', '--execute', $sql,
        ]);
        $this->assertSame(0, $result['exitCode'], $result['stderr']);

        return $result['stdout'];
    }

    private function currentUser(): string
    {
        return posix_geteuid() . ':' . posix_getegid();
    }
}
