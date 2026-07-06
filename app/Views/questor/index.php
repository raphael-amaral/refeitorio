<div class="card" style="max-width:480px;margin:40px auto;zoom:1.1;">
    <div class="card__header">
        <strong>Exportação Questor</strong>
    </div>
    <div class="card__body">
        <?php if ($ultimaExportacao): ?>
            <p style="font-size:.82rem;color:#555;font-family:var(--font-cards);margin-bottom:14px;background:#f5f5f5;border-radius:4px;padding:8px 12px;">
                Última exportação: <strong><?= date('d/m/Y', strtotime($ultimaExportacao['data_inicio'])) ?></strong>
                a <strong><?= date('d/m/Y', strtotime($ultimaExportacao['data_fim'])) ?></strong>.
                A próxima deve começar em <strong><?= date('d/m/Y', strtotime($dataInicioPadrao)) ?></strong>.
            </p>
        <?php endif; ?>
        <form method="GET" action="/questor/exportar" class="form-usuario">
            <div class="form-group">
                <label for="data_inicio">Data início</label>
                <input type="date" id="data_inicio" name="data_inicio"
                    value="<?= htmlspecialchars($dataInicioPadrao) ?>" required>
            </div>
            <div class="form-group">
                <label for="data_fim">Data fim</label>
                <input type="date" id="data_fim" name="data_fim"
                    value="<?= date('Y-m-t') ?>" required>
            </div>
            <p style="font-size:.82rem;color:#888;font-family:var(--font-cards);margin-bottom:14px;">
                Exporta apenas almoços <strong>concluídos</strong> no período. Uma linha por funcionário com o total a descontar,
                usando o valor fixo por refeição definido pela direção (R$ <?= number_format((float) ($_ENV['VALOR_REFEICAO'] ?? 15), 2, ',', '') ?>).
            </p>
            <div class="form-actions" style="justify-content:flex-end;">
                <button type="submit" class="btn-salvar">Baixar CSV</button>
            </div>
        </form>
    </div>
</div>