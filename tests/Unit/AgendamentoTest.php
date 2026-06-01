<?php

namespace Tests\Unit;

use App\Core\Agendamento;
use DateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AgendamentoTest extends TestCase
{
    // -------------------------------------------------------------------------
    // fimDeSemana
    // -------------------------------------------------------------------------

    #[DataProvider('diasUteisProvider')]
    public function testFimDeSemanaNaoDias_Uteis(string $data): void
    {
        $this->assertFalse(Agendamento::fimDeSemana($data));
    }

    public static function diasUteisProvider(): array
    {
        return [
            'segunda' => ['2026-05-25'],
            'terça'   => ['2026-05-26'],
            'quarta'  => ['2026-05-27'],
            'quinta'  => ['2026-05-28'],
            'sexta'   => ['2026-05-29'],
        ];
    }

    #[DataProvider('fimDeSemanaProvider')]
    public function testFimDeSemanaEmFimDeSemana(string $data): void
    {
        $this->assertTrue(Agendamento::fimDeSemana($data));
    }

    public static function fimDeSemanaProvider(): array
    {
        return [
            'sábado'  => ['2026-05-30'],
            'domingo' => ['2026-05-31'],
        ];
    }

    // -------------------------------------------------------------------------
    // foraPrazo — data passada
    // -------------------------------------------------------------------------

    public function testForaPrazoComDataPassada(): void
    {
        $this->assertTrue(Agendamento::foraPrazo('2020-01-01'));
    }

    // -------------------------------------------------------------------------
    // foraPrazo — data futura
    // -------------------------------------------------------------------------

    public function testForaPrazoComDataFutura(): void
    {
        $this->assertFalse(Agendamento::foraPrazo('2099-12-31'));
    }

    // -------------------------------------------------------------------------
    // foraPrazo — hoje, com clock injection
    // -------------------------------------------------------------------------

    public function testForaPrazoHojeAntesDoLimite(): void
    {
        $_ENV['LIMIT_TIME'] = '10:00';
        $hoje  = (new DateTime())->format('Y-m-d');
        $agora = new DateTime($hoje . ' 09:00:00'); // 1 hora antes

        $this->assertFalse(Agendamento::foraPrazo($hoje, $agora));
    }

    public function testForaPrazoHojeExatamenteNoLimite(): void
    {
        $_ENV['LIMIT_TIME'] = '10:00';
        $hoje  = (new DateTime())->format('Y-m-d');
        $agora = new DateTime($hoje . ' 10:00:00'); // exato — >= bloqueia

        $this->assertTrue(Agendamento::foraPrazo($hoje, $agora));
    }

    public function testForaPrazoHojeDepoisDoLimite(): void
    {
        $_ENV['LIMIT_TIME'] = '10:00';
        $hoje  = (new DateTime())->format('Y-m-d');
        $agora = new DateTime($hoje . ' 14:00:00'); // 4 horas depois

        $this->assertTrue(Agendamento::foraPrazo($hoje, $agora));
    }

    public function testForaPrazoRespeitaLimitTimeDoEnv(): void
    {
        $_ENV['LIMIT_TIME'] = '08:00';
        $hoje  = (new DateTime())->format('Y-m-d');
        $agora = new DateTime($hoje . ' 08:30:00'); // depois do limite customizado

        $this->assertTrue(Agendamento::foraPrazo($hoje, $agora));
    }
}
