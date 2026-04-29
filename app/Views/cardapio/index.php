<?php
$isAdminDev  = $_SESSION['usuario']['tipo'] === 'dev'; // criar novo cardápio — só dev
$isSND      = in_array($_SESSION['usuario']['tipo'], ['snd', 'dev'], true);

// Intervalo editável: hoje até próximo domingo
$hojeStr   = date('Y-m-d');
$diaNum    = (int) date('N'); // 1=Seg…7=Dom
$domingoStr = date('Y-m-d', strtotime('+' . (7 - $diaNum) . ' days'));
?>

<style>
    .filtro-cardapio {
        background: #fff;
        border-radius: 6px;
        padding: 16px 20px;
        margin-bottom: 18px;
        box-shadow: 1px 5px 12px rgba(0,0,0,0.25);
    }

    .filtro-cardapio__titulo {
        font-family: var(--font-principal);
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--verde);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 14px;
    }

    .filtro-cardapio form {
        display: flex;
        align-items: flex-end;
        gap: 16px;
        flex-wrap: wrap;
    }

    .filtro-cardapio .filtro-campo {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .filtro-cardapio label {
        font-size: 0.78rem;
        font-weight: 600;
        color: #555;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .filtro-cardapio input[type="date"] {
        padding: 7px 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
        font-size: 0.9rem;
        background: #fafafa;
        color: #333;
        min-width: 150px;
    }

    .filtro-cardapio .filtro-acoes {
        display: flex;
        gap: 8px;
        align-items: flex-end;
    }

    @media (max-width: 768px) {
        .filtro-cardapio {
            padding: 12px 14px;
            margin-bottom: 12px;
        }

        .filtro-cardapio form {
            flex-direction: column;
            align-items: stretch;
            gap: 10px;
        }

        .filtro-cardapio input[type="date"] {
            min-width: 0;
            width: 100%;
            box-sizing: border-box;
        }

        .filtro-cardapio .filtro-acoes {
            width: 100%;
        }

        .filtro-cardapio .filtro-acoes button,
        .filtro-cardapio .filtro-acoes a {
            flex: 1;
            text-align: center;
        }
    }
</style>

<!-- Filtro por data -->
<div class="filtro-cardapio">
    <p class="filtro-cardapio__titulo">
        Filtrar por período
        <?php if (empty($filtroExplicito)): ?>
            <span style="font-weight:400;text-transform:none;letter-spacing:0;color:#888;font-size:0.78rem;margin-left:6px;">— exibindo o mês atual</span>
        <?php endif; ?>
    </p>
    <form method="GET" action="/cardapio">
        <div class="filtro-campo">
            <label for="data_inicio">De</label>
            <input type="date" id="data_inicio" name="data_inicio" value="<?= htmlspecialchars($dataInicio ?? '') ?>">
        </div>
        <div class="filtro-campo">
            <label for="data_fim">Até</label>
            <input type="date" id="data_fim" name="data_fim" value="<?= htmlspecialchars($dataFim ?? '') ?>">
        </div>
        <div class="filtro-acoes">
            <button type="submit" style="background:var(--verde);color:#fff;border:none;border-radius:4px;padding:8px 20px;font-size:0.88rem;cursor:pointer;font-family:var(--font-cards);">Filtrar</button>
            <?php if (!empty($filtroExplicito)): ?>
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
    <table class="tabela" id="tabela-cardapios">
        <thead>
            <tr>
                <th>Data</th>
                <th>Dia</th>
                <th>Refeição</th>
                <th>Descrição</th>
                <th>Atualizado em</th>
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
                    </tr>
                <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($totalPaginas > 1): ?>
        <?php
        $qBase = array_filter([
            'data_inicio' => $dataInicio,
            'data_fim'    => $dataFim,
        ], fn($v) => $v !== '');
        function urlPaginaCardapio(int $p, array $base): string {
            return '/cardapio?' . http_build_query($base + ['pagina' => $p]);
        }
        ?>
        <nav class="paginacao" aria-label="Paginação de cardápios">
            <?php if ($pagina > 1): ?>
                <a href="<?= htmlspecialchars(urlPaginaCardapio($pagina - 1, $qBase)) ?>" class="paginacao__btn">← Anterior</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                <?php if ($i === $pagina): ?>
                    <span class="paginacao__atual"><?= $i ?></span>
                <?php elseif ($i === 1 || $i === $totalPaginas || ($i >= $pagina - 2 && $i <= $pagina + 2)): ?>
                    <a href="<?= htmlspecialchars(urlPaginaCardapio($i, $qBase)) ?>" class="paginacao__btn"><?= $i ?></a>
                <?php elseif ($i === $pagina - 3 || $i === $pagina + 3): ?>
                    <span class="paginacao__reticencias">…</span>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($pagina < $totalPaginas): ?>
                <a href="<?= htmlspecialchars(urlPaginaCardapio($pagina + 1, $qBase)) ?>" class="paginacao__btn">Próximo →</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>