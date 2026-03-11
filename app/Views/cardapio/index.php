<div class="page-header">
    <h1>Cardápios</h1>
    <?php if (in_array($_SESSION['usuario']['tipo'], ['admin', 'dev'], true)): ?>
        <a href="/cardapio/novo" class="btn">+ Novo Cardápio</a>
    <?php endif; ?>
</div>

<?php if (empty($cardapios)): ?>
    <p>Nenhum cardápio cadastrado.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Data</th>
                <th>Refeição</th>
                <th>Descrição</th>
                <?php if (in_array($_SESSION['usuario']['tipo'], ['admin', 'dev'], true)): ?>
                    <th>Ações</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($cardapios as $c): ?>
                <tr>
                    <td><?= htmlspecialchars(date('d/m/Y', strtotime($c['data']))) ?></td>
                    <td><?= htmlspecialchars(ucfirst($c['tipo'])) ?></td>
                    <td><?= htmlspecialchars($c['descricao'] ?? '—') ?></td>
                    <?php if (in_array($_SESSION['usuario']['tipo'], ['admin', 'dev'], true)): ?>
                        <td>
                            <a href="/cardapio/<?= (int) $c['id'] ?>/editar">Editar</a>
                            <form method="POST" action="/cardapio/<?= (int) $c['id'] ?>/excluir"
                                onsubmit="return confirm('Excluir este cardápio?')">
                                <button type="submit">Excluir</button>
                            </form>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>