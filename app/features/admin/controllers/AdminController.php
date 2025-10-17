<?php
// O namespace segue a estrutura de pastas
namespace App\Features\Admin\Controllers;

use App\Core\Controller;
use App\Features\Enquetes\Models\EnqueteRepository; // Vamos reutilizar o repositório de enquetes
use App\Features\Admin\Models\UsuarioRepository; // Adiciona o repositório de usuários

class AdminController extends Controller
{
    private EnqueteRepository $enqueteRepository;
    private UsuarioRepository $usuarioRepository;

    public function __construct()
    {
        // Instancia o repositório para que possamos usá-lo nos métodos
        $this->enqueteRepository = new EnqueteRepository();
        $this->usuarioRepository = new UsuarioRepository();
    }

    /**
     * Exibe o painel principal com a lista de todas as enquetes.
     */
    public function dashboard()
    {
        $this->verificarLogin();
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
        $this->verificarLogin();
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
        $this->verificarLogin();
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
        $this->verificarLogin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /admin/dashboard');
            exit;
        }
        $titulo = trim($_POST['titulo'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');
        $status = $_POST['status'] ?? 'inativa';

        // Filtra as opções, removendo as que foram enviadas em branco
        $opcoesTextos = array_filter($_POST['opcoes'] ?? [], function ($texto) {
            return !empty(trim($texto));
        });

        if (empty($titulo) || count($opcoesTextos) < 2) {
            echo "Erro: O título é obrigatório e a enquete deve ter pelo menos 2 opções.";
            return;
        }

        $dadosEnquete = [
            'titulo' => $titulo,
            'descricao' => $descricao,
            'status' => $status,
            'slug' => strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', $titulo)),
            'opcoes' => $opcoesTextos
        ];

        if ($id === null) {
            // Modo Criação
            $sucesso = $this->enqueteRepository->criarEnquete($dadosEnquete);
        } else {
            // Modo Edição
            $sucesso = $this->enqueteRepository->atualizarEnquete($id, $dadosEnquete);
        }

        if ($sucesso) {
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
        $this->verificarLogin();
        // Garante que a requisição seja via POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /admin/dashboard');
            exit;
        }

        $sucesso = $this->enqueteRepository->deleteById($id);

        // TODO: Adicionar mensagens de feedback (sucesso/erro) usando sessão flash.
        header('Location: /admin/dashboard');
        exit;
    }

    /**
     * Exibe a página de resultados de uma enquete específica.
     * @param int $id O ID da enquete.
     */
    public function resultados(int $id)
    {
        $this->verificarLogin();
        // Chama o novo método do repositório
        $enqueteComResultados = $this->enqueteRepository->findResultados($id);

        if (!$enqueteComResultados) {
            http_response_code(404);
            echo "Enquete não encontrada.";
            return;
        }

        $dadosParaView = [
            'pageTitle' => 'Resultados: ' . $enqueteComResultados['titulo'],
            'enquete' => $enqueteComResultados
        ];

        // Renderiza a nova view de resultados
        $this->view('features/admin/views/resultados', $dadosParaView);
    }

    /**
     * Exibe a página de login.
     */ /**
     * Exibe a página de login usando o layout padrão do sistema.
     */
    public function login()
    {
        // Prepara os dados para a view (mesmo que seja só o título)
        $dadosParaView = [
            'pageTitle' => 'Login - Painel Administrativo'
        ];

        // Usa o método view() para renderizar a página com o layout completo
        $this->view('features/admin/views/login', $dadosParaView);
    }
    private function verificarLogin()
    {
        // Se a sessão do usuário não estiver definida, redireciona para o login
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: /admin/login');
            exit;
        }
    }
    /**
     * Processa a tentativa de login.
     */
   public function autenticar()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: /admin/login');
        exit;
    }

    $nomeUsuario = $_POST['nome_usuario'] ?? '';
    $senha = $_POST['senha'] ?? '';

    $usuario = $this->usuarioRepository->buscarPorUsuarioOuEmail($nomeUsuario);

    // --- LÓGICA DE AUTENTICAÇÃO COM MODO DE TESTE ---

    $loginAprovado = false;

    if ($usuario) {
        // 1. Tenta a verificação segura primeiro (para o usuário 'admin')
        if (password_verify($senha, $usuario['senha'])) {
            $loginAprovado = true;
        } 
        // 2. Se a primeira falhar, tenta a verificação insegura (para o usuário 'tester')
        else if ($senha === $usuario['senha']) {
            // AVISO: Isto é inseguro e SÓ deve ser usado para depuração!
            $loginAprovado = true;
        }
    }

    // --- FIM DA LÓGICA DE AUTENTICAÇÃO ---

    if ($loginAprovado) {
        // Login bem-sucedido! Inicia a sessão.
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome_usuario'];

        header('Location: /admin/dashboard');
        exit;
    } else {
        // Credenciais inválidas para ambos os métodos.
        $dadosParaView = [
            'pageTitle' => 'Login - Erro',
            'erro' => 'Usuário ou senha inválidos.'
        ];
        $this->view('features/admin/views/login', $dadosParaView);
    }
}

    /**
     * Faz o logout do usuário.
     */
    public function logout()
    {
        // Limpa todas as variáveis de sessão
        $_SESSION = [];

        // Destrói a sessão
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        session_destroy();

        // Redireciona para a página de login
        header('Location: /admin/login');
        exit;
    }
}
