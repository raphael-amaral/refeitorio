 ## Atualização: `/scanner/excecao` — validação de credenciais

  ### O que mudou
  
  O endpoint passou a exigir o campo `senha` além de `matricula`.
  Antes de registrar a exceção, a API deve autenticar as credenciais do funcionário.

  ---

  ### Request

  | Campo | Tipo | Descrição |
  |---|---|---|
  | `matricula` | string | Matrícula do funcionário que vai receber a exceção |
  | `senha` | string | Senha do funcionário (mesmas credenciais do sistema) |

  ---

  ### Fluxo interno atualizado

  matricula + senha recebidos
    → se matricula ou senha vazios          → 422
    → fora do horário limite (13:30)        → 422
    → busca funcionário por matrícula       → se não encontrar → 404
    → valida senha do funcionário           → se inválida → 401
    → verifica refeição já registrada hoje  → se sim → 400
    → busca cardápio de almoço de hoje      → se não existir → 404
    → INSERT status='concluido'             → 200 ✓

  ---

  ### Responses adicionais

  **401 Unauthorized — credenciais inválidas**
  ```json
  { "ok": false, "erro": "Credenciais inválidas." }

  ---
  Responses completas do endpoint

  ┌────────┬───────────────────────────────────────────────────────────────────────┐
  │ Status │                               Situação                                │
  ├────────┼───────────────────────────────────────────────────────────────────────┤
  │ 200    │ Exceção registrada com sucesso                                        │
  ├────────┼───────────────────────────────────────────────────────────────────────┤
  │ 400    │ Refeição já registrada hoje                                           │
  ├────────┼───────────────────────────────────────────────────────────────────────┤
  │ 401    │ Token Bearer ausente/inválido ou credenciais do funcionário inválidas │
  ├────────┼───────────────────────────────────────────────────────────────────────┤
  │ 404    │ Funcionário não encontrado ou sem cardápio de almoço hoje             │
  ├────────┼───────────────────────────────────────────────────────────────────────┤
  │ 422    │ Matrícula ou senha não informadas ou fora do horário (após 13:30)     │
  └────────┴───────────────────────────────────────────────────────────────────────┘
