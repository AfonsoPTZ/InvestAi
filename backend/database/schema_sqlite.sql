-- Schema SQLite do InvestAI (equivalente ao antigo schema MySQL).
-- Aplicado automaticamente por Database::connect() quando o arquivo .sqlite ainda não tem tabelas.
-- Todos os statements são idempotentes (IF NOT EXISTS).

-- =========================
-- 1. USUÁRIOS
-- =========================
CREATE TABLE IF NOT EXISTS usuarios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE COLLATE NOCASE,
    cpf CHAR(11) NOT NULL UNIQUE,
    telefone VARCHAR(20) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    ativo INTEGER NOT NULL DEFAULT 1,
    criado_em DATETIME DEFAULT (datetime('now','localtime')),
    atualizado_em DATETIME DEFAULT (datetime('now','localtime'))
);

-- =========================
-- 2. PERFIL FINANCEIRO
-- =========================
CREATE TABLE IF NOT EXISTS perfil_financeiro (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario_id INTEGER NOT NULL UNIQUE,
    renda_mensal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    saldo_inicial DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    possui_investimentos INTEGER NOT NULL DEFAULT 0,
    possui_patrimonio INTEGER NOT NULL DEFAULT 0,
    objetivo_financeiro VARCHAR(255),
    perfil_comportamento TEXT DEFAULT 'moderado' CHECK (perfil_comportamento IN ('conservador', 'moderado', 'gastador')),
    criado_em DATETIME DEFAULT (datetime('now','localtime')),
    atualizado_em DATETIME DEFAULT (datetime('now','localtime')),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- =========================
-- 3. CATEGORIAS (GANHOS E DESPESAS)
-- =========================
CREATE TABLE IF NOT EXISTS categorias (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario_id INTEGER NULL,
    nome VARCHAR(100) NOT NULL,
    tipo TEXT NOT NULL CHECK (tipo IN ('ganho', 'despesa')),
    criado_em DATETIME DEFAULT (datetime('now','localtime')),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- =========================
-- 4. GANHOS
-- =========================
CREATE TABLE IF NOT EXISTS ganhos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario_id INTEGER NOT NULL,
    descricao VARCHAR(255) NOT NULL,
    valor DECIMAL(12,2) NOT NULL,
    data_ganho DATE NOT NULL,
    fixo INTEGER NOT NULL DEFAULT 0,
    categoria_id INTEGER NULL,
    criado_em DATETIME DEFAULT (datetime('now','localtime')),
    atualizado_em DATETIME DEFAULT (datetime('now','localtime')),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL
);

-- =========================
-- 5. DESPESAS
-- =========================
CREATE TABLE IF NOT EXISTS despesas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario_id INTEGER NOT NULL,
    descricao VARCHAR(255) NOT NULL,
    valor DECIMAL(12,2) NOT NULL,
    data_despesa DATE NOT NULL,
    fixo INTEGER NOT NULL DEFAULT 0,
    categoria_id INTEGER NULL,
    criado_em DATETIME DEFAULT (datetime('now','localtime')),
    atualizado_em DATETIME DEFAULT (datetime('now','localtime')),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL
);

-- =========================
-- 6. NOTÍCIAS FINANCEIRAS
-- =========================
CREATE TABLE IF NOT EXISTS noticias_financeiras (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    titulo VARCHAR(255) NOT NULL,
    fonte VARCHAR(150) NOT NULL,
    url VARCHAR(500) NOT NULL UNIQUE,
    resumo TEXT,
    categoria VARCHAR(100),
    nivel_impacto TEXT DEFAULT 'baixo' CHECK (nivel_impacto IN ('baixo', 'medio', 'alto')),
    cenario_hipotetico TEXT NULL,
    acoes_praticas TEXT NULL,
    sugestao_investimento TEXT NULL,
    dica_economia TEXT NULL,
    cor_fonte VARCHAR(20) DEFAULT '#6366f1',
    icone_fonte VARCHAR(50) DEFAULT 'bi-newspaper',
    processado_ia INTEGER NOT NULL DEFAULT 0,
    data_publicacao DATETIME,
    criado_em DATETIME DEFAULT (datetime('now','localtime')),
    atualizado_em DATETIME DEFAULT (datetime('now','localtime'))
);

-- =========================
-- 7. CACHE IA NOTÍCIAS
-- =========================
CREATE TABLE IF NOT EXISTS cache_ia_noticias (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    noticia_url_hash CHAR(32) NOT NULL,
    perfil_usuario VARCHAR(50) NOT NULL DEFAULT 'moderado',
    analise_json TEXT NOT NULL,
    criado_em DATETIME DEFAULT (datetime('now','localtime'))
);
CREATE INDEX IF NOT EXISTS idx_hash_perfil ON cache_ia_noticias (noticia_url_hash, perfil_usuario);

-- =========================
-- 8. CACHE EXPLICAÇÕES IA (explain.php)
-- =========================
CREATE TABLE IF NOT EXISTS noticias_ai (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario_id INTEGER NOT NULL,
    noticia_hash CHAR(32) NOT NULL,
    resposta_ia TEXT NOT NULL,
    criado_em DATETIME DEFAULT (datetime('now','localtime')),
    UNIQUE (usuario_id, noticia_hash),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- =========================
-- 9. SUGESTÕES DE ECONOMIA
-- =========================
CREATE TABLE IF NOT EXISTS sugestoes_economia (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario_id INTEGER NOT NULL,
    titulo VARCHAR(150) NOT NULL,
    descricao TEXT NOT NULL,
    fonte TEXT NOT NULL,
    categoria_nome VARCHAR(100) DEFAULT NULL,
    mes INTEGER DEFAULT NULL,
    ano INTEGER DEFAULT NULL,
    criado_em DATETIME DEFAULT (datetime('now','localtime')),
    UNIQUE (usuario_id, categoria_nome, mes, ano),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- =========================
-- 10. ORÇAMENTO POR CATEGORIA
-- =========================
CREATE TABLE IF NOT EXISTS orcamento_categorias (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario_id INTEGER NOT NULL,
    categoria_id INTEGER NOT NULL,
    limite_mensal DECIMAL(12,2) NOT NULL,
    mes INTEGER NOT NULL DEFAULT (CAST(strftime('%m','now','localtime') AS INTEGER)),
    ano INTEGER NOT NULL DEFAULT (CAST(strftime('%Y','now','localtime') AS INTEGER)),
    criado_em DATETIME DEFAULT (datetime('now','localtime')),
    atualizado_em DATETIME DEFAULT (datetime('now','localtime')),
    UNIQUE (usuario_id, categoria_id, mes, ano),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE CASCADE
);

-- =========================
-- 11. METAS FINANCEIRAS
-- =========================
CREATE TABLE IF NOT EXISTS metas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario_id INTEGER NOT NULL,
    nome VARCHAR(255) NOT NULL,
    valor_total DECIMAL(12,2) NOT NULL,
    valor_guardado DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    prazo DATE NULL,
    ativo INTEGER NOT NULL DEFAULT 1,
    criado_em DATETIME DEFAULT (datetime('now','localtime')),
    atualizado_em DATETIME DEFAULT (datetime('now','localtime')),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- =========================
-- 12. APORTES (depósitos para metas)
-- =========================
CREATE TABLE IF NOT EXISTS aportes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario_id INTEGER NOT NULL,
    meta_id INTEGER NOT NULL,
    valor DECIMAL(12,2) NOT NULL,
    data_aporte DATE NOT NULL DEFAULT (date('now','localtime')),
    criado_em DATETIME DEFAULT (datetime('now','localtime')),
    atualizado_em DATETIME DEFAULT (datetime('now','localtime')),
    FOREIGN KEY (meta_id) REFERENCES metas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- =========================
-- 13. TOKENS DE RECUPERAÇÃO DE SENHA
-- =========================
CREATE TABLE IF NOT EXISTS password_resets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario_id INTEGER NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expira_em DATETIME NOT NULL,
    usado INTEGER NOT NULL DEFAULT 0,
    criado_em DATETIME DEFAULT (datetime('now','localtime')),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_token_hash ON password_resets (token_hash);

-- =========================
-- 14. LOGS DE AUDITORIA
-- =========================
CREATE TABLE IF NOT EXISTS logs (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    timestamp     DATETIME     NOT NULL DEFAULT (datetime('now','localtime')),
    nivel         TEXT         NOT NULL CHECK (nivel IN ('INFO','WARN','ERROR','DEBUG')),
    usuario_id    INTEGER      NULL,
    usuario_email VARCHAR(150) NULL,
    ip            VARCHAR(45)  NOT NULL DEFAULT '0.0.0.0',
    acao          VARCHAR(100) NOT NULL,
    detalhes      TEXT         NULL,
    status        TEXT         NOT NULL CHECK (status IN ('sucesso','falha'))
);
CREATE INDEX IF NOT EXISTS idx_logs_nivel     ON logs (nivel);
CREATE INDEX IF NOT EXISTS idx_logs_acao      ON logs (acao);
CREATE INDEX IF NOT EXISTS idx_logs_usuario   ON logs (usuario_id);
CREATE INDEX IF NOT EXISTS idx_logs_timestamp ON logs (timestamp);
