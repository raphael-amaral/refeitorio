<?php $u = $_SESSION['usuario'] ?? []; ?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo ?? 'Refeitório') ?> — Refeitório</title>
    <link rel="stylesheet" href="/css/app.css">
</head>

<body>

    <nav class="navbar">
        <a href="/painel" class="navbar-brand">Refeitório</a>

        <ul class="navbar-nav">
            <li><a href="/painel">Painel</a></li>
            <?php if (($u['tipo'] ?? '') !== 'dev'): ?>
                <li><a href="/registro">Meus Agendamentos</a></li>
            <?php endif; ?>
            <?php if (in_array($u['tipo'] ?? '', ['copa', 'admin'], true)): ?>
                <li><a href="/cardapio">Cardápios</a></li>
            <?php endif; ?>
            <?php if (in_array($u['tipo'] ?? '', ['admin', 'dev'], true)): ?>
                <li><a href="/usuario">Usuários</a></li>
            <?php endif; ?>
        </ul>

        <div class="navbar-user">
            <span class="navbar-nome"><?= htmlspecialchars($u['nome'] ?? '') ?></span>
            <span class="badge badge--tipo"><?= htmlspecialchars($u['tipo'] ?? '') ?></span>
            <a href="/logout" class="btn-logout">Sair</a>
        </div>
    </nav>

    <main class="container">
        <?php if (!empty($_SESSION['flash'])): ?>
            <div class="alert alert--<?= htmlspecialchars($_SESSION['flash']['tipo']) ?>">
                <?= htmlspecialchars($_SESSION['flash']['mensagem']) ?>
            </div>
            <?php unset($_SESSION['flash']); ?>
        <?php endif; ?>

        <?= $conteudo ?>
    </main>

</body>

</html>