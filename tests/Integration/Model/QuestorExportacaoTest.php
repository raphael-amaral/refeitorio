<?php

namespace Tests\Integration\Model;

use App\Models\QuestorExportacao;
use Tests\Integration\DatabaseTestCase;

class QuestorExportacaoTest extends DatabaseTestCase
{
    public function testPersisteArquivoAssociadoAoBackup(): void
    {
        $this->inserirUsuario('dp01', 'DEPARTAMENTO PESSOAL', 'dp');

        QuestorExportacao::criar([
            'data_inicio'    => '2026-07-01',
            'data_fim'       => '2026-07-31',
            'valor_refeicao' => 15.0,
            'exportado_por'  => 'dp01',
            'arquivo_backup' => 'questor_refeitorio_snd_20260701_20260731_120000.sql',
        ]);

        $ultima = QuestorExportacao::ultima();

        $this->assertSame('questor_refeitorio_snd_20260701_20260731_120000.sql', $ultima['arquivo_backup']);
    }
}
