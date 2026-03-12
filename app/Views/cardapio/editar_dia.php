<div class="page-header" style="margin-bottom: 24px;">
    <a href="<?= htmlspecialchars($redirect ?? '/painel') ?>" class="btn btn-outline">
        ← Voltar
    </a>
</div>

<div class="editar-dia-card">
    <h2 class="editar-dia-titulo">
        <?= $labelTipo ?> &mdash;
        <?= date('d/m/Y', strtotime($data)) ?>
    </h2>

    <form method="POST" action="/cardapio/<?= htmlspecialchars($data) ?>/<?= htmlspecialchars($tipo) ?>">
        <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect ?? '/painel') ?>">
        <div class="form-group">
            <label for="descricao">Descrição do cardápio</label>
            <textarea
                id="descricao"
                name="descricao"
                class="form-textarea"
                rows="4"
                placeholder="Ex: Arroz, feijão, frango grelhado, salada..."><?= htmlspecialchars($descricao) ?></textarea>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-salvar">Salvar</button>
        </div>
    </form>
</div>