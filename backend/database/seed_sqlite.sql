-- Dados de exemplo (opcional). Aplicado só quando DB_SEED=true e o banco acabou de ser criado.
-- Nota: senha_hash aqui é inválida — para login real, registre via site
-- (o cadastro.php cria o hash correto e insere as categorias automaticamente).

INSERT INTO usuarios (nome, email, cpf, telefone, senha_hash)
VALUES (
    'Rafael Monteiro',
    'rafael.monteiro@exemplo.com',
    '12345678901',
    '41999123456',
    '$2y$10$examplehashfordevelopmentonly000000000000000000000000000'
);

INSERT INTO perfil_financeiro (
    usuario_id, renda_mensal, saldo_inicial, possui_investimentos,
    possui_patrimonio, objetivo_financeiro, perfil_comportamento
) VALUES (
    1, 4500.00, 1200.00, 1, 1,
    'Economizar mais e investir melhor ao longo do ano', 'moderado'
);

INSERT INTO ganhos (usuario_id, descricao, valor, data_ganho, fixo)
VALUES
(1, 'Salário mensal',                  4500.00, '2026-05-05', 1),
(1, 'Freelance de site institucional',  400.00, '2026-05-12', 0),
(1, 'Ganhei 20 reais no Tigrinho KKK',   20.00, '2026-05-14', 0);

INSERT INTO despesas (usuario_id, descricao, valor, data_despesa, fixo)
VALUES
(1, 'Aluguel',          1200.00, '2026-05-01', 1),
(1, 'Internet',           99.90, '2026-05-03', 1),
(1, 'Lanche da tarde',     5.00, '2026-05-06', 0),
(1, 'iFood pizza',        49.90, '2026-05-08', 0),
(1, 'Uber para faculdade', 18.00, '2026-05-09', 0),
(1, 'Mercado do mês',    210.75, '2026-05-10', 0);

-- processado_ia = 1 obrigatório para aparecer no get_news.php
INSERT INTO noticias_financeiras (
    titulo, fonte, url, resumo, categoria, processado_ia, data_publicacao
) VALUES (
    'Bitcoin volta a subir com melhora do mercado global',
    'Portal Finance News',
    'https://portalfinance-news.com/bitcoin-sobe-mercado-global',
    'O ativo apresentou valorização após melhora do sentimento do mercado internacional.',
    'Criptomoedas',
    1,
    '2026-05-18 10:30:00'
);

INSERT INTO sugestoes_economia (
    usuario_id, titulo, descricao, fonte, categoria_nome, mes, ano
) VALUES (
    1,
    'Reduzir gastos variáveis com delivery',
    'Você poderia diminuir pedidos por aplicativo nesta semana para economizar mais.',
    '{"acoes":["Limite pedidos a 2x por semana","Prefira cozinhar em casa","Use cupons de desconto"],"tipo":"comportamento"}',
    'Alimentação',
    5,
    2026
);
