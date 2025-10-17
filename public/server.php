<?php
// public/server.php

// Pega o caminho da URL solicitada.
$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
);

// Este arquivo não deve ser servido diretamente.
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    // Se a requisição for para um arquivo existente (como CSS, JS, imagem),
    // retorne 'false' para que o servidor embutido sirva o arquivo diretamente.
    return false;
}

// Para todas as outras requisições, inclua o seu front-controller (index.php).
// É aqui que a "mágica" do roteamento acontece.
require_once __DIR__ . '/index.php';
