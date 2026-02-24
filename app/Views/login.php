<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Refeitório</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@700&family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/login.css?v=<?= time() ?>">
</head>

<body>
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-4">
                <div class="card card-login p-4">
                    <h2 class="titulo-login text-center">Refeitório</h2>

                    <?php if (isset($_SESSION['erro_login'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= $_SESSION['erro_login']; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['erro_login']); ?>
                    <?php endif; ?>

                    <form action="/login/processar" method="POST">
                        <div class="mb-3">
                            <label class="label-custom">Matrícula</label>
                            <input type="text"
                                name="matricula"
                                class="form-control"
                                required
                                placeholder="Somente números"
                                inputmode="numeric"
                                oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                        </div>
                        <div class="mb-3">
                            <label class="label-custom">Senha</label>
                            <input type="password" name="senha" class="form-control" required placeholder="******">
                        </div>
                        <button type="submit" class="btn btn-oliva w-100 fw-bold shadow-sm">ENTRAR</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>