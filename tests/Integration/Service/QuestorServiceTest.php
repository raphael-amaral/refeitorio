<?php

namespace Tests\Integration\Service;

use App\Exceptions\PeriodoInvalidoException;
use App\Services\QuestorService;
use Tests\Integration\DatabaseTestCase;

class QuestorServiceTest extends DatabaseTestCase
{
    // =========================================================================
    // relatorio()
    // =========================================================================

    public function testRelatorioAgrupaRegistrosConcluidosPorMatricula(): void
    {
        $this->inserirUsuario('40001', 'FULANO');
        $idCard1 = $this->inserirCardapio('2026-07-01', 'almoco');
        $idCard2 = $this->inserirCardapio('2026-07-02', 'almoco');
        $this->inserirRegistro('40001', $idCard1, 'concluido');
        $this->inserirRegistro('40001', $idCard2, 'concluido');

        $relatorio = QuestorService::relatorio('2026-07-01', '2026-07-31', '15');

        $this->assertCount(1, $relatorio['funcionarios']);
        $this->assertSame('FULANO', $relatorio['funcionarios']['40001']['nome']);
        $this->assertSame(2, $relatorio['funcionarios']['40001']['count']);
    }

    public function testRelatorioIgnoraRegistrosNaoConcluidos(): void
    {
        $this->inserirUsuario('40002', 'FULANO');
        $idCard = $this->inserirCardapio('2026-07-01', 'almoco');
        $this->inserirRegistro('40002', $idCard, 'agendado');

        $relatorio = QuestorService::relatorio('2026-07-01', '2026-07-31', '15');

        $this->assertSame([], $relatorio['funcionarios']);
    }

    public function testRelatorioUsaMesAtualComoPadraoQuandoDatasInvalidas(): void
    {
        $relatorio = QuestorService::relatorio('data-invalida', '', '15');

        $this->assertSame(date('Y-m-01'), $relatorio['data_inicio']);
        $this->assertSame(date('Y-m-t'), $relatorio['data_fim']);
    }

    public function testRelatorioRejeitaPeriodoInvertido(): void
    {
        $this->expectException(PeriodoInvalidoException::class);

        QuestorService::relatorio('2026-08-01', '2026-07-31', '15');
    }

    public function testRelatorioUsaValorRefeicaoInformado(): void
    {
        $relatorio = QuestorService::relatorio('2026-07-01', '2026-07-31', '12,50');

        $this->assertSame(12.5, $relatorio['valor_refeicao']);
    }

    public function testRelatorioUsaQuinzeComoPadraoParaValorInvalido(): void
    {
        $relatorio = QuestorService::relatorio('2026-07-01', '2026-07-31', '0');

        $this->assertSame(15.0, $relatorio['valor_refeicao']);
    }

    public function testRelatorioUsaQuinzeComoPadraoParaValorVazio(): void
    {
        $relatorio = QuestorService::relatorio('2026-07-01', '2026-07-31', '');

        $this->assertSame(15.0, $relatorio['valor_refeicao']);
    }

    // =========================================================================
    // csv()
    // =========================================================================

    public function testCsvGeraCabecalhoELinhaPorFuncionario(): void
    {
        $relatorio = [
            'data_inicio'    => '2026-07-01',
            'data_fim'       => '2026-07-31',
            'valor_refeicao' => 15.0,
            'funcionarios'   => [
                '12345' => ['nome' => 'FULANO', 'count' => 3],
            ],
        ];

        $csv = QuestorService::csv($relatorio);

        $linhas = explode("\r\n", $csv);
        $this->assertSame(';;767', $linhas[0]);
        $this->assertSame(';;V', $linhas[1]);
        $this->assertSame('contrato;nome;Desconto Vale Refeicao', $linhas[2]);
        $this->assertSame('12345;FULANO;45,00', $linhas[3]);
    }

    public function testCsvSemFuncionariosGeraSoCabecalho(): void
    {
        $relatorio = [
            'data_inicio'    => '2026-07-01',
            'data_fim'       => '2026-07-31',
            'valor_refeicao' => 15.0,
            'funcionarios'   => [],
        ];

        $csv    = QuestorService::csv($relatorio);
        $linhas = explode("\r\n", trim($csv));

        $this->assertCount(3, $linhas);
    }

    // =========================================================================
    // nomeArquivo()
    // =========================================================================

    public function testNomeArquivoMontaNomeComDatasSemHifen(): void
    {
        $relatorio = ['data_inicio' => '2026-07-01', 'data_fim' => '2026-07-31'];

        $this->assertSame('questor_vale_refeicao_20260701_20260731.csv', QuestorService::nomeArquivo($relatorio));
    }
}
