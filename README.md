# InventAI - Controle de Finanças com Sugestões de IA

Um sistema web de controle de despesas e ganhos pessoais com recomendações inteligentes geradas por IA (Google Gemini API).

## 🎯 Funcionalidades

- **Controle de Despesas**: Registrar, visualizar, atualizar e deletar despesas
- **Controle de Ganhos**: Registrar, visualizar, atualizar e deletar ganhos
- **Autenticação**: Login seguro com sessões PHP
- **Sugestões de IA**: Análise inteligente de despesas e recomendações de economia usando Google Gemini API
- **Interface Web**: Frontend responsivo em JavaScript/HTML/CSS

## 🏗️ Arquitetura

### Backend
```
backend/
├── api/
│   └── ai_suggestion.php    # Endpoint de sugestões de IA
└── (Controllers e Services em estrutura MVC)
```

O backend utiliza:
- **PHP 8.1+** com orientação a objetos
- **SQLite** (PDO) para persistência de dados
- **Google Gemini API** para análise inteligente
- **Padrão MVC** com Controllers e Services

### Frontend
```
Front/
├── index.php               # Página principal
├── api/                    # Chamadas AJAX
│   ├── auth/              # Autenticação
│   ├── despesas/          # Operações CRUD de despesas
│   └── ganhos/            # Operações CRUD de ganhos
└── assets/
    ├── css/               # Estilos
    └── js/                # Scripts JavaScript
```

### Banco de Dados (SQLite)
```
backend/database/
├── conexao.php            # Abre a conexão ($conexao) usada pelas APIs
├── Database.php           # Localiza/cria o arquivo .sqlite e aplica o schema
├── SqliteCompat.php       # API estilo mysqli sobre PDO SQLite + tradução de SQL
├── schema_sqlite.sql      # Estrutura das tabelas
├── seed_sqlite.sql        # Dados de exemplo (opcional, DB_SEED=true)
└── data/investai.sqlite   # Criado automaticamente (ignorado pelo git)
```

O banco é um único arquivo. Na primeira execução o schema é aplicado sozinho —
não há nada para importar nem servidor de banco para subir.

## 🚀 Instalação e Configuração

### Pré-requisitos
- PHP 8.1+ com as extensões `pdo_sqlite`, `curl` e `mbstring` (ou Docker)
- Chave da Google Gemini API (ou Ollama local)

### Rodando localmente (terminal)

1. **Configure o ambiente**
   ```bash
   cp .env.example .env     # Windows: copy .env.example .env
   # edite .env: GEMINI_API_KEY, ADMIN_EMAIL, ADMIN_PASSWORD...
   ```

2. **Suba o servidor embutido do PHP**
   ```bash
   php -S localhost:8000 router.php
   # Acesse: http://localhost:8000
   ```
   O `router.php` faz o papel do `.htaccess` (bloqueia `.env`, `.sqlite`, `.sql`...).

Para recomeçar do zero, apague `backend/database/data/investai.sqlite`.

### Rodando com Docker
```bash
docker compose up --build
# Acesse: http://localhost:8080
```

## ☁️ Deploy na Vercel

O projeto já está pronto para importar do GitHub (`vercel.json` + `api/index.php`,
usando o runtime comunitário [vercel-php](https://github.com/vercel-community/php)).

1. Suba o repositório para o GitHub.
2. Na Vercel: **Add New → Project → Import** o repositório (sem framework, sem build command).
3. Em **Settings → Environment Variables** cadastre as mesmas chaves do `.env.example`
   (`AI_PROVIDER=gemini`, `GEMINI_API_KEY`, `ADMIN_EMAIL`, `ADMIN_PASSWORD`, `CRON_TOKEN`, `APP_URL`...).
4. Deploy.

> ⚠️ **Limitação importante:** o sistema de arquivos da Vercel é somente leitura, exceto `/tmp`,
> que é **temporário e por instância**. Na Vercel o SQLite é criado em `/tmp/investai.sqlite`:
> funciona para demonstração, mas **os dados somem** quando a função reinicia/escala e
> as sessões podem se perder entre instâncias. Para dados persistentes em produção,
> use um banco hospedado (ex.: Turso/libSQL, que é compatível com SQLite).

## 📁 Estrutura de Pastas

```
inventai/
├── backend/
│   └── api/
│       └── ai_suggestion.php      # Sugestões de IA
├── db/
│   ├── conexao.php                # Conexão SQLite
│   ├── schema.sql                 # Estrutura DB
│   └── seed.sql                   # Dados iniciais
├── Front/
│   ├── index.php                  # Página principal
│   ├── api/
│   │   ├── auth/                  # Login/autenticação
│   │   ├── despesas/              # CRUD despesas
│   │   └── ganhos/                # CRUD ganhos
│   └── assets/
│       ├── css/style.css          # Estilos
│       └── js/main.js             # Scripts
└── README.md                       # Este arquivo
```

## 💰 Como Usar

### 1. Autenticação
- Acesse `http://localhost/inventai`
- Faça login com suas credenciais

### 2. Registrar Despesa
- No painel, clique em "Nova Despesa"
- Preencha descrição, valor e categoria
- Sistema registra a data automaticamente

### 3. Registrar Ganho
- Clique em "Novo Ganho"
- Preencha descrição, valor e fonte
- Dados são salvos no banco

### 4. Obter Sugestões de IA
- Clique em "Sugestões de IA"
- Sistema analisa suas despesas e ganhos
- Receba recomendações inteligentes de economia

## 🤖 Sugestões de IA

O sistema utiliza Google Gemini API para:
- Analisar padrões de gastos
- Identificar áreas de economia
- Propor categorização automática
- Fornecer dicas personalizadas

### Requisição de Exemplo
```javascript
fetch('backend/api/ai_suggestion.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        despesas: [...],
        ganhos: [...]
    })
})
.then(res => res.json())
.then(data => console.log(data.sugestoes));
```

## 🔒 Segurança

- Senhas com hash bcrypt
- Sessões PHP com cookies HttpOnly
- Validação de entrada em backend
- Proteção CSRF com tokens
- Queries preparadas contra SQL injection

## 🛠️ Tecnologias

**Backend:**
- PHP 8.1+
- SQLite 3 (via PDO)
- Google Gemini API

**Frontend:**
- HTML5
- CSS3
- JavaScript (vanilla)

**DevOps:**
- Servidor embutido do PHP (`php -S`) ou Docker
- Vercel (vercel-php)

## 📝 APIs Principais

### Autenticação
- `POST /Front/api/auth/login.js` - Login
- `POST /Front/api/auth/auth.js` - Validação de sessão

### Despesas
- `GET /Front/api/despesas/read.js` - Listar despesas
- `POST /Front/api/despesas/create.js` - Criar despesa
- `PUT /Front/api/despesas/update.js` - Atualizar despesa
- `DELETE /Front/api/despesas/delete.js` - Deletar despesa

### Ganhos
- `GET /Front/api/ganhos/read.js` - Listar ganhos
- `POST /Front/api/ganhos/create.js` - Criar ganho
- `PUT /Front/api/ganhos/update.js` - Atualizar ganho
- `DELETE /Front/api/ganhos/delete.js` - Deletar ganho

### IA
- `POST /backend/api/ai_suggestion.php` - Obter sugestões

## 🤝 Contribuindo

Para melhorias no projeto:
1. Faça suas alterações
2. Teste antes de enviar
3. Mantenha a estrutura MVC
4. Documente mudanças significativas

## 📄 Licença

Este projeto é de uso pessoal/educacional.

## 📞 Suporte

Para dúvidas ou problemas:
- Verifique se `php -S localhost:8000 router.php` está rodando
- Confirme que a pasta `backend/database/data` é gravável
- Teste a conexão com Google Gemini API
- Verifique os logs de erro do Apache

---

**Versão:** 1.0  
**Última atualização:** 2026-03-28  
**Status:** Em desenvolvimento
