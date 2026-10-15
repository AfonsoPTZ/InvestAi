# Testes automatizados — Robot Framework

Testes de interface do InvestAI com [Robot Framework](https://robotframework.org) e a
biblioteca [Browser](https://robotframework-browser.org) (Playwright/Chromium).
Os resultados aparecem no dashboard **`/test`** (acesso livre, sem login).

## Estrutura

```text
tests-robot/
├── tests/
│   ├── __init__.robot              # setup da suíte: navegador, usuário de teste, teardown com screenshot
│   ├── 01_pagina_inicial.robot     # T01
│   ├── 02_autenticacao.robot       # T02, T03
│   ├── 03_navegacao_protegida.robot# T04
│   └── 04_despesas.robot           # T05 (escrita)
├── resources/
│   ├── config.py                   # variáveis lidas do ambiente / .env
│   ├── keywords.robot              # keywords compartilhadas
│   └── TestSupport.py              # criação do usuário de teste (stdlib)
├── runner/
│   ├── executar.py                 # roda a suíte com argumentos fixos e gera summary.json
│   ├── server.py                   # executor HTTP interno usado pelo dashboard /test
│   └── Dockerfile                  # imagem do serviço robot-runner
├── results/                        # saídas (não versionadas)
├── requirements.txt
└── .env.example
```

## Os cinco cenários

| ID  | Teste | O que verifica | Escreve dados? |
|-----|-------|----------------|----------------|
| T01 | Página inicial carrega elementos essenciais | título, `h1`, botão "Entrar" e navegação até o login | não |
| T02 | Login válido acessa o dashboard | alerta de sucesso, redirecionamento, nome do usuário na navbar | não |
| T03 | Login inválido exibe erro e não autentica | alerta "E-mail ou senha incorretos.", permanece no login, dashboard bloqueado | não |
| T04 | Página restrita exige autenticação | anônimo é redirecionado e a API `despesas/read.php` responde 401; depois do login a página abre | não |
| T05 | Cadastrar e excluir despesa | cria uma despesa pelo formulário, confere valor e categoria na lista e a exclui | **sim** (só os dados do usuário de teste) |

## Configuração

```bash
cp tests-robot/.env.example tests-robot/.env
```

| Variável | Uso |
|----------|-----|
| `ROBOT_ENV` | `local`, `homolog` ou `production`. Em `production`, o usuário de teste **não** é criado e o T05 é pulado. |
| `ROBOT_BASE_URL` | URL-base do site (padrão `http://localhost:8080`). No docker-compose, é sobrescrita para `http://investai-web`. |
| `ROBOT_USER_EMAIL` / `ROBOT_USER_PASSWORD` / `ROBOT_USER_NAME` | Usuário **dedicado** de teste. Sem eles, T02, T04 e T05 ficam como "não executado". |
| `ROBOT_CREATE_TEST_USER` | `true` cadastra o usuário de teste pela API se ele não existir. |
| `ROBOT_ALLOW_WRITE` | `true` habilita o T05. |
| `ROBOT_HEADLESS`, `ROBOT_BROWSER`, `ROBOT_TIMEOUT` | Navegador. |
| `RUNNER_MAX_SECONDS`, `RUNNER_KEEP_RUNS` | Timeout de uma execução e quantas execuções manter. |

No `.env` da raiz do projeto (aplicação):

| Variável | Uso |
|----------|-----|
| `TEST_RUNNER_URL` | `http://robot-runner:8270` no docker-compose. Vazio desabilita a execução pelo dashboard. |
| `TEST_RUNNER_TOKEN` | Segredo compartilhado entre o app e o executor (≥ 16 caracteres). |

## Executar

### Com Docker (recomendado)

```bash
docker compose up -d --build                                   # app, banco e robot-runner
docker compose exec robot-runner python3 runner/executar.py    # roda pelo terminal
```

As execuções feitas assim aparecem no histórico do dashboard (volume `robot_results`).

### Localmente, com venv (requer Python ≥ 3.10 e Node.js ≥ 20)

```bash
cd tests-robot
python3 -m venv .venv && source .venv/bin/activate
pip install -r requirements.txt
rfbrowser init chromium          # baixa o Playwright e o Chromium
python runner/executar.py        # ou: robot --outputdir results/manual tests
```

Para rodar só uma suíte ou tag: `robot --outputdir results/manual --include smoke tests`.

## Dashboard `/test`

1. Abra `http://localhost:8080/test` (não precisa de login).
2. **Executar testes** dispara a suíte real; a página se atualiza sozinha ao final.

O dashboard mostra os cards de resumo, a lista de cenários, os detalhes de cada teste (etapas,
esperado/obtido, erro e screenshot), o histórico e links para o `report.html`/`log.html`.

### Segurança

- A rota e os endpoints (`backend/api/testes/*`) são **públicos**: qualquer visitante vê os resultados e pode disparar execuções. Disparar exige o token CSRF gerado na sessão da página.
- Em produção, deixe `TEST_RUNNER_URL` vazio (ou não publique o executor): sem ele, ninguém consegue rodar testes pelo site.
- O executor não aceita comandos, caminhos nem argumentos: roda sempre a mesma suíte, uma execução por vez (409 se já houver uma) e com timeout.
- O `robot-runner` não publica porta. Só o app o alcança, pela rede interna, com `X-Runner-Token`.
- Os arquivos servidos passam por uma lista fixa: `report.html`, `log.html` e `screenshots/*.png`.
- `tests-robot/` fica fora da imagem de produção (`.dockerignore`) e é bloqueada no Apache (`.htaccess`).

## Lendo os relatórios

Cada execução fica em `results/<AAAAMMDD-HHMMSS>/`:

- `report.html`: visão geral e estatísticas por suíte e tag.
- `log.html`: cada keyword executada, com argumentos, tempos, mensagens e screenshots.
- `screenshots/falha-N.png`: captura da página no momento da falha.
- `summary.json`: resumo consumido pelo dashboard.
- `console.txt`: saída do console da execução.

Status: **PASS/aprovado**, **FAIL/falhou**, **SKIP/não executado** (o motivo aparece na mensagem; por exemplo, credenciais ausentes ou escrita desabilitada).

## Produção

O deploy (Railway) usa apenas o `Dockerfile` da aplicação, sem Python nem navegador. Lá,
`TEST_RUNNER_URL` fica vazio e o `/test` informa que o executor não está configurado. Para validar
a URL pública, rode pelo terminal apenas os testes de leitura:

```bash
ROBOT_ENV=production ROBOT_BASE_URL=https://seu-app.up.railway.app \
  robot --outputdir results/prod --include leitura tests
```
