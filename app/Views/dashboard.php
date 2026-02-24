<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Refeitório</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <nav class="navbar navbar-dark bg-primary mb-4">
        <div class="container">
            <span class="navbar-brand">Refeitório Hospitalar</span>
            <a href="/logout" class="btn btn-outline-light btn-sm">Sair</a>
        </div>
    </nav>

    <div class="container">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-body">
                        <h4 class="card-title">Olá, <?= $_SESSION['usuario_nome'] ?>! 🍽️</h4>
                        <p class="text-muted">Matrícula: <strong><?= $_SESSION['usuario_matricula'] ?></strong></p>
                        <hr>

                        <?php if (isset($_GET['sucesso'])): ?>
                            <div class="alert alert-success">✅ Agendamento(s) realizado(s) com sucesso!</div>
                        <?php endif; ?>

                        <?php if (isset($_GET['erro'])): ?>
                            <div class="alert alert-danger">❌ Horário limite (09:30) atingido para hoje.</div>
                        <?php endif; ?>

                        <div class="row text-center py-4">
                            <div class="col-md-6 mb-3">
                                <h5>Hoje (<?= date('d/m/Y') ?>)</h5>
                                <form action="/agendar" method="POST">
                                    <input type="hidden" name="tipo" value="hoje">
                                    <button type="submit" class="btn btn-primary btn-lg px-4 shadow-sm"
                                        <?= (date('H:i') > "09:30") ? 'disabled' : '' ?>>
                                        Agendar Hoje
                                    </button>
                                </form>
                                <?php if (date('H:i') > "09:30"): ?>
                                    <small class="text-danger d-block mt-2">Indisponível após as 09:30</small>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <h5>Próximos Dias Úteis</h5>
                                <form action="/agendar" method="POST">
                                    <input type="hidden" name="tipo" value="semana">
                                    <button type="submit" class="btn btn-success btn-lg px-4 shadow-sm">
                                        Agendar Semana Toda
                                    </button>
                                </form>
                                <small class="text-muted d-block mt-2">Agendará os próximos 5 dias úteis</small>
                            </div>
                        </div>
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Meus Agendamentos</h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Data do Almoço</th>
                                                <th>Status</th>
                                                <th>Registrado em</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            // Instanciamos o Model direto aqui para teste rápido
                                            $registroModel = new \App\Models\Registro();
                                            $meusAgendamentos = $registroModel->buscarMeusAgendamentos($_SESSION['usuario_id']);

                                            if (empty($meusAgendamentos)): ?>
                                                <tr>
                                                    <td colspan="3" class="text-center text-muted py-3">Nenhum agendamento encontrado.</td>
                                                </tr>
                                                <?php else:
                                                foreach ($meusAgendamentos as $reg): ?>
                                                    <tr>
                                                        <td><strong><?= date('d/m/Y', strtotime($reg['data_agendada'])) ?></strong></td>
                                                        <td>
                                                            <span class="badge rounded-pill bg-success">
                                                                <?= ucfirst($reg['status']) ?>
                                                            </span>
                                                        </td>
                                                        <td class="small text-muted">
                                                            <?= date('d/m/Y H:i', strtotime($reg['criado_em'])) ?>
                                                        </td>
                                                    </tr>
                                            <?php endforeach;
                                            endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>