<?php
// Adicione o namespace para o autoloader encontrar a classe
namespace App\Core;

class Controller
{
    /**
     * Renderiza um arquivo de view, passando dados para ele.
     *
     * @param string $viewPath O caminho para a view a partir da pasta 'app/features/'
     * @param array $data Os dados que a view poderá usar (ex: $titulo, $enquetes)
     */
    public function view(string $viewPath, array $data = [])
    {
        // A função extract() transforma as chaves de um array em variáveis.
        // Ex: ['titulo' => 'Minha Página'] se torna a variável $titulo.
        extract($data);

        // Constrói o caminho completo para o arquivo da view.
        // __DIR__ aqui é 'app/core', então voltamos um nível para 'app'.
        $fullPath = __DIR__ . '/../' . $viewPath . '.php';

        if (file_exists($fullPath)) {
            require $fullPath;
        } else {
            // Lança um erro claro se a view não for encontrada.
            // Isso ajuda muito na depuração.
            echo "<h1>Erro no Controller</h1>";
            echo "<p>Arquivo de View não encontrado no caminho: {$fullPath}</p>";
            exit;
        }
    }
}
