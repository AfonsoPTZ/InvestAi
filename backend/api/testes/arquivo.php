<?php
// backend/api/testes/arquivo.php/<execucao>/<arquivo> — Serve relatórios do Robot Framework (acesso livre)
// O caminho vem em PATH_INFO para que os links relativos do report.html/log.html
// (report ↔ log, screenshots/...) continuem funcionando. Só nomes da lista fixa são aceitos.
session_start();

$root = dirname(dirname(dirname(dirname(__FILE__))));
require_once $root . '/backend/includes/TestRunnerClient.php';

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Content-Type: application/json');

$partes  = explode('/', ltrim($_SERVER['PATH_INFO'] ?? '', '/'), 2);
$execId  = $partes[0] ?? '';
$arquivo = $partes[1] ?? '';

if (!preg_match(TestRunnerClient::RUN_ID_PATTERN, $execId)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Execução inválida.']);
    exit;
}

// Sem arquivo: devolve o resumo JSON completo da execução.
$caminho = $arquivo === '' ? "/runs/$execId/summary" : "/runs/$execId/$arquivo";
if ($arquivo !== '' && !preg_match(TestRunnerClient::FILE_PATTERN, $arquivo)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Arquivo não permitido.']);
    exit;
}

if (!TestRunnerClient::isConfigured()) {
    header('Content-Type: application/json');
    TestRunnerClient::respondUnavailable();
}

$resposta = TestRunnerClient::request('GET', $caminho, 30);
if ($resposta === null) {
    header('Content-Type: application/json');
    TestRunnerClient::respondUnavailable();
}

if ($resposta['code'] !== 200) {
    http_response_code($resposta['code'] === 404 ? 404 : 502);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Arquivo não encontrado.']);
    exit;
}

// Tipos permitidos são fixos; nunca repassa um Content-Type arbitrário.
$tipos = ['html' => 'text/html; charset=utf-8', 'png' => 'image/png'];
$ext   = $arquivo === '' ? 'json' : pathinfo($arquivo, PATHINFO_EXTENSION);
header('Content-Type: ' . ($tipos[$ext] ?? 'application/json'));
echo $resposta['body'];
