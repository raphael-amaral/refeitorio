<?php

use App\Services\Perfil;

$u        = $_SESSION['usuario'] ?? [];
$urlAtual = strtok($_SERVER['REQUEST_URI'], '?');
$navAtivo = fn(string $href): string => $urlAtual === $href ? ' class="active"' : '';
$tipoU    = $u['tipo'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo ?? 'Refeitório') ?> — Refeitório</title>
    <link rel="icon" type="image/x-icon" href="/img/favicon.ico">
    <link rel="stylesheet" href="/css/app.css?v=2">
</head>

<body>

    <nav class="navbar">
        <a href="/painel" class="navbar-brand">Refeitório Santa Casa Alegrete</a>

        <ul class="navbar-nav">
            <?php if (!Perfil::ehDp($tipoU)): ?>
                <li><a href="/painel" <?= $navAtivo('/painel') ?>>Painel</a></li>
            <?php endif; ?>
            <?php if (Perfil::ehComum($tipoU)): ?>
                <li><a href="/registro" <?= $navAtivo('/registro') ?>>Meus Agendamentos</a></li>
            <?php endif; ?>
            <?php if (Perfil::podeScanner($tipoU)): ?>
                <li><a href="/cardapio" <?= $navAtivo('/cardapio') ?>>Cardápios</a></li>
            <?php endif; ?>
            <?php if (Perfil::podeDp($tipoU)): ?>
                <li><a href="/questor" <?= $navAtivo('/questor') ?>>Exportação (Questor)</a></li>
            <?php endif; ?>
            <?php if (Perfil::podeRelatorio($tipoU)): ?>
                <li><a href="/relatorios" <?= $navAtivo('/relatorios') ?>>Relatórios</a></li>
            <?php endif; ?>
            <?php if (Perfil::ehDev($tipoU)): ?>
                <li><a href="/usuario" <?= $navAtivo('/usuario') ?>>Usuários</a></li>
            <?php endif; ?>
        </ul>
        <div class="navbar-user">
            <span class="navbar-nome navbar-nome--full"><?= htmlspecialchars($u['nome'] ?? '') ?></span>
            <span class="navbar-nome navbar-nome--short"><?= htmlspecialchars(explode(' ', $u['nome'] ?? '')[0]) ?></span>
            <span class="badge badge--tipo"><?= htmlspecialchars(Perfil::ehComum($tipoU) ? 'Colaborador' : $tipoU) ?></span>
            <?php if (Perfil::ehComum($tipoU)): ?>
                <button type="button" onclick="document.getElementById('modal-trocar-senha').style.display='flex'"
                    class="btn-logout" style="background:none;border:none;cursor:pointer;">Trocar senha</button>
            <?php endif; ?>
            <a href="/logout" class="btn-logout">Sair</a>
        </div>
    </nav>

    <?php if (($_ENV['APP_ENV'] ?? 'production') !== 'production'): ?>
        <div style="background:#f59e0b;color:#1c1917;text-align:center;font-size:.85rem;font-weight:600;padding:8px;letter-spacing:.05em;">
            AMBIENTE DE TESTE — alterações aqui não afetam a produção
        </div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['usuario']['senha_padrao']) && Perfil::ehComum($tipoU)): ?>
        <div class="alert-senha-padrao">
            Você está usando a senha provisória. Recomendamos trocar para maior segurança.
            <button type="button" onclick="document.getElementById('modal-trocar-senha').style.display='flex'"
                class="btn-trocar-senha">Trocar agora</button>
        </div>
    <?php endif; ?>

    <main class="container">
        <?php if (!empty($_SESSION['flash'])): ?>
            <div class="alert alert--<?= htmlspecialchars($_SESSION['flash']['tipo']) ?>">
                <?= htmlspecialchars($_SESSION['flash']['mensagem']) ?>
            </div>
            <?php unset($_SESSION['flash']); ?>
        <?php endif; ?>

        <?php /** @var string $conteudo */ ?>
        <?= $conteudo ?>
    </main>

    <?php if (Perfil::ehComum($tipoU)): ?>
        <!-- Modal troca de senha (apenas usuários comuns) -->
        <div id="modal-trocar-senha" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:900;align-items:center;justify-content:center;">
            <div style="background:#fff;border-radius:8px;padding:28px 32px;width:100%;max-width:380px;box-shadow:0 8px 32px rgba(0,0,0,.2);">
                <strong style="display:block;margin-bottom:18px;font-size:1rem;font-family:var(--font-principal);color:#333;">Trocar senha</strong>
                <p id="erro-modal-senha" style="display:none;background:#fee2e2;color:#991b1b;border:1px solid #fecaca;border-radius:4px;padding:8px 12px;font-size:.83rem;font-family:var(--font-cards);margin-bottom:14px;"></p>
                <form id="form-trocar-senha" method="POST" action="/conta/senha" class="form-usuario">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="senha_atual">Senha atual</label>
                        <input type="password" id="senha_atual" name="senha_atual" required placeholder="Sua senha atual">
                    </div>
                    <div class="form-group">
                        <label for="nova_senha">Nova senha</label>
                        <input type="password" id="nova_senha" name="nova_senha" required minlength="6" placeholder="Mínimo 6 caracteres">
                    </div>
                    <div class="form-group">
                        <label for="confirmar_nova_senha">Confirmar nova senha</label>
                        <input type="password" id="confirmar_nova_senha" name="confirmar_nova_senha" required minlength="6" placeholder="Repita a nova senha">
                    </div>
                    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:4px;">
                        <button type="button" id="btn-fechar-modal-senha" class="btn-acao btn-inativar" style="padding:6px 14px;">Cancelar</button>
                        <button type="submit" class="btn-salvar">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
        <script>
            (function() {
                var modal = document.getElementById('modal-trocar-senha');
                var form = document.getElementById('form-trocar-senha');
                var erroBox = document.getElementById('erro-modal-senha');
                var btnFechar = document.getElementById('btn-fechar-modal-senha');

                function fecharModal() {
                    modal.style.display = 'none';
                    form.reset();
                    erroBox.style.display = 'none';
                    erroBox.textContent = '';
                }

                function mostrarErro(msg) {
                    erroBox.textContent = msg;
                    erroBox.style.display = 'block';
                }

                btnFechar.addEventListener('click', fecharModal);
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) fecharModal();
                });

                form.addEventListener('submit', function(e) {
                    var nova = document.getElementById('nova_senha').value;
                    var confirmar = document.getElementById('confirmar_nova_senha').value;

                    erroBox.style.display = 'none';

                    if (nova.length < 6) {
                        e.preventDefault();
                        mostrarErro('A nova senha deve ter pelo menos 6 caracteres.');
                        return;
                    }
                    if (nova !== confirmar) {
                        e.preventDefault();
                        mostrarErro('As senhas não coincidem.');
                        return;
                    }
                });
            }());
        </script>
    <?php endif; ?>

</body>

</html>
