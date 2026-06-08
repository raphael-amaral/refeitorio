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
                    function urlPaginaUsuario(int $p, string $busca): string
                    {
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
            <div class="card__header" style="gap:12px;flex-wrap:wrap;align-items:center;">
                <strong id="form-titulo">Novo usuário</strong>
                <div style="margin-left:auto;display:flex;gap:6px;">
                    <button type="button" id="btn-aba-unico" class="btn-acao btn-salvar btn-aba--ativo" style="font-size:.8rem;padding:4px 12px;">Individual</button>
                    <button type="button" id="btn-aba-lote" class="btn-acao" style="font-size:.8rem;padding:4px 12px;">Importar em lote</button>
                </div>
            </div>
            <div class="card__body">

                <!-- Formulário individual -->
                <div id="painel-unico">
                    <form method="POST" id="form-usuario" action="/usuario" class="form-usuario">
                        <?= csrf_field() ?>
                        <div class="form-group" id="campo-matricula">
                            <label for="matricula">Matrícula</label>
                            <input type="text" id="matricula" name="matricula" maxlength="12"
                                placeholder="Ex: 1234">
                        </div>
                        <div class="form-group">
                            <label for="nome">Nome</label>
                            <input type="text" id="nome" name="nome" required maxlength="100"
                                placeholder="Nome completo">
                        </div>
                        <div class="form-group">
                            <label for="senha" id="label-senha">Senha</label>
                            <input type="password" id="senha" name="senha" minlength="6"
                                placeholder="Deixe vazio para usar senha padrão">
                            <small id="hint-senha-padrao" style="color:#888;font-size:.78rem">
                                Se vazio, a senha será <strong>stcasa</strong> + matrícula
                            </small>
                            <small id="hint-senha-edicao" style="display:none;color:#888;font-size:.78rem">
                                Deixe em branco para não alterar
                            </small>
                        </div>
                        <div class="form-group" id="campo-confirmar-senha" style="display:none;">
                            <label for="confirmar_senha">Confirmar senha</label>
                            <input type="password" id="confirmar_senha" name="confirmar_senha" minlength="6"
                                placeholder="Repita a senha">
                        </div>
                        <div class="form-group">
                            <label for="tipo">Perfil</label>
                            <select id="tipo" name="tipo">
                                <option value="comum">Comum</option>
                                <option value="snd">SND</option>
                                <option value="dp">Dep. Pessoal</option>
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

                <!-- Formulário importação em lote -->
                <div id="painel-lote" style="display:none;">

                    <?php
                    $errosImport = array_merge(
                        $_SESSION['import_invalidas'] ?? [],
                        $_SESSION['import_duplicadas'] ?? []
                    );
                    unset($_SESSION['import_invalidas'], $_SESSION['import_duplicadas']);
                    ?>
                    <?php if (!empty($errosImport)): ?>
                        <div style="background:#fee2e2;border:1px solid #fecaca;border-radius:4px;padding:10px 14px;margin-bottom:14px;font-family:var(--font-cards);font-size:.83rem;color:#991b1b;">
                            <strong>Problemas encontrados — corrija e tente novamente:</strong>
                            <ul style="margin:6px 0 0 16px;line-height:1.8;">
                                <?php foreach ($errosImport as $erro): ?>
                                    <li><?= htmlspecialchars($erro) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div id="overlay-importando" style="display:none;text-align:center;padding:32px 0;">
                        <p style="font-family:var(--font-cards);font-size:.95rem;color:#555;margin-bottom:6px;">
                            Importando usuários, aguarde…
                        </p>
                        <p style="font-family:var(--font-cards);font-size:.8rem;color:#999;">
                            Isso pode levar alguns segundos dependendo da quantidade.
                        </p>
                    </div>
                    <form id="form-importar-lote" method="POST" action="/usuario/importar"
                        class="form-usuario" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <div class="form-group">
                            <label for="arquivo">Arquivo CSV</label>
                            <input type="file" id="arquivo" name="arquivo" accept=".csv,.txt"
                                style="display:block;font-family:var(--font-cards);font-size:.85rem;">
                            <small style="color:#888;font-size:.78rem;">
                                Aceita <strong>.csv</strong> com separador <strong>;</strong> ou <strong>,</strong>
                            </small>
                        </div>
                        <div class="form-group">
                            <label for="lista" id="label-lista-lote">ou cole a lista diretamente</label>
                            <textarea id="lista" name="lista" rows="7"
                                style="width:100%;font-family:monospace;font-size:.85rem;padding:8px;border:1px solid #ccc;border-radius:4px;resize:vertical;"
                                placeholder="matricula;Nome Completo&#10;1234;João da Silva&#10;5678;Maria Souza"></textarea>
                            <small style="color:#888;font-size:.78rem;">
                                Formato: <strong>matrícula;Nome Completo</strong> — senha padrão <strong>stcasa</strong> + matrícula para todos
                            </small>
                        </div>
                        <div class="form-group">
                            <label for="tipo_importacao">Perfil de todos</label>
                            <select id="tipo_importacao" name="tipo_importacao">
                                <option value="comum">Comum</option>
                                <option value="snd">SND</option>
                                <option value="dp">Dep. Pessoal</option>
                                <option value="dev">Dev</option>
                            </select>
                        </div>
                        <div class="form-actions" style="gap:8px;display:flex;justify-content:flex-end">
                            <button type="submit" id="btn-importar" class="btn-salvar">Importar usuários</button>
                        </div>
                    </form>
                </div>

            </div>
        </section>

    </div>
</div>

<script>
    (function() {
        var form = document.getElementById('form-usuario');
        var titulo = document.getElementById('form-titulo');
        var campoMat = document.getElementById('campo-matricula');
        var inputMat = document.getElementById('matricula');
        var inputNome = document.getElementById('nome');
        var inputSenha = document.getElementById('senha');
        var campoConfirmar = document.getElementById('campo-confirmar-senha');
        var inputConfirmar = document.getElementById('confirmar_senha');
        var hintSenhaPadrao = document.getElementById('hint-senha-padrao');
        var hintSenhaEdicao = document.getElementById('hint-senha-edicao');
        var selectTipo = document.getElementById('tipo');
        var btnSubmit = document.getElementById('btn-submit');
        var btnCancel = document.getElementById('btn-cancelar-edicao');

        // Exibe/oculta campo de confirmação conforme o usuário digita a senha
        var modoEdicao = false;
        inputSenha.addEventListener('input', function() {
            var preenchida = inputSenha.value !== '';
            if (!modoEdicao) {
                campoConfirmar.style.display = preenchida ? '' : 'none';
                if (preenchida) {
                    inputConfirmar.setAttribute('required', '');
                } else {
                    inputConfirmar.removeAttribute('required');
                    inputConfirmar.value = '';
                }
            } else {
                // Na edição, confirmação só aparece se digitar algo
                campoConfirmar.style.display = preenchida ? '' : 'none';
                if (preenchida) {
                    inputConfirmar.setAttribute('required', '');
                } else {
                    inputConfirmar.removeAttribute('required');
                    inputConfirmar.value = '';
                }
            }
        });

        document.querySelectorAll('.btn-editar-u').forEach(function(btn) {
            btn.addEventListener('click', function() {
                modoEdicao = true;
                var mat = btn.dataset.matricula;
                var nome = btn.dataset.nome;
                var tipo = btn.dataset.tipo;

                form.action = '/usuario/' + encodeURIComponent(mat) + '/editar';
                titulo.textContent = 'Editar usuário';
                campoMat.style.display = 'none';
                inputMat.removeAttribute('required');
                hintSenhaPadrao.style.display = 'none';
                hintSenhaEdicao.style.display = 'block';

                inputNome.value = nome;
                selectTipo.value = tipo;
                inputSenha.value = '';
                campoConfirmar.style.display = 'none';
                inputConfirmar.value = '';
                inputConfirmar.removeAttribute('required');

                btnSubmit.textContent = 'Salvar alterações';
                btnCancel.style.display = 'inline-block';
                inputNome.focus();
            });
        });

        btnCancel.addEventListener('click', function() {
            modoEdicao = false;
            form.action = '/usuario';
            titulo.textContent = 'Novo usuário';
            campoMat.style.display = '';
            inputMat.setAttribute('required', '');
            hintSenhaPadrao.style.display = 'block';
            hintSenhaEdicao.style.display = 'none';
            campoConfirmar.style.display = 'none';
            inputConfirmar.removeAttribute('required');
            form.reset();
            btnSubmit.textContent = 'Criar usuário';
            btnCancel.style.display = 'none';
        });

        inputMat.setAttribute('required', '');

        // Alternância entre abas Individual / Lote
        var btnAbaUnico = document.getElementById('btn-aba-unico');
        var btnAbaLote = document.getElementById('btn-aba-lote');
        var painelUnico = document.getElementById('painel-unico');
        var painelLote = document.getElementById('painel-lote');

        btnAbaUnico.addEventListener('click', function() {
            painelUnico.style.display = '';
            painelLote.style.display = 'none';
            btnAbaUnico.classList.add('btn-aba--ativo');
            btnAbaLote.classList.remove('btn-aba--ativo');
            titulo.textContent = modoEdicao ? 'Editar usuário' : 'Novo usuário';
        });

        btnAbaLote.addEventListener('click', function() {
            painelUnico.style.display = 'none';
            painelLote.style.display = '';
            btnAbaLote.classList.add('btn-aba--ativo');
            btnAbaUnico.classList.remove('btn-aba--ativo');
            titulo.textContent = 'Importar em lote';
        });

        // Abre aba de lote automaticamente se houver erros a exibir
        if (document.querySelector('#painel-lote [style*="fee2e2"]')) {
            painelUnico.style.display = 'none';
            painelLote.style.display = '';
            btnAbaLote.classList.add('btn-aba--ativo');
            btnAbaUnico.classList.remove('btn-aba--ativo');
            titulo.textContent = 'Importar em lote';
        }

        // Mostra nome do arquivo selecionado e limpa o textarea
        var inputArquivo = document.getElementById('arquivo');
        var labelLista = document.getElementById('label-lista-lote');
        var textareaLista = document.getElementById('lista');

        inputArquivo.addEventListener('change', function() {
            if (inputArquivo.files.length > 0) {
                labelLista.textContent = 'ou cole a lista diretamente (arquivo selecionado será usado)';
                textareaLista.placeholder = 'O arquivo CSV será usado. Você pode deixar isso em branco.';
            } else {
                labelLista.textContent = 'ou cole a lista diretamente';
                textareaLista.placeholder = 'matricula;Nome Completo\n1234;João da Silva\n5678;Maria Souza';
            }
        });

        // Overlay de aguarde na importação em lote
        var formLote = document.getElementById('form-importar-lote');
        var overlayLote = document.getElementById('overlay-importando');
        var btnImportar = document.getElementById('btn-importar');

        formLote.addEventListener('submit', function() {
            var temArquivo = inputArquivo.files.length > 0;
            var temTexto = textareaLista.value.trim() !== '';
            if (!temArquivo && !temTexto) return; // validação nativa cobre isso
            formLote.style.display = 'none';
            overlayLote.style.display = 'block';
            btnImportar.disabled = true;
        });

        // Submete o form de busca automaticamente após o usuário parar de digitar
        var inputBusca = document.querySelector('input[name="busca"]');
        if (inputBusca) {
            var debounceTimer;
            inputBusca.addEventListener('input', function() {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(function() {
                    inputBusca.closest('form').submit();
                }, 800);
            });
        }
    }());
</script>

</div>
</div>