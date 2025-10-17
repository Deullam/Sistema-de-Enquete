<?php

/**
 * Entidade Opção
 * 
 * Representa uma opção de resposta em uma enquete.
 * Gerencia dados de uma opção.
 */
class Opcao
{
    // Propriedades privadas (encapsulamento)
    private ?int $id = null;
    private ?int $enquete_id = null;
    private string $texto;
    private int $ordem = 0;
    private int $total_votos = 0;
    private float $percentual = 0.0;
    
    /**
     * Construtor
     */
    public function __construct(string $texto = '', int $ordem = 0)
    {
        $this->texto = $texto;
        $this->ordem = $ordem;
    }
    
    // ========================================
    #region GETTERS
    // ========================================
    
    public function getId(): ?int
    {
        return $this->id;
    }
    
    public function getEnqueteId(): ?int
    {
        return $this->enquete_id;
    }
    
    public function getTexto(): string
    {
        return $this->texto;
    }
    
    public function getOrdem(): int
    {
        return $this->ordem;
    }
    
    public function getTotalVotos(): int
    {
        return $this->total_votos;
    }
    
    public function getPercentual(): float
    {
        return $this->percentual;
    }
    
    #endregion GETTERS
    // ========================================

    
    #region SETTERS
    // ========================================
    
    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }
    
    public function setEnqueteId(int $enquete_id): self
    {
        $this->enquete_id = $enquete_id;
        return $this;
    }
    
    public function setTexto(string $texto): self
    {
        if (empty(trim($texto))) {
            throw new InvalidArgumentException('Texto da opção não pode ser vazio');
        }
        
        $this->texto = $texto;
        return $this;
    }
    
    public function setOrdem(int $ordem): self
    {
        if ($ordem < 0) {
            throw new InvalidArgumentException('Ordem não pode ser negativa');
        }
        
        $this->ordem = $ordem;
        return $this;
    }
    
    public function setTotalVotos(int $total_votos): self
    {
        $this->total_votos = $total_votos;
        return $this;
    }
    
    public function setPercentual(float $percentual): self
    {
        $this->percentual = $percentual;
        return $this;
    }
    
    #endregion SETTERS
    // ========================================
    #
    // ========================================
    #region MÉTODOS DE NEGÓCIO  
    // ========================================
    
    /**
     * Incrementa contador de votos
     */
    public function incrementarVoto(): self
    {
        $this->total_votos++;
        return $this;
    }
    
    /**
     * Calcula percentual baseado no total geral
     */
    public function calcularPercentual(int $total_geral): self
    {
        if ($total_geral > 0) {
            $this->percentual = round(($this->total_votos / $total_geral) * 100, 1);
        } else {
            $this->percentual = 0.0;
        }
        
        return $this;
    }
    
    /**
     * Verifica se esta opção tem votos
     */
    public function temVotos(): bool
    {
        return $this->total_votos > 0;
    }
    
    /**
     * Verifica se é a opção vencedora
     */
    public function eVencedora(int $maior_total): bool
    {
        return $this->total_votos === $maior_total && $maior_total > 0;
    }
    #endregion MÉTODOS DE NEGÓCIO  
    // ========================================

    
    #region CONVERSÃO DE DADOS
    // ========================================
    
    /**
     * Converte para array
     */
    public function paraArray(): array
    {
        return [
            'id' => $this->id,
            'enquete_id' => $this->enquete_id,
            'texto' => $this->texto,
            'ordem' => $this->ordem,
            'total_votos' => $this->total_votos,
            'percentual' => $this->percentual
        ];
    }
    
    /**
     * Cria Option a partir de array do banco
     * Factory Method Pattern
     */
    public static function criarDoBanco(array $dados): self
    {
        $option = new self();
        
        if (isset($dados['id'])) {
            $option->setId((int) $dados['id']);
        }
        
        if (isset($dados['enquete_id'])) {
            $option->setEnqueteId((int) $dados['enquete_id']);
        }
        
        $option->setTexto($dados['text'] ?? '');
        $option->setOrdem((int) ($dados['display_order'] ?? 0));
        
        if (isset($dados['total_votos'])) {
            $option->setTotalVotos((int) $dados['total_votos']);
        }
        
        if (isset($dados['percentual'])) {
            $option->setPercentual((float) $dados['percentual']);
        }
        
        return $option;
    }
        
    #endregion CONVERSÃO DE DADOS
    // ========================================
    
    #region VALIDACAO
    // ========================================
    
    /**
     * Validação
     */
    public function validar(): array
    {
        $erros = [];
        
        if (empty($this->texto)) {
            $erros[] = 'Texto da opção é obrigatório';
        }
        
        if ($this->ordem < 0) {
            $erros[] = 'Ordem não pode ser negativa';
        }
        
        return $erros;
    }
    
    /**
     * Verifica se é válido
     */
    public function estaValido(): bool
    {
        return empty($this->validar());
    }
    #endregion VALIDACAO
}
    // ========================================
