# Migrations de banco de dados

O projeto controla alterações de schema pelos arquivos SQL de `database/migrations/` e pela tabela `schema_migrations` de cada banco.

O comando deve ser executado dentro do container web, porque ele possui o driver `pdo_mysql` e usa o `.env` do ambiente correto.

## Consultar o estado

```bash
docker compose exec -T web php bin/migrate status
```

Estados possíveis:

- `aplicada`: arquivo e checksum conferem com o histórico do banco;
- `pendente`: arquivo ainda não executado nem registrado;
- `alterada`: arquivo foi modificado depois de registrado; o deploy é bloqueado;
- `arquivo_ausente`: existe histórico no banco, mas o arquivo não está mais no repositório; o deploy é bloqueado.

## Aplicar migrations pendentes

Faça e valide um backup completo antes. A escrita exige a confirmação explícita do banco:

```bash
docker compose exec -T web php bin/migrate up --database=refeitorio_teste
```

Em produção, use obrigatoriamente o nome de produção:

```bash
docker compose exec -T web php bin/migrate up --database=refeitorio_snd
```

O comando mostra o banco selecionado, obtém um lock exclusivo no MySQL, valida todo o histórico e só então executa as migrations pendentes em ordem alfabética. Uma segunda execução não reaplica migrations.

## Baseline de instalações existentes

`baseline` registra uma migration sem executar seu SQL. Ele existe apenas para adotar o controle em bancos que já receberam uma alteração manualmente.

Antes do baseline, confirme manualmente que o schema esperado já existe. Exemplo para a migration atual:

```sql
SELECT DATABASE();
SHOW COLUMNS FROM questor_exportacoes LIKE 'arquivo_backup';
```

Depois registre exatamente o arquivo verificado:

```bash
docker compose exec -T web php bin/migrate baseline \
  20260807_add_arquivo_backup_questor_exportacoes.sql \
  --database=refeitorio_teste
```

Não use baseline para pular uma migration realmente pendente.

## Fluxo de deploy

1. Atualize o código e confirme a branch/commit.
2. Gere e valide um backup completo do banco.
3. Execute `php bin/migrate status` dentro do container.
4. Execute `php bin/migrate up --database=NOME_EXATO`.
5. Execute `status` novamente; todos os arquivos devem aparecer como `aplicada`.
6. Faça o teste funcional da aplicação relacionado à alteração.

## Criar uma migration

- Use o formato `AAAAMMDD_descricao_em_snake_case.sql`.
- Faça cada arquivo representar uma alteração coesa e somente para frente.
- Nunca edite nem remova uma migration já aplicada; crie outra migration corretiva.
- Atualize também `database/init.sql`, que representa o schema final de uma instalação nova.
- Registre no baseline de `database/init.sql` o nome e o SHA-256 da nova migration.

O MySQL faz commit implícito em várias operações DDL. Portanto, não presuma que uma transação conseguirá desfazer um `ALTER TABLE`; backup e migrations pequenas continuam obrigatórios.
