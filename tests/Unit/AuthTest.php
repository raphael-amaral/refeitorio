<?php

namespace Tests\Unit;

use App\Middleware\Auth;
use PHPUnit\Framework\TestCase;
use Tests\Support\RedirectException;

class AuthTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        $_SERVER['HTTP_AUTHORIZATION'] = '';
        unset($_SERVER['HTTP_AUTHORIZATION']);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    // =========================================================================
    // handle()
    // =========================================================================

    public function testHandleRedirecionaParaRaizSeNaoLogado(): void
    {
        $this->expectException(RedirectException::class);
        $this->expectExceptionMessage('/');

        Auth::handle();
    }

    public function testHandleRedirecionaDpParaQuestor(): void
    {
        $_SESSION['usuario'] = ['tipo' => 'dp', 'matricula' => '999'];

        $this->expectException(RedirectException::class);
        $this->expectExceptionMessage('/questor');

        Auth::handle();
    }

    public function testHandlePermiteComum(): void
    {
        $_SESSION['usuario'] = ['tipo' => 'comum', 'matricula' => '1'];

        Auth::handle();
        $this->addToAssertionCount(1);
    }

    public function testHandlePermiteSnd(): void
    {
        $_SESSION['usuario'] = ['tipo' => 'snd', 'matricula' => '2'];

        Auth::handle();
        $this->addToAssertionCount(1);
    }

    public function testHandlePermiteDev(): void
    {
        $_SESSION['usuario'] = ['tipo' => 'dev', 'matricula' => '3'];

        Auth::handle();
        $this->addToAssertionCount(1);
    }

    // =========================================================================
    // dev()
    // =========================================================================

    public function testDevPermiteDev(): void
    {
        $_SESSION['usuario'] = ['tipo' => 'dev', 'matricula' => '1'];

        Auth::dev();
        $this->addToAssertionCount(1);
    }

    public function testDevRedirecionaSndParaPainel(): void
    {
        $_SESSION['usuario'] = ['tipo' => 'snd', 'matricula' => '2'];

        $this->expectException(RedirectException::class);
        $this->expectExceptionMessage('/painel');

        Auth::dev();
    }

    public function testDevRedirecionaComumParaPainel(): void
    {
        $_SESSION['usuario'] = ['tipo' => 'comum', 'matricula' => '3'];

        $this->expectException(RedirectException::class);
        $this->expectExceptionMessage('/painel');

        Auth::dev();
    }

    // =========================================================================
    // snd()
    // =========================================================================

    public function testSndPermiteSnd(): void
    {
        $_SESSION['usuario'] = ['tipo' => 'snd', 'matricula' => '1'];

        Auth::snd();
        $this->addToAssertionCount(1);
    }

    public function testSndPermiteDev(): void
    {
        $_SESSION['usuario'] = ['tipo' => 'dev', 'matricula' => '1'];

        Auth::snd();
        $this->addToAssertionCount(1);
    }

    public function testSndRedirecionaComumParaPainel(): void
    {
        $_SESSION['usuario'] = ['tipo' => 'comum', 'matricula' => '2'];

        $this->expectException(RedirectException::class);
        $this->expectExceptionMessage('/painel');

        Auth::snd();
    }

    public function testSndRedirecionaDpParaQuestor(): void
    {
        // dp é redirecionado por handle() antes de chegar na checagem de snd
        $_SESSION['usuario'] = ['tipo' => 'dp', 'matricula' => '3'];

        $this->expectException(RedirectException::class);
        $this->expectExceptionMessage('/questor');

        Auth::snd();
    }

    // =========================================================================
    // dp()
    // =========================================================================

    public function testDpPermiteDp(): void
    {
        $_SESSION['usuario'] = ['tipo' => 'dp', 'matricula' => '1'];

        Auth::dp();
        $this->addToAssertionCount(1);
    }

    public function testDpPermiteDev(): void
    {
        $_SESSION['usuario'] = ['tipo' => 'dev', 'matricula' => '1'];

        Auth::dp();
        $this->addToAssertionCount(1);
    }

    public function testDpRedirecionaComumParaPainel(): void
    {
        $_SESSION['usuario'] = ['tipo' => 'comum', 'matricula' => '2'];

        $this->expectException(RedirectException::class);
        $this->expectExceptionMessage('/painel');

        Auth::dp();
    }

    public function testDpRedirecionaSndParaPainel(): void
    {
        $_SESSION['usuario'] = ['tipo' => 'snd', 'matricula' => '3'];

        $this->expectException(RedirectException::class);
        $this->expectExceptionMessage('/painel');

        Auth::dp();
    }

    public function testDpRedirecionaParaRaizSeNaoLogado(): void
    {
        $this->expectException(RedirectException::class);
        $this->expectExceptionMessage('/');

        Auth::dp();
    }

    // =========================================================================
    // relatorio()
    // =========================================================================

    public function testRelatorioPermiteSnd(): void
    {
        $_SESSION['usuario'] = ['tipo' => 'snd', 'matricula' => '1'];

        Auth::relatorio();
        $this->addToAssertionCount(1);
    }

    public function testRelatorioPermiteDp(): void
    {
        $_SESSION['usuario'] = ['tipo' => 'dp', 'matricula' => '2'];

        Auth::relatorio();
        $this->addToAssertionCount(1);
    }

    public function testRelatorioPermiteDev(): void
    {
        $_SESSION['usuario'] = ['tipo' => 'dev', 'matricula' => '3'];

        Auth::relatorio();
        $this->addToAssertionCount(1);
    }

    public function testRelatorioRedirecionaComumParaPainel(): void
    {
        $_SESSION['usuario'] = ['tipo' => 'comum', 'matricula' => '4'];

        $this->expectException(RedirectException::class);
        $this->expectExceptionMessage('/painel');

        Auth::relatorio();
    }

    public function testRelatorioRedirecionaParaRaizSeNaoLogado(): void
    {
        $this->expectException(RedirectException::class);
        $this->expectExceptionMessage('/');

        Auth::relatorio();
    }

    // =========================================================================
    // comum()
    // =========================================================================

    public function testComumPermiteComum(): void
    {
        $_SESSION['usuario'] = ['tipo' => 'comum', 'matricula' => '1'];

        Auth::comum();
        $this->addToAssertionCount(1);
    }

    public function testComumRedirecionaSndParaPainel(): void
    {
        $_SESSION['usuario'] = ['tipo' => 'snd', 'matricula' => '2'];

        $this->expectException(RedirectException::class);
        $this->expectExceptionMessage('/painel');

        Auth::comum();
    }

    public function testComumRedirecionaDevParaPainel(): void
    {
        $_SESSION['usuario'] = ['tipo' => 'dev', 'matricula' => '3'];

        $this->expectException(RedirectException::class);
        $this->expectExceptionMessage('/painel');

        Auth::comum();
    }

    // =========================================================================
    // apiSnd()
    // =========================================================================

    public function testApiSndPermiteTokenCorreto(): void
    {
        $_ENV['SCANNER_TOKEN'] = 'token-secreto-test';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer token-secreto-test';

        Auth::apiSnd();
        $this->addToAssertionCount(1);
    }

    public function testApiSndNegaSemToken(): void
    {
        $_ENV['SCANNER_TOKEN'] = 'token-secreto-test';
        unset($_SERVER['HTTP_AUTHORIZATION']);

        // http_response_code(401) lança RedirectException('401') antes do exit
        $this->expectException(\Tests\Support\RedirectException::class);
        $this->expectExceptionMessage('401');

        Auth::apiSnd();
    }

    public function testApiSndNegaTokenErrado(): void
    {
        $_ENV['SCANNER_TOKEN'] = 'token-correto';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer token-errado';

        // http_response_code(401) lança RedirectException('401') antes do exit
        $this->expectException(\Tests\Support\RedirectException::class);
        $this->expectExceptionMessage('401');

        Auth::apiSnd();
    }
}
