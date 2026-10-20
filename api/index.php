<?php
// api/index.php — Ponto de entrada da Vercel (runtime vercel-php).
// Todas as rotas são reescritas para cá (ver vercel.json); o roteamento real fica em router.php.
require __DIR__ . '/../router.php';
