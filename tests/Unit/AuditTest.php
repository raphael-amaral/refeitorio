<?php

namespace Tests\Unit;

use App\Core\Audit;
use PHPUnit\Framework\TestCase;

class AuditTest extends TestCase
{
    private string $logPath;

    protected function setUp(): void
    {
        $this->logPath = sys_get_temp_dir() . '/audit_test_' . uniqid() . '.log';
        $_ENV['AUDIT_LOG_PATH'] = $this->logPath;
        $_SESSION = [];
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
    }

    protected function tearDown(): void
    {
        if (is_file($this->logPath)) {
            unlink($this->logPath);
        }
        unset($_ENV['AUDIT_LOG_PATH']);
        $_SESSION = [];
    }

    // -------------------------------------------------------------------------
    // Criação e formato do log
    // -------------------------------------------------------------------------

    public function testWriteCriaArquivoDeLog(): void
    {
        Audit::write('usuario.login', 'success');

        $this->assertFileExists($this->logPath);
    }

    public function testWriteRegistraJsonValido(): void
    {
        Audit::write('usuario.login', 'success');

        $linha   = trim(file_get_contents($this->logPath));
        $entrada = json_decode($linha, true);

        $this->assertIsArray($entrada);
        $this->assertSame('usuario.login', $entrada['action']);
        $this->assertSame('success',       $entrada['status']);
    }

    public function testWriteIncluiCamposObrigatorios(): void
    {
        Audit::write('cardapio.criar', 'blocked', 'cardapio_42');

        $entrada = json_decode(trim(file_get_contents($this->logPath)), true);

        $this->assertArrayHasKey('ts',     $entrada);
        $this->assertArrayHasKey('action', $entrada);
        $this->assertArrayHasKey('status', $entrada);
        $this->assertArrayHasKey('target', $entrada);
        $this->assertArrayHasKey('ip',     $entrada);
        $this->assertSame('cardapio_42', $entrada['target']);
    }

    public function testWriteIncluiIpDoCliente(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.5';

        Audit::write('teste', 'ok');

        $entrada = json_decode(trim(file_get_contents($this->logPath)), true);
        $this->assertSame('10.0.0.5', $entrada['ip']);
    }

    public function testWritePreferenceXForwardedForParaIp(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.1, 10.0.0.1';
        $_SERVER['REMOTE_ADDR']          = '192.168.1.1';

        Audit::write('teste', 'ok');

        $entrada = json_decode(trim(file_get_contents($this->logPath)), true);
        $this->assertSame('203.0.113.1', $entrada['ip']);
    }

    public function testWriteRegistraActorDaSessao(): void
    {
        $_SESSION['usuario'] = ['matricula' => '12345', 'tipo' => 'snd'];

        Audit::write('registro.concluir', 'success');

        $entrada = json_decode(trim(file_get_contents($this->logPath)), true);
        $this->assertSame('12345', $entrada['actor_matricula']);
        $this->assertSame('snd',   $entrada['actor_tipo']);
    }

    public function testWriteRegistraActorNuloSemSessao(): void
    {
        Audit::write('acesso.anonimo', 'blocked');

        $entrada = json_decode(trim(file_get_contents($this->logPath)), true);
        $this->assertNull($entrada['actor_matricula']);
        $this->assertNull($entrada['actor_tipo']);
    }

    public function testWriteRegistraDetalhesExtras(): void
    {
        Audit::write('usuario.criar', 'success', '999', ['tipo' => 'comum', 'lote' => true]);

        $entrada = json_decode(trim(file_get_contents($this->logPath)), true);
        $this->assertSame(['tipo' => 'comum', 'lote' => true], $entrada['details']);
    }

    public function testWriteAcumulaMultiplasLinhas(): void
    {
        Audit::write('acao.a', 'success');
        Audit::write('acao.b', 'error');
        Audit::write('acao.c', 'blocked');

        $linhas = array_filter(explode(PHP_EOL, trim(file_get_contents($this->logPath))));
        $this->assertCount(3, $linhas);
    }

    public function testWriteUsaCaminhoMensalPadraoSemEnv(): void
    {
        unset($_ENV['AUDIT_LOG_PATH']);

        // Confirma o cálculo do caminho padrão (audit-YYYY-MM.log) via reflection,
        // sem escrever no arquivo real — ele pertence ao www-data (uso via HTTP real
        // dentro do container), e o phpunit roda como outro usuário no host.
        $metodo = new \ReflectionMethod(Audit::class, 'logPath');
        $metodo->setAccessible(true);

        $nomeArquivo = 'audit-' . date('Y-m') . '.log';
        $esperado    = realpath(dirname(__DIR__, 2) . '/storage/logs') . '/' . $nomeArquivo;
        $obtido      = realpath(dirname($metodo->invoke(null))) . '/' . $nomeArquivo;

        $this->assertSame($esperado, $obtido);
    }
}
