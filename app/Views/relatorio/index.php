<?php

/** @var array[] $registros */
/** @var array   $totais */
/** @var string  $modo */
/** @var string  $dataInicio */
/** @var string  $dataFim */
/** @var string  $status */
/** @var string  $matricula */

$labelStatus = [
    'agendado'  => 'Agendado',
    'cancelado' => 'Cancelado',
    'concluido' => 'Concluído',
];
$diasSemana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
$mesesPtBr = [1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril', 5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'];

$mesFiltro = date('m', strtotime($dataInicio));
$anoFiltro = date('Y', strtotime($dataInicio));
?>

<!-- Estilos do Filtro Aba -->
<style>
    .tabs {
        display: flex;
        gap: 4px;
        border-bottom: 2px solid #e5e7eb;
        margin-bottom: 16px;
        align-items: center;
    }

    .tab-btn {
        padding: 8px 16px;
        border: none;
        background: transparent;
        color: #6b7280;
        font-weight: 600;
        cursor: pointer;
        border-bottom: 2px solid transparent;
        margin-bottom: -2px;
    }

    .tab-btn.active {
        color: var(--verde);
        border-bottom-color: var(--verde);
    }

    .tab-pane {
        display: none;
    }

    .tab-pane.active {
        display: flex;
    }
</style>


<!-- Filtros -->
<div style="background:#fff;border-radius:6px;padding:18px 22px;margin-bottom:18px;box-shadow:1px 5px 12px rgba(0,0,0,0.15);">
    <div class="tabs">
        <button type="button" class="tab-btn <?= $modo === 'dia' ? 'active' : '' ?>" data-target="tab-dia">Dia Específico</button>
        <button type="button" class="tab-btn <?= $modo === 'mes' ? 'active' : '' ?>" data-target="tab-mes">Por Mês</button>
        <button type="button" class="tab-btn <?= $modo === 'periodo' ? 'active' : '' ?>" data-target="tab-periodo">Período</button>
        <a href="/relatorios/pdf?<?= http_build_query($_GET) ?>" target="_blank" style="margin-left:auto; margin-bottom:6px; background:var(--laranja); color:white; padding:6px 14px; border-radius:6px; text-decoration:none; font-weight:600; font-size:0.88rem; white-space:nowrap;">🖨️ Gerar PDF</a>
    </div>

    <form method="get" action="/relatorios" style="display:flex; flex-direction:column; gap:16px;">
        <input type="hidden" name="modo" id="modo" value="<?= htmlspecialchars($modo) ?>">

        <!-- Painéis de Data -->
        <div id="tab-dia" class="tab-pane <?= $modo === 'dia' ? 'active' : '' ?>" style="gap:16px;">
            <div>
                <label style="display:block; font-size:0.8rem; font-weight:600; color:#555; margin-bottom:4px;">Data</label>
                <input type="date" name="data_dia" value="<?= $modo === 'dia' ? $dataInicio : date('Y-m-d') ?>" style="padding:8px 12px; border:1px solid #ccc; border-radius:4px;">
            </div>
        </div>

        <div id="tab-mes" class="tab-pane <?= $modo === 'mes' ? 'active' : '' ?>" style="gap:16px;">
            <div>
                <label style="display:block; font-size:0.8rem; font-weight:600; color:#555; margin-bottom:4px;">Mês</label>
                <select name="mes" style="padding:8px 12px; border:1px solid #ccc; border-radius:4px; min-width:120px;">
                    <?php foreach ($mesesPtBr as $m => $nomeMes): $mStr = str_pad((string)$m, 2, '0', STR_PAD_LEFT); ?>
                        <option value="<?= $mStr ?>" <?= $mesFiltro === $mStr ? 'selected' : '' ?>><?= $nomeMes ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display:block; font-size:0.8rem; font-weight:600; color:#555; margin-bottom:4px;">Ano</label>
                <select name="ano" style="padding:8px 12px; border:1px solid #ccc; border-radius:4px;">
                    <?php for ($a = date('Y'); $a >= 2024; $a--): ?>
                        <option value="<?= $a ?>" <?= $anoFiltro === (string)$a ? 'selected' : '' ?>><?= $a ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>

        <div id="tab-periodo" class="tab-pane <?= $modo === 'periodo' ? 'active' : '' ?>" style="gap:16px;">
            <div>
                <label style="display:block; font-size:0.8rem; font-weight:600; color:#555; margin-bottom:4px;">De</label>
                <input type="date" name="data_inicio" value="<?= $modo === 'periodo' ? $dataInicio : date('Y-m-01') ?>" style="padding:8px 12px; border:1px solid #ccc; border-radius:4px;">
            </div>
            <div>
                <label style="display:block; font-size:0.8rem; font-weight:600; color:#555; margin-bottom:4px;">Até</label>
                <input type="date" name="data_fim" value="<?= $modo === 'periodo' ? $dataFim : date('Y-m-t') ?>" style="padding:8px 12px; border:1px solid #ccc; border-radius:4px;">
            </div>
        </div>

        <!-- Filtros Comuns -->
        <div style="display:flex; gap:16px; flex-wrap:wrap; padding-top:12px; border-top:1px solid #eee;">
            <div>
                <label style="display:block; font-size:0.8rem; font-weight:600; color:#555; margin-bottom:4px;">Matrícula</label>
                <input type="text" name="matricula" value="<?= htmlspecialchars($matricula) ?>" placeholder="Ex: 123456" style="padding:8px 12px; border:1px solid #ccc; border-radius:4px; max-width:140px;">
            </div>
            <div>
                <label style="display:block; font-size:0.8rem; font-weight:600; color:#555; margin-bottom:4px;">Status</label>
                <select name="status" style="padding:8px 12px; border:1px solid #ccc; border-radius:4px; min-width:140px;">
                    <option value="">Todos</option>
                    <?php foreach ($labelStatus as $val => $label): ?>
                        <option value="<?= $val ?>" <?= $status === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex; align-items:flex-end; gap:8px;">
                <button type="submit" style="background:var(--verde); color:white; padding:8px 20px; border:none; border-radius:4px; font-weight:600; cursor:pointer;">Filtrar</button>
                <?php if (!empty($_GET)): ?>
                    <a href="/relatorios" style="padding:8px 16px; border:1px solid #ccc; color:#555; border-radius:4px; text-decoration:none; font-size:0.9rem;">Limpar</a>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<!-- Totalizadores -->
<div style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:20px;">
    <?php
    $cards = [
        ['label' => 'Agendados',  'val' => $totais['agendado'],  'cor' => 'var(--verde)'],
        ['label' => 'Concluídos', 'val' => $totais['concluido'], 'cor' => 'var(--laranja)'],
        ['label' => 'Cancelados', 'val' => $totais['cancelado'], 'cor' => '#991b1b'],
        ['label' => 'Total',      'val' => count($registros),    'cor' => '#6b7280'],
    ];
    foreach ($cards as $c): ?>
        <div style="background:#fff;border-radius:8px;padding:14px 22px;box-shadow:1px 5px 12px rgba(0,0,0,0.18);min-width:120px;text-align:center;">
            <div style="font-size:1.7rem;font-weight:700;color:<?= $c['cor'] ?>;"><?= $c['val'] ?></div>
            <div style="font-size:0.78rem;color:#555;text-transform:uppercase;letter-spacing:0.04em;margin-top:2px;"><?= $c['label'] ?></div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Tabela -->
<?php if (empty($registros)): ?>
    <p style="color:rgba(255,255,255,0.85);font-size:0.9rem;">Nenhum registro encontrado para estes filtros.</p>
<?php else: ?>
    <div style="background:#fff;border-radius:6px;overflow:hidden;box-shadow:1px 5px 12px rgba(0,0,0,0.2);">
        <table class="tabela">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Dia</th>
                    <th>Refeição</th>
                    <th>Matrícula</th>
                    <th>Nome</th>
                    <th>Cardápio</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($registros as $r):
                    $ts  = strtotime($r['data_cardapio']);
                    $cls = 'badge--' . $r['status'];
                ?>
                    <tr>
                        <td style="white-space:nowrap;"><?= date('d/m/Y', $ts) ?></td>
                        <td><?= $diasSemana[(int) date('w', $ts)] ?></td>
                        <td>
                            <span class="badge <?= $r['tipo_refeicao'] === 'almoco' ? 'badge--agendado' : 'badge--concluido' ?>">
                                <?= $r['tipo_refeicao'] === 'almoco' ? 'Almoço' : 'Jantar' ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($r['matricula']) ?></td>
                        <td><?= htmlspecialchars($r['nome_usuario']) ?></td>
                        <td><?= htmlspecialchars($r['descricao']) ?></td>
                        <td><span class="badge <?= $cls ?>"><?= $labelStatus[$r['status']] ?? $r['status'] ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<script>
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            // Remove active
            document.querySelectorAll('.tab-btn, .tab-pane').forEach(el => el.classList.remove('active'));
            // Add active
            btn.classList.add('active');
            document.getElementById(btn.dataset.target).classList.add('active');
            // Update hidden input
            const map = {
                'tab-mes': 'mes',
                'tab-dia': 'dia',
                'tab-periodo': 'periodo'
            };
            document.getElementById('modo').value = map[btn.dataset.target];
        });
    });
</script>