# Ambiente de Teste — Refeitório SND

Este diretório é o ambiente de testes isolado do sistema Refeitório. Alterações são feitas e validadas aqui antes de irem para produção.

---

## Visão geral

```
[GitHub — branch develop]                [GitHub — branch main]
        │                                         │
        ▼                                         ▼
/home/usuario/raphael/projetos/       /home/raphael/projeto/
  refeitorio-teste/                     refeitorio/
  (ESTE DIRETÓRIO — TESTE)              (PRODUÇÃO)
  Porta web : 8151                      Porta web : 8101
  Porta db  : 3151                      Porta db  : 3101
  DB        : refeitorio_teste          DB        : refeitorio_snd
```

O fluxo correto é sempre:

```
develop (teste) ──── Pull Request ───▶ main ──── git pull ───▶ produção
```

Nunca fazer `git pull` sem verificar em qual branch o repositório de produção está apontando.

---

## 1. Preencher o `.env`

O arquivo `.env` já existe neste diretório mas **não é versionado** (`.gitignore`). Preencha os campos em branco antes de subir os containers:

```env
APP_ENV=development

DB_HOST=db
DB_PORT=3306
DB_DATABASE=refeitorio_teste   # não alterar — banco separado da produção
DB_USERNAME=                   # preencher
DB_PASSWORD=                   # preencher
SCANNER_TOKEN=                 # preencher (pode ser diferente do de produção)
```

As demais variáveis já têm valores padrão adequados para teste e podem ser ajustadas conforme necessário:

| Variável | Valor padrão | Descrição |
|---|---|---|
| `APP_ENV` | `development` | Habilita mensagens de erro detalhadas |
| `AGENDAMENTO_HABILITADO` | `false` | Agendamento de refeições desativado |
| `AGENDAMENTO_SABADO_DOMINGO` | `false` | Fim de semana desativado |
| `LIMIT_TIME` | `10:00` | Horário limite para agendamento |
| `EXCECAO_LIMIT_TIME` | `13:30` | Horário limite para exceções |
| `SESSION_IDLE_TIMEOUT` | `1800` | Timeout de sessão em segundos |
| `LOGIN_MAX_ATTEMPTS` | `8` | Tentativas de login antes do bloqueio |
| `AUDIT_LOG_PATH` | *(vazio)* | Caminho para log de auditoria |

> O `.env.example` na raiz do projeto serve como referência — nunca coloque senhas reais nele.

---

## 2. Subir os containers

```bash
cd /home/usuario/raphael/projetos/refeitorio-teste

# Primeira vez (ou após rebuild do Dockerfile)
docker compose up -d --build

# Demais vezes
docker compose up -d
```

O banco é inicializado automaticamente pelo script `database/init.sql` na primeira vez que o volume `refeitorio-teste_db_data` é criado.

### Verificar se subiu corretamente

```bash
docker compose ps
```

Deve exibir `web` e `db` com status `running`.

Acessar no navegador: **http://localhost:8151**

### Derrubar os containers

```bash
docker compose down
```

Para derrubar **e apagar o banco de dados** (volume):

```bash
docker compose down -v
```

> Use `down -v` com cuidado — apaga todos os dados do banco de teste.

---

## 3. Popular o banco de dados

O `init.sql` cria apenas a estrutura das tabelas (schema). Para ter dados úteis para testes, execute um dos seguintes após subir os containers:

**Opção A — Importar um dump da produção** (dados reais anonimizados, recomendado):

```bash
# Copiar dump da produção para este diretório
# Em seguida, importar:
docker exec -i refeitorio-teste-db-1 mysql -u<DB_USERNAME> -p<DB_PASSWORD> refeitorio_teste < dump.sql
```

**Opção B — Dados de teste prontos** (arquivo `database/test_data.sql`, se existir):

```bash
docker exec -i refeitorio-teste-db-1 mysql -u<DB_USERNAME> -p<DB_PASSWORD> refeitorio_teste < database/test_data.sql
```

**Opção C — Seed via PHP** (arquivo `database/seed_questor_test.php`):

```bash
docker compose run --rm python_app
# ou executar o PHP diretamente dentro do container web
```

---

## 4. Conectar ao banco de dados

| Campo | Valor |
|---|---|
| Host | `127.0.0.1` |
| Porta | `3151` |
| Banco | `refeitorio_teste` |
| Usuário | valor de `DB_USERNAME` no `.env` |
| Senha | valor de `DB_PASSWORD` no `.env` |

Usando linha de comando:

```bash
docker exec -it refeitorio-teste-db-1 mysql -u<DB_USERNAME> -p<DB_PASSWORD> refeitorio_teste
```

---

## 5. Fluxo Git — como trabalhar neste ambiente

Este repositório está na branch **`develop`**, rastreando `origin/develop`. O repositório de produção está em `main`.

### Fazer uma alteração

```bash
# Garantir que está na develop e atualizado
git checkout develop
git pull

# Fazer as alterações, testar em http://localhost:8151, depois:
git add arquivo_alterado.php
git commit -m "tipo: descrição da mudança"
git push
```

### Levar para produção

1. Abrir um Pull Request no GitHub: `develop` → `main`
2. Revisar e aprovar o PR
3. No servidor de produção (`/home/raphael/projeto/refeitorio`):
   ```bash
   git pull
   docker compose up -d --build  # só se houver mudança no Dockerfile
   ```

> **Nunca** fazer `git checkout main` neste diretório de teste — mantenha sempre em `develop`.

---

## 6. Isolamento em relação à produção

| Aspecto | Teste | Produção |
|---|---|---|
| Diretório | `refeitorio-teste/` | `refeitorio/` |
| Branch Git | `develop` | `main` |
| Porta web | `8151` | `8101` |
| Porta banco | `3151` | `3101` |
| Banco de dados | `refeitorio_teste` | `refeitorio_snd` |
| Volume Docker | `refeitorio-teste_db_data` | `refeitorio_db_data` |
| Nome do projeto Docker | `refeitorio-teste` | `refeitorio` |

Os ambientes são completamente independentes e podem rodar simultaneamente no mesmo servidor sem conflito.
