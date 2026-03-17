<?php
/** @var array[] $registros */
/** @var array   $totais */
/** @var string  $modo */
/** @var string  $dataInicio */
/** @var string  $dataFim */

$labelStatus = ['agendado' => 'Não Concluído', 'cancelado' => 'Cancelado', 'concluido' => 'Concluído'];
$mesesPtBr = [1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril', 5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'];

// Formatar subtítulo
$subtitulo = '';
if ($modo === 'dia') {
    $subtitulo = 'Data: ' . date('d/m/Y', strtotime($dataInicio));
} elseif ($modo === 'periodo') {
    $subtitulo = 'Período: ' . date('d/m/Y', strtotime($dataInicio)) . ' a ' . date('d/m/Y', strtotime($dataFim));
} else {
    $numMes = (int) date('m', strtotime($dataInicio));
    $ano    = date('Y', strtotime($dataInicio));
    $subtitulo = 'Mês: ' . $mesesPtBr[$numMes] . ' de ' . $ano;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($titulo) ?></title>
    <link rel="stylesheet" href="/css/relatorio-print.css">
</head>
<body>

    <div class="print-header">
        <div class="logo-box">SND</div>
        <div>
            <h1>Relatório Refeitório</h1>
            <p><?= $subtitulo ?></p>
            <p>Gerado em: <?= date('d/m/Y \à\s H:i') ?></p>
        </div>
    </div>

    <!-- Resumo -->
    <div class="resumo-box">
        <div><strong>Concluídos:</strong> <?= $totais['concluido'] ?></div>
        <div><strong>Cancelados:</strong> <?= $totais['cancelado'] ?></div>
        <div><strong>Não Concluídos:</strong> <?= $totais['agendado'] ?></div>
        <div><strong>Total Geral:</strong> <?= count($registros) ?></div>
    </div>

    <?php if (empty($registros)): ?>
        <p class="empty">Nenhum registro encontrado para este filtro.</p>
    <?php else: ?>
        <table class="tabela-print">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Refeição</th>
                    <th>Matrícula</th>
                    <th>Nome do Usuário</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($registros as $r):
                    $ts = strtotime($r['data_cardapio']);
                ?>
                <tr>
                    <td class="col-data">
                        <?= date('d/m/Y', $ts) ?><br>
                    </td>
                    <td><?= $r['tipo_refeicao'] === 'almoco' ? 'Almoço' : 'Jantar' ?></td>
                    <td><?= htmlspecialchars($r['matricula']) ?></td>
                    <td><?= htmlspecialchars($r['nome_usuario']) ?></td>
                    <td class="status-<?= $r['status'] ?>"><?= $labelStatus[$r['status']] ?? $r['status'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div class="print-footer">
        Sistema de Refeitório — Santa Casa
    </div>

    <!-- Aciona a impressão automaticamente -->
    <script>
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>
