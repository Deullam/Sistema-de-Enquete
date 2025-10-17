<?php
// app/core/Router.php

namespace App\Core; // Use namespaces!

class Router {
    protected $controller = 'App\Features\Enquetes\Controllers\EnqueteController'; // Controller padrão
    protected $method = 'index'; // Método padrão
    protected $params = [];

    public function __construct() {
        // O construtor fica vazio. A lógica vai para o dispatch.
    }

    private function parseUrl() {
        if (isset($_GET['url'])) {
            return explode('/', filter_var(rtrim($_GET['url'], '/'), FILTER_SANITIZE_URL));
        }
        return [];
    }

    public function dispatch() {
        $url = $this->parseUrl();

        // Parte 1: Determinar o Controller
        // Se a URL for 'enquetes', usamos o EnqueteController.
        // Se for 'admin', usamos o AdminController, etc.
        if (!empty($url[0])) {
            $controllerName = ucfirst(strtolower($url[0])); // Ex: 'enquetes' -> 'Enquetes'
            
            // ATENÇÃO: A sua estrutura é 'features/admin' e 'features/enquetes'.
            // O nome do controller é diferente (AdminController vs EnqueteController).
            // Vamos fazer um mapeamento simples por enquanto.
            
            $featureName = strtolower($url[0]); // 'enquetes' ou 'admin'
            $controllerClass = 'App\\Features\\' . $featureName . '\\Controllers\\' . $controllerName . 'Controller';

            // Ajuste manual para o seu caso específico
            if ($featureName === 'enquetes') {
                $controllerClass = 'App\\Features\\Enquetes\\Controllers\\EnqueteController';
            }
            if ($featureName === 'admin') {
                $controllerClass = 'App\\Features\\Admin\\Controllers\\AdminController';
            }

            if (class_exists($controllerClass)) {
                $this->controller = $controllerClass;
                unset($url[0]);
            } else {
                $this->show404("Controller não encontrado: " . $controllerClass);
                return;
            }
        }

        // Instancia o controller
        $this->controller = new $this->controller;

        // Parte 2: Determinar o Método
        if (isset($url[1])) {
            if (method_exists($this->controller, $url[1])) {
                $this->method = $url[1];
                unset($url[1]);
            }
        }
        
        // Parte 3: Obter os Parâmetros
        $this->params = $url ? array_values($url) : [];

        // Chama o método do controller com os parâmetros
        try {
            call_user_func_array([$this->controller, $this->method], $this->params);
        } catch (\Exception $e) {
            $this->show404("Erro ao executar método: " . $e->getMessage());
        }
    }

    private function show404($message = "Página não encontrada") {
        http_response_code(404 );
        // Para depuração, é útil ver a mensagem.
        echo "<h1>Erro 404</h1>";
        echo "<p>{$message}</p>";
        exit;
    }
}
