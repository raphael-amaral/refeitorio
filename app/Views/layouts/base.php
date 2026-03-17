<?php
$u       = $_SESSION['usuario'] ?? [];
$urlAtual = strtok($_SERVER['REQUEST_URI'], '?');
$navAtivo = function (string $href) use ($urlAtual): string {
    return $urlAtual === $href ? ' class="active"' : '';
};
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo ?? 'Refeitório') ?> — Refeitório</title>
    <link rel="stylesheet" href="/css/app.css?v=2">
</head>

<body>

    <nav class="navbar">
        <a href="/painel" class="navbar-brand">Refeitório</a>

        <ul class="navbar-nav">
            <li><a href="/painel" <?= $navAtivo('/painel') ?>>Painel</a></li>
            <?php if (($u['tipo'] ?? '') === 'comum'): ?>
                <li><a href="/registro" <?= $navAtivo('/registro') ?>>Meus Agendamentos</a></li>
            <?php endif; ?>
            <?php if (in_array($u['tipo'] ?? '', ['snd', 'dev'], true)): ?>
                <li><a href="/cardapio" <?= $navAtivo('/cardapio') ?>>Cardápios</a></li>
            <?php endif; ?>
            <?php if (in_array($u['tipo'] ?? '', ['snd', 'dev'], true)): ?>
                <li><a href="/relatorios" <?= $navAtivo('/relatorios')?>>Relatórios</a></li>
            <?php endif;?>
            <?php if (($u['tipo'] ?? '') === 'dev'): ?>
                <li><a href="/usuario" <?= $navAtivo('/usuario') ?>>Usuários</a></li>
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