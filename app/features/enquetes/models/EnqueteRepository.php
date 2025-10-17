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

            // Atualiza a enquete principal
            $sqlEnquete = "UPDATE enquetes SET titulo = :titulo, descricao = :descricao, slug = :slug, status = :status WHERE id = :id";
            $stmtEnquete = $this->db->prepare($sqlEnquete);
            $stmtEnquete->execute([
                ':titulo' => $dados['titulo'],
                ':descricao' => $dados['descricao'],
                ':slug' => $dados['slug'],
                ':status' => $dados['status'],
                ':id' => $id
            ]);

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


    /**
     * Busca os resultados de uma enquete, incluindo a contagem de votos e percentuais.
     * @param int $id O ID da enquete.
     * @return array|null Retorna um array com os dados da enquete e seus resultados, ou null se não encontrada.
     */
    public function findResultados(int $id): ?array
    {
        try {
            // 1. Primeiro, busca os dados básicos da enquete para garantir que ela existe.
            $sqlEnquete = "SELECT id, titulo, descricao FROM enquetes WHERE id = :id";
            $stmtEnquete = $this->db->prepare($sqlEnquete);
            $stmtEnquete->execute(['id' => $id]);
            $enquete = $stmtEnquete->fetch();

            if (!$enquete) {
                return null; // Enquete não encontrada
            }

            // 2. Agora, a consulta principal para buscar as opções e contar os votos.
            // LEFT JOIN: Garante que todas as opções da enquete sejam listadas, mesmo que não tenham votos.
            // COUNT(v.id): Conta o número de votos para cada opção.
            // GROUP BY o.id: Agrupa os votos por opção.
            $sqlResultados = "
            SELECT 
                o.id, 
                o.texto, 
                COUNT(v.id) as total_votos
            FROM opcoes o
            LEFT JOIN votos v ON o.id = v.opcao_id
            WHERE o.enquete_id = :enquete_id
            GROUP BY o.id, o.texto
            ORDER BY total_votos DESC
        ";

            $stmtResultados = $this->db->prepare($sqlResultados);
            $stmtResultados->execute(['enquete_id' => $id]);
            $opcoesComVotos = $stmtResultados->fetchAll();

            // 3. Calcula o total geral de votos para poder calcular os percentuais.
            $totalGeralVotos = array_sum(array_column($opcoesComVotos, 'total_votos'));

            // 4. Adiciona o percentual a cada opção.
            foreach ($opcoesComVotos as &$opcao) { // O '&' permite modificar o array diretamente
                if ($totalGeralVotos > 0) {
                    $opcao['percentual'] = round(($opcao['total_votos'] / $totalGeralVotos) * 100, 2);
                } else {
                    $opcao['percentual'] = 0;
                }
            }

            // 5. Adiciona os resultados ao array da enquete.
            $enquete['resultados'] = $opcoesComVotos;
            $enquete['total_geral_votos'] = $totalGeralVotos;

            return $enquete;
        } catch (\PDOException $e) {
            error_log("Erro ao buscar resultados da enquete: " . $e->getMessage());
            return null;
        }
    }
}
