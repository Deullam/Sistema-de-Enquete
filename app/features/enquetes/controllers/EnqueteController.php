<?php
namespace App\Features\Enquetes\Controllers;

use App\Core\Controller;
use App\Features\Enquetes\Models\EnqueteRepository;

class EnqueteController extends Controller {
    private EnqueteRepository $enqueteRepository;

    public function __construct() {
        $this->enqueteRepository = new EnqueteRepository();
    }

    public function index() {
        // 1. Pede os dados ao repositório
        $enquetesDoBanco = $this->enqueteRepository->findAllActive();
        $dadosParaView = [
            'pageTitle' => 'Nossas Enquetes',
            'enquetes' => $enquetesDoBanco 
        ];

        $this->view('features/enquetes/views/EnqueteView', $dadosParaView);
    }
}
