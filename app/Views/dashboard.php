<?php if (session_status() === PHP_SESSION_NONE) session_start(); ?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agendamento</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Montserrat:wght@300;400;600&family=Nunito:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/dashboard.css?v=<?= time() ?>">
    <link rel="icon" href="/img/favicon.ico" type="image/x-icon">
</head>

<body>
    <div class="fundo-escuro"></div>

    <div id="toast-container">
        <?php if (isset($_SESSION['aviso_sucesso'])): ?>
            <div class="alerta-toast sucesso">
                <span>✅ <?= $_SESSION['aviso_sucesso'];
                        unset($_SESSION['aviso_sucesso']); ?></span>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['aviso_erro'])): ?>
            <div class="alerta-toast erro">
                <span>⚠️ <?= $_SESSION['aviso_erro'];
                            unset($_SESSION['aviso_erro']); ?></span>
            </div>
        <?php endif; ?>
    </div>

    <header>
        <section id="cabs">
            <div id="cab">
                <h1>Bem Vindo ao Cardápio</h1>
                <div class="user-info">
                    <span>Usuário: <strong><?= htmlspecialchars($_SESSION['usuario_nome']) ?></strong></span>
                    <a href="/logout" class="btn-sair">Sair</a>
                </div>
            </div>
        </section>
    </header>

    <main class="conteudo-principal">
        <div id="almoco-faixa">
            <div class="faixa-titulo">
                <h2>Almoço</h2>
            </div>
            <div class="botao-semana-container right">
                <form action="/agendar" method="POST">
                    <input type="hidden" name="tipo" value="semana">
                    <button type="submit" class="btn-agendar-semana">🗓️ Agendar de Segunda a Sexta</button>
                </form>
            </div>
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
            $matriculaUser = $_SESSION['usuario_matricula'] ?? $_SESSION['matricula'];
            $meusAgendamentos = $registroModel->buscarMeusAgendamentos($matriculaUser);

            $statusPorData = [];
            foreach ($meusAgendamentos as $reg) {
                $statusPorData[$reg['data_agendada']] = $reg['status'];
            }

            $diasNome = ['Domingo', 'Segunda-Feira', 'Terça-Feira', 'Quarta-Feira', 'Quinta-Feira', 'Sexta-Feira', 'Sábado'];

            for ($i = 0; $i < 7; $i++):
                $dataAlvo = date('Y-m-d', strtotime("$segundaFeira +$i days"));
                $diaNum = (int)date('w', strtotime($dataAlvo));

                $status = $statusPorData[$dataAlvo] ?? null;
                $estaReservado = ($status && $status !== 'cancelado');
                $isAgendado = ($status === 'agendado');
                $isConcluido = ($status === 'concluido');

                $isHoje = ($dataAlvo === $hoje);
                $isPassado = ($dataAlvo < $hoje);
                $passouLimite = ($isHoje && $agora >= $limite);
            ?>
                <div class="card-dia <?= $isHoje ? 'hoje' : '' ?> <?= ($isPassado || $passouLimite) ? 'dia-bloqueado' : '' ?>">
                    <div class="dia-header">
                        <h5 class="cardtitle"><?= $diasNome[$diaNum] ?></h5>
                        <?php if ($isHoje): ?>
                            <span class="badge-hoje">HOJE</span>
                        <? endif; ?>
                    </div>
                    <p class="data-texto"><?= date('d/m/Y', strtotime($dataAlvo)) ?></p>
                    <hr class="linha">
                    <p class="cardapio-placeholder">ARROZ, FEIJAO ,CARNE MOIDA COM OVO PICADO E TV,FAROFA E MASSA NA MANTEIGA E SALADA MISTA.</p>

                    <div class="status-box">
                        <?php if ($estaReservado): ?>
                            <?php if ($isAgendado): ?>
                                <span class="status-confirmado">✓ AGENDADO</span>

                                <?php if (!$isPassado && !$passouLimite): ?>
                                    <form action="/cancelar" method="POST" style="margin-top: 5px;">
                                        <input type="hidden" name="data_cancelar" value="<?= $dataAlvo ?>">
                                        <button type="submit" class="btn-cancelar">
                                            Cancelar
                                        </button>
                                    </form>
                                <?php endif; ?>

                            <?php elseif ($isConcluido): ?>
                                <span class="status-confirmado">✓ CONCLUÍDO</span>
                            <?php else: ?>
                                <span class="status-confirmado"><?= htmlspecialchars(strtoupper($status)) ?></span>
                            <?php endif; ?>

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

        <div id="jantar-faixa">
            <div class="faixa-titulo">
                <h2>Jantar</h2>
            </div>
        </div>

        <section id="grid-jantar">
            <?php
            // reutiliza datas já calculadas acima para a semana
            for ($i = 0; $i < 7; $i++):
                $dataAlvo = date('Y-m-d', strtotime("$segundaFeira +$i days"));
                $diaNum = (int)date('w', strtotime($dataAlvo));
                $isHoje = ($dataAlvo === $hoje);
            ?>
                <div class="card-dia card-jantar <?= $isHoje ? 'hoje' : '' ?>">
                    <div class="dia-header">
                        <h5 class="cardtitle"><?= $diasNome[$diaNum] ?></h5>
                        <?php if ($isHoje): ?>
                            <span class="badge-hoje">HOJE</span>
                        <?php endif; ?>
                    </div>
                    <p class="data-texto"><?= date('d/m/Y', strtotime($dataAlvo)) ?></p>
                    <hr class="linha">
                    <p class="cardapio-placeholder">ARROZ, FEIJAO ,CARNE MOIDA COM OVO PICADO E TV,FAROFA E MASSA NA MANTEIGA E SALADA MISTA.</p>
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