<?php
$isAdminDev  = $_SESSION['usuario']['tipo'] === 'dev'; // criar/editar/excluir estrutural — só dev
$isCopa      = in_array($_SESSION['usuario']['tipo'], ['copa', 'dev'], true);
$mostraAcoes = $isAdminDev || $isCopa;

// Intervalo editável: hoje até próximo domingo
$hojeStr   = date('Y-m-d');
$diaNum    = (int) date('N'); // 1=Seg…7=Dom
$domingoStr = date('Y-m-d', strtotime('+' . (7 - $diaNum) . ' days'));
?>

<div class="page-header">
    <h1>Cardápios</h1>
    <?php if ($isAdminDev): ?>
        <a href="/cardapio/novo" class="btn" style="background:var(--verde);color:#fff;">+ Novo Cardápio</a>
    <?php endif; ?>
</div>

<!-- Filtro por data -->
<div style="background:#fff;border-radius:6px;padding:18px 22px;margin-bottom:18px;box-shadow:1px 5px 12px rgba(0,0,0,0.25);">
    <p style="font-family:var(--font-principal);font-size:0.85rem;font-weight:600;color:var(--verde);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:14px;">
        Filtrar por período
    </p>
    <form method="GET" action="/cardapio" style="display:flex;align-items:flex-end;gap:16px;flex-wrap:wrap;">
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
        <div style="display:flex;gap:8px;align-items:flex-end;">
            <button type="submit" style="background:var(--verde);color:#fff;border:none;border-radius:4px;padding:8px 20px;font-size:0.88rem;cursor:pointer;font-family:var(--font-cards);">Filtrar</button>
            <?php if (!empty($dataInicio) || !empty($dataFim)): ?>
                <a href="/cardapio" style="background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;border-radius:4px;padding:8px 14px;font-size:0.88rem;text-decoration:none;font-family:var(--font-cards);">Limpar</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Tabela -->
<?php if (empty($cardapios)): ?>
    <p style="padding:4px 0;color:rgba(255,255,255,0.85);font-size:0.9rem;text-shadow:0 1px 3px rgba(0,0,0,0.4);">
        Nenhum cardápio encontrado<?= (!empty($dataInicio) || !empty($dataFim)) ? ' para o período selecionado' : '' ?>.
    </p>
<?php else: ?>
    <table class="tabela">
        <thead>
            <tr>
                <th>Data</th>
                <th>Dia</th>
                <th>Refeição</th>
                <th>Descrição</th>
                <th>Atualizado em</th>
                <?php if ($mostraAcoes): ?>
                    <th style="width:160px;">Ações</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($cardapios as $c): ?>
                <?php
                $ts       = strtotime($c['data']);
                $diaLabel = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'][(int) date('w', $ts)];
                $ehHoje   = $c['data'] === date('Y-m-d');
                ?>
                <tr<?= $ehHoje ? ' style="background:rgba(209,104,34,0.07);"' : '' ?>>
                    <td><?= htmlspecialchars(date('d/m/Y', $ts)) ?></td>
                    <td><?= $diaLabel ?></td>
                    <td>
                        <span class="badge <?= $c['tipo'] === 'almoco' ? 'badge--agendado' : 'badge--concluido' ?>">
                            <?= $c['tipo'] === 'almoco' ? 'Almoço' : 'Jantar' ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($c['descricao'] ?? '—') ?></td>
                    <td style="white-space:nowrap;color:#888;font-size:0.82rem;">
                        <?php
                        if ($c['atualizado_em']) {
                            $dt = new DateTime($c['atualizado_em'], new DateTimeZone('UTC'));
                            $dt->setTimezone(new DateTimeZone('America/Sao_Paulo'));
                            echo htmlspecialchars($dt->format('d/m/Y H:i'));
                        } else {
                            echo '—';
                        }
                        ?>
                    </td>
                    <?php if ($mostraAcoes): ?>
                        <?php $editavel = $c['data'] >= $hojeStr && $c['data'] <= $domingoStr; ?>
                        <td>
                            <div style="display:flex;gap:6px;align-items:center;">
                                <?php if ($isAdminDev): ?>
                                    <a href="/cardapio/<?= (int) $c['id'] ?>/editar"
                                        class="btn btn-outline" style="font-size:0.78rem;padding:4px 10px;">Editar</a>
                                    <form method="POST" action="/cardapio/<?= (int) $c['id'] ?>/excluir"
                                        onsubmit="return confirm('Excluir este cardápio?')">
                                        <button type="submit" class="btn"
                                            style="background:#fee2e2;color:#991b1b;font-size:0.78rem;padding:4px 10px;border:1px solid #fca5a5;">Excluir</button>
                                    </form>
                                <?php elseif ($isCopa && $editavel): ?>
                                    <a href="/cardapio/<?= htmlspecialchars($c['data']) ?>/<?= htmlspecialchars($c['tipo']) ?>/editar?from=cardapio"
                                        class="btn btn-outline" style="font-size:0.78rem;padding:4px 10px;">Editar</a>
                                <?php elseif ($isCopa): ?>
                                    <span style="font-size:0.75rem;color:#bbb;">—</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>