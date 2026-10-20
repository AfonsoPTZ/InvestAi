<?php
/**
 * backend/api/noticias/get_news.php
 * Endpoint autenticado: lê notícias do banco e calcula impacto_pessoal
 * cruzando categorias com as despesas do usuário logado.
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

// ─── Autenticação ─────────────────────────────────────────────────────────────
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Não autorizado.']);
    exit;
}

$root = dirname(dirname(dirname(dirname(__FILE__))));
require_once $root . '/backend/database/conexao.php';

$usuarioId = $_SESSION['usuario_id'];
$limite    = max(1, min(100, (int)($_GET['limite'] ?? 50)));
$categoria = $_GET['categoria'] ?? null;
$fonte     = $_GET['fonte']     ?? null;

// ─── Mapa de palavras-chave → categoria ──────────────────────────────────────
$mapaCategorias = [
    'Transporte'      => ['combustível','gasolina','diesel','etanol','ônibus','metrô','uber',
                          'táxi','passagem','trem','estacionamento','pedágio','frete','carro'],
    'Alimentação'     => ['aliment','comida','supermercado','mercado','feira','restaurante',
                          'lanchonete','padaria','açougue','cesta básica','delivery','ifood',
                          'rappi','refeição','jantar','almoço','café'],
    'Moradia'         => ['aluguel','condomínio','iptu','água','luz','energia elétrica',
                          'gás','internet residencial','reforma','imóvel','financiamento imóvel'],
    'Lazer'           => ['cinema','netflix','spotify','disney','entretenimento','viagem',
                          'hotel','passagem aérea','academia','streaming','show','bar','festa'],
    'Tecnologia'      => ['celular','smartphone','computador','notebook','tablet','software',
                          'aplicativo','internet','tecnologia','eletrônico'],
    'Saúde'           => ['saúde','plano de saúde','consulta','médico','dentista','remédio',
                          'medicamento','farmácia','hospital','clínica','exame','psicólogo'],
    'Finanças Gerais' => ['investimento','poupança','tesouro','fundo','ação','bolsa','seguro',
                          'previdência','imposto','conta','banco','empréstimo','financiamento',
                          'cartão','crédito','débito','taxa'],
];

// ─── Buscar categorias de despesa do usuário ──────────────────────────────────
$inicioMes = date('Y-m-01');
$hoje      = date('Y-m-d');

$stmt = $conexao->prepare(
    "SELECT descricao FROM despesas WHERE usuario_id = ? AND data_despesa BETWEEN ? AND ? ORDER BY valor DESC LIMIT 30"
);
$stmt->bind_param('iss', $usuarioId, $inicioMes, $hoje);
$stmt->execute();
$res = $stmt->get_result();

$descricoesDespesas = [];
while ($row = $res->fetch_assoc()) {
    $descricoesDespesas[] = mb_strtolower($row['descricao']);
}

// Descobrir em quais categorias o usuário tem gastos
$categoriasDoUsuario = [];
foreach ($descricoesDespesas as $desc) {
    foreach ($mapaCategorias as $cat => $palavras) {
        if (in_array($cat, $categoriasDoUsuario)) continue;
        foreach ($palavras as $p) {
            if (mb_strpos($desc, $p) !== false) {
                $categoriasDoUsuario[] = $cat;
                break;
            }
        }
    }
}
$categoriasDoUsuario = array_unique($categoriasDoUsuario);

// ─── Buscar notícias do banco ─────────────────────────────────────────────────
$where  = ['n.processado_ia = 1'];
$params = [];
$types  = '';

if ($categoria && $categoria !== 'todas') {
    $where[]  = 'n.categoria = ?';
    $params[] = $categoria;
    $types   .= 's';
}

if ($fonte && $fonte !== 'todas') {
    $where[]  = 'n.fonte = ?';
    $params[] = $fonte;
    $types   .= 's';
}

$whereClause = implode(' AND ', $where);
$sql = "SELECT n.id, n.titulo, n.fonte, n.url, n.resumo, n.categoria,
               n.nivel_impacto, n.cenario_hipotetico, n.acoes_praticas,
               n.sugestao_investimento, n.dica_economia,
               n.cor_fonte, n.icone_fonte, n.data_publicacao, n.criado_em
        FROM noticias_financeiras n
        WHERE {$whereClause}
        ORDER BY n.data_publicacao DESC, n.criado_em DESC
        LIMIT ?";

$params[] = $limite;
$types   .= 'i';

$stmt = $conexao->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();

$noticias = [];
while ($row = $res->fetch_assoc()) {
    // Calcular impacto pessoal
    $impactoPessoal = in_array($row['categoria'], $categoriasDoUsuario);

    // Decodificar ações práticas (JSON)
    $acoes = [];
    if (!empty($row['acoes_praticas'])) {
        $decoded = json_decode($row['acoes_praticas'], true);
        if (is_array($decoded)) $acoes = $decoded;
    }

    // Formatar data para exibição
    $dataFormatada = $row['data_publicacao']
        ? date('d/m/Y H:i', strtotime($row['data_publicacao']))
        : date('d/m/Y H:i', strtotime($row['criado_em']));

    $noticias[] = [
        'id'                   => (int)$row['id'],
        'titulo'               => $row['titulo'],
        'fonte'                => $row['fonte'],
        'url'                  => $row['url'],
        'resumo'               => $row['resumo'],
        'categoria'            => $row['categoria'] ?? 'Finanças Gerais',
        'nivel_impacto'        => $row['nivel_impacto'] ?? 'baixo',
        'cenario_hipotetico'   => $row['cenario_hipotetico'] ?? '',
        'acoes_praticas'       => $acoes,
        'sugestao_investimento'=> $row['sugestao_investimento'] ?? '',
        'dica_economia'        => $row['dica_economia'] ?? '',
        'cor_fonte'            => $row['cor_fonte'] ?? '#6366f1',
        'icone_fonte'          => $row['icone_fonte'] ?? 'bi-newspaper',
        'data'                 => $dataFormatada,
        'impacto_pessoal'      => $impactoPessoal,
    ];
}

// ─── Estatísticas rápidas ─────────────────────────────────────────────────────
$totalRelevantes = count(array_filter($noticias, fn($n) => $n['impacto_pessoal']));

// ─── Última atualização ───────────────────────────────────────────────────────
$stmt2 = $conexao->prepare("SELECT MAX(criado_em) as ultima FROM noticias_financeiras WHERE processado_ia = 1");
$stmt2->execute();
$ultimaRow     = $stmt2->get_result()->fetch_assoc();
$ultimaAtualizacao = $ultimaRow['ultima'] ?? null;
$ultimaFormatada   = $ultimaAtualizacao
    ? date('d/m/Y \à\s H:i', strtotime($ultimaAtualizacao))
    : 'Nunca';

// ─── Contagem por categoria (para filtros com badge) ─────────────────────────────────
$stmt3 = $conexao->prepare(
    "SELECT categoria, COUNT(*) as total FROM noticias_financeiras
     WHERE processado_ia = 1 AND categoria IS NOT NULL
     GROUP BY categoria"
);
$stmt3->execute();
$res3 = $stmt3->get_result();
$contagemPorCategoria = [];
while ($r = $res3->fetch_assoc()) {
    $contagemPorCategoria[$r['categoria']] = (int)$r['total'];
}

ob_clean();
echo json_encode([
    'status'                 => 'ok',
    'noticias'               => $noticias,
    'total'                  => count($noticias),
    'total_relevantes'       => $totalRelevantes,
    'categorias_usuario'     => array_values($categoriasDoUsuario),
    'ultima_atualizacao'     => $ultimaFormatada,
    'contagem_por_categoria' => $contagemPorCategoria,
], JSON_UNESCAPED_UNICODE);
