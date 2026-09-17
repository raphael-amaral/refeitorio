<?php

namespace Tests\Integration\Scripts;

use Tests\Support\ProcessTestCase;

class BackupScriptsTest extends ProcessTestCase
{
    private string $projectDirectory;
    private string $backupDirectory;
    private string $fakeBinDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->projectDirectory = $this->temporaryDirectory . '/project';
        $this->backupDirectory = $this->temporaryDirectory . '/backups';
        $this->fakeBinDirectory = $this->temporaryDirectory . '/bin';
        mkdir($this->projectDirectory . '/scripts', 0700, true);
        mkdir($this->backupDirectory . '/diarios', 0700, true);
        mkdir($this->fakeBinDirectory, 0700, true);

        copy(dirname(__DIR__, 3) . '/scripts/backup_mysql.sh', $this->projectDirectory . '/scripts/backup_mysql.sh');
        copy(dirname(__DIR__, 3) . '/scripts/restaurar_backup.sh', $this->projectDirectory . '/scripts/restaurar_backup.sh');

        $this->writeEnvironmentFile();
        $this->createFakeClients();
    }

    public function testBackupPublicaDumpComPermissaoRestritaEParametrosEsperados(): void
    {
        $result = $this->runProcess(
            ['/bin/bash', $this->projectDirectory . '/scripts/backup_mysql.sh'],
            '',
            $this->processEnvironment(['FAKE_MYSQLDUMP_MODE' => 'success'])
        );

        $arquivos = glob($this->backupDirectory . '/diarios/backup_refeitorio_test_*.sql');
        $argumentos = (string) file_get_contents($this->temporaryDirectory . '/mysqldump.args');

        $this->assertSame(0, $result['exitCode'], $result['stderr']);
        $this->assertCount(1, $arquivos);
        $this->assertSame("CREATE TABLE teste (id INT);\n", file_get_contents($arquivos[0]));
        $this->assertSame(0600, fileperms($arquivos[0]) & 0777);
        $this->assertStringContainsString("--host=database.example\n", $argumentos);
        $this->assertStringContainsString("--port=4406\n", $argumentos);
        $this->assertStringContainsString("--user=backup_user\n", $argumentos);
        $this->assertStringEndsWith("refeitorio_test\n", $argumentos);
        $this->assertSame([], glob($this->backupDirectory . '/diarios/*.tmp'));
    }

    public function testBackupVazioFalhaSemPublicarArquivo(): void
    {
        $result = $this->runProcess(
            ['/bin/bash', $this->projectDirectory . '/scripts/backup_mysql.sh'],
            '',
            $this->processEnvironment(['FAKE_MYSQLDUMP_MODE' => 'empty'])
        );

        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('mysqldump gerou um arquivo vazio', $result['stderr']);
        $this->assertSame([], glob($this->backupDirectory . '/diarios/*'));
    }

    public function testBackupMantemTodosOsArquivosDosUltimosSeteDias(): void
    {
        $hoje = new \DateTimeImmutable('today');
        $arquivosMantidos = [];

        for ($diasAtras = 0; $diasAtras <= 6; $diasAtras++) {
            $data = $hoje->modify("-{$diasAtras} days")->format('Y-m-d');
            $arquivo = $this->backupDirectory . "/diarios/backup_refeitorio_test_{$data}_00-00-00.sql";
            file_put_contents($arquivo, "backup {$data}\n");
            $arquivosMantidos[] = $arquivo;
        }

        $arquivoAntigo = $this->backupDirectory . '/diarios/backup_refeitorio_test_'
            . $hoje->modify('-7 days')->format('Y-m-d') . '_00-00-00.sql';
        file_put_contents($arquivoAntigo, "backup antigo\n");

        $result = $this->runProcess(
            ['/bin/bash', $this->projectDirectory . '/scripts/backup_mysql.sh'],
            '',
            $this->processEnvironment(['FAKE_MYSQLDUMP_MODE' => 'success'])
        );

        $this->assertSame(0, $result['exitCode'], $result['stderr']);
        $this->assertFileDoesNotExist($arquivoAntigo);
        foreach ($arquivosMantidos as $arquivoMantido) {
            $this->assertFileExists($arquivoMantido);
        }
        $this->assertCount(8, glob($this->backupDirectory . '/diarios/backup_refeitorio_test_*.sql'));
    }

    public function testBackupRemoveArquivosOperacionaisComMaisDeTrintaDias(): void
    {
        foreach (['pre_restore', 'manual_migrations'] as $diretorio) {
            mkdir($this->backupDirectory . '/' . $diretorio, 0700, true);
            $arquivoAntigo = $this->backupDirectory . "/{$diretorio}/antigo.sql";
            $arquivoRecente = $this->backupDirectory . "/{$diretorio}/recente.sql";
            file_put_contents($arquivoAntigo, "antigo\n");
            file_put_contents($arquivoRecente, "recente\n");
            touch($arquivoAntigo, time() - (31 * 86400));
            touch($arquivoRecente, time() - (29 * 86400));
        }

        $result = $this->runProcess(
            ['/bin/bash', $this->projectDirectory . '/scripts/backup_mysql.sh'],
            '',
            $this->processEnvironment(['FAKE_MYSQLDUMP_MODE' => 'success'])
        );

        $this->assertSame(0, $result['exitCode'], $result['stderr']);
        foreach (['pre_restore', 'manual_migrations'] as $diretorio) {
            $this->assertFileDoesNotExist($this->backupDirectory . "/{$diretorio}/antigo.sql");
            $this->assertFileExists($this->backupDirectory . "/{$diretorio}/recente.sql");
        }
    }

    public function testRestauracaoCriaBackupDeSegurancaAntesDeImportar(): void
    {
        $arquivoSelecionado = $this->backupDirectory . '/diarios/backup_refeitorio_test_2026-08-26_02-00-00.sql';
        file_put_contents($arquivoSelecionado, "INSERT INTO teste VALUES (1);\n");

        $result = $this->runProcess(
            ['/bin/bash', $this->projectDirectory . '/scripts/restaurar_backup.sh', 'latest'],
            "y\n",
            $this->processEnvironment(['FAKE_MYSQLDUMP_MODE' => 'success'])
        );

        $preBackups = glob($this->backupDirectory . '/pre_restore/pre_restore_refeitorio_test_*.sql');
        $mysqlArgs = (string) file_get_contents($this->temporaryDirectory . '/mysql.args');

        $this->assertSame(0, $result['exitCode'], $result['stderr']);
        $this->assertStringContainsString('Restauracao concluida com sucesso', $result['stdout']);
        $this->assertCount(1, $preBackups);
        $this->assertSame("CREATE TABLE teste (id INT);\n", file_get_contents($preBackups[0]));
        $this->assertSame(0600, fileperms($preBackups[0]) & 0777);
        $this->assertSame(file_get_contents($arquivoSelecionado), file_get_contents($this->temporaryDirectory . '/mysql.stdin'));
        $this->assertStringContainsString("--host=database.example\n", $mysqlArgs);
        $this->assertStringContainsString("--port=4406\n", $mysqlArgs);
        $this->assertStringContainsString("--user=backup_user\n", $mysqlArgs);
        $this->assertStringEndsWith("refeitorio_test\n", $mysqlArgs);
    }

    /** @param array<string,string> $extra */
    private function processEnvironment(array $extra = []): array
    {
        return array_merge([
            'PATH'                => $this->fakeBinDirectory . ':/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
            'FAKE_MYSQLDUMP_ARGS' => $this->temporaryDirectory . '/mysqldump.args',
            'FAKE_MYSQL_ARGS'     => $this->temporaryDirectory . '/mysql.args',
            'FAKE_MYSQL_STDIN'    => $this->temporaryDirectory . '/mysql.stdin',
        ], $extra);
    }

    private function writeEnvironmentFile(): void
    {
        $contents = implode("\n", [
            'BACKUP_DB_HOST=database.example',
            'BACKUP_DB_PORT=4406',
            'DB_USERNAME=backup_user',
            'DB_PASSWORD=backup_password',
            'DB_DATABASE=refeitorio_test',
            'BACKUP_DIR=' . $this->backupDirectory,
            'MYSQLDUMP_BIN=' . $this->fakeBinDirectory . '/mysqldump',
            '',
        ]);

        file_put_contents($this->projectDirectory . '/.env', $contents);
    }

    private function createFakeClients(): void
    {
        $mysqldump = <<<'SH'
#!/bin/sh
printf '%s\n' "$@" > "$FAKE_MYSQLDUMP_ARGS"
case "${FAKE_MYSQLDUMP_MODE:-success}" in
    success)
        printf '%s\n' 'CREATE TABLE teste (id INT);'
        ;;
    empty)
        ;;
    failure)
        echo 'falha simulada do mysqldump' >&2
        exit 2
        ;;
esac
SH;
        $mysql = <<<'SH'
#!/bin/sh
printf '%s\n' "$@" > "$FAKE_MYSQL_ARGS"
cat > "$FAKE_MYSQL_STDIN"
SH;

        file_put_contents($this->fakeBinDirectory . '/mysqldump', $mysqldump);
        file_put_contents($this->fakeBinDirectory . '/mysql', $mysql);
        chmod($this->fakeBinDirectory . '/mysqldump', 0700);
        chmod($this->fakeBinDirectory . '/mysql', 0700);
    }
}
