<?php

/**
 * Entidade Enquete
 * Contém regras de negócio específicas da entidade Enquete
 */
class Enquete
{
    private ?int $id = null;
    private string $titulo;
    private ?string $descricao = null;
    private string $slug;
    private string $status = 'active';
    private ?string $criado_em = null;
    private ?string $atualizado_em = null;
    private array $opcoes = [];
    
    public function __construct(string $titulo = '', string $slug = '')
    {
        $this->titulo = $titulo;
        $this->slug = $slug;
    }
    
    // ========================================
    #region GETTERS (Acessar propriedades)
    // ========================================
    
    public function getId(): ?int
    {
        return $this->id;
    }
    
    
    public function getTitulo(): string
    {
        return $this->titulo;
    }
    
    public function getDescricao(): ?string
    {
        return $this->descricao;
    }
    
    public function getSlug(): string
    {
        return $this->slug;
    }
    
    public function getStatus(): string
    {
        return $this->status;
    }
    
    public function getCriadoEm(): ?string
    {
        return $this->criado_em;
    }
    
    public function getAtualizadoEm(): ?string
    {
        return $this->atualizado_em;
    }
    
    public function getOpcoes(): array
    {
        return $this->opcoes;
    }
    #endregion GETTERS
    // ========================================
   
   
   
    #region SETTERS (Modificar propriedades)
    // ========================================
    
    public function setId(int $id): self
    {
        $this->id = $id;
        return $this; // Permite encadeamento: $poll->setId(1)->setTitulo('Teste')
    }
    
    public function setTitulo(string $titulo): self
    {
        if (empty(trim($titulo))) {
            throw new InvalidArgumentException('Título não pode ser vazio');
        }
        
        $this->titulo = $titulo;
        return $this;
    }
    
    public function setDescricao(?string $descricao): self
    {
        $this->descricao = $descricao;
        return $this;
    }
    
    public function setSlug(string $slug): self
    {
        if (empty(trim($slug))) {
            throw new InvalidArgumentException('Slug não pode ser vazio');
        }
        
        $this->slug = $slug;
        return $this;
    }
    
    public function setStatus(string $status): self
    {
        // Validação: só aceita 'active' ou 'inactive'
        if (!in_array($status, ['active', 'inactive'])) {
            throw new InvalidArgumentException('Status inválido. Use: active ou inactive');
        }
        
        $this->status = $status;
        return $this;
    }
    
    public function setCriadoEm(string $criado_em): self
    {
        $this->criado_em = $criado_em;
        return $this;
    }
    
    public function setAtualizadoEm(string $atualizado_em): self
    {
        $this->atualizado_em = $atualizado_em;
        return $this;
    }
    
    public function setOpcoes(array $opcoes): self
    {
        $this->opcoes = $opcoes;
        return $this;
    }
    
    public function adicionarOpcao($opcao): self
    {
        $this->opcoes[] = $opcao;
        return $this;
    }
    #endregion SETTERS
    // ========================================
    
    
    #region MÉTODOS DE NEGÓCIO
    // ========================================
    
    /**
     * Verifica se a enquete está ativa
     */
    public function estaAtiva(): bool
    {
        return $this->status === 'active';
    }
    
    /**
     * Ativa a enquete
     */
    public function ativar(): self
    {
        $this->status = 'active';
        return $this;
    }
    
    /**
     * Desativa a enquete
     */
    public function desativar(): self
    {
        $this->status = 'inactive';
        return $this;
    }
    
    /**
     * Conta total de opções
     */
    public function contarOpcoes(): int
    {
        return count($this->opcoes);
    }
    #endregion MÉTODOS DE NEGÓCIO
    // ========================================


    #region CONVERSÃO DE DADOS
    // ========================================
    
    /**
     * Converte objeto para array
     */
    public function paraArray(): array
    {
        return [
            'id' => $this->id,
            'titulo' => $this->titulo,
            'descricao' => $this->descricao,
            'slug' => $this->slug,
            'status' => $this->status,
            'criado_em' => $this->criado_em,
            'atualizado_em' => $this->atualizado_em,
            'opcoes' => array_map(function($opcao) {
                return is_object($opcao) && method_exists($opcao, 'paraArray') 
                    ? $opcao->paraArray() 
                    : $opcao;
            }, $this->opcoes)
        ];
    }
    
    /**
     * Cria Poll a partir de array do banco
     * Factory Method Pattern
     */
    public static function criarDoBanco(array $dados): self
    {
        $poll = new self();
        
        if (isset($dados['id'])) {
            $poll->setId((int) $dados['id']);
        }
        
        $poll->setTitulo($dados['title'] ?? '');
        
        if (isset($dados['description'])) {
            $poll->setDescricao($dados['description']);
        }
        
        $poll->setSlug($dados['slug'] ?? '');
        
        if (isset($dados['status'])) {
            $poll->setStatus($dados['status']);
        }
        
        if (isset($dados['created_at'])) {
            $poll->setCriadoEm($dados['created_at']);
        }
        
        if (isset($dados['updated_at'])) {
            $poll->setAtualizadoEm($dados['updated_at']);
        }
        
        return $poll;
    }
    #endregion CONVERSÃO DE DADOS
    // ========================================


    #region VALIDACAO
    // ========================================

     /**
     * Verifica se tem pelo menos 2 opções
     */
    public function temOpcoesSuficientes(): bool
    {
        return count($this->opcoes) >= 2;
    }
    /**
     * Validação completa do objeto
     */
    public function validar(): array
    {
        $erros = [];
        
        if (empty($this->titulo)) {
            $erros[] = 'Título é obrigatório';
        }
        
        if (empty($this->slug)) {
            $erros[] = 'Slug é obrigatório';
        }
        
        if (!$this->temOpcoesSuficientes()) {
            $erros[] = 'É necessário pelo menos 2 opções';
        }
        
        return $erros;
    }
    
    /**
     * Verifica se o objeto é válido
     */
    public function estaValido(): bool
    {
        return empty($this->validar());
    }
    #endregion VALIDACAO
    // ========================================
}