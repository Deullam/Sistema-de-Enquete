<?php
// app/core/Router.php

namespace App\Core; // Use namespaces!

class Router
{
    /**
     * Armazena o nome da classe do controller (string) ou a instância (objeto).
     * @var string|object
     */
    protected string|object $controller = 'App\Features\Enquetes\Controllers\EnqueteController';
    protected $method = 'index'; // Método padrão
    protected $params = [];

    public function __construct()
    {
        // O construtor fica vazio. A lógica vai para o dispatch.
    }

    public function dispatch()
    {
        $url = $this->parseUrl();

        // Lógica Específica para o Admin
        if (isset($url[0]) && $url[0] === 'admin') {
            $this->controller = 'App\\Features\\Admin\\Controllers\\AdminController';
            unset($url[0]);

            // O método é a segunda parte da URL (ou 'dashboard' como padrão)
            if (isset($url[1])) {
                $this->method = $url[1];
                unset($url[1]);
            } else {
                $this->method = 'dashboard'; // Se for só /admin, vai para o dashboard
            }
        }
        // Lógica para a Área Pública (Enquetes)
        else if (isset($url[0]) && $url[0] === 'enquetes') {
            $this->controller = 'App\\Features\\Enquetes\\Controllers\\EnqueteController';
            unset($url[0]);

            if (isset($url[1])) {
                // Se a segunda parte for um método que existe (ex: 'votar'), use-o
                if (method_exists($this->controller, $url[1])) {
                    $this->method = $url[1];
                    unset($url[1]);
                } else {
                    // Senão, é um slug para o método 'exibir'
                    $this->method = 'exibir';
                    $this->params[] = $url[1];
                    unset($url[1]);
                }
            } else {
                $this->method = 'index'; // /enquetes vai para o index
            }
        }
        // Adicione mais 'else if' aqui para outras áreas do site no futuro.

        // Instancia o controller
        if (!class_exists($this->controller)) {
            $this->show404("Controller não encontrado: " . $this->controller);
            return;
        }
        $this->controller = new $this->controller;

        // Pega os parâmetros restantes
        $this->params = array_merge($this->params, $url ? array_values($url) : []);

        // Verifica se o método existe e chama
        if (!method_exists($this->controller, $this->method)) {
            $this->show404("Método não encontrado: " . $this->method . " no controller " . get_class($this->controller));
            return;
        }

        call_user_func_array([$this->controller, $this->method], $this->params);
    }


    private function parseUrl(): array
    {
        // Este método pega a URL do 'server.php' e a divide.
        // O 'server.php' coloca a URL em $_SERVER['REQUEST_URI']
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri = trim($uri, '/');
        return explode('/', $uri);
    }

    private function show404($message = "Página não encontrada")
    {
        http_response_code(404);
        echo "<h1>Erro 404</h1><p>{$message}</p>";
        exit;
    }
}
