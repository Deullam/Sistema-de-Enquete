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
    public function view(string $viewPath, array $data = []) {
        extract($data);

        // Caminho para os arquivos de layout
        $cabecalhoPath = __DIR__ . '/../shared/views/layouts/cabecalho.php';
        $conteudoPath = __DIR__ . '/../' . $viewPath . '.php'; // O caminho da sua view específica
        $rodapePath = __DIR__ . '/../shared/views/layouts/rodape.php';

        // Inclui o cabeçalho
        if (file_exists($cabecalhoPath)) {
            require $cabecalhoPath;
        }

        // Inclui o conteúdo da página
        if (file_exists($conteudoPath)) {
            require $conteudoPath;
        } else {
            echo "<h1>Erro: View de conteúdo não encontrada em {$conteudoPath}</h1>";
        }

        // Inclui o rodapé
        if (file_exists($rodapePath)) {
            require $rodapePath;
        }
    }
}
