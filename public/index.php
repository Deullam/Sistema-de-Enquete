<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
    //  -> app/features/enquetes/controllers/EnqueteController.php
    $relativeClass = substr($className, $len);

    // Diretórios em minúsculo (app/core, app/features/admin/controllers), mas o nome do
    // arquivo preserva o PascalCase da classe (Router.php, AdminController.php): em sistemas
    // de arquivos sensíveis a maiúsculas (Linux, contêiner) o lowercase total não encontra nada.
    $parts = explode('\\', $relativeClass);
    $class = array_pop($parts);
    $dir = $parts ? strtolower(implode('/', $parts)) . '/' : '';
    $file = $baseDir . $dir . $class . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Agora que o autoloader está pronto, instanciamos o roteador.
// O autoloader vai carregar 'App\Core\Router' automaticamente.
use App\Core\Router;

$router = new Router();
$router->dispatch(); // O método dispatch cuidará de tudo
