<?php

namespace App\Features\Enquetes\Models;

use App\Core\Database;
use PDO;

class EnqueteRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function findAllActive(): array
    {
        try {

            $sql = "SELECT id, titulo, descricao, slug FROM enquetes WHERE status = 'ativa' ORDER BY criado_em DESC";

            $stmt = $this->db->query($sql);
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            error_log("Erro ao buscar enquetes: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Busca uma enquete ativa pelo seu slug e inclui suas opções.
     * @param string $slug
     * @return array|null Retorna um array com os dados da enquete e suas opções, ou null se não encontrar.
     */
    public function findBySlugWithOptions(string $slug): ?array
    {
        try {
            // 1. Busca a enquete
            $sqlEnquete = "SELECT id, titulo, descricao FROM enquetes WHERE slug = :slug AND status = 'ativa'";
            $stmtEnquete = $this->db->prepare($sqlEnquete);
            $stmtEnquete->execute(['slug' => $slug]);
            $enquete = $stmtEnquete->fetch();

            // Se a enquete não for encontrada, retorna null imediatamente
            if (!$enquete) {
                return null;
            }

            // 2. Se encontrou a enquete, busca suas opções
            $sqlOpcoes = "SELECT id, texto FROM opcoes WHERE enquete_id = :enquete_id";
            $stmtOpcoes = $this->db->prepare($sqlOpcoes);
            $stmtOpcoes->execute(['enquete_id' => $enquete['id']]);
            $opcoes = $stmtOpcoes->fetchAll();

            // 3. Adiciona as opções ao array da enquete
            $enquete['opcoes'] = $opcoes;

            return $enquete;
        } catch (\PDOException $e) {
            error_log("Erro ao buscar enquete por slug: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Salva um novo voto no banco de dados.
     * @param int $opcaoId
     * @param string $ipAddress
     * @return bool Retorna true em caso de sucesso, false em caso de falha.
     */
    public function salvarVoto(int $opcaoId, string $ipAddress): bool
    {
        try {
            $sql = "INSERT INTO votos (opcao_id, endereco_ip) VALUES (:opcao_id, :endereco_ip)";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'opcao_id' => $opcaoId,
                'endereco_ip' => $ipAddress
            ]);
        } catch (\PDOException $e) {
            error_log("Erro ao salvar voto: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Busca TODAS as enquetes no banco de dados, independentemente do status.
     * @return array
     */
    public function findAll(): array
    {
        try {
            // A query é a mesma da área pública, mas sem o "WHERE status = 'ativa'"
            $sql = "SELECT id, titulo, slug, status, criado_em FROM enquetes ORDER BY criado_em DESC";

            $stmt = $this->db->query($sql);
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            error_log("Erro ao buscar todas as enquetes: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Busca uma enquete e suas opções pelo ID.
     * @param int $id
     * @return array|null
     */
    public function findByIdWithOptions(int $id): ?array
    {
        try {
            // Busca a enquete pelo ID, sem verificar o status
            $sqlEnquete = "SELECT * FROM enquetes WHERE id = :id";
            $stmtEnquete = $this->db->prepare($sqlEnquete);
            $stmtEnquete->execute(['id' => $id]);
            $enquete = $stmtEnquete->fetch();

            if (!$enquete) {
                return null;
            }

            // Busca as opções associadas
            $sqlOpcoes = "SELECT id, texto FROM opcoes WHERE enquete_id = :enquete_id";
            $stmtOpcoes = $this->db->prepare($sqlOpcoes);
            $stmtOpcoes->execute(['enquete_id' => $enquete['id']]);
            $opcoes = $stmtOpcoes->fetchAll();

            $enquete['opcoes'] = $opcoes;
            return $enquete;
        } catch (\PDOException $e) {
            error_log("Erro ao buscar enquete por ID: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Cria uma nova enquete e suas opções usando uma transação.
     * @param array $dados Os dados da enquete e opções.
     * @return bool True em sucesso, false em falha.
     */
    public function criarEnquete(array $dados): bool
    {
        try {
            // Inicia a transação
            $this->db->beginTransaction();

            // 1. Insere a enquete principal
            $sqlEnquete = "INSERT INTO enquetes (titulo, descricao, slug, status) VALUES (:titulo, :descricao, :slug, :status)";
            $stmtEnquete = $this->db->prepare($sqlEnquete);
            $stmtEnquete->execute([
                ':titulo' => $dados['titulo'],
                ':descricao' => $dados['descricao'],
                ':slug' => $dados['slug'] . '-' . uniqid(), // Adiciona uniqid para garantir slug único
                ':status' => $dados['status']
            ]);

            // Pega o ID da enquete que acabamos de inserir
            $enqueteId = $this->db->lastInsertId();

            // 2. Insere as opções em um loop
            $sqlOpcao = "INSERT INTO opcoes (enquete_id, texto) VALUES (:enquete_id, :texto)";
            $stmtOpcao = $this->db->prepare($sqlOpcao);
            foreach ($dados['opcoes'] as $textoOpcao) {
                $stmtOpcao->execute([
                    ':enquete_id' => $enqueteId,
                    ':texto' => $textoOpcao
                ]);
            }

            // Se tudo deu certo, confirma a transação
            $this->db->commit();
            return true;
        } catch (\PDOException $e) {
            // Se algo deu errado, desfaz a transação
            $this->db->rollBack();
            error_log("Erro ao criar enquete: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Atualiza uma enquete e suas opções usando uma transação.
     * @param int $id O ID da enquete a ser atualizada.
     * @param array $dados Os novos dados.
     * @return bool True em sucesso, false em falha.
     */
    public function atualizarEnquete(int $id, array $dados): bool
    {
        try {
            $this->db->beginTransaction();

            // 1. Atualiza a enquete principal
            $sqlEnquete = "UPDATE enquetes SET titulo = :titulo, descricao = :descricao, slug = :slug, status = :status WHERE id = :id";
            $stmtEnquete = $this->db->prepare($sqlEnquete);
            $stmtEnquete->execute([
                ':titulo' => $dados['titulo'],
                ':descricao' => $dados['descricao'],
                ':slug' => $dados['slug'],
                ':status' => $dados['status'],
                ':id' => $id
            ]);

            // 2. Lógica para as opções: Apaga todas as opções antigas e insere as novas.
            // Esta é a abordagem mais simples e segura para evitar lógica complexa de
            // verificar quais opções foram adicionadas, removidas ou alteradas.

            // Apaga opções antigas
            $stmtDelete = $this->db->prepare("DELETE FROM opcoes WHERE enquete_id = :enquete_id");
            $stmtDelete->execute([':enquete_id' => $id]);

            // Insere as novas opções
            $sqlOpcao = "INSERT INTO opcoes (enquete_id, texto) VALUES (:enquete_id, :texto)";
            $stmtOpcao = $this->db->prepare($sqlOpcao);
            foreach ($dados['opcoes'] as $textoOpcao) {
                $stmtOpcao->execute([
                    ':enquete_id' => $id,
                    ':texto' => $textoOpcao
                ]);
            }

            $this->db->commit();
            return true;
        } catch (\PDOException $e) {
            $this->db->rollBack();
            error_log("Erro ao atualizar enquete: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Exclui uma enquete do banco de dados pelo seu ID.
     * @param int $id
     * @return bool True em sucesso, false em falha.
     */
    public function deleteById(int $id): bool
    {
        try {
            $sql = "DELETE FROM enquetes WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([':id' => $id]);
        } catch (\PDOException $e) {
            error_log("Erro ao excluir enquete: " . $e->getMessage());
            return false;
        }
    }
}
