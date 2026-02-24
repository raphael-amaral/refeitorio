<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Login - Refeitorio</title>
    <style>
        body {
            background: #f4f7f6;
            height: 100vh;
            display: flex;
            align-items: center;
        }

        .login-card {
            width: 100%;
            max-width: 400px;
            margin: auto;
            padding: 20px;
        }
    </style>
</head>

<body>
    <div class="login-card">
        <div class="card shadow-sm">
            <div class="card-body">
                <h3 class="text-center mb-4">Refeitório Santa Casa Alegrete</h3>
                <form action="/login/processar" method="POST">
                    <div class="mb-3">
                        <label>Matrícula</label>
                        <input type="text" name="matricula" class="form-control" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label>Senha</label>
                        <input type="password" name="senha" class="form-control">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Entrar</button>
                </form>
            </div>
        </div>
    </div>
</body>

</html>