<?php
// frontend/public/pages/admin/testes.php — Dashboard de testes automatizados (rota /test, acesso livre)
session_start();

// A página é servida tanto em /test (rewrite no .htaccess) quanto pelo caminho físico,
// então todos os links usam a base absoluta do projeto em vez de caminhos relativos.
$script = $_SERVER['SCRIPT_NAME'];
$base   = rtrim(substr($script, 0, (int) strpos($script, '/frontend/')), '/');

if (empty($_SESSION['csrf_testes'])) {
    $_SESSION['csrf_testes'] = bin2hex(random_bytes(32));
}

$assets = $base . '/frontend';
$v = time();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>InvestAI — Testes Automatizados</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= $assets ?>/assets/style/css/variables.css?v=<?= $v ?>">
    <link rel="stylesheet" href="<?= $assets ?>/assets/style/css/animations.css?v=<?= $v ?>">
    <link rel="stylesheet" href="<?= $assets ?>/assets/style/css/navbar.css?v=<?= $v ?>">
    <link rel="stylesheet" href="<?= $assets ?>/assets/style/css/internal-pages.css?v=<?= $v ?>">
    <link rel="stylesheet" href="<?= $assets ?>/assets/style/css/admin.css?v=<?= $v ?>">
    <link rel="stylesheet" href="<?= $assets ?>/assets/style/css/testes.css?v=<?= $v ?>">
</head>
<body>

    <!-- ===== NAVBAR ===== -->
    <nav class="navbar-custom">
        <div class="container d-flex align-items-center justify-content-between" style="max-width:1200px;">
            <a href="<?= $assets ?>/" class="logo">
                <svg class="neural-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M4 18L9 13M9 13L15 15M15 15L20 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <circle cx="4" cy="18" r="2" fill="currentColor"/>
                    <circle cx="9" cy="13" r="2" fill="currentColor"/>
                    <circle cx="15" cy="15" r="2" fill="currentColor"/>
                    <circle cx="20" cy="6" r="3" fill="var(--brand-accent)"/>
                </svg>
                Invest<span>AI</span>
            </a>
            <div class="d-flex align-items-center gap-4">
                <a href="<?= $assets ?>/" class="nav-link-custom">
                    <i class="bi bi-house-door me-1" style="color:var(--brand-accent)"></i>Início
                </a>
                <span class="nav-link-custom active" style="cursor:default" aria-current="page">
                    <i class="bi bi-robot me-1"></i>Testes
                </span>
            </div>
        </div>
    </nav>

    <main class="main-container">

        <!-- ===== A. CABEÇALHO ===== -->
        <header class="testes-header fade-in-up">
            <div class="admin-header mb-0">
                <h1><i class="bi bi-robot"></i>Testes Automatizados</h1>
                <p>Resultados reais das execuções do Robot Framework sobre as páginas do InvestAI.</p>
                <div class="testes-meta" aria-live="polite">
                    <span id="estado-execucao" class="badge-teste neutro"><i class="bi bi-hourglass"></i>Carregando…</span>
                    <span id="data-execucao" class="text-muted-sm"></span>
                </div>
            </div>

            <!-- ===== F. AÇÕES ===== -->
            <div class="testes-acoes">
                <button type="button" id="btn-executar" class="btn btn-primary btn-sm px-3" disabled>
                    <i class="bi bi-play-fill me-1"></i><span>Executar testes</span>
                </button>
                <button type="button" id="btn-atualizar" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-clockwise me-1"></i>Atualizar
                </button>
                <a id="link-relatorio" class="btn btn-outline-secondary btn-sm disabled" aria-disabled="true" target="_blank" rel="noopener">
                    <i class="bi bi-file-earmark-bar-graph me-1"></i>Relatório
                </a>
                <a id="link-log" class="btn btn-outline-secondary btn-sm disabled" aria-disabled="true" target="_blank" rel="noopener">
                    <i class="bi bi-journal-code me-1"></i>Log detalhado
                </a>
            </div>
        </header>

        <div id="testes-aviso" class="testes-aviso" role="status" hidden></div>

        <!-- ===== B. CARDS DE RESUMO ===== -->
        <section class="summary-cards testes-cards fade-in-up delay-1" aria-label="Resumo da execução">
            <div class="summary-card"><div class="label"><i class="bi bi-list-check me-1"></i>Total</div><div class="value neutral" id="card-total">—</div></div>
            <div class="summary-card"><div class="label"><i class="bi bi-check-circle me-1"></i>Aprovados</div><div class="value ok" id="card-aprovados">—</div></div>
            <div class="summary-card"><div class="label"><i class="bi bi-x-circle me-1"></i>Falhas</div><div class="value fail" id="card-falhas">—</div></div>
            <div class="summary-card"><div class="label"><i class="bi bi-dash-circle me-1"></i>Não executados</div><div class="value skip" id="card-skip">—</div></div>
            <div class="summary-card"><div class="label"><i class="bi bi-stopwatch me-1"></i>Duração</div><div class="value neutral" id="card-duracao">—</div></div>
        </section>

        <!-- ===== C. LISTA DE TESTES ===== -->
        <section class="mb-4 fade-in-up delay-2">
            <div class="testes-secao-titulo">
                <h2><i class="bi bi-ui-checks"></i>Cenários</h2>
                <small id="execucao-exibida" class="text-muted-sm"></small>
            </div>
            <div class="admin-table-wrapper">
                <table class="admin-table testes-table">
                    <caption class="visually-hidden">Cenários da execução selecionada</caption>
                    <thead>
                        <tr>
                            <th scope="col">Teste</th>
                            <th scope="col">Status</th>
                            <th scope="col">Duração</th>
                            <th scope="col">Mensagem</th>
                            <th scope="col"><span class="visually-hidden">Ações</span></th>
                        </tr>
                    </thead>
                    <tbody id="testes-tbody">
                        <tr><td colspan="5" class="admin-loading"><div class="spinner-border spinner-border-sm me-2" role="status"></div>Carregando…</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- ===== E. HISTÓRICO ===== -->
        <section class="fade-in-up delay-3">
            <div class="testes-secao-titulo">
                <h2><i class="bi bi-clock-history"></i>Histórico de execuções</h2>
            </div>
            <div class="admin-table-wrapper">
                <table class="admin-table testes-table">
                    <caption class="visually-hidden">Últimas execuções</caption>
                    <thead>
                        <tr>
                            <th scope="col">Data</th>
                            <th scope="col">Status</th>
                            <th scope="col">Resultado</th>
                            <th scope="col">Duração</th>
                            <th scope="col"><span class="visually-hidden">Ações</span></th>
                        </tr>
                    </thead>
                    <tbody id="historico-tbody">
                        <tr><td colspan="5" class="admin-loading"><div class="spinner-border spinner-border-sm me-2" role="status"></div>Carregando…</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <!-- ===== D. DETALHES DO TESTE ===== -->
    <div class="modal fade" id="modal-teste" tabindex="-1" aria-labelledby="modal-teste-titulo" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-teste-titulo"><i class="bi bi-clipboard-check"></i><span></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body" id="modal-teste-corpo"></div>
            </div>
        </div>
    </div>

    <script>
        const BASE_PATH  = <?= json_encode($base) ?>;
        const CSRF_TESTES = <?= json_encode($_SESSION['csrf_testes']) ?>;
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= $assets ?>/api/admin/testes.js?v=<?= $v ?>"></script>
</body>
</html>
