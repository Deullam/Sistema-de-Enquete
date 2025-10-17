<?php
/**
 * Database Class
 * Singleton pattern implementation for database connection
 */
class Database
{
    private static $instance = null;
    private $connection = null;
    private $host;
    private $db_name;
    private $username;
    private $password;

    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct()
    {
        // $this->loadEnv();
        $this->carregarEnv();
        // The default values are for docker environment
        $this->host = getenv('DB_HOST') ?: 'localhost';
        $this->db_name = getenv('DB_NAME') ?: 'deullam';
        $this->username = getenv('DB_USER') ?: 'deullam';
        $this->password = getenv('DB_PASSWORD') ?: 'deullam';
    }
private static $conexao = null;
    
    /**
     * Obtém conexão PDO
     * Se não existir, cria uma nova
     */
    public static function conectar()
    {
        if (self::$conexao === null) {
            self::criarConexao();
        }
        
        return self::$conexao;
    }
    
    /**
     * Cria conexão PDO com MySQL
     */
    private static function criarConexao()
    {
        //Carrega variáveis do .env
        self::carregarEnv();
        
        // Pega configurações do ambiente
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $banco = $_ENV['DB_NAME'] ?? 'deullam';
        $usuario = $_ENV['DB_USER'] ?? 'deullam';
        $senha = $_ENV['DB_PASSWORD'] ?? 'deullam';
        
        // Monta string de conexão
        $dsn = "mysql:host={$host};dbname={$banco};charset=utf8mb4;user={$usuario};password={$senha}";
        
        // Opções de segurança
        $opcoes = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];
        try {
            self::$conexao = new PDO($dsn, $usuario, $senha, $opcoes);
        } catch (PDOException $e) {
             // Better error handling based on environment
            if (getenv('APP_DEBUG') === 'true') {
                throw $e; 
            } else {
                // Log error but don't expose details in production
                error_log('Database connection failed: ' . $e->getMessage());
                die("Erro ao conectar no banco: " . $e->getMessage());
            }
        }
    }

    /**
     * Carrega arquivo .env
     */
    private static function carregarEnv()
    {
        // Se já carregou, não faz nada
        if (isset($_ENV['DB_HOST'])) {
            return;
        }
        $arquivo = __DIR__ . '/../../.env';
        if (!file_exists($arquivo)) {
            die("Arquivo .env não encontrado!");
        }
        // Lê arquivo linha por linha
        $linhas = file($arquivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($linhas as $linha) {
            $linha = trim($linha);
            // Ignora comentários e linhas vazias
            if (empty($linha) || $linha[0] === '#') {
                continue;
            }
            // Separa CHAVE=VALOR
            if (strpos($linha, '=') !== false) {
                list($chave, $valor) = explode('=', $linha, 2);
                
                $chave = trim($chave);
                $valor = trim($valor);
                // Remove aspas se tiver
                $valor = trim($valor, '"\'');
                // Salva na variável de ambiente
                $_ENV[$chave] = $valor;
                putenv("{$chave}={$valor}");
            }
        }
    }

    /**
     * Retorna instância única do Banco de dados
     * @return Database
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
     
    /**
     * Executa query com parâmetros (prepared statement)
     * @param string $query SQL query com placeholders
     * @param array $params Parametros para bindar na query
     * @return PDOStatement|false
     */
      /**
     * Executa query com parâmetros (prepared statement)
     */
    public static function query($sql, $parametros = [])
    {
        $conexao = self::conectar();
        $stmt = $conexao->prepare($sql);
        $stmt->execute($parametros);
        
        return $stmt;
    }

    /**
     * Busca um único registro
     * @param string $query SQL query com placeholders
     * @param array $params Parametros para bindar na query
     * @return array|false Registro único ou false em caso de falha
     */
    public function single($query, $params = [])
    {
        $stmt = $this->query($query, $params);
        return $stmt ? $stmt->fetch() : false;
    }

    /**
     * Busca múltiplos registros
     * @param string $query SQL query com placeholders
     * @param array $params Parametros para bindar na query
     * @return array|false Array de registros ou false em caso de falha
     */
    public function resultSet($query, $params = [])
    {
        $stmt = $this->query($query, $params);
        return $stmt ? $stmt->fetchAll() : false;
    }

    
    /**
     * Retorna ID do último insert
     */
    public static function ultimoId()
    {
        return self::conectar()->lastInsertId();
    }
    
    /**
     * Inicia transação
     */
    public static function iniciarTransacao()
    {
        return self::conectar()->beginTransaction();
    }
    
    /**
     * Confirma transação
     */
    public static function confirmar()
    {
        return self::conectar()->commit();
    }
    
    /**
     * Cancela transação
     */
    public static function cancelar()
    {
        return self::conectar()->rollBack();
    }
}
