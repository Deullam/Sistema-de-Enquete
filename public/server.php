<?php
// public/server.php - VERSÃO FINAL

// Pega a URL solicitada, por exemplo: /css/estilo.css
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// O caminho para a pasta 'public' é o diretório onde este script (server.php) está.
$publicPath = __DIR__;

// Constrói o caminho completo no sistema de arquivos.
// Ex: C:\Project\Deullam-Enquete\public/css/estilo.css
$filePath = $publicPath . $path;

// Se o caminho aponta para um arquivo que realmente existe...
if (file_exists($filePath) && !is_dir($filePath)) {
    // ...então retorne 'false'. Isso diz ao servidor PHP: "Pare tudo e apenas sirva este arquivo".
    return false;
}

// Se não for um arquivo (ex: /enquetes), a requisição deve ser tratada pelo roteador.
// Carrega o index.php para que o Router.php assuma o controle.
require_once __DIR__ . '/index.php';
