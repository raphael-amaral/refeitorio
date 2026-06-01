<?php

namespace Tests\Unit;

use App\Core\Security;
use PHPUnit\Framework\TestCase;

class SecurityTest extends TestCase
{
    private const SCOPE = 'test_login';
    private const KEY   = 'usuario_test@example.com';

    protected function setUp(): void
    {
        Security::clear(self::SCOPE, self::KEY);
    }

    protected function tearDown(): void
    {
        Security::clear(self::SCOPE, self::KEY);
    }

    // -------------------------------------------------------------------------
    // clientIp
    // -------------------------------------------------------------------------

    public function testClientIpRetornaRemoteAddr(): void
    {
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';

        $this->assertSame('192.168.1.100', Security::clientIp());
    }

    public function testClientIpPreferenceXForwardedFor(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.1, 10.0.0.2';
        $_SERVER['REMOTE_ADDR']          = '192.168.1.1';

        $this->assertSame('10.0.0.1', Security::clientIp());
    }

    public function testClientIpFallbackSemServidor(): void
    {
        unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['REMOTE_ADDR']);

        $this->assertSame('0.0.0.0', Security::clientIp());
    }

    // -------------------------------------------------------------------------
    // isBlocked — estado inicial
    // -------------------------------------------------------------------------

    public function testIsBlockedSemTentativasRetornaZero(): void
    {
        $resultado = Security::isBlocked(self::SCOPE, self::KEY, 60, 5, 300);

        $this->assertSame(0, $resultado);
    }

    // -------------------------------------------------------------------------
    // registerFailure — acúmulo de tentativas
    // -------------------------------------------------------------------------

    public function testRegisterFailureAbaixoDoLimiteNaoBloqueeia(): void
    {
        $resultado = Security::registerFailure(self::SCOPE, self::KEY, 60, 5, 300);

        $this->assertSame(0, $resultado); // 1 tentativa, limite é 5
    }

    public function testRegisterFailureAtingeLimiteEBloquia(): void
    {
        // 4 tentativas sem bloquear
        for ($i = 0; $i < 4; $i++) {
            Security::registerFailure(self::SCOPE, self::KEY, 60, 5, 300);
        }

        // 5ª tentativa aciona o bloqueio
        $lockout = Security::registerFailure(self::SCOPE, self::KEY, 60, 5, 300);

        $this->assertGreaterThan(0, $lockout);
        $this->assertLessThanOrEqual(300, $lockout);
    }

    public function testIsBlockedAposLockoutRetornaTempRestante(): void
    {
        // Provoca bloqueio
        for ($i = 0; $i < 5; $i++) {
            Security::registerFailure(self::SCOPE, self::KEY, 60, 5, 300);
        }

        $restante = Security::isBlocked(self::SCOPE, self::KEY, 60, 5, 300);

        $this->assertGreaterThan(0, $restante);
    }

    // -------------------------------------------------------------------------
    // clear
    // -------------------------------------------------------------------------

    public function testClearRemoveBloqueioAtivo(): void
    {
        // Provoca bloqueio
        for ($i = 0; $i < 5; $i++) {
            Security::registerFailure(self::SCOPE, self::KEY, 60, 5, 300);
        }

        Security::clear(self::SCOPE, self::KEY);

        $resultado = Security::isBlocked(self::SCOPE, self::KEY, 60, 5, 300);
        $this->assertSame(0, $resultado);
    }
}
