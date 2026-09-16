<?php

namespace Tests\Integration\Service;

use App\Services\RelatorioService;
use DateTime;
use Tests\Integration\DatabaseTestCase;

class RelatorioServiceTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->inserirUsuario('60001', 'FUNCIONARIO TESTE');
    }

    public function testModoDiaUsaDataDiaInformada(): void
    {
        $dados = RelatorioService::preparar('dia', '2026-07-15', '', '', '', '', '', '');

        $this->assertSame('dia', $dados['modo']);
        $this->assertSame('2026-07-15', $dados['dataInicio']);
        $this->assertSame('2026-07-15', $dados['dataFim']);
    }

    public function testModoDiaUsaHojeQuandoDataInvalida(): void
    {
        $dados = RelatorioService::preparar('dia', 'invalida', '', '', '', '', '', '');

        $this->assertSame(date('Y-m-d'), $dados['dataInicio']);
    }

    public function testModoPeriodoUsaDomingoASabadoComoPadrao(): void
    {
        $dados = RelatorioService::preparar('periodo', '', '', '', '', '', '', '');

        $domingo = (new DateTime())->modify('-' . (new DateTime())->format('w') . ' days');
        $sabado  = (clone $domingo)->modify('+6 days');

        $this->assertSame($domingo->format('Y-m-d'), $dados['dataInicio']);
        $this->assertSame($sabado->format('Y-m-d'), $dados['dataFim']);
    }

    public function testModoPeriodoUsaDatasInformadas(): void
    {
        $dados = RelatorioService::preparar('periodo', '', '2026-07-01', '2026-07-10', '', '', '', '');

        $this->assertSame('2026-07-01', $dados['dataInicio']);
        $this->assertSame('2026-07-10', $dados['dataFim']);
    }

    public function testModoMesUsaAnoEMesInformados(): void
    {
        $dados = RelatorioService::preparar('mes', '', '', '', '2026', '3', '', '');

        $this->assertSame('2026-03-01', $dados['dataInicio']);
        $this->assertSame('2026-03-31', $dados['dataFim']);
    }

    public function testModoMesUsaAnoAtualParaAnoForaDoIntervalo(): void
    {
        $dados = RelatorioService::preparar('mes', '', '', '', '1999', '3', '', '');

        $this->assertSame(date('Y') . '-03-01', $dados['dataInicio']);
    }

    public function testModoInvalidoCaiParaDia(): void
    {
        $dados = RelatorioService::preparar('modo_qualquer', '', '', '', '', '', '', '');

        $this->assertSame('dia', $dados['modo']);
    }

    public function testStatusInvalidoCaiParaVazio(): void
    {
        $dados = RelatorioService::preparar('dia', '', '', '', '', '', 'status_invalido', '');

        $this->assertSame('', $dados['status']);
    }

    public function testTotaisContamPorStatus(): void
    {
        $idCard1 = $this->inserirCardapio('2026-07-15', 'almoco');
        $idCard2 = $this->inserirCardapio('2026-07-16', 'almoco');
        $idCard3 = $this->inserirCardapio('2026-07-17', 'almoco');
        $this->inserirRegistro('60001', $idCard1, 'agendado');
        $this->inserirRegistro('60001', $idCard2, 'concluido');
        $this->inserirRegistro('60001', $idCard3, 'cancelado');

        $dados = RelatorioService::preparar('periodo', '', '2026-07-01', '2026-07-31', '', '', '', '');

        $this->assertSame(1, $dados['totais']['agendado']);
        $this->assertSame(1, $dados['totais']['concluido']);
        $this->assertSame(1, $dados['totais']['cancelado']);
    }

    public function testConsolidadoAgrupaSomenteConcluidosPorUsuario(): void
    {
        $this->inserirUsuario('60002', 'OUTRO FUNCIONARIO');
        $idCard1 = $this->inserirCardapio('2026-07-15', 'almoco');
        $idCard2 = $this->inserirCardapio('2026-07-16', 'almoco');
        $idCard3 = $this->inserirCardapio('2026-07-17', 'almoco');
        $this->inserirRegistro('60001', $idCard1, 'concluido');
        $this->inserirRegistro('60001', $idCard2, 'concluido');
        $this->inserirRegistro('60002', $idCard3, 'agendado');

        $valorAnterior = $_ENV['VALOR_REFEICAO'] ?? null;
        $_ENV['VALOR_REFEICAO'] = '12.50';
        try {
            $dados = RelatorioService::preparar(
                'periodo', '', '2026-07-01', '2026-07-31', '', '', '', '', true
            );
        } finally {
            if ($valorAnterior === null) {
                unset($_ENV['VALOR_REFEICAO']);
            } else {
                $_ENV['VALOR_REFEICAO'] = $valorAnterior;
            }
        }

        $this->assertTrue($dados['consolidado']);
        $this->assertSame('concluido', $dados['status']);
        $this->assertCount(1, $dados['funcionarios']);
        $this->assertSame('FUNCIONARIO TESTE', $dados['funcionarios']['60001']['nome']);
        $this->assertSame(2, $dados['funcionarios']['60001']['quantidade']);
        $this->assertSame(25.0, $dados['funcionarios']['60001']['valor_total']);
    }

    public function testCsvConsolidadoIncluiQuantidadeEValorTotal(): void
    {
        $csv = RelatorioService::csv([
            'funcionarios' => [
                '60001' => [
                    'nome'        => 'FUNCIONÁRIO; TESTE',
                    'quantidade'  => 2,
                    'valor_total' => 25.0,
                ],
            ],
        ]);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Matrícula;Nome;Quantidade;"Valor total"', $csv);
        $this->assertStringContainsString('60001;"FUNCIONÁRIO; TESTE";2;25,00', $csv);
    }

    public function testNomeArquivoCsvIncluiPeriodo(): void
    {
        $nome = RelatorioService::nomeArquivoCsv([
            'dataInicio' => '2026-07-01',
            'dataFim'    => '2026-07-31',
        ]);

        $this->assertSame('relatorio_refeitorio_20260701_20260731.csv', $nome);
    }
}
