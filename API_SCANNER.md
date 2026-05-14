# API Scanner — Refeitório SND

Documentação dos endpoints consumidos pelo aplicativo Android do scanner de crachás.

---

## Visão geral

| Item | Detalhe |
|---|---|
| Formato das requisições | `application/x-www-form-urlencoded` (form-data) |
| Formato das respostas | `application/json` |
| Autenticação | Bearer Token fixo (exceto `/api/login`) |
| Codificação | UTF-8 |

Todas as respostas seguem o padrão:

```json
{ "ok": true,  ... }   // sucesso
{ "ok": false, "erro": "mensagem explicativa" }  // erro
```

---

## Autenticação

Os endpoints de scanner (exceto `/api/login`) exigem o header:

```
Authorization: Bearer <SCANNER_TOKEN>
```

O token é obtido na resposta do `/api/login` e permanece válido indefinidamente — não expira. Trate-o como uma senha: não logue, não exponha em código-fonte versionado.

---

## Endpoints

### 1. Login

```
POST /api/login
```

Autentica um operador do scanner (perfil `snd` ou `dev`). Retorna o token de acesso.

**Não exige Authorization header.**

#### Request

| Campo | Tipo | Descrição |
|---|---|---|
| `usuario` | string | Matrícula do operador |
| `senha` | string | Senha do operador |

#### Responses

**200 OK — sucesso**
```json
{
  "ok": true,
  "nome": "Maria da Silva",
  "token": "Bearer abc123token"
}
```

**400 Bad Request — campos vazios**
```json
{ "ok": false, "erro": "Preencha matrícula e senha." }
```

**401 Unauthorized — credenciais inválidas ou perfil sem acesso**
```json
{ "ok": false, "erro": "Credenciais inválidas." }
```

**429 Too Many Requests — muitas tentativas**
```json
{ "ok": false, "erro": "Muitas tentativas. Tente novamente mais tarde." }
```

> **Rate limiting:** bloqueio após 8 tentativas em 5 minutos. O bloqueio dura 5 minutos.
> O bloqueio é feito por IP **e** por matrícula — ambos precisam estar desbloqueados.

---

### 2. Processar leitura de crachá

```
POST /scanner/processar
Authorization: Bearer <token>
```

Recebe uma matrícula lida pelo scanner, verifica se o funcionário tem agendamento para hoje e marca a refeição como concluída.

#### Request

| Campo | Tipo | Descrição |
|---|---|---|
| `matricula` | string | Matrícula do funcionário |

#### Responses

**200 OK — refeição registrada com sucesso**
```json
{
  "ok": true,
  "nome": "João da Silva",
  "refeicao": "Almoço"
}
```

**400 Bad Request — refeição já registrada hoje**
```json
{ "ok": false, "erro": "Refeição já registrada hoje." }
```

**400 Bad Request — agendamento cancelado**
```json
{ "ok": false, "erro": "Agendamento está cancelado." }
```

**401 Unauthorized — token ausente ou inválido**
```json
{ "ok": false, "erro": "Acesso negado pelo aplicativo." }
```

**404 Not Found — sem agendamento para hoje**
```json
{ "ok": false, "erro": "Nenhum agendamento encontrado para hoje." }
```

**422 Unprocessable — matrícula não enviada**
```json
{ "ok": false, "erro": "Matrícula não informada." }
```

#### Fluxo interno

```
matrícula recebida
  → busca registro de hoje no banco (status ASC, prioriza 'agendado')
  → se não encontrar       → 404
  → se status = concluido  → 400 (já comeu)
  → se status = cancelado  → 400
  → se status = agendado   → UPDATE status='concluido' → 200 ✓
```

---

### 3. Registrar exceção

```
POST /scanner/excecao
Authorization: Bearer <token>
```

Registra a refeição de um funcionário **sem agendamento prévio**. Ignora o horário limite de agendamento (`LIMIT_TIME`) e cria o registro diretamente como concluído.

A exceção é **sempre vinculada ao almoço do dia** e só pode ser usada até **13:30** (configurável via `EXCECAO_LIMIT_TIME` no servidor).

#### Request

| Campo | Tipo | Descrição |
|---|---|---|
| `matricula` | string | Matrícula do funcionário que vai receber a exceção |
| `senha` | string | Senha do funcionário (mesmas credenciais do sistema web) |

#### Responses

**200 OK — exceção registrada com sucesso**
```json
{
  "ok": true,
  "nome": "Carlos Pereira",
  "refeicao": "Almoço"
}
```

**400 Bad Request — refeição já registrada hoje**
```json
{ "ok": false, "erro": "Refeição já registrada hoje." }
```

**401 Unauthorized — token Bearer ausente/inválido ou credenciais do funcionário inválidas**
```json
{ "ok": false, "erro": "Acesso negado pelo aplicativo." }
{ "ok": false, "erro": "Credenciais inválidas." }
```

**403 Forbidden — funcionário não é do tipo comum (snd, dp ou dev)**
```json
{ "ok": false, "erro": "Exceção disponível apenas para funcionários comuns." }
```

**404 Not Found — funcionário não cadastrado**
```json
{ "ok": false, "erro": "Funcionário não encontrado." }
```

**404 Not Found — sem cardápio de almoço hoje**
```json
{ "ok": false, "erro": "Nenhum cardápio de almoço cadastrado para hoje." }
```

**422 Unprocessable — fora do horário ou campos vazios**
```json
{ "ok": false, "erro": "Exceções encerradas após 13:30." }
{ "ok": false, "erro": "Matrícula e senha são obrigatórias." }
```

#### Diferenças em relação ao `/scanner/processar`

| Comportamento | `/processar` | `/excecao` |
|---|---|---|
| Exige agendamento prévio | Sim | **Não** |
| Respeita `LIMIT_TIME` | Sim | **Não** |
| Tem prazo próprio | Não | **Sim — 13:30** |
| Refeição | Almoço ou Jantar (o que estiver agendado) | **Sempre Almoço** |
| Registrado em log de auditoria | Sim | **Sim (`scanner.excecao`)** |

---

### 4. Lista de hoje

```
GET /scanner/lista-hoje
Authorization: Bearer <token>
```

Retorna todos os registros do dia atual (todos os status, todos os funcionários). Útil para exibir uma lista de presença no app.

#### Request

Sem parâmetros.

#### Responses

**200 OK**
```json
{
  "ok": true,
  "lista": [
    {
      "matricula": "12345",
      "nome_usuario": "João da Silva",
      "tipo_refeicao": "Almoço",
      "status": "concluido"
    },
    {
      "matricula": "67890",
      "nome_usuario": "Ana Souza",
      "tipo_refeicao": "Almoço",
      "status": "agendado"
    }
  ]
}
```

**401 Unauthorized — token ausente ou inválido**
```json
{ "ok": false, "erro": "Acesso negado pelo aplicativo." }
```

#### Valores possíveis para `status`

| Valor | Significado |
|---|---|
| `agendado` | Agendou mas ainda não passou pelo scanner |
| `concluido` | Refeição registrada (scanner ou exceção) |
| `cancelado` | Agendamento cancelado pelo funcionário |

---

## Resumo dos endpoints

| Método | Endpoint | Auth | Descrição |
|---|---|---|---|
| `POST` | `/api/login` | Nenhuma | Autentica o operador e retorna o token |
| `POST` | `/scanner/processar` | Bearer | Lê crachá e marca refeição como concluída |
| `POST` | `/scanner/excecao` | Bearer | Registra refeição sem agendamento (exceção) |
| `GET` | `/scanner/lista-hoje` | Bearer | Retorna todos os registros do dia |

---

## Exemplo de fluxo completo (Kotlin)

```kotlin
// 1. Login
val loginResponse = api.login(usuario = "1234", senha = "senha123")
val token = loginResponse.token  // "Bearer abc123token"

// 2. Leitura de crachá (fluxo normal)
val result = api.processar(
    authorization = token,
    matricula = barcodeScanned
)

if (result.ok) {
    showSuccess("${result.nome} — ${result.refeicao}")
} else {
    showError(result.erro)
}

// 3. Exceção (funcionário sem agendamento)
val result = api.excecao(
    authorization = token,
    matricula = barcodeScanned
)
```
