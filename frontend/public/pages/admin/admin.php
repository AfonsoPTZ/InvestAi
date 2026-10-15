<?php
// Front/admin.php — Painel administrativo (acesso restrito ao admin via .env)
session_start();

$root = dirname(__DIR__, 4);
require_once $root . '/backend/includes/admin_middleware.php';
require_once $root . '/backend/includes/Logger.php';

requireAdminPage();

$nome          = htmlspecialchars($_SESSION['usuario_nome']);
$usuario_id    = $_SESSION['usuario_id'];
$usuario_email = $_SESSION['usuario_email'] ?? null;

Logger::log('INFO', 'ADMIN_ACCESS', ['secao' => 'painel'], 'sucesso', $usuario_id, $usuario_email);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>InvestAI — Painel Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../../assets/style/css/variables.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../../../assets/style/css/animations.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../../../assets/style/css/navbar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../../../assets/style/css/internal-pages.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../../../assets/style/css/admin.css?v=<?= time() ?>">
</head>
<body>

    <!-- ===== NAVBAR ===== -->
    <nav class="navbar-custom">
        <div class="container d-flex align-items-center justify-content-between" style="max-width:1200px;">
            <a href="admin.php" class="logo">
                <svg class="neural-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M4 18L9 13M9 13L15 15M15 15L20 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <circle cx="4" cy="18" r="2" fill="currentColor"/>
                    <circle cx="9" cy="13" r="2" fill="currentColor"/>
                    <circle cx="15" cy="15" r="2" fill="currentColor"/>
                    <circle cx="20" cy="6" r="3" fill="var(--brand-accent)"/>
                </svg>
                Invest<span>AI</span>
            </a>
            <div class="d-flex align-items-center gap-4">
                <span class="nav-link-custom active" style="cursor:default">
                    <i class="bi bi-shield-lock-fill me-1" style="color:var(--brand-accent)"></i>Admin
                </span>
                <a href="../../../../test" class="nav-link-custom"><i class="bi bi-robot me-1"></i>Testes</a>
                <a href="../../../logout.php" class="nav-link-custom" title="Sair"><i class="bi bi-box-arrow-right"></i></a>
            </div>
        </div>
    </nav>

    <div class="main-container">

        <!-- ===== HEADER ===== -->
        <div class="admin-header fade-in-up">
            <h1><i class="bi bi-shield-lock-fill"></i>Painel Administrativo</h1>
            <p>Logs de auditoria e gerenciamento de usuários do sistema.</p>
        </div>

        <!-- ===== TABS ===== -->
        <div class="admin-tabs fade-in-up delay-1">
            <button class="admin-tab-btn active" data-tab="logs">
                <i class="bi bi-journal-text"></i> Logs de Auditoria
            </button>
            <button class="admin-tab-btn" data-tab="usuarios">
                <i class="bi bi-people-fill"></i> Usuários
            </button>
        </div>

        <!-- ========================== TAB LOGS ========================== -->
        <div class="admin-tab-content active" id="tab-logs">

            <!-- Filtros -->
            <div class="filter-bar">
                <div>
                    <label class="form-label">Nível</label>
                    <select id="filtro-nivel" class="form-select form-select-sm" style="width:110px">
                        <option value="">Todos</option>
                        <option value="INFO">INFO</option>
                        <option value="WARN">WARN</option>
                        <option value="ERROR">ERROR</option>
                        <option value="DEBUG">DEBUG</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Ação</label>
                    <input id="filtro-acao" type="text" class="form-control form-control-sm" placeholder="ex: USER_LOGIN" style="width:160px">
                </div>
                <div>
                    <label class="form-label">ID do Usuário</label>
                    <input id="filtro-uid" type="number" class="form-control form-control-sm" placeholder="—" style="width:110px" min="0">
                </div>
                <div>
                    <label class="form-label">De</label>
                    <input id="filtro-de" type="date" class="form-control form-control-sm" style="width:145px">
                </div>
                <div>
                    <label class="form-label">Até</label>
                    <input id="filtro-ate" type="date" class="form-control form-control-sm" style="width:145px">
                </div>
                <div class="d-flex gap-2 align-self-end">
                    <button id="btn-filtrar-logs" class="btn btn-sm btn-primary px-3">
                        <i class="bi bi-search me-1"></i>Filtrar
                    </button>
                    <button id="btn-limpar-logs" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>

            <div class="mb-2">
                <small id="logs-info" class="text-muted"></small>
            </div>

            <!-- Tabela -->
            <div class="admin-table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Data/Hora</th>
                            <th>Nível</th>
                            <th>Usuário</th>
                            <th>IP</th>
                            <th>Ação</th>
                            <th>Detalhes</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="logs-tbody">
                        <tr><td colspan="7" class="admin-loading">
                            <div class="spinner-border spinner-border-sm me-2"></div>Carregando...
                        </td></tr>
                    </tbody>
                </table>
                <div id="logs-paginacao"></div>
            </div>
        </div>

        <!-- ========================= TAB USUÁRIOS ========================= -->
        <div class="admin-tab-content" id="tab-usuarios">

            <!-- Busca -->
            <div class="filter-bar">
                <div style="flex:1;min-width:200px">
                    <label class="form-label">Buscar por nome ou e-mail</label>
                    <input id="busca-usuario" type="text" class="form-control form-control-sm" placeholder="Digite para buscar...">
                </div>
                <div class="align-self-end">
                    <button id="btn-buscar-usuarios" class="btn btn-sm btn-primary px-3">
                        <i class="bi bi-search me-1"></i>Buscar
                    </button>
                </div>
            </div>

            <div class="mb-2">
                <small id="usuarios-info" class="text-muted"></small>
            </div>

            <!-- Tabela -->
            <div class="admin-table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>E-mail</th>
                            <th>Cadastrado em</th>
                            <th>Perfil</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody id="usuarios-tbody">
                        <tr><td colspan="6" class="admin-loading">
                            <div class="spinner-border spinner-border-sm me-2"></div>Carregando...
                        </td></tr>
                    </tbody>
                </table>
                <div id="usuarios-paginacao"></div>
            </div>
        </div>

    </div><!-- /main-container -->

    <!-- ===== MODAL DETALHES DO LOG ===== -->
    <div class="modal fade" id="modal-log-detalhes" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-code-square"></i>Detalhes do Log</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <pre id="modal-log-body"></pre>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../../api/admin/logs.js?v=<?= time() ?>"></script>
    <script src="../../../api/admin/usuarios.js?v=<?= time() ?>"></script>
    <script>
        // Controle de tabs — inicializa cada aba na primeira visita
        document.querySelectorAll('.admin-tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const tab = btn.dataset.tab;

                document.querySelectorAll('.admin-tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.admin-tab-content').forEach(c => c.classList.remove('active'));

                btn.classList.add('active');
                document.getElementById('tab-' + tab).classList.add('active');

                if (tab === 'usuarios' && !UsuariosAdmin._iniciado) {
                    UsuariosAdmin.init();
                    UsuariosAdmin._iniciado = true;
                }
            });
        });

        // Inicializa aba de logs na abertura da página
        LogsAdmin.init();
    </script>
</body>
</html>
