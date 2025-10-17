<?php
// O namespace segue a estrutura de pastas
namespace App\Features\Admin\Controllers;

use App\Core\Controller;
use App\Features\Enquetes\Models\EnqueteRepository; // Vamos reutilizar o repositório de enquetes

class AdminController extends Controller
{
    private EnqueteRepository $enqueteRepository;

    public function __construct()
    {
        // Instancia o repositório para que possamos usá-lo nos métodos
        $this->enqueteRepository = new EnqueteRepository();
    }

    /**
     * Exibe o painel principal com a lista de todas as enquetes.
     */
    public function dashboard()
    {
        // Busca todas as enquetes do banco de dados usando o novo método que vamos criar
        $todasAsEnquetes = $this->enqueteRepository->findAll();

        $dadosParaView = [
            'pageTitle' => 'Painel Administrativo - Enquetes',
            'enquetes' => $todasAsEnquetes
        ];

        // Renderiza a view do dashboard
        $this->view('features/admin/views/dashboard', $dadosParaView);
    }

    /**
     * Exibe o formulário para criar uma nova enquete.
     */
    public function criar()
    {
        $dadosParaView = [
            'pageTitle' => 'Criar Nova Enquete'
            // Não passamos uma enquete, então o formulário ficará vazio
        ];
        $this->view('features/admin/views/formEnquete', $dadosParaView);
    }

    /**
     * Exibe o formulário para editar uma enquete existente.
     * @param int $id O ID da enquete vindo da URL.
     */
    public function editar(int $id)
    {
        // Busca a enquete e suas opções no banco de dados
        $enquete = $this->enqueteRepository->findByIdWithOptions($id);

        if (!$enquete) {
            // Lidar com o caso de enquete não encontrada
            http_response_code(404);
            echo "Enquete não encontrada.";
            return;
        }

        $dadosParaView = [
            'pageTitle' => 'Editar Enquete: ' . $enquete['titulo'],
            'enquete' => $enquete // Passa os dados da enquete para a view
        ];
        $this->view('features/admin/views/formEnquete', $dadosParaView);
    }

    /**
     * Salva os dados de uma enquete (criação ou edição).
     * @param int|null $id O ID da enquete para edição, ou null para criação.
     */
    public function salvar(?int $id = null)
    {
        // Garante que o método só seja acessível via POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /admin/dashboard');
            exit;
        }

        // 1. Coleta e sanitiza os dados do formulário
        $titulo = trim($_POST['titulo'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $status = $_POST['status'] ?? 'inativa';

        // Filtra as opções, removendo as que foram enviadas em branco
        $opcoesTextos = array_filter($_POST['opcoes'] ?? [], function ($texto) {
            return !empty(trim($texto));
        });

        // 2. Validação básica
        if (empty($titulo) || count($opcoesTextos) < 2) {
            // Se a validação falhar, redireciona de volta com uma mensagem de erro
            // (Uma implementação mais avançada usaria sessões para mostrar os erros)
            echo "Erro: O título é obrigatório e a enquete deve ter pelo menos 2 opções.";
            // Idealmente, redirecionar de volta para o formulário preenchido
            // header('Location: ' . $_SERVER['HTTP_REFERER']);
            return;
        }

        // 3. Prepara os dados para o repositório
        $dadosEnquete = [
            'titulo' => $titulo,
            'descricao' => $descricao,
            'status' => $status,
            // Gera um slug a partir do título (lógica simples)
            'slug' => strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', $titulo)),
            'opcoes' => $opcoesTextos
        ];

        // 4. Chama o repositório para salvar os dados
        if ($id === null) {
            // Modo Criação
            $sucesso = $this->enqueteRepository->criarEnquete($dadosEnquete);
        } else {
            // Modo Edição
            $sucesso = $this->enqueteRepository->atualizarEnquete($id, $dadosEnquete);
        }

        // 5. Redireciona com base no resultado
        if ($sucesso) {
            // Redireciona para o dashboard em caso de sucesso
            header('Location: /admin/dashboard');
            exit;
        } else {
            // Mostra uma mensagem de erro genérica
            echo "Ocorreu um erro ao salvar a enquete.";
        }
    }

    /**
     * Processa a exclusão de uma enquete.
     * @param int $id O ID da enquete a ser excluída.
     */
    public function excluir(int $id)
    {
        // Garante que a requisição seja via POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /admin/dashboard');
            exit;
        }

        // Chama o repositório para deletar a enquete
        $sucesso = $this->enqueteRepository->deleteById($id);

        // TODO: Adicionar mensagens de feedback (sucesso/erro) usando sessão flash.

        // Redireciona de volta para o dashboard em qualquer caso
        header('Location: /admin/dashboard');
        exit;
    }
}
