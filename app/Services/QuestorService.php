<?php

namespace App\Services;

use App\Core\Audit;
use App\Exceptions\PeriodoInvalidoException;
use App\Exceptions\QuestorBackupException;
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

        if ($dataInicio > $dataFim) {
            Audit::write('questor_export', 'blocked', null, [
                'motivo'      => 'periodo_invertido',
                'data_inicio' => $dataInicio,
                'data_fim'    => $dataFim,
            ]);
            throw new PeriodoInvalidoException();
        }

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
     * Gera um backup do banco via mysqldump. A suíte simula o processo para validar publicação
     * e falhas sem acessar o MySQL configurado. Retorna o nome do arquivo publicado; uma falha
     * cancela a exportação do CSV.
     */
    public static function gerarBackup(string $dataInicio, string $dataFim): string
    {
        $host     = $_ENV['DB_HOST']     ?? 'host.docker.internal';
        $port     = $_ENV['DB_PORT']     ?? '3103';
        $user     = $_ENV['DB_USERNAME'] ?? '';
        $password = $_ENV['DB_PASSWORD'] ?? '';
        $database = $_ENV['DB_DATABASE'] ?? '';

        $backupDir = rtrim($_ENV['BACKUP_DIR'] ?? '', '/');
        $dir       = $backupDir . '/questor';
        if ($backupDir === '' || (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir))) {
            Audit::write('questor_backup', 'error', null, ['saida' => 'BACKUP_DIR ausente ou diretório inacessível.']);
            throw new QuestorBackupException();
        }

        $sufixo      = str_replace('-', '', $dataInicio) . '_' . str_replace('-', '', $dataFim);
        $nomeArquivo = 'questor_' . $database . '_' . $sufixo . '_' . date('His') . '.sql';
        $arquivo     = $dir . '/' . $nomeArquivo;
        $temporario  = $arquivo . '.tmp';
        $arquivoErro = $arquivo . '.err';

        $cmd = sprintf(
            'MYSQL_PWD=%s mysqldump --skip-ssl --single-transaction --quick --no-tablespaces -h %s -P %s -u %s %s > %s 2> %s',
            escapeshellarg($password),
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($user),
            escapeshellarg($database),
            escapeshellarg($temporario),
            escapeshellarg($arquivoErro)
        );

        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            $erro = is_file($arquivoErro) ? trim((string) file_get_contents($arquivoErro)) : implode("\n", $output);
            if (is_file($temporario)) {
                unlink($temporario);
            }
            if (is_file($arquivoErro)) {
                unlink($arquivoErro);
            }
            Audit::write('questor_backup', 'error', null, ['saida' => $erro]);
            throw new QuestorBackupException();
        }

        if (is_file($arquivoErro)) {
            unlink($arquivoErro);
        }

        if (!is_file($temporario) || filesize($temporario) === 0 || !rename($temporario, $arquivo)) {
            if (is_file($temporario)) {
                unlink($temporario);
            }
            Audit::write('questor_backup', 'error', null, ['saida' => 'Dump vazio ou falha ao publicar o arquivo.']);
            throw new QuestorBackupException();
        }

        Audit::write('questor_backup', 'success', null, ['arquivo' => $nomeArquivo]);
        return $nomeArquivo;
    }
}
