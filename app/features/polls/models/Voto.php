<?php

/**
 * Entidade Voto
 * 
 * Representa um voto registrado em uma enquete.
 * Gerencia apenas dados de votos.
 */
class Voto
{
    // Propriedades privadas (encapsulamento)
    private ?int $id = null;
    private int $enquete_id;
    private int $opcao_id;
    private string $votante_id;
    private ?string $user_agent = null;
    private ?string $votado_em = null;
    
    /**
     * Construtor
     */
    public function __construct(
        int $enquete_id = 0, 
        int $opcao_id = 0, 
        string $identificador = ''
    ) {
        $this->enquete_id = $enquete_id;
        $this->opcao_id = $opcao_id;
        $this->votante_id = $identificador;
    }
    
    // ========================================
    #region GETTERS
    // ========================================
    
    public function getId(): ?int
    {
        return $this->id;
    }
    
    public function getEnqueteId(): int
    {
        return $this->enquete_id;   
    }
    
    public function getOpcaoId(): int
    {
        return $this->opcao_id;     
    }
    
    public function getVotanteId(): string
    {
        return $this->votante_id;
    }
    
    public function getUserAgent(): ?string
    {
        return $this->user_agent;
    }
    
    public function getVotadoEm(): ?string
    {
        return $this->votado_em;
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
    
    public function setEnqueteId(int $poll_id): self
    {
        if ($poll_id <= 0) {
            throw new InvalidArgumentException('Enquete ID deve ser maior que zero');
        }
        
        $this->enquete_id = $poll_id;
        return $this;
    }
    
    public function setOpcaoId(int $option_id): self
    {
        if ($option_id <= 0) {
            throw new InvalidArgumentException('Opcao ID deve ser maior que zero');
        }
        
        $this->opcao_id = $option_id;
        return $this;
    }
    
    public function setVotanteId(string $identificador): self
    {
        if (empty($identificador)) {
            throw new InvalidArgumentException('Identificador do votante não pode ser vazio');
        }
        
        $this->votante_id = $identificador;
        return $this;
    }
    
    public function setUserAgent(?string $user_agent): self
    {
        $this->user_agent = $user_agent;
        return $this;
    }
    
    public function setVotadoEm(string $votado_em): self
    {
        $this->votado_em = $votado_em;
        return $this;
    }
    #endregion SETTERS
    // ========================================
    #region MÉTODOS DE NEGÓCIO
    // ========================================
    
    /**
     * Verifica se o voto é válido
     */
    public function estaCompleto(): bool
    {
        return $this->enquete_id > 0 
            && $this->opcao_id > 0 
            && !empty($this->votante_id);
    }
    
    /**
     * Obtém hash do identificador (para comparações seguras)
     */
    public function getIdentificadorHash(): string
    {
        return md5($this->votante_id);
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
            'poll_id' => $this->enquete_id,
            'option_id' => $this->opcao_id,
            'votante_id' => $this->votante_id,
            'user_agent' => $this->user_agent,
            'votado_em' => $this->votado_em
        ];
    }
    
    /**
     * Cria Vote a partir de array do banco
     * Factory Method Pattern
     */
    public static function criarDoBanco(array $dados): self
    {
        $vote = new self();
        
        if (isset($dados['id'])) {
            $vote->setId((int) $dados['id']);
        }
        
        $vote->setEnqueteId((int) ($dados['enquete_id'] ?? 0));
        $vote->setOpcaoId((int) ($dados['option_id'] ?? 0));
        $vote->setVotanteId($dados['voter_identifier'] ?? '');
        
        if (isset($dados['user_agent'])) {
            $vote->setUserAgent($dados['user_agent']);
        }
        
        if (isset($dados['voted_at'])) {
            $vote->setVotadoEm($dados['voted_at']);
        }
        
        return $vote;
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
        
        if ($this->enquete_id <= 0) {
            $erros[] = 'Enquete ID inválido';
        }
        
        if ($this->opcao_id <= 0) {     
            $erros[] = 'Opcao ID inválido';
        }
        
        if (empty($this->votante_id)) {
            $erros[] = 'Identificador do votante é obrigatório';
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
    // ========================================
}