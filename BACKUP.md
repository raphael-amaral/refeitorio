# Sistema de Backup — Refeitório SND

O backup opera em duas camadas independentes: o **servidor Linux** gera os dumps diariamente, e a **máquina Windows INF010** baixa e arquiva uma cópia local via SCP.

---

## Visão geral

```
[Servidor Linux 10.1.0.225]                  [Windows INF010 10.1.0.127]
  cron 02:00                                   Agendador de Tarefas
  backup_mysql.sh                              baixar_backups.bat
       │                                              │
       ▼                                             ▼
  /home/usuario/raphael/projetos/        C:\Users\usuario\estagio\producao\
    backups_refeitorio/backups/            backups_refeitorio\backups\
  (dump SQL do container Docker)         (cópia local dos arquivos .sql)
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
   /home/usuario/raphael/projetos/backups_refeitorio/backups/
     backup_refeitorio_snd_YYYY-MM-DD_HH-MM-SS.sql
   ```
4. Se for o **último dia do mês**, cria uma cópia especial com o nome do mês por extenso (em português):
   ```
   backup_refeitorio_snd_FIM_JULHO_YYYY.sql
   ```
5. Remove backups diários com **mais de 7 dias**. Backups `FIM_<MES>` (ex.: `FIM_JANEIRO`, `FIM_FEVEREIRO`) **nunca são removidos** automaticamente.

### Retenção no servidor Linux

| Tipo              | Retenção                   |
|-------------------|----------------------------|
| Diário            | 7 dias (os 7 mais recentes)|
| Fim de mês        | Indefinida (manual)        |
| Pré-restauração   | Indefinida (manual)        |

### Variáveis relevantes do `.env`

| Variável         | Fallback padrão      | Descrição              |
|------------------|----------------------|------------------------|
| `CONTAINER_NAME` | `refeitorio-db-1`    | Nome do container MySQL|
| `DB_USER`        | `root`               | Usuário do banco       |
| `DB_PASSWORD`    | —                    | Senha do banco         |
| `DB_DATABASE`    | `refeitorio_snd`     | Nome do banco          |

---

## Camada 2 — Agendador de Tarefas (Windows INF010)

**Arquivo original (ativo):** `C:\Users\usuario\estagio\producao\backups_refeitorio\baixar_backups.bat`  
**Arquivo no repositório:** `scripts/baixar_backups.bat` *(cópia de referência — não é executado diretamente)*

> Qualquer alteração no script deve ser feita **nos dois arquivos** (repositório e máquina Windows).  
> Não execute este `.bat` pelo VS Code ou terminal Linux — ele deve rodar no Windows.

### O que faz

1. Baixa via **SCP** todos os arquivos `.sql` do servidor Linux:
   - **Origem:** `usuario@10.1.0.225:/home/usuario/raphael/projetos/backups_refeitorio/backups/*.sql`
   - **Destino:** `C:\Users\usuario\estagio\producao\backups_refeitorio\backups\`
2. Após o download, **remove** do destino Windows os backups diários que não sejam de hoje nem de ontem — mantendo sempre:
   - Backup de **hoje**
   - Backup de **ontem**
   - Todos os backups **`FIM_<MES>`** (ex.: `FIM_JANEIRO`, `FIM_FEVEREIRO`)

### Retenção na máquina Windows

| Tipo              | Retenção                      |
|-------------------|-------------------------------|
| Diário            | Hoje + ontem (2 arquivos)     |
| Fim de mês        | Todos (não são removidos)     |

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
backups/pre_restore_refeitorio_snd_YYYY-MM-DD_HH-MM-SS.sql
```
Se precisar desfazer a restauração, basta restaurar esse arquivo.

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
   set "ORIGEM=usuario@NOVO_IP:/home/usuario/raphael/projetos/backups_refeitorio/backups/*.sql"
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
| `/home/usuario/raphael/projetos/backups_refeitorio/backups/` (servidor Linux)  | Fonte primária, gerada pelo cron |
| `C:\Users\usuario\estagio\producao\backups_refeitorio\backups\` (Windows INF010)| Cópia secundária baixada via SCP |
