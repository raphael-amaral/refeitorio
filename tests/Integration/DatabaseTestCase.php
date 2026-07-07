<?php

namespace Tests\Integration;

use App\Core\Audit;
use App\Core\Database;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Classe base para testes de integração dos Models e Services.
 *
 * Cria um banco SQLite em memória com o schema equivalente ao MySQL de produção.
 * Cada teste recebe um banco limpo; não há estado compartilhado entre testes.
 * Redireciona o Audit::write() pra um arquivo descartável, pra não poluir o log real.
 */
abstract class DatabaseTestCase extends TestCase
{
    protected PDO $pdo;
    private string $auditTestPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Habilita foreign keys no SQLite (desativadas por padrão)
        $this->pdo->exec('PRAGMA foreign_keys = ON');

        Database::setTestConnection($this->pdo);
        $this->criarSchema();

        $this->auditTestPath = tempnam(sys_get_temp_dir(), 'audit_test_');
        Audit::setTestPath($this->auditTestPath);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Database::reset();

        Audit::reset();
        @unlink($this->auditTestPath);
    }

    private function criarSchema(): void
    {
        $this->pdo->exec("
            CREATE TABLE usuarios (
                matricula TEXT PRIMARY KEY,
                nome      TEXT NOT NULL,
                senha     TEXT NOT NULL,
                tipo      TEXT NOT NULL DEFAULT 'comum',
                ativo     INTEGER NOT NULL DEFAULT 1,
                criado_em TEXT DEFAULT (datetime('now'))
            )
        ");

        $this->pdo->exec("
            CREATE TABLE cardapios (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                data         TEXT NOT NULL,
                tipo         TEXT NOT NULL,
                descricao    TEXT NOT NULL,
                atualizado_em TEXT DEFAULT (datetime('now')),
                UNIQUE(data, tipo)
            )
        ");

        $this->pdo->exec("
            CREATE TABLE registros (
                id                INTEGER PRIMARY KEY AUTOINCREMENT,
                matricula_usuario TEXT NOT NULL,
                id_cardapio       INTEGER NOT NULL,
                status            TEXT NOT NULL DEFAULT 'agendado',
                criado_em         TEXT DEFAULT (datetime('now')),
                atualizado_em     TEXT DEFAULT (datetime('now')),
                UNIQUE(matricula_usuario, id_cardapio),
                FOREIGN KEY (matricula_usuario) REFERENCES usuarios(matricula),
                FOREIGN KEY (id_cardapio)       REFERENCES cardapios(id)
            )
        ");
    }

    // -------------------------------------------------------------------------
    // Helpers de fixture
    // -------------------------------------------------------------------------

    protected function inserirUsuario(
        string $matricula,
        string $nome = 'USUARIO TESTE',
        string $tipo = 'comum',
        int $ativo = 1,
        string $senha = ''
    ): void {
        if ($senha === '') {
            $senha = password_hash('senha123', PASSWORD_BCRYPT, ['cost' => 4]);
        }

        $this->pdo->prepare(
            "INSERT INTO usuarios (matricula, nome, senha, tipo, ativo) VALUES (?, ?, ?, ?, ?)"
        )->execute([$matricula, $nome, $senha, $tipo, $ativo]);
    }

    protected function inserirCardapio(
        string $data,
        string $tipo = 'almoco',
        string $descricao = 'FRANGO GRELHADO'
    ): int {
        $this->pdo->prepare(
            "INSERT INTO cardapios (data, tipo, descricao) VALUES (?, ?, ?)"
        )->execute([$data, $tipo, $descricao]);

        return (int) $this->pdo->lastInsertId();
    }

    protected function inserirRegistro(
        string $matricula,
        int $idCardapio,
        string $status = 'agendado'
    ): int {
        $this->pdo->prepare(
            "INSERT INTO registros (matricula_usuario, id_cardapio, status) VALUES (?, ?, ?)"
        )->execute([$matricula, $idCardapio, $status]);

        return (int) $this->pdo->lastInsertId();
    }
}
