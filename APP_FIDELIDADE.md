# Programa de fidelidade — integração no aplicativo

Documento para o time do **app** (**Ionic** + **Angular**). O backend já expõe os endpoints abaixo na pasta `/app/`. Copie este arquivo para o repositório do aplicativo se quiser manter a documentação junto do código do app.

---

## 1. Configuração

| Item | Observação |
|------|------------|
| **URL base** | Mesma base já usada nas outras APIs (ex.: `https://SEU_DOMINIO/app/`). Ajuste por ambiente (homologação / produção). |
| **Autenticação** | Campo `token` no **corpo JSON**, com o mesmo valor `TOKEN` usado hoje em `reservas_save`, `login`, etc. (definido no servidor). |
| **Formato** | `POST` com `Content-Type: application/json` e corpo em JSON (`php://input`). Query string não é usada para estes endpoints. |
| **Usuário** | Sempre enviar `id_usuario` numérico do cliente logado (o mesmo `id` retornado no login / cadastro). |

**Exemplo de corpo genérico:**

```json
{
  "token": "<TOKEN_CONFIGURADO_NO_APP>",
  "id_usuario": 123
}
```

---

## 2. Endpoints

### 2.1 Saldo e resumo — `fidelidade_saldo.php`

**Objetivo:** tela inicial da fidelidade (saldo, totais, textos das regras).

**POST** `{BASE}/fidelidade_saldo.php`

**Body:**

| Campo | Obrigatório | Tipo | Descrição |
|-------|-------------|------|-----------|
| `token` | sim | string | Token da API |
| `id_usuario` | sim | int | ID do usuário logado |

**Resposta `200` — sucesso:**

```json
{
  "result": "success",
  "saldo": 1150,
  "total_ganho": 2070,
  "total_gasto": 920,
  "programa_ativo": true,
  "regras": {
    "pontos_por_real": 1,
    "resgate_suite_pontos": 920,
    "resgate_alimento_pontos": 300,
    "resgate_bebida_pontos": 150,
    "valor_ponto_reais": 0.25,
    "pontos_por_real_em_resgate": 4,
    "mensagem": "Faça até 5 reservas pelo aplicativo, junte 920 pontos e troque por uma nova reserva da mesma suíte.",
    "regras": [
      "Acúmulo exclusivo em reservas de suíte feitas pelo aplicativo.",
      "R$ 1,00 gasto em reserva (pagamento aprovado) = 1 ponto.",
      "..."
    ]
  }
}
```

- Se `programa_ativo` for `false`, as tabelas de fidelidade ainda não existem no banco (ou falha de leitura). Exiba mensagem amigável e oculte resgate; `saldo` / totais virão zerados.
- Use `regras.mensagem` e `regras.regras[]` para a seção “Como funciona”.

**Erros:**

| HTTP | `result` | Quando |
|------|----------|--------|
| 401 | `error` | `token` inválido |
| 400 | `error` | `id_usuario` ausente ou inválido |

---

### 2.2 Extrato — `fidelidade_extrato.php`

**Objetivo:** lista cronológica de ganhos e gastos de pontos.

**POST** `{BASE}/fidelidade_extrato.php`

**Body:**

| Campo | Obrigatório | Tipo | Descrição |
|-------|-------------|------|-----------|
| `token` | sim | string | Token da API |
| `id_usuario` | sim | int | ID do usuário |
| `limite` | não | int | Padrão `50`, máximo `200` |

**Resposta `200` — programa ativo:**

```json
{
  "result": "success",
  "programa_ativo": true,
  "movimentacoes": [
    {
      "id": 12,
      "pontos": 230,
      "tipo": "credito_reserva_app",
      "descricao": "Pontos por reserva paga (cód. ABC12345)",
      "id_reserva": 45,
      "id_resgate": null,
      "criado_em": "2026-04-01 14:30:00"
    },
    {
      "id": 11,
      "pontos": -920,
      "tipo": "debito_resgate_suite",
      "descricao": "Resgate: reserva da mesma suíte",
      "id_reserva": null,
      "id_resgate": 3,
      "criado_em": "2026-03-28 10:00:00"
    }
  ]
}
```

**Tipos (`tipo`) para rótulos na UI (sugestão):**

| Valor | Significado sugerido na UI |
|-------|----------------------------|
| `credito_reserva_app` | Pontos ganhos — reserva paga |
| `debito_resgate_suite` | Pontos usados — resgate suíte |
| `debito_resgate_alimento` | Pontos usados — alimentação |
| `debito_resgate_bebida` | Pontos usados — bebida |
| `ajuste_admin` | Ajuste manual (se existir no futuro) |

- `pontos` **positivo** = crédito; **negativo** = débito.
- Formate `criado_em` no fuso do app (ex.: `America/Sao_Paulo`).

**Programa inativo:** mesma estrutura com `movimentacoes: []` e `programa_ativo: false`.

---

### 2.3 Regras só leitura (cache) — `fidelidade_info.php`

**Objetivo:** carregar textos e números das regras **sem** precisar de `id_usuario` (ex.: onboarding ou tela pública antes do login).

**POST** `{BASE}/fidelidade_info.php`

**Body:** apenas `token`.

**Resposta:**

```json
{
  "result": "success",
  "programa_ativo": true,
  "regras": { "... mesmo objeto da seção 2.1 ..." }
}
```

---

### 2.4 Resgate — `fidelidade_resgatar.php`

**Objetivo:** debitar pontos e registrar pedido para o motel atender (status inicia como **pendente** no painel).

**POST** `{BASE}/fidelidade_resgatar.php`

**Body:**

| Campo | Obrigatório | Tipo | Descrição |
|-------|-------------|------|-----------|
| `token` | sim | string | Token da API |
| `id_usuario` | sim | int | ID do usuário |
| `tipo` | sim | string | `suite`, `alimento` ou `bebida` (minúsculo) |
| `id_suite` | condicional | int | **Obrigatório** se `tipo` = `suite` — mesmo `id` da suíte usado em reservas / listagem de suítes |

**Regras de negócio (validadas no servidor):**

- **Suíte:** custo **920** pontos; o usuário precisa já ter **pelo menos uma reserva** (`reservas.id_usuario` + `id_suite`) naquela suíte.
- **Alimento:** custo **300** pontos (configurável no backend).
- **Bebida:** custo **150** pontos (configurável no backend).
- Saldo deve ser ≥ custo; caso contrário retorna erro.

**Resposta `200` — sucesso:**

```json
{
  "result": "success",
  "message": "Resgate registrado. Aguarde confirmação no local (sujeito à disponibilidade).",
  "id_resgate": 5,
  "pontos_debitados": 920,
  "saldo_atual": 230
}
```

**Resposta `422` — regra de negócio:**

```json
{
  "result": "error",
  "message": "Saldo de pontos insuficiente para este resgate."
}
```

Outras mensagens possíveis: suíte não informada, suíte sem histórico de reserva, tipo inválido, programa inativo.

**Resposta `400`:** falta `id_usuario` ou `tipo`.

---

## 3. Sugestão de fluxo de telas no app

1. **Entrada** — ícone ou item de menu “Fidelidade” / “Meus pontos”.
2. **Resumo** — chamar `fidelidade_saldo.php` ao abrir; mostrar `saldo`, `total_ganho`, `total_gasto` e a `regras.mensagem`.
3. **Extrato** — botão “Ver extrato” → `fidelidade_extrato.php` (paginação opcional: aumentar `limite` ou novas chamadas com cursor por `id` no futuro, se o backend evoluir).
4. **Resgate** — três ações ou cards:
   - Suíte: listar apenas suítes que o usuário **já reservou** (use o histórico de reservas que o app já carrega ou um endpoint existente) e enviar `id_suite` no resgate.
   - Alimento / Bebida: botões com custo vindo de `regras.resgate_alimento_pontos` e `regras.resgate_bebida_pontos` (ou do primeiro load de `fidelidade_saldo`).
5. **Confirmação** — modal com texto legal curto (pontos não reembolsáveis automaticamente, sujeito a disponibilidade, etc.) antes do POST em `fidelidade_resgatar.php`.
6. **Após sucesso** — atualizar saldo localmente com `saldo_atual` ou refazer `fidelidade_saldo.php`.

---

## 4. CORS e OPTIONS

O servidor envia `Access-Control-Allow-Origin: *` nestes arquivos. No **Ionic** (Capacitor/Cordova), as requisições partem em geral do **WebView**; se a API estiver em outro domínio, o CORS do servidor continua relevante — alinhado ao padrão dos outros `.php` da pasta `app/`.

---

## 5. Checklist para o time do app (Ionic/Angular)

- [ ] Constante `BASE_URL` + paths `/fidelidade_*.php`
- [ ] Incluir `token` + `id_usuario` em todas as chamadas que exigem login
- [ ] Tratar `programa_ativo === false`
- [ ] Tratar HTTP 401, 400, 422 com `message` para exibir ao usuário
- [ ] Resgate de suíte: filtrar lista de suítes pelo histórico do usuário
- [ ] Após resgate bem-sucedido, atualizar saldo e extrato

---

## 6. Referência no repositório backend

| Arquivo | Função |
|---------|--------|
| `app/fidelidade_saldo.php` | Saldo + regras |
| `app/fidelidade_extrato.php` | Lista de movimentações |
| `app/fidelidade_info.php` | Regras sem usuário |
| `app/fidelidade_resgatar.php` | Resgate |
| `config/fidelidade.php` | Constantes de pontos e lógica server-side |
| `database/fidelidade_schema.sql` | Estrutura das tabelas (operacional / DBA) |
| `FIDELIDADE.md` | Regras de negócio detalhadas (marketing / produto) |

Qualquer dúvida sobre novos campos ou paginação do extrato, alinhar com o backend antes de publicar versão do app.
