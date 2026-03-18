<?php
// $cardapio é null na criação, array associativo na edição
$editando = $cardapio !== null;
$action   = $editando ? '/cardapio/' . (int) $cardapio['id'] : '/cardapio';
?>


<form method="POST" action="<?= $action ?>">

    <div class="form-group">
        <label for="data">Data</label>
        <input type="date" id="data" name="data" required
            value="<?= htmlspecialchars($cardapio['data'] ?? '') ?>">
    </div>

    <div class="form-group">
        <label for="tipo">Tipo de Refeição</label>
        <select id="tipo" name="tipo" required>
            <option value="almoco" <?= ($cardapio['tipo'] ?? '') === 'almoco'  ? 'selected' : '' ?>>Almoço</option>
            <option value="jantar" <?= ($cardapio['tipo'] ?? '') === 'jantar'  ? 'selected' : '' ?>>Jantar</option>
        </select>
    </div>

    <div class="form-group">
        <label for="descricao">Descrição</label>
        <textarea id="descricao" name="descricao" rows="4"><?= htmlspecialchars($cardapio['descricao'] ?? '') ?></textarea>
    </div>

    <button type="submit"><?= $editando ? 'Salvar Alterações' : 'Criar Cardápio' ?></button>
    <a href="/cardapio">Cancelar</a>

</form>