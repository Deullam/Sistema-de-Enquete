<?php
namespace App\Core; // Adicione o namespace!

use PDO;
use PDOException;

/**
 * Database Class
 * Implantação do padrão Singleton para garantir uma única conexão PDO.
 */
final class Database { // 'final' impede que a classe seja estendida.
    
    private static ?self $instance = null; // Armazena a instância única (PHP 7.4+)
    private PDO $connection; // Armazena o objeto PDO

    /**
     * O construtor é privado. Ele carrega o .env e estabelece a conexão.
     */
    private function __construct() {
        $this->loadEnv();
        
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $dbname = $_ENV['DB_NAME'] ?? 'deullam';
        $user = $_ENV['DB_USER'] ?? 'deullam';
        $pass = $_ENV['DB_PASSWORD'] ?? 'deullam';
        
        $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $this->connection = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            // Em um ambiente de produção, logue o erro em vez de exibi-lo.
            error_log('Database Connection Failed: ' . $e->getMessage());
            // Para o usuário final, uma mensagem genérica é mais segura.
            die("Ocorreu um erro inesperado. Por favor, tente novamente mais tarde.");
        }
    }

    /**
     * O método estático que controla o acesso à instância Singleton.
     */
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Retorna o objeto de conexão PDO bruto para ser usado em repositórios.
     * É a forma mais flexível de usar a classe.
     */
    public function getConnection(): PDO {
        return $this->connection;
    }

    /**
     * Carrega as variáveis de ambiente do arquivo .env.
     * Método privado para ser usado apenas pelo construtor.
     */
    private function loadEnv(): void {
        if (isset($_ENV['DB_HOST'])) {
            return; // Já carregado
        }

        $envFile = __DIR__ . '/../../.env';
        if (!file_exists($envFile)) {
            die("Arquivo de configuração .env não encontrado.");
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) {
                continue;
            }
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }

    /**
     * Impede a clonagem da instância.
     */
    private function __clone() {}

    /**
     * Impede a desserialização da instância.
     */
    public function __wakeup() {
        throw new \Exception("Cannot unserialize a singleton.");
    }
}
