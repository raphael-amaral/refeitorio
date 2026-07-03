<?php

namespace Tests\Unit;

use App\Services\Perfil;
use PHPUnit\Framework\TestCase;

class PerfilTest extends TestCase
{
    public function testValidoAceitaOsQuatroTipos(): void
    {
        foreach (['comum', 'snd', 'dp', 'dev'] as $tipo) {
            $this->assertTrue(Perfil::valido($tipo));
        }
    }

    public function testValidoRejeitaTipoDesconhecido(): void
    {
        $this->assertFalse(Perfil::valido('estoquista'));
    }

    public function testEhComum(): void
    {
        $this->assertTrue(Perfil::ehComum('comum'));
        $this->assertFalse(Perfil::ehComum('snd'));
    }

    public function testEhDev(): void
    {
        $this->assertTrue(Perfil::ehDev('dev'));
        $this->assertFalse(Perfil::ehDev('snd'));
    }

    public function testPodeScannerAceitaSndEDev(): void
    {
        $this->assertTrue(Perfil::podeScanner('snd'));
        $this->assertTrue(Perfil::podeScanner('dev'));
        $this->assertFalse(Perfil::podeScanner('comum'));
        $this->assertFalse(Perfil::podeScanner('dp'));
    }

    public function testPodeDpAceitaDpEDev(): void
    {
        $this->assertTrue(Perfil::podeDp('dp'));
        $this->assertTrue(Perfil::podeDp('dev'));
        $this->assertFalse(Perfil::podeDp('comum'));
        $this->assertFalse(Perfil::podeDp('snd'));
    }

    public function testPodeRelatorioAceitaSndDpEDev(): void
    {
        $this->assertTrue(Perfil::podeRelatorio('snd'));
        $this->assertTrue(Perfil::podeRelatorio('dp'));
        $this->assertTrue(Perfil::podeRelatorio('dev'));
        $this->assertFalse(Perfil::podeRelatorio('comum'));
    }

    public function testRotaInicialVaiParaQuestorSeDp(): void
    {
        $this->assertSame('/questor', Perfil::rotaInicial('dp'));
    }

    public function testRotaInicialVaiParaPainelParaOutrosTipos(): void
    {
        $this->assertSame('/painel', Perfil::rotaInicial('comum'));
        $this->assertSame('/painel', Perfil::rotaInicial('snd'));
        $this->assertSame('/painel', Perfil::rotaInicial('dev'));
    }
}
