<?php

use App\Models\Usuario; ?>
<div class="usuarios-page">

    <div class="usuarios-layout">

        <!-- Tabela de usuários -->
        <section class="usuarios-lista card">
            <div class="card__header" style="gap:12px;">
                <strong>Usuários cadastrados</strong>
                <input type="search" id="busca-usuario"
                    placeholder="Buscar por nome ou matrícula…"
                    style="margin-left:auto;padding:4px 10px;border:1px solid #ccc;border-radius:4px;font-size:0.85rem;font-family:var(--font-cards);width:220px;">
            </div>
            <div class="card__body" style="overflow-y: auto;">
                <table class="tabela-usuarios">
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
                                                <button class="btn-acao btn-inativar">Inativar</button>
                                            </form>
                                        <?php elseif (!$u['ativo']): ?>
                                            <form method="POST" action="/usuario/<?= urlencode($u['matricula']) ?>/ativar">
                                                <button class="btn-acao btn-ativar">Reativar</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if (!$proprio && !$temRegistros): ?>
                                            <form method="POST" action="/usuario/<?= urlencode($u['matricula']) ?>/excluir"
                                                onsubmit="return confirm('Excluir permanentemente <?= htmlspecialchars($u['nome'], ENT_QUOTES) ?>?')">
                                                <button class="btn-acao btn-excluir">Excluir</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Formulário novo / editar -->
        <section class="usuarios-form card">
            <div class="card__header">
                <strong id="form-titulo">Novo usuário</strong>
            </div>
            <div class="card__body">
                <form method="POST" id="form-usuario" action="/usuario" class="form-usuario">
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

        // -----------------------------------------------------------------
        // NOVA LÓGICA DE PAGINAÇÃO E BUSCA
        // -----------------------------------------------------------------

        var linhasPorPagina = 10; // Defina quantos usuários aparecerão por tela
        var paginaAtual = 1;
        var tbody = document.querySelector('.tabela-usuarios tbody');
        var todasAsLinhas = Array.from(tbody.querySelectorAll('tr'));
        var linhasFiltradas = todasAsLinhas;

        // Cria o contêiner para os botões de paginação via JS
        var paginacaoContainer = document.createElement('div');
        paginacaoContainer.style.display = 'flex';
        paginacaoContainer.style.justifyContent = 'center';
        paginacaoContainer.style.gap = '8px';
        paginacaoContainer.style.marginTop = '20px';
        paginacaoContainer.style.flexWrap = 'wrap';
        document.querySelector('.usuarios-lista .card__body').appendChild(paginacaoContainer);

        function renderizarPagina(pagina) {
            // Oculta todas as linhas primeiro
            todasAsLinhas.forEach(function(linha) {
                linha.style.display = 'none';
            });

            // Calcula o início e o fim para exibir apenas a página atual
            var inicio = (pagina - 1) * linhasPorPagina;
            var fim = inicio + linhasPorPagina;

            linhasFiltradas.slice(inicio, fim).forEach(function(linha) {
                linha.style.display = '';
            });

            atualizarBotoesPaginacao();
        }

        function atualizarBotoesPaginacao() {
            paginacaoContainer.innerHTML = '';
            var totalPaginas = Math.ceil(linhasFiltradas.length / linhasPorPagina);

            // Não mostra paginação se couber tudo em uma tela só
            if (totalPaginas <= 1) return;

            // Lógica simples para criar os botões (limitado para não criar 50 botões se a busca for vazia)
            // Aqui criamos botões para as primeiras, a página atual e as últimas
            for (let i = 1; i <= totalPaginas; i++) {
                // Mostra a primeira, a última, e as duas antes e depois da página atual
                if (i === 1 || i === totalPaginas || (i >= paginaAtual - 2 && i <= paginaAtual + 2)) {
                    let btn = document.createElement('button');
                    btn.textContent = i;
                    btn.style.padding = '5px 10px';
                    btn.style.border = '1px solid #ccc';
                    btn.style.borderRadius = '4px';
                    btn.style.cursor = 'pointer';
                    btn.style.backgroundColor = (i === paginaAtual) ? '#0056b3' : '#fff';
                    btn.style.color = (i === paginaAtual) ? '#fff' : '#333';

                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        paginaAtual = i;
                        renderizarPagina(paginaAtual);
                    });

                    paginacaoContainer.appendChild(btn);
                } else if (i === paginaAtual - 3 || i === paginaAtual + 3) {
                    // Adiciona reticências "..." para saltos grandes
                    let reticencias = document.createElement('span');
                    reticencias.textContent = '...';
                    reticencias.style.padding = '5px';
                    paginacaoContainer.appendChild(reticencias);
                }
            }
        }

        // Integração do campo de busca existente com a nova paginação
        document.getElementById('busca-usuario').addEventListener('input', function() {
            var termo = this.value.trim().toLowerCase();

            linhasFiltradas = todasAsLinhas.filter(function(linha) {
                var mat = linha.cells[0] ? linha.cells[0].textContent.toLowerCase() : '';
                var nome = linha.cells[1] ? linha.cells[1].textContent.toLowerCase() : '';
                return !termo || mat.includes(termo) || nome.includes(termo);
            });

            // Sempre volta para a primeira página ao fazer uma nova busca
            paginaAtual = 1;
            renderizarPagina(paginaAtual);
        });

        // Inicializa a primeira página ao carregar a tela
        renderizarPagina(paginaAtual);
    }());
</script>

</div>
</div>