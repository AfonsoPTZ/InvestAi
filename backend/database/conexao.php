<?php
// backend/database/conexao.php — Conexão centralizada ao banco (SQLite)
// O arquivo do banco é criado e populado com o schema automaticamente na primeira execução.
// Configurações (DB_SQLITE_PATH, DB_SEED) vêm do .env — ver Database.php.

require_once __DIR__ . '/Database.php';

try {
    // Mesma interface do mysqli (prepare/bind_param/get_result...), ver SqliteCompat.php
    $conexao = Database::connect();
} catch (Throwable $e) {
    error_log('[conexao.php] Erro ao abrir o banco SQLite: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Não foi possível conectar ao banco de dados. Tente novamente mais tarde."]);
    exit;
}
?>
