<?php

namespace App\Services;

use App\Core\Audit;
use App\Models\Registro;

class QuestorService
{
    /**
     * Monta o relatório de vale-refeição do período: registros concluídos agrupados por funcionário.
     * Datas/valor inválidos ou ausentes caem no padrão (mês atual / R$15).
     *
     * @return array{data_inicio: string, data_fim: string, valor_refeicao: float, funcionarios: array<string, array{nome: string, count: int}>}
     */
    public static function relatorio(string $dataInicioRaw, string $dataFimRaw, string $valorRefeicaoRaw): array
    {
        $reData     = '/^\d{4}-\d{2}-\d{2}$/';
        $dataInicio = preg_match($reData, $dataInicioRaw) ? $dataInicioRaw : date('Y-m-01');
        $dataFim    = preg_match($reData, $dataFimRaw)    ? $dataFimRaw    : date('Y-m-t');

        $valorRefeicao = (float) str_replace(',', '.', $valorRefeicaoRaw);
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

        Audit::write('questor_export', 'success', null, [
            'data_inicio'        => $dataInicio,
            'data_fim'           => $dataFim,
            'valor_refeicao'     => $valorRefeicao,
            'total_funcionarios' => count($funcionarios),
        ]);

        return [
            'data_inicio'    => $dataInicio,
            'data_fim'       => $dataFim,
            'valor_refeicao' => $valorRefeicao,
            'funcionarios'   => $funcionarios,
        ];
    }

    /**
     * Gera o conteúdo do CSV no layout Questor a partir do relatório.
     *
     * Estrutura do arquivo:
     *   Linha 1 → ;;767          (código do evento)
     *   Linha 2 → ;;V            (tipo: valor)
     *   Linha 3 → contrato;nome;Desconto Vale Refeicao  (título — não importado)
     *   Linha 4+ → contrato;nome;valor_total
     */
    public static function csv(array $relatorio): string
    {
        $linhas   = [];
        $linhas[] = ';;767';
        $linhas[] = ';;V';
        $linhas[] = 'contrato;nome;Desconto Vale Refeicao';

        foreach ($relatorio['funcionarios'] as $contrato => $dados) {
            $total    = number_format($dados['count'] * $relatorio['valor_refeicao'], 2, ',', '');
            $linhas[] = "{$contrato};{$dados['nome']};{$total}";
        }

        return implode("\r\n", $linhas) . "\r\n";
    }

    public static function nomeArquivo(array $relatorio): string
    {
        return 'questor_vale_refeicao_'
            . str_replace('-', '', $relatorio['data_inicio']) . '_'
            . str_replace('-', '', $relatorio['data_fim']) . '.csv';
    }

    /**
     * Gera um backup do banco via mysqldump. Dependência externa verdadeira (processo + MySQL
     * de verdade) — não é testável pela suíte automatizada, que roda contra SQLite em memória.
     */
    public static function gerarBackup(string $dataInicio, string $dataFim): void
    {
        $host     = $_ENV['DB_HOST']     ?? 'db';
        $user     = $_ENV['DB_USERNAME'] ?? '';
        $password = $_ENV['DB_PASSWORD'] ?? '';
        $database = $_ENV['DB_DATABASE'] ?? '';

        $dir = dirname(__DIR__, 2) . '/storage/backups/questor';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $sufixo  = str_replace('-', '', $dataInicio) . '_' . str_replace('-', '', $dataFim);
        $arquivo = $dir . '/questor_' . $sufixo . '_' . date('His') . '.sql';

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
