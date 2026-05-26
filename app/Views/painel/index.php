<?php
$domingo = array_values(array_slice($semana, -1))[0]['data'];

// Helper para gerar o botão Editar com data-* attributes (seguro contra qualquer descrição)
function btnEditar(string $chave, string $tipo, string $label, string $dataFormatada, ?string $descricao, string $diaNome = ''): string
{
    $prefixo = $diaNome !== '' ? $diaNome . '(' . $label . ')' : $label;
    $titulo = htmlspecialchars($prefixo . ' — ' . $dataFormatada, ENT_QUOTES);
    $desc   = htmlspecialchars($descricao ?? '', ENT_QUOTES);
    $action = htmlspecialchars('/cardapio/' . $chave . '/' . $tipo, ENT_QUOTES);
    return '<button class="btn-editar js-editar" '
        . 'data-titulo="' . $titulo . '" '
        . 'data-action="' . $action . '" '
        . 'data-descricao="' . $desc . '">'
        . 'Editar</button>';
}
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
                    $editavel    = $usuario['tipo'] === 'snd'
                        && $chave >= $segunda->format('Y-m-d')
                        && $chave <= $domingo->format('Y-m-d');
                    $classes     = 'card';
                    if ($chave === $hoje) $classes .= ' card--hoje';
                    if ($agendado)        $classes .= ' card--agendado';
                    if (!$cardapio)       $classes .= ' card--vazio';
                    $dataFmt     = $dia['data']->format('d/m/Y');
                    $descAtual   = $cardapio['descricao'] ?? null;
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
                                <?php if ($editavel): ?>
                                    <?= btnEditar($chave, $tipo, $label, $dataFmt, $descAtual, $dia['label']) ?>
                                <?php else: ?>
                                    <span class="badge badge--vazio">Indisponível</span>
                                <?php endif; ?>
                            <?php elseif ($agendado): ?>
                                <?php if ($podeAgendar): ?>
                                    <form method="POST" action="/registro/<?= (int) $registroId ?>/cancelar">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-cancelar">Cancelar</button>
                                    </form>
                                <?php else: ?>
                                    <span class="badge badge--agendado">Agendado</span>
                                <?php endif; ?>
                                <?php if ($editavel): ?>
                                    <?= btnEditar($chave, $tipo, $label, $dataFmt, $descAtual, $dia['label']) ?>
                                <?php endif; ?>
                            <?php elseif ($statusReg === 'cancelado'): ?>
                                <?php if ($cardapio && $podeAgendar): ?>
                                    <form method="POST" action="/registro">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id_cardapio" value="<?= (int) $cardapio['id'] ?>">
                                        <button class="btn btn-agendar">Agendar</button>
                                    </form>
                                <?php else: ?>
                                    <span class="badge badge--cancelado">Cancelado</span>
                                <?php endif; ?>
                                <?php if ($editavel): ?>
                                    <?= btnEditar($chave, $tipo, $label, $dataFmt, $descAtual, $dia['label']) ?>
                                <?php endif; ?>
                            <?php elseif ($statusReg === 'concluido'): ?>
                                <span class="badge badge--concluido">Concluído</span>
                                <?php if ($editavel): ?>
                                    <?= btnEditar($chave, $tipo, $label, $dataFmt, $descAtual, $dia['label']) ?>
                                <?php endif; ?>
                            <?php elseif ($cardapio && $podeAgendar): ?>
                                <form method="POST" action="/registro">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id_cardapio" value="<?= (int) $cardapio['id'] ?>">
                                    <button class="btn btn-agendar">Agendar</button>
                                </form>
                                <?php if ($editavel): ?>
                                    <?= btnEditar($chave, $tipo, $label, $dataFmt, $descAtual, $dia['label']) ?>
                                <?php endif; ?>
                            <?php elseif (!$cardapio): ?>
                                <?php if ($editavel): ?>
                                    <?= btnEditar($chave, $tipo, $label, $dataFmt, $descAtual, $dia['label']) ?>
                                <?php else: ?>
                                    <span class="badge badge--vazio">Indisponível</span>
                                <?php endif; ?>
                            <?php elseif ($editavel): ?>
                                <?= btnEditar($chave, $tipo, $label, $dataFmt, $descAtual, $dia['label']) ?>
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

<!-- Modal de edição de cardápio -->
<div id="modal-editar" class="modal-overlay" style="display:none;">
    <div class="modal-box">
        <div class="modal-header">
            <span id="modal-titulo"></span>
            <button type="button" class="modal-fechar" id="modal-fechar">&times;</button>
        </div>
        <form id="modal-form" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="redirect" value="/painel">
            <div id="modal-erro" style="display:none;background:#fee2e2;color:#991b1b;border:1px solid #fecaca;border-radius:4px;padding:8px 12px;margin-bottom:12px;font-size:0.88rem;"></div>
            <div class="form-group" style="margin-bottom:16px;">
                <label for="modal-descricao" style="display:block;margin-bottom:6px;font-size:0.9rem;color:#555;">Descrição do cardápio</label>
                <textarea
                    id="modal-descricao"
                    name="descricao"
                    rows="4"
                    required
                    placeholder="Ex: Arroz, feijão, frango grelhado, salada..."
                    style="width:100%;padding:10px 12px;border:1.5px solid #ccc;border-radius:4px;font-size:0.95rem;font-family:inherit;resize:vertical;box-sizing:border-box;"></textarea>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:10px;">
                <button type="button" class="btn btn-outline" id="modal-cancelar">Cancelar</button>
                <button type="submit" class="btn btn-salvar">Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
    (function() {
        var overlay = document.getElementById('modal-editar');
        var form = document.getElementById('modal-form');
        var titulo = document.getElementById('modal-titulo');
        var textarea = document.getElementById('modal-descricao');
        var erro = document.getElementById('modal-erro');

        function abrir(btn) {
            titulo.textContent = btn.dataset.titulo;
            form.action = btn.dataset.action;
            textarea.value = btn.dataset.descricao;
            erro.style.display = 'none';
            erro.textContent = '';
            overlay.style.display = 'flex';
            setTimeout(function() {
                textarea.focus();
            }, 50);
        }

        function fechar() {
            overlay.style.display = 'none';
        }

        // Delegação: qualquer botão .js-editar abre o modal
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.js-editar');
            if (btn) {
                e.preventDefault();
                abrir(btn);
            }
        });

        // Validação antes de submeter
        form.addEventListener('submit', function(e) {
            if (textarea.value.trim() === '') {
                e.preventDefault();
                erro.textContent = 'A descrição não pode ser vazia.';
                erro.style.display = 'block';
                textarea.focus();
            }
        });

        document.getElementById('modal-fechar').addEventListener('click', fechar);
        document.getElementById('modal-cancelar').addEventListener('click', fechar);

        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) fechar();
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') fechar();
        });
    }());
</script>