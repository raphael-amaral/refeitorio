<?php

namespace Tests\Integration\Service;

use App\Exceptions\DataOuTipoInvalidoException;
use App\Exceptions\DescricaoVaziaException;
use App\Models\Cardapio;
use App\Services\CardapioService;
use Tests\Integration\DatabaseTestCase;

class CardapioServiceTest extends DatabaseTestCase
{
    public function testSalvarDiaCriaCardapioComSucesso(): void
    {
        $hoje = date('Y-m-d');

        CardapioService::salvarDia($hoje, 'almoco', 'frango grelhado');

        $cardapios = Cardapio::buscarPorData($hoje);
        $this->assertCount(1, $cardapios);
        $this->assertSame('FRANGO GRELHADO', $cardapios[0]['descricao']);
    }

    public function testSalvarDiaAtualizaCardapioExistente(): void
    {
        $hoje = date('Y-m-d');
        $this->inserirCardapio($hoje, 'almoco', 'ORIGINAL');

        CardapioService::salvarDia($hoje, 'almoco', 'atualizado');

        $cardapios = Cardapio::buscarPorData($hoje);
        $this->assertCount(1, $cardapios);
        $this->assertSame('ATUALIZADO', $cardapios[0]['descricao']);
    }

    public function testSalvarDiaNormalizaDescricaoParaMaiusculasSemEspacos(): void
    {
        $hoje = date('Y-m-d');

        CardapioService::salvarDia($hoje, 'almoco', '  peixe assado  ');

        $cardapios = Cardapio::buscarPorData($hoje);
        $this->assertSame('PEIXE ASSADO', $cardapios[0]['descricao']);
    }

    public function testSalvarDiaLancaDataOuTipoInvalidoParaTipoInvalido(): void
    {
        $hoje = date('Y-m-d');

        $this->expectException(DataOuTipoInvalidoException::class);

        CardapioService::salvarDia($hoje, 'lanche', 'ALGO');
    }

    public function testSalvarDiaLancaDataOuTipoInvalidoParaDataForaDaSemana(): void
    {
        $dataDistante = (new \DateTime('+30 days'))->format('Y-m-d');

        $this->expectException(DataOuTipoInvalidoException::class);

        CardapioService::salvarDia($dataDistante, 'almoco', 'ALGO');
    }

    public function testSalvarDiaLancaDescricaoVaziaParaStringVazia(): void
    {
        $hoje = date('Y-m-d');

        $this->expectException(DescricaoVaziaException::class);

        CardapioService::salvarDia($hoje, 'almoco', '');
    }

    public function testSalvarDiaLancaDescricaoVaziaParaSomenteEspacos(): void
    {
        $hoje = date('Y-m-d');

        $this->expectException(DescricaoVaziaException::class);

        CardapioService::salvarDia($hoje, 'almoco', '    ');
    }

    public function testSalvarDiaNaoPersisteQuandoDescricaoVazia(): void
    {
        $hoje = date('Y-m-d');

        try {
            CardapioService::salvarDia($hoje, 'almoco', '');
        } catch (DescricaoVaziaException) {
            // esperado
        }

        $this->assertSame([], Cardapio::buscarPorData($hoje));
    }
}
