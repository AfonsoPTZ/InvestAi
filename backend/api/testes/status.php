<?php
// backend/api/testes/status.php — Estado do executor, última execução e histórico (acesso livre)
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-store');

$root = dirname(dirname(dirname(dirname(__FILE__))));
require_once $root . '/backend/includes/TestRunnerClient.php';


if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método não permitido.']);
    exit;
}

if (!TestRunnerClient::isConfigured()) {
    echo json_encode(['status' => 'success', 'executor' => ['configurado' => false, 'disponivel' => false]]);
    exit;
}

$resposta = TestRunnerClient::request('GET', '/status');
if ($resposta === null || $resposta['code'] !== 200) {
    echo json_encode(['status' => 'success', 'executor' => ['configurado' => true, 'disponivel' => false]]);
    exit;
}

$dados = json_decode($resposta['body'], true);
echo json_encode([
    'status'   => 'success',
    'executor' => ['configurado' => true, 'disponivel' => true],
    'dados'    => $dados,
]);
?>
