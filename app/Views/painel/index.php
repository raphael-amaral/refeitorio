<?php
$domingo = array_values(array_slice($semana, -1))[0]['data'];
?>

<div class="painel-dashboard">
    <?php foreach (['almoco' => 'Almoço', 'jantar' => 'Jantar'] as $tipo => $label): ?>
        <section class="refeicao-section">
            <h2 class="refeicao-title"><?= $label ?></h2>
            <div class="cards-grid">
                <?php foreach ($semana as $chave => $dia): ?>
                    <?php
                    $cardapio    = $dia[$tipo];
                    $reg         = $registrosSemana[$cardapio['id'] ?? ''] ?? null;
                    $registroId  = $reg['id'] ?? null;
                    $statusReg   = $reg['status'] ?? null;
                    $agendado    = $statusReg === 'agendado';
                    $podeAgendar = $dia['podeAgendar'];
                    $classes     = 'card';
                    if ($chave === $hoje) $classes .= ' card--hoje';
                    if ($agendado)        $classes .= ' card--agendado';
                    if (!$cardapio)       $classes .= ' card--vazio';
                    ?>
                    <div class="<?= $classes ?>">
                        <div class="card__header">
                            <strong><?= $dia['label'] ?></strong>
                            <span class="card__data"><?= $dia['data']->format('d/m') ?></span>
                        </div>
                        <div class="card__body">
                            <?php if ($cardapio): ?>
                                <p><?= htmlspecialchars($cardapio['descricao'] ?? '') ?></p>
                            <?php else: ?>
                                <p class="text-muted">Sem cardápio</p>
                            <?php endif; ?>
                        </div>
                        <div class="card__footer">
                            <?php if ($tipo === 'jantar'): ?>
                                <?php /* Jantar: apenas visualização */ ?>
                                <?php if ($usuario['tipo'] === 'copa'): ?>
                                    <a href="/cardapio/<?= $chave ?>/<?= $tipo ?>/editar" class="btn-editar">Editar</a>
                                <?php endif; ?>
                            <?php elseif ($agendado): ?>
                                <?php if ($podeAgendar): ?>
                                    <form method="POST" action="/registro/<?= (int) $registroId ?>/cancelar">
                                        <button class="btn btn-cancelar">Cancelar</button>
                                    </form>
                                <?php else: ?>
                                    <span class="badge badge--agendado">Agendado</span>
                                <?php endif; ?>
                                <?php if ($usuario['tipo'] === 'copa'): ?>
                                    <a href="/cardapio/<?= $chave ?>/<?= $tipo ?>/editar" class="btn-editar">Editar</a>
                                <?php endif; ?>
                            <?php elseif ($statusReg === 'cancelado'): ?>
                                <?php if ($cardapio && $podeAgendar): ?>
                                    <form method="POST" action="/registro">
                                        <input type="hidden" name="id_cardapio" value="<?= (int) $cardapio['id'] ?>">
                                        <button class="btn btn-agendar">Agendar</button>
                                    </form>
                                <?php else: ?>
                                    <span class="badge badge--cancelado">Cancelado</span>
                                <?php endif; ?>
                                <?php if ($usuario['tipo'] === 'copa'): ?>
                                    <a href="/cardapio/<?= $chave ?>/<?= $tipo ?>/editar" class="btn-editar">Editar</a>
                                <?php endif; ?>
                            <?php elseif ($statusReg === 'concluido'): ?>
                                <span class="badge badge--concluido">Concluído</span>
                                <?php if ($usuario['tipo'] === 'copa'): ?>
                                    <a href="/cardapio/<?= $chave ?>/<?= $tipo ?>/editar" class="btn-editar">Editar</a>
                                <?php endif; ?>
                            <?php elseif ($cardapio && $podeAgendar): ?>
                                <form method="POST" action="/registro">
                                    <input type="hidden" name="id_cardapio" value="<?= (int) $cardapio['id'] ?>">
                                    <button class="btn btn-agendar">Agendar</button>
                                </form>
                                <?php if ($usuario['tipo'] === 'copa'): ?>
                                    <a href="/cardapio/<?= $chave ?>/<?= $tipo ?>/editar" class="btn-editar">Editar</a>
                                <?php endif; ?>
                            <?php elseif (!$cardapio): ?>
                                <?php if ($usuario['tipo'] === 'copa'): ?>
                                    <a href="/cardapio/<?= $chave ?>/<?= $tipo ?>/editar" class="btn-editar">Editar</a>
                                <?php else: ?>
                                    <span class="badge badge--vazio">Indisponível</span>
                                <?php endif; ?>
                            <?php elseif ($usuario['tipo'] === 'copa'): ?>
                                <a href="/cardapio/<?= $chave ?>/<?= $tipo ?>/editar" class="btn-editar">Editar</a>
                            <?php else: ?>
                                <span class="badge badge--vazio">Indisponível</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
</div>