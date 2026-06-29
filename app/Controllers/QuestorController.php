<?php

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Controller;
use App\Models\Registro;

class QuestorController extends Controller
{
    public function index(): void
    {
        $this->render('questor/index', ['titulo' => 'Exportação Questor']);
    }

    /**
     * Gera e envia o CSV no layout Questor (GET /questor/exportar).
     *
     * Estrutura do arquivo:
     *   Linha 1 → #;#;767          (código do evento)
     *   Linha 2 → #;#;V            (tipo: valor)
     *   Linha 3 → #;#;Desconto Vale Refeicao  (título — não importado)
     *   Linha 4+ → contrato;nome;valor_total
     */
    public function exportar(): void
    {
        $reData = '/^\d{4}-\d{2}-\d{2}$/';

        $di         = $_GET['data_inicio'] ?? '';
        $df         = $_GET['data_fim']    ?? '';
        $dataInicio = preg_match($reData, $di) ? $di : date('Y-m-01');
        $dataFim    = preg_match($reData, $df) ? $df : date('Y-m-t');

        $valorRefeicao = (float) str_replace(',', '.', $_GET['valor_refeicao'] ?? '15');
        if ($valorRefeicao <= 0) {
            $valorRefeicao = 15.0;
        }

        $registros = Registro::buscarParaRelatorio('concluido', $dataInicio, $dataFim);

        // Agrupa por funcionário: contrato → [nome, contagem]
        $funcionarios = [];
        foreach ($registros as $r) {
            $mat = $r['matricula'];
            if (!isset($funcionarios[$mat])) {
                $funcionarios[$mat] = ['nome' => $r['nome_usuario'], 'count' => 0];
            }
            $funcionarios[$mat]['count']++;
        }

        $nomeArquivo = 'questor_vale_refeicao_' . str_replace('-', '', $dataInicio) . '_' . str_replace('-', '', $dataFim) . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');

        $out = fopen('php://output', 'w');

        // Linhas de cabeçalho do layout Questor
        fwrite($out, ";;767\r\n");
        fwrite($out, ";;V\r\n");
        fwrite($out, "contrato;nome;Desconto Vale Refeicao\r\n");

        // Uma linha por funcionário com o total descontado
        foreach ($funcionarios as $contrato => $dados) {
            $total = number_format($dados['count'] * $valorRefeicao, 2, ',', '');
            fwrite($out, "{$contrato};{$dados['nome']};{$total}\r\n");
        }

        fclose($out);

        Audit::write('questor_export', 'success', null, [
            'data_inicio'        => $dataInicio,
            'data_fim'           => $dataFim,
            'valor_refeicao'     => $valorRefeicao,
            'total_funcionarios' => count($funcionarios),
        ]);

        $this->gerarBackup($dataInicio, $dataFim);

        exit;
    }

    private function gerarBackup(string $dataInicio, string $dataFim): void
    {
        $host     = $_ENV['DB_HOST']     ?? 'db';
        $user     = $_ENV['DB_USERNAME'] ?? '';
        $password = $_ENV['DB_PASSWORD'] ?? '';
        $database = $_ENV['DB_DATABASE'] ?? '';

        $dir = dirname(__DIR__, 2) . '/storage/backups/questor';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $sufixo   = str_replace('-', '', $dataInicio) . '_' . str_replace('-', '', $dataFim);
        $arquivo  = $dir . '/questor_' . $sufixo . '_' . date('His') . '.sql';

        $cmd = sprintf(
            'mysqldump -h %s -u %s -p%s %s > %s 2>&1',
            escapeshellarg($host),
            escapeshellarg($user),
            escapeshellarg($password),
            escapeshellarg($database),
            escapeshellarg($arquivo)
        );

        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            Audit::write('questor_backup', 'error', null, ['saida' => implode("\n", $output)]);
        } else {
            Audit::write('questor_backup', 'success', null, ['arquivo' => basename($arquivo)]);
        }
    }
}
