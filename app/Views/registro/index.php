<div class="page-header">
    <h1>Meus Agendamentos</h1>
    <a href="/painel" class="btn btn-outline">← Voltar ao Painel</a>
</div>

<?php if (empty($registros)): ?>
    <p class="text-muted">Nenhum agendamento encontrado.</p>
<?php else: ?>
    <table class="tabela">
        <thead>
            <tr>
                <th>Data</th>
                <th>Refeição</th>
                <th>Descrição</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($registros as $r): ?>
                <tr>
                    <td><?= htmlspecialchars(date('d/m/Y', strtotime($r['data']))) ?></td>
                    <td><?= htmlspecialchars(ucfirst($r['tipo_refeicao'])) ?></td>
                    <td><?= htmlspecialchars($r['descricao'] ?? '—') ?></td>
                    <td>
                        <?php
                        $statusMap = ['agendado' => 'badge--agendado', 'cancelado' => 'badge--cancelado', 'concluido' => 'badge--concluido'];
                        $cls = $statusMap[$r['status']] ?? '';
                        ?>
                        <span class="badge <?= $cls ?>"><?= htmlspecialchars(ucfirst($r['status'])) ?></span>
                    </td>
                    <td>
                        <?php if ($r['status'] === 'agendado'): ?>
                            <form method="POST" action="/registro/<?= (int) $r['id'] ?>/cancelar">
                                <button class="btn btn-cancelar">Cancelar</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>