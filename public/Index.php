<?php
// public/index.php

/**
 * AUTOLOADER MANUAL
 * Carrega as classes automaticamente com base em seus namespaces.
 */
spl_autoload_register(function ($className) {
    // Prefixo do namespace que corresponde à pasta 'app'
    $namespacePrefix = 'App\\';
    $baseDir = __DIR__ . '/../app/';

    // Verifica se a classe pertence ao nosso projeto
    if (strncmp($namespacePrefix, $className, $len = strlen($namespacePrefix)) !== 0) {
        return;
    }

    // Converte o namespace em caminho de arquivo
    // Ex: App\Features\Enquetes\Controllers\EnqueteController
    //  -> app/features/enquetes/controllers/enquetecontroller.php
    $relativeClass = substr($className, $len);
    
    // IMPORTANTE: Sua estrutura usa 'controllers' com 'c' minúsculo.
    // Vamos garantir que o caminho gerado também seja minúsculo.
    $file = $baseDir . str_replace('\\', '/', strtolower($relativeClass)) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Agora que o autoloader está pronto, instanciamos o roteador.
// O autoloader vai carregar 'App\Core\Router' automaticamente.
use App\Core\Router;

$router = new Router();
$router->dispatch(); // O método dispatch cuidará de tudo
