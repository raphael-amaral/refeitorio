<?php

namespace Tests\Integration\Model;

use App\Models\Cardapio;
use Tests\Integration\DatabaseTestCase;

class CardapioTest extends DatabaseTestCase
{
    // =========================================================================
    // buscarPorData()
    // =========================================================================

    public function testBuscarPorDataRetornaCardapiosDaData(): void
    {
        $this->inserirCardapio('2026-07-01', 'almoco', 'FRANGO');
        $this->inserirCardapio('2026-07-01', 'jantar', 'SOPA');
        $this->inserirCardapio('2026-07-02', 'almoco', 'PEIXE');

        $cardapios = Cardapio::buscarPorData('2026-07-01');

        $this->assertCount(2, $cardapios);
        // Ordenados por tipo ASC: almoco < jantar
        $this->assertSame('almoco', $cardapios[0]['tipo']);
        $this->assertSame('jantar', $cardapios[1]['tipo']);
    }

    public function testBuscarPorDataRetornaArrayVazioSemDados(): void
    {
        $this->assertSame([], Cardapio::buscarPorData('2099-01-01'));
    }

    // =========================================================================
    // almocoDaData()
    // =========================================================================

    public function testAlmocoDaDataRetornaCardapioDeAlmoco(): void
    {
        $this->inserirCardapio('2026-07-10', 'almoco', 'ARROZ E FEIJAO');
        $this->inserirCardapio('2026-07-10', 'jantar', 'MACARRAO');

        $almoco = Cardapio::almocoDaData('2026-07-10');

        $this->assertIsArray($almoco);
        $this->assertSame('almoco',        $almoco['tipo']);
        $this->assertSame('ARROZ E FEIJAO', $almoco['descricao']);
    }

    public function testAlmocoDaDataRetornaNullSemAlmoco(): void
    {
        $this->inserirCardapio('2026-07-11', 'jantar', 'SOPA');

        $this->assertNull(Cardapio::almocoDaData('2026-07-11'));
    }

    public function testAlmocoDaDataRetornaNullSemCardapio(): void
    {
        $this->assertNull(Cardapio::almocoDaData('2099-12-31'));
    }

    // =========================================================================
    // buscarPorPeriodoPaginado() + contarPorPeriodo()
    // =========================================================================

    public function testContarPorPeriodoContaCorreto(): void
    {
        $this->inserirCardapio('2026-07-01', 'almoco');
        $this->inserirCardapio('2026-07-02', 'almoco');
        $this->inserirCardapio('2026-07-03', 'almoco');
        $this->inserirCardapio('2026-07-15', 'almoco'); // fora do período

        $total = Cardapio::contarPorPeriodo('2026-07-01', '2026-07-05');

        $this->assertSame(3, $total);
    }

    public function testBuscarPorPeriodoPaginadoRespeita(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $data = sprintf('2026-08-%02d', $i);
            $this->inserirCardapio($data, 'almoco');
        }

        $pagina1 = Cardapio::buscarPorPeriodoPaginado('2026-08-01', '2026-08-31', 0, 3);
        $pagina2 = Cardapio::buscarPorPeriodoPaginado('2026-08-01', '2026-08-31', 3, 3);

        $this->assertCount(3, $pagina1);
        $this->assertCount(2, $pagina2);
    }

    public function testBuscarPorPeriodoPaginadoOrdenadoPorDataDesc(): void
    {
        $this->inserirCardapio('2026-09-01', 'almoco');
        $this->inserirCardapio('2026-09-03', 'almoco');
        $this->inserirCardapio('2026-09-02', 'almoco');

        $resultados = Cardapio::buscarPorPeriodoPaginado('2026-09-01', '2026-09-30', 0, 10);

        // Deve vir em ordem DESC por data
        $this->assertSame('2026-09-03', $resultados[0]['data']);
        $this->assertSame('2026-09-02', $resultados[1]['data']);
        $this->assertSame('2026-09-01', $resultados[2]['data']);
    }

    // =========================================================================
    // semanaAtual()
    // =========================================================================

    public function testSemanaAtualRetornaCardapiosDaSemana(): void
    {
        // Semana: 2026-07-06 (segunda) a 2026-07-12 (domingo)
        $segunda = new \DateTime('2026-07-06');

        $this->inserirCardapio('2026-07-06', 'almoco', 'SEGUNDA');
        $this->inserirCardapio('2026-07-08', 'almoco', 'QUARTA');
        $this->inserirCardapio('2026-07-13', 'almoco', 'FORA DA SEMANA');

        $cardapios = Cardapio::semanaAtual($segunda);

        $this->assertCount(2, $cardapios);
        // Ordenados por data ASC
        $this->assertSame('SEGUNDA', $cardapios[0]['descricao']);
        $this->assertSame('QUARTA',  $cardapios[1]['descricao']);
    }

    public function testSemanaAtualRetornaVazioSemDados(): void
    {
        $segunda   = new \DateTime('2099-01-01');
        $cardapios = Cardapio::semanaAtual($segunda);

        $this->assertSame([], $cardapios);
    }

    // =========================================================================
    // Métodos MySQL-específicos (pulados no SQLite)
    // =========================================================================

    public function testProximosDiasRequerMySQL(): void
    {
        $this->markTestSkipped('proximosDias() usa CURDATE() e DATE_ADD() — requer MySQL.');
    }

    public function testSalvarDiaRequerMySQL(): void
    {
        $this->markTestSkipped('salvarDia() usa ON DUPLICATE KEY UPDATE — requer MySQL.');
    }

    // =========================================================================
    // Model base: criar(), buscarPorId(), atualizar(), deletar()
    // =========================================================================

    public function testCriarInsereCardapio(): void
    {
        $id = Cardapio::criar([
            'data'      => '2026-10-01',
            'tipo'      => 'almoco',
            'descricao' => 'TESTE CRIAR',
        ]);

        $this->assertNotFalse($id);
        $row = $this->pdo->query("SELECT * FROM cardapios WHERE id = {$id}")->fetch();
        $this->assertSame('TESTE CRIAR', $row['descricao']);
    }

    public function testBuscarPorIdRetornaCardapio(): void
    {
        $id = $this->inserirCardapio('2026-10-02', 'jantar', 'SOPA DE LEGUMES');

        $cardapio = Cardapio::buscarPorId($id);

        $this->assertIsArray($cardapio);
        $this->assertSame('SOPA DE LEGUMES', $cardapio['descricao']);
    }

    public function testAtualizarCardapio(): void
    {
        $id = $this->inserirCardapio('2026-10-03', 'almoco', 'ORIGINAL');

        Cardapio::atualizar($id, ['descricao' => 'ATUALIZADO']);

        $cardapio = Cardapio::buscarPorId($id);
        $this->assertSame('ATUALIZADO', $cardapio['descricao']);
    }

    public function testDeletarCardapio(): void
    {
        $id = $this->inserirCardapio('2026-10-04', 'almoco', 'PARA DELETAR');

        $this->assertTrue(Cardapio::deletar($id));

        $this->assertFalse(Cardapio::buscarPorId($id));
    }
}
