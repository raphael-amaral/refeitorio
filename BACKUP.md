# Sistema de Backup — Refeitório SND

O backup opera em duas camadas independentes: o **servidor Linux** gera os dumps (diários, fim de mês e Questor), e a **máquina Windows INF010** baixa e arquiva uma cópia local via SCP. Dentro do diretório de backups, cada tipo fica na sua própria subpasta.

---

## Visão geral

```
[Servidor Linux 10.1.0.225]                  [Windows INF010 10.1.0.127]
  cron 02:00          container web            Agendador de Tarefas
  backup_mysql.sh     (export Questor)         baixar_backups.bat
       │                    │                         │
       ▼                    ▼                        ▼
  backups_refeitorio/backups/             C:\...\backups_refeitorio\backups\
    ├── diarios/       (últimos 7 dias)      (cópia local via SCP)
    ├── fim_mes/       (indefinido)
    ├── questor/       (indefinido, escrito direto pelo container)
    ├── pre_restore/   (30 dias, somente servidor)
    └── manual_migrations/ (30 dias, somente servidor)
```

---

## Camada 1 — Cron no servidor Linux

**Arquivo:** `scripts/backup_mysql.sh`  
**Agendamento (crontab do usuário `usuario`):**
```
0 2 * * * /home/usuario/raphael/projetos/refeitorio/scripts/backup_mysql.sh
```
Executa todo dia às **02:00**.

### O que faz

1. Carrega as variáveis de `refeitorio/.env` (banco, usuário, senha, nome do container).
2. Executa `mysqldump` dentro do container Docker `refeitorio-db-1` (ou o valor de `CONTAINER_NAME` no `.env`).
3. Salva o dump em:
   ```
   backups_refeitorio/backups/diarios/backup_refeitorio_snd_YYYY-MM-DD_HH-MM-SS.sql
   ```
4. Se for o **último dia do mês**, cria uma cópia especial com o nome do mês por extenso:
   ```
   backups_refeitorio/backups/fim_mes/backup_refeitorio_snd_FIM_JULHO_YYYY.sql
   ```
5. Mantém todos os backups das **7 datas mais recentes, incluindo hoje**, em `diarios/`. Execuções adicionais no mesmo dia não reduzem a cobertura dos outros dias.
6. Remove de `pre_restore/` e `manual_migrations/` os arquivos `.sql` com mais de 30 dias. `fim_mes/` nunca é tocado por essa rotina — fica em pasta separada.

Backups **Questor** não passam por esse script: o container web escreve direto em `backups_refeitorio/backups/questor/` a cada exportação (ver seção própria abaixo).

### Retenção no servidor Linux

| Tipo              | Pasta           | Retenção                   |
|-------------------|-----------------|-----------------------------|
| Diário            | `diarios/`      | Todos dos últimos 7 dias   |
| Fim de mês        | `fim_mes/`      | Indefinida                 |
| Questor           | `questor/`      | Indefinida                 |
| Pré-restauração   | `pre_restore/`  | 30 dias, somente servidor  |
| Migração manual   | `manual_migrations/` | 30 dias, somente servidor |

Fim de mês e Questor não têm rotação automática. Os fechamentos mensais são pontos periódicos de recuperação; os backups Questor preservam o estado do banco associado a cada exportação para auditoria.

### Backup Questor (exportação de vale-refeição)

Toda vez que o RH exporta o CSV Questor em `/questor/exportar` (usado para desconto em folha), o `QuestorService::gerarBackup()` roda um `mysqldump` completo do banco **direto** no container web e salva em:
```
backups_refeitorio/backups/questor/questor_<banco>_<data_inicio>_<data_fim>_<HHMMSS>.sql
```
O nome do banco (`refeitorio_snd` em produção, `refeitorio_teste` em teste) fica sempre no nome do arquivo. Cada ambiente deve usar um `BACKUP_DIR` diferente: `backups_refeitorio/backups` em produção e `backups_refeitorio_teste/backups` em teste.

Isso exige um bind mount do host para dentro do container (ver `compose.yaml`), para que o `mysqldump` do PHP escreva direto no destino final — sem depender do cron noturno para "materializar" o backup fora do container.

O nome do arquivo gerado é salvo na coluna `arquivo_backup` da tabela `questor_exportacoes`, junto com o restante do histórico daquela exportação.

O dump é escrito primeiro com extensão `.tmp` e só é publicado como `.sql` depois de o `mysqldump` terminar com sucesso e produzir conteúdo. Se isso falhar, o temporário é removido, a falha fica registrada na auditoria e a exportação do CSV continua normalmente com `arquivo_backup` nulo no histórico.

### Atualização de uma instalação existente

As alterações de schema são controladas pelo executor descrito em `MIGRATIONS.md`. Antes de aplicar migrations, gere e valide um backup completo e consulte o estado:

```bash
docker compose exec -T web php bin/migrate status
docker compose exec -T web php bin/migrate up --database=refeitorio_snd
```

Instalações que já receberam uma migration manualmente devem usar o baseline explícito somente depois de verificar o schema, conforme `MIGRATIONS.md`.

Depois, recrie o container web para ativar o bind mount de `questor/`:

```bash
docker compose up -d --build web
```

Backups anteriores que ainda estejam diretamente na raiz de `BACKUP_DIR` continuam aparecendo no restaurador. Eles podem ser reorganizados manualmente depois da implantação; a atualização não os move nem remove automaticamente.

### Variáveis relevantes do `.env`

| Variável          | Obrigatória | Descrição                         |
|-------------------|-------------|-----------------------------------|
| `CONTAINER_NAME`  | Sim         | Nome do container MySQL           |
| `DB_USERNAME`     | Sim         | Usuário do banco                  |
| `DB_PASSWORD`     | Sim         | Senha do banco                    |
| `DB_DATABASE`     | Sim         | Nome do banco                     |
| `BACKUP_DIR`      | Sim         | Diretório absoluto exclusivo do ambiente |

---

## Camada 2 — Agendador de Tarefas (Windows INF010)

**Arquivo original (ativo):** `C:\Users\usuario\estagio\producao\backups_refeitorio\baixar_backups.bat`  
**Arquivo no repositório:** `scripts/baixar_backups.bat` *(cópia de referência — não é executado diretamente)*

> Qualquer alteração no script deve ser feita **nos dois arquivos** (repositório e máquina Windows).  
> Não execute este `.bat` pelo VS Code ou terminal Linux — ele deve rodar no Windows.

### O que faz

1. Baixa via **SCP** somente as pastas de produção `diarios/`, `fim_mes/` e `questor/`:
   - **Origem:** `usuario@10.1.0.225:/home/usuario/raphael/projetos/backups_refeitorio/backups`
   - **Destino:** `C:\Users\usuario\estagio\producao\backups_refeitorio\backups\`
2. Após o download, remove de `%DESTINO%\diarios\` os backups de produção anteriores aos últimos 7 dias. `fim_mes\` e `questor\` ficam com o histórico completo.
3. `pre_restore/`, `manual_migrations/` e todo o ambiente de teste não são transferidos para o Windows.

### Retenção na máquina Windows

| Tipo              | Pasta          | Retenção                      |
|-------------------|----------------|--------------------------------|
| Diário            | `diarios\`     | Todos dos últimos 7 dias      |
| Fim de mês        | `fim_mes\`     | Todos (não são removidos)     |
| Questor           | `questor\`     | Todos (não são removidos)     |
| Pré-restauração   | —              | Não é baixado                 |
| Migração manual   | —              | Não é baixado                 |
| Ambiente de teste | —              | Não é baixado                 |

---

## Restauração

**Arquivo:** `scripts/restaurar_backup.sh`  
Deve ser executado no servidor Linux.

### Uso

```bash
# Menu interativo (lista backups e pede escolha)
./scripts/restaurar_backup.sh

# Restaurar o backup mais recente
./scripts/restaurar_backup.sh latest

# Restaurar um arquivo específico
./scripts/restaurar_backup.sh /caminho/para/arquivo.sql

# Apenas listar backups disponíveis
./scripts/restaurar_backup.sh --list

# Simular sem executar (dry-run)
./scripts/restaurar_backup.sh --dry-run latest

# Restaurar sem criar backup de segurança antes
./scripts/restaurar_backup.sh --no-pre-backup latest
```

### Comportamento padrão

Por padrão, antes de restaurar, o script cria automaticamente um **backup de segurança** do estado atual do banco:
```
backups/pre_restore/pre_restore_refeitorio_snd_YYYY-MM-DD_HH-MM-SS.sql
```
Se precisar desfazer a restauração, basta restaurar esse arquivo.

O menu de restauração lista os backups de `diarios/`, `fim_mes/` e, por compatibilidade, os backups antigos ainda presentes na raiz. Os backups `questor/` são registros de auditoria de exportações específicas e não fazem parte do fluxo normal de disaster recovery.

---

## Troca de máquinas

### Cenário A — Servidor Linux muda de IP ou é substituído

O IP do servidor está hardcoded na variável `ORIGEM` do `.bat` no Windows.

**No novo servidor Linux:**

1. Clonar o repositório e configurar o `.env` normalmente.
2. Criar o diretório de backups:
   ```bash
   mkdir -p /home/usuario/raphael/projetos/backups_refeitorio/backups
   ```
3. Autorizar a chave SSH do INF010 para que o SCP continue funcionando:
   ```bash
   # Adicionar ao final do arquivo
   nano ~/.ssh/authorized_keys
   ```
   Copie a linha abaixo (chave pública do INF010 — já registrada no servidor atual):
   ```
   ssh-rsa AAAAB3NzaC1yc2EAAAADAQAB... usuario@INF010
   ```
   > A chave pública completa pode ser obtida rodando no INF010: `type C:\Users\usuario\.ssh\id_rsa.pub`
4. Configurar o crontab:
   ```bash
   crontab -e
   ```
   Adicionar:
   ```
   0 2 * * * /home/usuario/raphael/projetos/refeitorio/scripts/backup_mysql.sh
   ```

**No Windows INF010 (e no repositório):**

5. Editar a variável `ORIGEM` nos **dois arquivos** `.bat` com o novo IP:
   ```bat
   set "ORIGEM=usuario@NOVO_IP:/home/usuario/raphael/projetos/backups_refeitorio/backups/."
   ```
   - `C:\Users\usuario\estagio\producao\backups_refeitorio\baixar_backups.bat`
   - `scripts/baixar_backups.bat` (no repositório)

6. Testar rodando o `.bat` manualmente no Windows e verificar se os arquivos chegam em `%DESTINO%`.

---

### Cenário B — Máquina Windows muda (INF010 é substituído)

A nova máquina precisa de chave SSH própria registrada no servidor Linux.

**Na nova máquina Windows:**

1. Criar o diretório de destino dos backups:
   ```
   C:\Users\usuario\estagio\producao\backups_refeitorio\backups\
   ```
2. Copiar o `.bat` do repositório para:
   ```
   C:\Users\usuario\estagio\producao\backups_refeitorio\baixar_backups.bat
   ```
3. Gerar um par de chaves SSH (no Prompt de Comando ou PowerShell):
   ```cmd
   ssh-keygen -t rsa -b 4096 -C "usuario@NOME_NOVA_MAQUINA"
   ```
   Aceite o caminho padrão (`C:\Users\usuario\.ssh\id_rsa`).
4. Exibir a chave pública gerada:
   ```cmd
   type C:\Users\usuario\.ssh\id_rsa.pub
   ```
   Copie a linha inteira.

**No servidor Linux:**

5. Adicionar a chave pública da nova máquina ao `authorized_keys`:
   ```bash
   nano ~/.ssh/authorized_keys
   # Cole a linha copiada no passo 4
   ```
6. Se a máquina antiga (INF010) for descontinuada, remover a linha `usuario@INF010` do mesmo arquivo.
7. Testar a conexão SSH a partir da nova máquina Windows:
   ```cmd
   ssh usuario@10.1.0.225
   ```
   Deve conectar sem pedir senha.

**Nos arquivos `.bat` (repositório e nova máquina Windows):**

8. Atualizar o comentário com o nome e IP da nova máquina:
   ```bat
   REM Este arquivo é uma cópia do original que fica no NOVA_MAQUINA (NOVO_IP) em C:\...
   ```
9. Configurar o **Agendador de Tarefas do Windows** na nova máquina para executar o `.bat` no horário desejado.
10. Testar rodando o `.bat` manualmente e verificar se os arquivos chegam corretamente.

---

## Localização dos arquivos de backup

| Local                                                                          | Descrição                        |
|--------------------------------------------------------------------------------|----------------------------------|
| `/home/usuario/raphael/projetos/backups_refeitorio/backups/` (servidor Linux) | Produção — todas as categorias |
| `/home/usuario/raphael/projetos/backups_refeitorio_teste/backups/` (servidor Linux) | Teste — isolado e não transferido |
| `C:\Users\usuario\estagio\producao\backups_refeitorio\backups\` (Windows INF010)| Cópia secundária de `diarios/`, `fim_mes/` e `questor/` de produção |

Produção e teste devem usar diretórios separados. O `.env` de `refeitorio` aponta para `backups_refeitorio/backups`; o de `refeitorio-teste`, para `backups_refeitorio_teste/backups`.
