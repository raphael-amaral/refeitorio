<?php

/** @var array[] $registros */
/** @var array   $funcionarios */
/** @var bool    $consolidado */
/** @var float   $valorRefeicao */
/** @var array   $totais */
/** @var string  $modo */
/** @var string  $dataInicio */
/** @var string  $dataFim */

$labelStatus = ['agendado' => 'Agendado', 'cancelado' => 'Cancelado', 'concluido' => 'Concluído'];
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

    <table class="tabela-print">
        <thead>
            <tr>
                <td colspan="<?= $consolidado ? 4 : 5 ?>" style="border: none !important; background: transparent !important; padding: 0 !important; text-align: left;">
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
                        <?php if ($consolidado): ?>
                            <div><strong>Funcionários:</strong> <?= count($funcionarios) ?></div>
                            <div><strong>Refeições concluídas:</strong> <?= count($registros) ?></div>
                            <div><strong>Valor unitário:</strong> R$ <?= number_format($valorRefeicao, 2, ',', '.') ?></div>
                            <div><strong>Valor total:</strong> R$ <?= number_format(count($registros) * $valorRefeicao, 2, ',', '.') ?></div>
                        <?php else: ?>
                            <div><strong>Concluídos:</strong> <?= $totais['concluido'] ?></div>
                            <div><strong>Cancelados:</strong> <?= $totais['cancelado'] ?></div>
                            <div><strong>Agendados/Não Concluidos:</strong> <?= $totais['agendado'] ?></div>
                            <div><strong>Total Geral:</strong> <?= count($registros) ?></div>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <tr>
                <?php if ($consolidado): ?>
                    <th>Matrícula</th>
                    <th>Nome do Usuário</th>
                    <th>Quantidade</th>
                    <th>Valor total</th>
                <?php else: ?>
                    <th>Data</th>
                    <th>Refeição</th>
                    <th>Matrícula</th>
                    <th>Nome do Usuário</th>
                    <th>Status</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($registros)): ?>
                <tr>
                    <td colspan="<?= $consolidado ? 4 : 5 ?>" class="empty" style="text-align: center; padding: 20px;">Nenhum registro encontrado para este filtro.</td>
                </tr>
            <?php elseif ($consolidado): ?>
                <?php foreach ($funcionarios as $matriculaFuncionario => $dados): ?>
                    <tr>
                        <td><?= htmlspecialchars($matriculaFuncionario) ?></td>
                        <td><?= htmlspecialchars($dados['nome']) ?></td>
                        <td><?= $dados['quantidade'] ?></td>
                        <td><strong>R$ <?= number_format($dados['valor_total'], 2, ',', '.') ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
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
            <?php endif; ?>
        </tbody>
    </table>

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
