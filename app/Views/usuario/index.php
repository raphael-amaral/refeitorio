<?php

use App\Models\Usuario; ?>
<div class="usuarios-page">

    <div class="usuarios-layout">

        <!-- Tabela de usuários -->
        <section class="usuarios-lista card">
            <div class="card__header" style="gap:12px;flex-wrap:wrap;">
                <strong>Usuários cadastrados</strong>
                <form method="GET" action="/usuario" style="margin-left:auto;display:flex;gap:6px;align-items:center;">
                    <input type="search" name="busca" value="<?= htmlspecialchars($busca ?? '') ?>"
                        placeholder="Buscar por nome ou matrícula…"
                        style="padding:4px 10px;border:1px solid #ccc;border-radius:4px;font-size:0.85rem;font-family:var(--font-cards);width:220px;">
                    <button type="submit" style="padding:4px 12px;border:1px solid #ccc;border-radius:4px;background:#fff;font-size:0.85rem;font-family:var(--font-cards);cursor:pointer;">Buscar</button>
                    <?php if (!empty($busca)): ?>
                        <a href="/usuario" style="padding:4px 10px;border:1px solid #fca5a5;border-radius:4px;background:#fee2e2;color:#991b1b;font-size:0.85rem;font-family:var(--font-cards);text-decoration:none;">Limpar</a>
                    <?php endif; ?>
                </form>
            </div>
            <div class="card__body" style="overflow-y: auto;">
                <table class="tabela-usuarios" id="tabela-usuarios">
                    <thead>
                        <tr>
                            <th>Matrícula</th>
                            <th>Nome</th>
                            <th>Tipo</th>
                            <th>Status</th>
                            <th style="width:140px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $u): ?>
                            <tr class="<?= $u['ativo'] ? '' : 'row--inativo' ?>">
                                <td><?= htmlspecialchars($u['matricula']) ?></td>
                                <td><?= htmlspecialchars($u['nome']) ?></td>
                                <td><span class="badge badge--tipo"><?= htmlspecialchars($u['tipo']) ?></span></td>
                                <td>
                                    <?php if ($u['ativo']): ?>
                                        <span class="status-ativo">Ativo</span>
                                    <?php else: ?>
                                        <span class="status-inativo">Inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="col-acao">
                                    <?php
                                    $proprio      = $u['matricula'] === ($_SESSION['usuario']['matricula'] ?? '');
                                    $temRegistros = Usuario::temRegistros($u['matricula']);
                                    ?>
                                    <div class="acoes-inline">
                                        <button class="btn-acao btn-editar-u"
                                            data-matricula="<?= htmlspecialchars($u['matricula']) ?>"
                                            data-nome="<?= htmlspecialchars($u['nome']) ?>"
                                            data-tipo="<?= htmlspecialchars($u['tipo']) ?>">
                                            Editar
                                        </button>
                                        <?php if ($u['ativo'] && !$proprio): ?>
                                            <form method="POST" action="/usuario/<?= urlencode($u['matricula']) ?>/inativar">
                                                <?= csrf_field() ?>
                                                <button class="btn-acao btn-inativar">Inativar</button>
                                            </form>
                                        <?php elseif (!$u['ativo']): ?>
                                            <form method="POST" action="/usuario/<?= urlencode($u['matricula']) ?>/ativar">
                                                <?= csrf_field() ?>
                                                <button class="btn-acao btn-ativar">Reativar</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if (!$proprio && !$temRegistros): ?>
                                            <form method="POST" action="/usuario/<?= urlencode($u['matricula']) ?>/excluir"
                                                onsubmit="return confirm('Excluir permanentemente <?= htmlspecialchars($u['nome'], ENT_QUOTES) ?>?')">
                                                <?= csrf_field() ?>
                                                <button class="btn-acao btn-excluir">Excluir</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if (($totalPaginas ?? 1) > 1): ?>
                    <?php
                    function urlPaginaUsuario(int $p, string $busca): string {
                        $q = array_filter(['busca' => $busca, 'pagina' => $p], fn($v) => $v !== '' && $v !== 0);
                        return '/usuario' . ($q ? '?' . http_build_query($q) : '');
                    }
                    ?>
                    <nav class="paginacao" aria-label="Paginação de usuários" style="margin-bottom:8px;">
                        <?php if ($pagina > 1): ?>
                            <a href="<?= htmlspecialchars(urlPaginaUsuario($pagina - 1, $busca ?? '')) ?>" class="paginacao__btn">← Anterior</a>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                            <?php if ($i === $pagina): ?>
                                <span class="paginacao__atual"><?= $i ?></span>
                            <?php elseif ($i === 1 || $i === $totalPaginas || ($i >= $pagina - 2 && $i <= $pagina + 2)): ?>
                                <a href="<?= htmlspecialchars(urlPaginaUsuario($i, $busca ?? '')) ?>" class="paginacao__btn"><?= $i ?></a>
                            <?php elseif ($i === $pagina - 3 || $i === $pagina + 3): ?>
                                <span class="paginacao__reticencias">…</span>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($pagina < $totalPaginas): ?>
                            <a href="<?= htmlspecialchars(urlPaginaUsuario($pagina + 1, $busca ?? '')) ?>" class="paginacao__btn">Próximo →</a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            </div>
        </section>

        <!-- Formulário novo / editar -->
        <section class="usuarios-form card">
            <div class="card__header">
                <strong id="form-titulo">Novo usuário</strong>
            </div>
            <div class="card__body">
                <form method="POST" id="form-usuario" action="/usuario" class="form-usuario">
                    <?= csrf_field() ?>
                    <div class="form-group" id="campo-matricula">
                        <label for="matricula">Matrícula</label>
                        <input type="text" id="matricula" name="matricula" maxlength="12"
                            placeholder="Ex: 00123">
                    </div>
                    <div class="form-group">
                        <label for="nome">Nome</label>
                        <input type="text" id="nome" name="nome" required maxlength="100"
                            placeholder="Nome completo">
                    </div>
                    <div class="form-group">
                        <label for="senha" id="label-senha">Senha</label>
                        <input type="password" id="senha" name="senha"
                            placeholder="Senha inicial">
                        <small id="hint-senha" style="display:none;color:#888;font-size:.78rem">
                            Deixe em branco para não alterar
                        </small>
                    </div>
                    <div class="form-group">
                        <label for="tipo">Perfil</label>
                        <select id="tipo" name="tipo">
                            <option value="comum">Comum</option>
                            <option value="snd">SND</option>
                            <option value="dev">Dev</option>
                        </select>
                    </div>
                    <div class="form-actions" style="gap:8px;display:flex;justify-content:flex-end">
                        <button type="button" id="btn-cancelar-edicao" class="btn-acao btn-inativar"
                            style="display:none;padding:6px 14px">Cancelar</button>
                        <button type="submit" id="btn-submit" class="btn-salvar">Criar usuário</button>
                    </div>
                </form>
            </div>
        </section>

    </div>
</div>

<script>
    (function() {
        // Lógica do formulário (mantida como a sua original)
        var form = document.getElementById('form-usuario');
        var titulo = document.getElementById('form-titulo');
        var campoMat = document.getElementById('campo-matricula');
        var inputMat = document.getElementById('matricula');
        var inputNome = document.getElementById('nome');
        var inputSenha = document.getElementById('senha');
        var hintSenha = document.getElementById('hint-senha');
        var selectTipo = document.getElementById('tipo');
        var btnSubmit = document.getElementById('btn-submit');
        var btnCancel = document.getElementById('btn-cancelar-edicao');

        document.querySelectorAll('.btn-editar-u').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var mat = btn.dataset.matricula;
                var nome = btn.dataset.nome;
                var tipo = btn.dataset.tipo;

                form.action = '/usuario/' + encodeURIComponent(mat) + '/editar';
                titulo.textContent = 'Editar usuário';
                campoMat.style.display = 'none';
                inputMat.removeAttribute('required');
                inputSenha.removeAttribute('required');
                hintSenha.style.display = 'block';

                inputNome.value = nome;
                selectTipo.value = tipo;
                inputSenha.value = '';

                btnSubmit.textContent = 'Salvar alterações';
                btnCancel.style.display = 'inline-block';
                inputNome.focus();
            });
        });

        btnCancel.addEventListener('click', function() {
            form.action = '/usuario';
            titulo.textContent = 'Novo usuário';
            campoMat.style.display = '';
            inputMat.setAttribute('required', '');
            inputSenha.setAttribute('required', '');
            hintSenha.style.display = 'none';
            form.reset();
            btnSubmit.textContent = 'Criar usuário';
            btnCancel.style.display = 'none';
        });

        inputMat.setAttribute('required', '');
        inputSenha.setAttribute('required', '');

        // Submete o form de busca automaticamente após o usuário parar de digitar
        var inputBusca = document.querySelector('input[name="busca"]');
        if (inputBusca) {
            var debounceTimer;
            inputBusca.addEventListener('input', function () {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(function () {
                    inputBusca.closest('form').submit();
                }, 800);
            });
        }
    }());
</script>

</div>
</div>