<?php if (session_status() === PHP_SESSION_NONE) session_start(); ?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Agendamento</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Montserrat:wght@300;400;600&family=Nunito:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/dashboard.css?v=<?= time() ?>">
</head>

<body>
    <div class="fundo-escuro"></div>

    <div id="toast-container">
        <?php if (isset($_SESSION['aviso_sucesso'])): ?>
            <div class="alerta-toast sucesso">
                <span>✅ <?= $_SESSION['aviso_sucesso'];
                        unset($_SESSION['aviso_sucesso']); ?></span>
                <button onclick="this.parentElement.remove()">×</button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['aviso_erro'])): ?>
            <div class="alerta-toast erro">
                <span>⚠️ <?= $_SESSION['aviso_erro'];
                            unset($_SESSION['aviso_erro']); ?></span>
                <button onclick="this.parentElement.remove()">×</button>
            </div>
        <?php endif; ?>
    </div>

    <header>
        <section id="cabs">
            <div id="cab">
                <h1>Agendamento de Almoço</h1>
                <div class="user-info">
                    <span>Usuário: <strong><?= htmlspecialchars($_SESSION['usuario_nome']) ?></strong></span>
                    <a href="/logout" class="btn-sair">Sair</a>
                </div>
            </div>
        </section>
    </header>

    <main class="conteudo-principal">
        <div id="almoco-faixa">
            <h2>Selecione o Dia</h2>
        </div>

        <div class="botao-semana-container">
            <form action="/agendar" method="POST">
                <input type="hidden" name="tipo" value="semana">
                <button type="submit" class="btn-agendar-semana">🗓️ Agendar de Segunda a Sexta</button>
            </form>
            <p class="legenda-botao">* Clique nos cards para agendamentos individuais.</p>
        </div>

        <section id="grid-agendamento">
            <?php
            $hoje = date('Y-m-d');
            $agora = date('H:i');
            $limite = "09:30";

            $diaW = date('w');
            $diffSegunda = ($diaW == 0) ? 6 : $diaW - 1;
            $segundaFeira = date('Y-m-d', strtotime("-$diffSegunda days"));

            $registroModel = new \App\Models\Registro();
            $meusAgendamentos = $registroModel->buscarMeusAgendamentos($_SESSION['usuario_id']);
            $datasAgendadas = array_column($meusAgendamentos, 'data_agendada');

            $diasNome = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];

            for ($i = 0; $i < 7; $i++):
                $dataAlvo = date('Y-m-d', strtotime("$segundaFeira +$i days"));
                $diaNum = (int)date('w', strtotime($dataAlvo));
                $jaAgendado = in_array($dataAlvo, $datasAgendadas);
                $isHoje = ($dataAlvo === $hoje);
                $isPassado = ($dataAlvo < $hoje);
                $passouLimite = ($isHoje && $agora >= $limite);
            ?>
                <div class="card-dia <?= $isHoje ? 'hoje' : '' ?> <?= ($isPassado || $passouLimite) ? 'dia-bloqueado' : '' ?>">
                    <div class="dia-header">
                        <h5 class="cardtitle"><?= $diasNome[$diaNum] ?></h5>
                        <?php if ($isHoje): ?>
                            <span class="badge-hoje">HOJE</span>
                        <?php endif; ?>
                    </div>
                    <p class="data-texto"><?= date('d/m/Y', strtotime($dataAlvo)) ?></p>
                    <hr class="linha">

                    <div class="conteudo-vazio"></div>

                    <div class="status-box">
                        <?php if ($jaAgendado): ?>
                            <span class="status-confirmado">✓ AGENDADO</span>
                        <?php elseif ($isPassado || $passouLimite): ?>
                            <span class="bloqueado-txt">🔒 INDISPONÍVEL</span>
                        <?php else: ?>
                            <form action="/agendar" method="POST">
                                <input type="hidden" name="data_especifica" value="<?= $dataAlvo ?>">
                                <button type="submit" class="btn-card">Agendar</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endfor; ?>
        </section>
    </main>

    <script>
        document.querySelectorAll('.alerta-toast').forEach(toast => {
            setTimeout(() => {
                toast.style.transition = "0.5s";
                toast.style.opacity = "0";
                toast.style.transform = "translateX(100%)";
                setTimeout(() => toast.remove(), 500);
            }, 4000);
        });
    </script>
</body>

</html>