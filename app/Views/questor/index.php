<div class="card" style="max-width:480px;margin:40px auto;">
    <div class="card__header">
        <strong>Exportação Questor</strong>
    </div>
    <div class="card__body">
        <form method="GET" action="/questor/exportar" class="form-usuario">
            <div class="form-group">
                <label for="data_inicio">Data início</label>
                <input type="date" id="data_inicio" name="data_inicio"
                    value="<?= date('Y-m-01') ?>" required>
            </div>
            <div class="form-group">
                <label for="data_fim">Data fim</label>
                <input type="date" id="data_fim" name="data_fim"
                    value="<?= date('Y-m-t') ?>" required>
            </div>
            <div class="form-group">
                <label for="valor_refeicao">Valor por refeição (R$)</label>
                <input type="number" id="valor_refeicao" name="valor_refeicao"
                    value="15" min="0.01" step="0.01" required
                    style="max-width:120px;">
            </div>
            <p style="font-size:.82rem;color:#888;font-family:var(--font-cards);margin-bottom:14px;">
                Exporta apenas almoços <strong>concluídos</strong> no período. Uma linha por funcionário com o total a descontar.
            </p>
            <div class="form-actions" style="justify-content:flex-end;">
                <button type="submit" class="btn-salvar">Baixar CSV</button>
            </div>
        </form>
    </div>
</div>
