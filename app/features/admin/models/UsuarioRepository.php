<?php

namespace App\Features\Admin\Models;

use App\Core\Database;
use PDO;

class UsuarioRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Busca um usuário pelo seu nome de usuário ou email para autenticação.
     * @param string $nomeUsuario O nome de usuário ou email fornecido no login.
     * @return array|null Retorna os dados do usuário ou null se não for encontrado.
     */
    public function buscarPorUsuarioOuEmail(string $nomeUsuario): ?array
    {
        try {
            // Usando placeholders anônimos (?)
            $sql = "SELECT id, nome_usuario, senha FROM usuarios WHERE nome_usuario = ? OR email = ?";

            $stmt = $this->db->prepare($sql);

            // Passa os valores em um array indexado. A ordem importa!
            $stmt->execute([$nomeUsuario, $nomeUsuario]);

            $usuario = $stmt->fetch();
            return $usuario ?: null;
        } catch (\PDOException $e) {
            error_log("Erro ao buscar usuário: " . $e->getMessage());
            return null;
        }
    }
}
