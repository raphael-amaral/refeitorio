<?php
$temFiltro = !empty($dataInicio) || !empty($dataFim) || !empty($status);
$labelStatus = ['agendado' => 'Agendado', 'cancelado' => 'Cancelado', 'concluido' => 'Concluído'];
?>

<div class="page-header">
    <h1>Meus Agendamentos</h1>
    <a href="/painel" class="btn btn-outline">← Voltar ao Painel</a>
</div>

<!-- Filtros -->
<div style="background:#fff;border-radius:6px;padding:18px 22px;margin-bottom:18px;box-shadow:1px 5px 12px rgba(0,0,0,0.25);">
    <p style="font-family:var(--font-principal);font-size:0.85rem;font-weight:600;color:var(--verde);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:14px;">
        Filtrar agendamentos
    </p>
    <form method="GET" action="/registro" style="display:flex;align-items:flex-end;gap:16px;flex-wrap:wrap;">
        <div style="display:flex;flex-direction:column;gap:4px;">
            <label for="data_inicio" style="font-size:0.78rem;font-weight:600;color:#555;text-transform:uppercase;letter-spacing:0.04em;">De</label>
            <input type="date" id="data_inicio" name="data_inicio"
                value="<?= htmlspecialchars($dataInicio ?? '') ?>"
                style="padding:7px 10px;border:1px solid #ccc;border-radius:4px;font-size:0.9rem;background:#fafafa;color:#333;min-width:160px;">
        </div>
        <div style="display:flex;flex-direction:column;gap:4px;">
            <label for="data_fim" style="font-size:0.78rem;font-weight:600;color:#555;text-transform:uppercase;letter-spacing:0.04em;">Até</label>
            <input type="date" id="data_fim" name="data_fim"
                value="<?= htmlspecialchars($dataFim ?? '') ?>"
                style="padding:7px 10px;border:1px solid #ccc;border-radius:4px;font-size:0.9rem;background:#fafafa;color:#333;min-width:160px;">
        </div>
        <div style="display:flex;flex-direction:column;gap:4px;">
            <label for="status" style="font-size:0.78rem;font-weight:600;color:#555;text-transform:uppercase;letter-spacing:0.04em;">Status</label>
            <select id="status" name="status"
                style="padding:7px 10px;border:1px solid #ccc;border-radius:4px;font-size:0.9rem;background:#fafafa;color:#333;min-width:150px;">
                <option value="">Todos</option>
                <option value="agendado" <?= ($status ?? '') === 'agendado'  ? 'selected' : '' ?>>Agendado</option>
                <option value="cancelado" <?= ($status ?? '') === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                <option value="concluido" <?= ($status ?? '') === 'concluido' ? 'selected' : '' ?>>Concluído</option>
            </select>
        </div>
        <div style="display:flex;gap:8px;align-items:flex-end;">
            <button type="submit" style="background:var(--verde);color:#fff;border:none;border-radius:4px;padding:8px 20px;font-size:0.88rem;cursor:pointer;font-family:var(--font-cards);">Filtrar</button>
            <?php if ($temFiltro): ?>
                <a href="/registro" style="background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;border-radius:4px;padding:8px 14px;font-size:0.88rem;text-decoration:none;font-family:var(--font-cards);">Limpar</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Tabela -->
<?php if (empty($registros)): ?>
    <p style="padding:4px 0;color:rgba(255,255,255,0.85);font-size:0.9rem;text-shadow:0 1px 3px rgba(0,0,0,0.4);">
        Nenhum agendamento encontrado<?= $temFiltro ? ' para o filtro selecionado' : '' ?>.
    </p>
<?php else: ?>
    <table class="tabela">
        <thead>
            <tr>
                <th>Data</th>
                <th>Dia</th>
                <th>Refeição</th>
                <th>Descrição</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($registros as $r): ?>
                <?php
                $ts       = strtotime($r['data']);
                $diaLabel = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'][(int) date('w', $ts)];
                $ehHoje   = $r['data'] === date('Y-m-d');
                $statusMap = ['agendado' => 'badge--agendado', 'cancelado' => 'badge--cancelado', 'concluido' => 'badge--concluido'];
                $cls = $statusMap[$r['status']] ?? '';
                ?>
                <tr<?= $ehHoje ? ' style="background:rgba(209,104,34,0.07);"' : '' ?>>
                    <td style="white-space:nowrap;"><?= htmlspecialchars(date('d/m/Y', $ts)) ?></td>
                    <td><?= $diaLabel ?></td>
                    <td>
                        <span class="badge <?= $r['tipo_refeicao'] === 'almoco' ? 'badge--agendado' : 'badge--concluido' ?>">
                            <?= $r['tipo_refeicao'] === 'almoco' ? 'Almoço' : 'Jantar' ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($r['descricao'] ?? '—') ?></td>
                    <td><span class="badge <?= $cls ?>"><?= htmlspecialchars(ucfirst($r['status'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>