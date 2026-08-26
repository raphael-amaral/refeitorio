<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class QuestorControllerTest extends TestCase
{
    public function testNaoIniciaDownloadAntesDeSalvarHistorico(): void
    {
        $codigo = file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/QuestorController.php');

        $salvarHistorico = strpos($codigo, 'QuestorExportacao::criar');
        $iniciarDownload = strpos($codigo, "header('Content-Type: text/csv");

        $this->assertNotFalse($salvarHistorico);
        $this->assertNotFalse($iniciarDownload);
        $this->assertLessThan($iniciarDownload, $salvarHistorico);
    }
}
