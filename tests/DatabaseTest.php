<?php
// tests/DatabaseTest.php

// Define a raiz do projeto para que o autoloader funcione corretamente
// __DIR__ é a pasta 'tests', então voltamos um nível.
define('PROJECT_ROOT', dirname(__DIR__));

// Inclui o autoloader manual do seu projeto.
// Isso é crucial para que 'use App\Core\Database;' funcione.
require_once PROJECT_ROOT . '/public/index.php'; // Ou onde quer que seu autoloader esteja definido

// Usa o namespace da classe Database
use App\Core\Database;

// Classe de teste para manter o código organizado
class DatabaseTest {

    public function run() {
        echo "=====================================\n";
        echo "Iniciando Teste de Conexão com o Banco de Dados...\n";
        echo "=====================================\n\n";

        try {
            // Passo 1: Tenta obter a instância Singleton da classe Database.
            // O construtor privado será chamado aqui na primeira vez.
            // É neste momento que a conexão é realmente criada.
            echo "1. Tentando obter a instância do Database...\n";
            $databaseInstance = Database::getInstance();
            echo "   -> Sucesso! Instância obtida.\n\n";

            // Passo 2: A partir da instância, tenta obter o objeto de conexão PDO.
            echo "2. Tentando obter o objeto de conexão PDO...\n";
            $pdoConnection = $databaseInstance->getConnection();

            // Passo 3: Verifica se o objeto retornado é realmente uma instância de PDO.
            // Este é o teste definitivo.
            if ($pdoConnection instanceof PDO) {
                // Para ter 100% de certeza, podemos pegar uma informação do servidor.
                $serverVersion = $pdoConnection->getAttribute(PDO::ATTR_SERVER_VERSION);
                echo "   -> Sucesso! Objeto PDO recebido.\n";
                echo "   -> Versão do Servidor de Banco de Dados: " . $serverVersion . "\n\n";
                echo "✅ SUCESSO: Conexão com o banco de dados estabelecida com êxito!\n";
            } else {
                // Este caso é improvável se não houver exceção, mas é uma boa verificação.
                echo "❌ ERRO: A classe Database não retornou um objeto PDO válido.\n";
                exit(1); // Sai com código de erro
            }

        } catch (\PDOException $e) {
            // Se a conexão falhar dentro do construtor da classe Database, uma PDOException será lançada.
            echo "\n❌ ERRO FATAL (PDOException):\n";
            echo "   Não foi possível conectar ao banco de dados.\n";
            echo "   Mensagem: " . $e->getMessage() . "\n";
            echo "   Verifique suas credenciais no arquivo .env (DB_HOST, DB_NAME, DB_USER, DB_PASSWORD).\n";
            exit(1); // Sai com código de erro

        } catch (\Exception $e) {
            // Captura qualquer outra exceção que possa ocorrer.
            echo "\n❌ ERRO INESPERADO (Exception):\n";
            echo "   Mensagem: " . $e->getMessage() . "\n";
            exit(1); // Sai com código de erro
        }

        echo "\nTeste de banco de dados concluído.\n";
    }
}

// Cria uma instância da classe de teste e executa o teste.
$test = new DatabaseTest();
$test->run();
