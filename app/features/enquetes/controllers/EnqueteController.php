<?php

namespace App\Features\Enquetes\Controllers;

use App\Core\Controller;
use App\Features\Enquetes\Models\EnqueteRepository;

class EnqueteController extends Controller
{
    private EnqueteRepository $enqueteRepository;

    public function __construct()
    {
        $this->enqueteRepository = new EnqueteRepository();
    }

    public function index()
    {
        // 1. Pede os dados ao repositório
        $enquetesDoBanco = $this->enqueteRepository->findAllActive();
        $dadosParaView = [
            'pageTitle' => 'Nossas Enquetes',
            'enquetes' => $enquetesDoBanco
        ];

        $this->view('features/enquetes/views/EnqueteView', $dadosParaView);
    }

    /**
     * Exibe uma única enquete e suas opções de voto.
     * @param string $slug O slug da enquete vindo da URL.
     */
    public function exibir(string $slug)
    {
        // Pede ao repositório para buscar a enquete e suas opções pelo slug
        $enquete = $this->enqueteRepository->findBySlugWithOptions($slug);

        // Verifica se a enquete foi encontrada
        if (!$enquete) {
            // Se não encontrou, mostra uma página 404.
            // Você pode criar uma view bonita para isso depois.
            http_response_code(404);
            echo "<h1>404 - Enquete não encontrada</h1>";
            return;
        }

        $dadosParaView = [
            'pageTitle' => $enquete['titulo'], // Título da página será o título da enquete
            'enquete' => $enquete
        ];

        // Renderiza uma NOVA view, específica para exibir uma enquete.
        $this->view('features/enquetes/views/DetalheEnqueteView', $dadosParaView);
    }

    public function votar()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['opcao_id']) && isset($_POST['enquete_id'])) {

            $opcaoId = filter_var($_POST['opcao_id'], FILTER_VALIDATE_INT);
            $enqueteId = filter_var($_POST['enquete_id'], FILTER_VALIDATE_INT);

            // Validação de voto duplicado por sessão
            if (!isset($_SESSION['enquetes_votadas'])) {
                $_SESSION['enquetes_votadas'] = [];
            }

            if (in_array($enqueteId, $_SESSION['enquetes_votadas'])) {
                // --- USA A VIEW DE ERRO ---
                $dados = [
                    'pageTitle' => 'Erro na Votação',
                    'titulo_mensagem' => 'Voto Duplicado',
                    'mensagem' => 'Você já participou desta enquete.'
                ];
                $this->view('shared/views/mensagem_erro', $dados);
                return;
            }

            if ($opcaoId && $enqueteId) {
                $ipAddress = $_SERVER['REMOTE_ADDR'];
                $sucesso = $this->enqueteRepository->salvarVoto($opcaoId, $ipAddress);

                if ($sucesso) {
                    $_SESSION['enquetes_votadas'][] = $enqueteId;

                    // --- USA A VIEW DE SUCESSO ---
                    $dados = [
                        'pageTitle' => 'Voto Registrado',
                        'titulo_mensagem' => 'Obrigado por Votar!',
                        'mensagem' => 'Seu voto foi computado com sucesso.'
                    ];
                    $this->view('shared/views/mensagem_sucesso', $dados);
                } else {
                    // --- USA A VIEW DE ERRO ---
                    $dados = [
                        'pageTitle' => 'Erro no Sistema',
                        'titulo_mensagem' => 'Erro Inesperado',
                        'mensagem' => 'Não foi possível registrar seu voto no momento. Tente novamente mais tarde.'
                    ];
                    $this->view('shared/views/mensagem_erro', $dados);
                }
            }
        } else {
            header('Location: /enquetes');
            exit;
        }
    }
}
