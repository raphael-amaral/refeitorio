<?php
/**
 * Seed de dados de teste para a exportação Questor.
 * Insere 10 usuários e distribui refeições concluídas ao longo de maio/2026.
 *
 * Uso: docker exec refeitorio-web-1 php /var/www/html/database/seed_questor_test.php
 */

// Carrega variáveis de ambiente do .env
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linha) {
        if ($linha[0] === '#' || !str_contains($linha, '=')) continue;
        [$chave, $valor] = explode('=', $linha, 2);
        $_ENV[trim($chave)] = trim($valor);
    }
}

$dsn  = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    $_ENV['DB_HOST'] ?? 'db',
    $_ENV['DB_PORT'] ?? '3306',
    $_ENV['DB_DATABASE'] ?? 'refeitorio_snd'
);
$pdo  = new PDO($dsn, $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

// ── 1. Usuários de teste ─────────────────────────────────────────────────────

$usuarios = [
    ['1001', 'ANA LIMA'],
    ['1002', 'BRUNO COSTA'],
    ['1003', 'CARLA MENDES'],
    ['1004', 'DIEGO ROCHA'],
    ['1005', 'ELISA FERREIRA'],
    ['1006', 'FELIPE SANTOS'],
    ['1007', 'GABRIELA NUNES'],
    ['1008', 'HENRIQUE ALVES'],
    ['1009', 'ISABELA MARTINS'],
    ['1010', 'JOAO OLIVEIRA'],
];

$stmtUsuario = $pdo->prepare(
    "INSERT IGNORE INTO usuarios (matricula, nome, senha, tipo)
     VALUES (:matricula, :nome, :senha, 'comum')"
);

foreach ($usuarios as [$mat, $nome]) {
    $stmtUsuario->execute([
        'matricula' => $mat,
        'nome'      => $nome,
        'senha'     => password_hash('stcasa' . $mat, PASSWORD_BCRYPT, ['cost' => 8]),
    ]);
}
echo "Usuários inseridos (ou já existentes).\n";

// ── 2. Cardápios — dias úteis de maio/2026 ──────────────────────────────────

$mes        = '2026-05';
$diasUteis  = [];
$descricoes = [
    'almoco' => ['Frango grelhado, arroz, feijão e salada', 'Carne assada, macarrão e legumes', 'Peixe ao forno, arroz integral e brócolis', 'Strogonoff de frango com arroz', 'Bife acebolado, purê e feijão'],
    'jantar' => ['Sopa de legumes e pão', 'Macarrão ao molho vermelho', 'Omelete, arroz e salada', 'Caldo de feijão e broa', 'Lanche natural e suco'],
];

for ($dia = 1; $dia <= 31; $dia++) {
    $data = "{$mes}-" . str_pad($dia, 2, '0', STR_PAD_LEFT);
    if (!checkdate(5, $dia, 2026)) break;
    $diaSemana = (int) date('N', strtotime($data)); // 1=Seg … 7=Dom
    if ($diaSemana <= 5) {
        $diasUteis[] = $data;
    }
}

$stmtCardapio = $pdo->prepare(
    "INSERT IGNORE INTO cardapios (data, descricao, tipo) VALUES (:data, :descricao, :tipo)"
);

foreach ($diasUteis as $idx => $data) {
    $stmtCardapio->execute(['data' => $data, 'descricao' => $descricoes['almoco'][$idx % 5], 'tipo' => 'almoco']);
    $stmtCardapio->execute(['data' => $data, 'descricao' => $descricoes['jantar'][$idx % 5], 'tipo' => 'jantar']);
}
echo count($diasUteis) . " dias úteis processados (almoço + jantar).\n";

// ── 3. Registros concluídos distribuídos ─────────────────────────────────────
// Cada par (usuário, cardápio) segue uma seleção determinística:
//   - almoço: ~80 % de presença  (crc32 mod 10 < 8)
//   - jantar: ~40 % de presença  (crc32 mod 10 < 4)

$stmtBusca = $pdo->prepare(
    "SELECT id, tipo FROM cardapios WHERE data = :data AND tipo IN ('almoco','jantar')"
);
$stmtRegistro = $pdo->prepare(
    "INSERT IGNORE INTO registros (matricula_usuario, id_cardapio, status)
     VALUES (:matricula, :id_cardapio, 'concluido')"
);

$totalRegistros = 0;

foreach ($diasUteis as $data) {
    $stmtBusca->execute(['data' => $data]);
    $cardapiosDia = $stmtBusca->fetchAll(PDO::FETCH_ASSOC);

    foreach ($cardapiosDia as $c) {
        if ($c['tipo'] !== 'almoco') continue;
        foreach ($usuarios as [$mat]) {
            $chave = abs(crc32($mat . $data)) % 10;
            if ($chave < 8) { // ~80% de presença
                $stmtRegistro->execute(['matricula' => $mat, 'id_cardapio' => $c['id']]);
                $totalRegistros++;
            }
        }
    }
}

echo "{$totalRegistros} registros concluídos inseridos.\n";
echo "Pronto! Acesse /questor e exporte maio/2026.\n";
