<?php
// backend/api/testes/executar.php — Dispara a suíte fixa do Robot Framework (token CSRF da sessão)
// Não recebe parâmetros: o executor só conhece um comando, definido em tests-robot/runner/executar.py.
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-store');

$root = dirname(dirname(dirname(dirname(__FILE__))));
require_once $root . '/backend/includes/TestRunnerClient.php';
require_once $root . '/backend/includes/Logger.php';


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método não permitido.']);
    exit;
}

$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['csrf_testes']) || !hash_equals($_SESSION['csrf_testes'], $csrf)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Token de segurança inválido. Recarregue a página.']);
    exit;
}

if (!TestRunnerClient::isConfigured()) {
    TestRunnerClient::respondUnavailable();
}

$resposta = TestRunnerClient::request('POST', '/run');
if ($resposta === null) {
    TestRunnerClient::respondUnavailable();
}

$dados = json_decode($resposta['body'], true) ?: [];

if ($resposta['code'] === 409) {
    http_response_code(409);
    echo json_encode(['status' => 'error', 'message' => 'Já existe uma execução em andamento. Aguarde a conclusão.']);
    exit;
}

if ($resposta['code'] !== 202) {
    Logger::log('ERROR', 'TESTS_RUN', ['http' => $resposta['code']], 'falha', $_SESSION['usuario_id'] ?? null, $_SESSION['usuario_email'] ?? null);
    http_response_code(502);
    echo json_encode(['status' => 'error', 'message' => 'O executor recusou a solicitação.']);
    exit;
}

Logger::log('INFO', 'TESTS_RUN', ['execucao' => $dados['id'] ?? null], 'sucesso', $_SESSION['usuario_id'] ?? null, $_SESSION['usuario_email'] ?? null);

http_response_code(202);
echo json_encode([
    'status'  => 'success',
    'message' => 'Execução iniciada.',
    'id'      => $dados['id'] ?? null,
]);
?>
