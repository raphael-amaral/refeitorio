<?php

namespace Tests\Integration\Service;

use App\Core\Audit;
use App\Services\QuestorService;
use Tests\Support\ProcessTestCase;

class QuestorBackupTest extends ProcessTestCase
{
    private string|false $originalPath;

    /** @var array<string, array{exists: bool, value: mixed}> */
    private array $originalEnvironment = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE', 'BACKUP_DIR'] as $key) {
            $this->originalEnvironment[$key] = [
                'exists' => array_key_exists($key, $_ENV),
                'value'  => $_ENV[$key] ?? null,
            ];
        }

        $_ENV['DB_HOST'] = 'database.example';
        $_ENV['DB_PORT'] = '4406';
        $_ENV['DB_USERNAME'] = 'backup_user';
        $_ENV['DB_PASSWORD'] = 'backup_password';
        $_ENV['DB_DATABASE'] = 'refeitorio_test';
        $_ENV['BACKUP_DIR'] = $this->temporaryDirectory . '/backups';

        $this->originalPath = getenv('PATH');
        $this->writeExecutable('bin/mysqldump', <<<'SH'
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
SH);
        putenv('PATH=' . $this->temporaryDirectory . '/bin:' . $this->originalPath);
        putenv('FAKE_MYSQLDUMP_ARGS=' . $this->temporaryDirectory . '/mysqldump.args');
        putenv('FAKE_MYSQLDUMP_MODE=success');

        Audit::setTestPath($this->temporaryDirectory . '/audit.log');
    }

    protected function tearDown(): void
    {
        Audit::reset();
        putenv('PATH=' . $this->originalPath);
        putenv('FAKE_MYSQLDUMP_ARGS');
        putenv('FAKE_MYSQLDUMP_MODE');

        foreach ($this->originalEnvironment as $key => $original) {
            if ($original['exists']) {
                $_ENV[$key] = $original['value'];
            } else {
                unset($_ENV[$key]);
            }
        }

        parent::tearDown();
    }

    public function testGerarBackupPublicaDumpCompletoComParametrosDoAmbiente(): void
    {
        $nomeArquivo = QuestorService::gerarBackup('2026-08-01', '2026-08-31');

        $arquivo = $_ENV['BACKUP_DIR'] . '/questor/' . $nomeArquivo;
        $argumentos = (string) file_get_contents($this->temporaryDirectory . '/mysqldump.args');

        $this->assertMatchesRegularExpression(
            '/^questor_refeitorio_test_20260801_20260831_\d{6}\.sql$/',
            $nomeArquivo
        );
        $this->assertSame("CREATE TABLE teste (id INT);\n", file_get_contents($arquivo));
        $this->assertStringContainsString("--single-transaction\n", $argumentos);
        $this->assertStringContainsString("database.example\n", $argumentos);
        $this->assertStringContainsString("4406\n", $argumentos);
        $this->assertStringContainsString("backup_user\n", $argumentos);
        $this->assertStringEndsWith("refeitorio_test\n", $argumentos);
        $this->assertSame([], glob($_ENV['BACKUP_DIR'] . '/questor/*.{tmp,err}', GLOB_BRACE));
    }

    public function testGerarBackupRetornaNullERemoveTemporariosQuandoMysqldumpFalha(): void
    {
        putenv('FAKE_MYSQLDUMP_MODE=failure');

        $nomeArquivo = QuestorService::gerarBackup('2026-08-01', '2026-08-31');

        $arquivos = glob($_ENV['BACKUP_DIR'] . '/questor/*');
        $this->assertNull($nomeArquivo);
        $this->assertSame([], $arquivos);
        $this->assertStringContainsString('falha simulada do mysqldump', file_get_contents($this->temporaryDirectory . '/audit.log'));
    }

    public function testGerarBackupRetornaNullParaDumpVazioSemPublicarArquivo(): void
    {
        putenv('FAKE_MYSQLDUMP_MODE=empty');

        $nomeArquivo = QuestorService::gerarBackup('2026-08-01', '2026-08-31');

        $arquivos = glob($_ENV['BACKUP_DIR'] . '/questor/*');
        $this->assertNull($nomeArquivo);
        $this->assertSame([], $arquivos);
        $this->assertStringContainsString('Dump vazio', file_get_contents($this->temporaryDirectory . '/audit.log'));
    }
}
