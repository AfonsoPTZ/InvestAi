<?php
// router.php — Roteador único da aplicação (substitui o .htaccess fora do Apache).
//
//   Local:  php -S localhost:8000 router.php
//   Vercel: api/index.php inclui este arquivo (todas as rotas apontam para lá)
//
// Executa os .php do projeto, serve os arquivos estáticos e bloqueia o resto
// (.env, .sql, .sqlite, .log, .git...), como o .htaccess fazia no Apache.

const STATIC_TYPES = [
    'css'   => 'text/css; charset=utf-8',
    'js'    => 'application/javascript; charset=utf-8',
    'map'   => 'application/json',
    'png'   => 'image/png',
    'jpg'   => 'image/jpeg',
    'jpeg'  => 'image/jpeg',
    'gif'   => 'image/gif',
    'svg'   => 'image/svg+xml',
    'webp'  => 'image/webp',
    'ico'   => 'image/x-icon',
    'woff'  => 'font/woff',
    'woff2' => 'font/woff2',
    'ttf'   => 'font/ttf',
    'eot'   => 'application/vnd.ms-fontobject',
];

function notFound(): never
{
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo '404 Not Found';
    exit;
}

$root = __DIR__;
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

// Path traversal, byte nulo e qualquer segmento oculto (.env, .git, .htaccess...)
if (str_contains($path, "\0") || preg_match('#(^|/)\.#', $path)) {
    notFound();
}

$rel = trim($path, '/');
// Pastas internas que nunca devem ser servidas via HTTP
if (preg_match('#^(logs|docker|api)(/|$)#', $rel)) {
    notFound();
}

$file = $root . '/' . $rel;
if ($rel === '' || is_dir($file)) {
    $file = rtrim($file, '/') . '/index.php';
    $rel = ltrim(substr($file, strlen($root)), '/');
}

if (!is_file($file)) {
    notFound();
}

$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

if ($ext === 'php') {
    // Equivalente ao que o Apache informaria ao script
    $_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = '/' . $rel;
    $_SERVER['SCRIPT_FILENAME'] = $file;
    $_SERVER['DOCUMENT_ROOT'] = $root;
    chdir(dirname($file));
    require $file;
    return;
}

if (!isset(STATIC_TYPES[$ext])) {
    notFound();
}

header('Content-Type: ' . STATIC_TYPES[$ext]);
header('Content-Length: ' . filesize($file));
header('Cache-Control: public, max-age=3600');
readfile($file);
