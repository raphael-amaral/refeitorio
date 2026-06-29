<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Refeitório Hospitalar</title>
    <link rel="icon" type="image/x-icon" href="/img/favicon.ico">
    <link rel="stylesheet" href="/css/login.css?v=9">
</head>

<body>

    <?php if (($_ENV['APP_ENV'] ?? 'production') !== 'production'): ?>
        <div style="position:fixed;top:0;left:0;right:0;z-index:1000;background:#f59e0b;color:#1c1917;text-align:center;font-size:.85rem;font-weight:600;padding:8px;letter-spacing:.05em;">
            AMBIENTE DE TESTE — alterações aqui não afetam a produção
        </div>
    <?php endif; ?>

    <div class="login-wrapper">

        <!-- Cardápios do dia -->
        <div class="login-cardapios">
            <div class="login-cardapios__cabecalho">
                <p class="login-cardapios__titulo">Cardápio de hoje</p>
                <p class="login-cardapios__data"><?= date('d/m/Y') ?></p>
                <p class="login-cardapios__nota">* Agendamentos encerram às <?= htmlspecialchars($_ENV['LIMIT_TIME'] ?? '10:00') ?>h</p>
                <p class="login-cardapios__nota">* Lembre-se de trazer o crachá</p>
            </div>
            <div class="login-cardapios__cards">
                <?php foreach (['almoco' => 'Almoço', 'jantar' => 'Jantar'] as $chave => $label): ?>
                    <div class="login-card">
                        <div class="login-card__header"><?= $label ?></div>
                        <div class="login-card__body">
                            <?php if (!empty($cardapiosHoje[$chave])): ?>
                                <?= htmlspecialchars($cardapiosHoje[$chave]['descricao'] ?? '') ?>
                            <?php else: ?>
                                <span class="login-card__vazio">Sem cardápio cadastrado</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Formulário de login -->
        <div class="login-container">
            <h2>Refeitório Santa Casa Alegrete</h2>

            <?php if (!empty($erro)): ?>
                <div class="alert-error">
                    <?= htmlspecialchars($erro) ?>
                </div>
            <?php endif; ?>

            <form action="/login" method="POST">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="matricula">Matrícula</label>
                    <input type="text" id="matricula" name="matricula" required autofocus>
                </div>

                <div class="form-group">
                    <label for="senha">Senha</label>
                    <input type="password" id="senha" name="senha" required>
                    <small style="color:#888;font-size:0.9rem;margin-top:4px;display:block;">Senha inicial: <strong>stcasa</strong> + matrícula &nbsp;(ex: <em>stcasa1234</em>)</small>
                </div>

                <button type="submit">Entrar</button>
            </form>

            <div class="alert-senha-padrao" style="margin-top:16px;">
                Problemas para acessar? Entre em contato com o setor de TI.
            </div>
        </div>

    </div>

</body>

</html>