<?php
namespace App\Features\Enquetes\Models;

use App\Core\Database;
use PDO;

class EnqueteRepository {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function findAllActive(): array {
        try {
            $sql = "SELECT id, titulo, descricao, slug FROM enquetes WHERE status = 'ativa' ORDER BY criado_em DESC";
            
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll();

        } catch (\PDOException $e) {
            error_log("Erro ao buscar enquetes: " . $e->getMessage());
            return [];
        }
    }
}
