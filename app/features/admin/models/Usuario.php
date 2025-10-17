<?php

/**
 * User Entity (Entidade Usuário Admin)
 * 
 * Representa um usuário administrativo do sistema.
 * Segue Single Responsibility: gerencia dados de usuários.
 */
class Usuario
{
    // Propriedades privadas (encapsulamento)
    private ?int $id = null;
    private string $nomeUsuario;
    private string $email;
    private string $password;
    private ?string $criado_em = null;
    private ?string $atualizado_em = null;
    
    /**
     * Construtor
     */
    public function __construct(string $nomeUsuario = '', string $email = '')
    {
        $this->nomeUsuario = $nomeUsuario;
        $this->email = $email;
    }
    
    // ========================================
    #region GETTERS
    // ========================================
    
    public function getId(): ?int
    {
        return $this->id;
    }
    
    public function getNomeUsuario(): string
    {
        return $this->nomeUsuario;
    }
    
    public function getEmail(): string
    {
        return $this->email;
    }
    
    public function getPassword(): string
    {
        return $this->password;
    }
    
    public function getCriadoEm(): ?string
    {
        return $this->criado_em;
    }
    
    public function getAtualizadoEm(): ?string
    {
        return $this->atualizado_em;
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
    
    public function setNomeUsuario(string $nomeUsuario): self
    {
        if (empty(trim($nomeUsuario))) {
            throw new InvalidArgumentException('Nome do Usuário não pode ser vazio');
        }
        
        if (strlen($nomeUsuario) < 3) {
            throw new InvalidArgumentException('Nome do Usuário deve ter no mínimo 3 caracteres');
        }
        
        $this->nomeUsuario = $nomeUsuario;
        return $this;
    }
    
    public function setEmail(string $email): self
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Email inválido');
        }
        
        $this->email = $email;
        return $this;
    }
    
    public function setPassword(string $password): self
    {
        $this->password = $password;
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
    #endregion SETTERS
    // ========================================
    #region MÉTODOS DE NEGÓCIO
    // ========================================
    
    /**
     * Define senha com hash bcrypt
     */
    public function definirSenha(string $senha_plana): self
    {
        if (strlen($senha_plana) < 6) {
            throw new InvalidArgumentException('Senha deve ter no mínimo 6 caracteres');
        }
        
        $this->password = password_hash($senha_plana, PASSWORD_DEFAULT);
        return $this;
    }
    
    /**
     * Verifica se senha está correta
     */
    public function verificarSenha(string $senha_plana): bool
    {
        return password_verify($senha_plana, $this->password);
    }
    
    /**
     * Verifica se senha precisa ser rehash
     */
    public function precisaRehash(): bool
    {
        return password_needs_rehash($this->password, PASSWORD_DEFAULT);
    }
    
    // ========================================
    #region CONVERSÃO DE DADOS
    // ========================================
    
    /**
     * Converte para array (SEM a senha por segurança)
     */
    public function paraArray(bool $incluir_senha = false): array
    {
        $dados = [
            'id' => $this->id,
            'nomeUsuario' => $this->nomeUsuario,
            'email' => $this->email,
            'criado_em' => $this->criado_em,
            'atualizado_em' => $this->atualizado_em
        ];
        
        if ($incluir_senha) {
            $dados['password'] = $this->password;
        }
        
        return $dados;
    }
    
    /**
     * Cria User a partir de array do banco
     * Factory Method Pattern
     */
    public static function criarDoBanco(array $dados): self
    {
        $user = new self();
        
        if (isset($dados['id'])) {
            $user->setId((int) $dados['id']);
        }
        
        $user->setNomeUsuario($dados['nomeUsuario'] ?? '');
        $user->setEmail($dados['email'] ?? '');
        
        if (isset($dados['password'])) {
            $user->setPassword($dados['password']);
        }
        
        if (isset($dados['created_at'])) {
            $user->setCriadoEm($dados['created_at']);
        }
        
        if (isset($dados['updated_at'])) {
            $user->setAtualizadoEm($dados['updated_at']);
        }
        
        return $user;
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
        
        if (empty($this->nomeUsuario)) {
            $erros[] = 'Nome do Usuário é obrigatório';
        } elseif (strlen($this->nomeUsuario) < 3) {
            $erros[] = 'Nome do Usuário deve ter no mínimo 3 caracteres';
        }
        
        if (empty($this->email)) {
            $erros[] = 'Email é obrigatório';
        } elseif (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $erros[] = 'Email inválido';
        }
        
        if (empty($this->password)) {
            $erros[] = 'Senha é obrigatória';
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