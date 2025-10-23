-- Cria e utiliza o banco de dados
CREATE DATABASE IF NOT EXISTS enquete;
USE enquete;

-- Apaga as tabelas se existirem para evitar conflitos
DROP TABLE IF EXISTS votos;
DROP TABLE IF EXISTS opcoes;
DROP TABLE IF EXISTS enquetes;
DROP TABLE IF EXISTS usuarios;

-- Cria a tabela de usuários
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_usuario VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Cria a tabela de enquetes
CREATE TABLE enquetes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT,
    slug VARCHAR(100) NOT NULL UNIQUE,
    status ENUM('ativa', 'inativa') DEFAULT 'ativa', -- Traduzido
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Cria a tabela de opções
CREATE TABLE opcoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    enquete_id INT NOT NULL,
    texto VARCHAR(255) NOT NULL,
    FOREIGN KEY (enquete_id) REFERENCES enquetes(id) ON DELETE CASCADE
);

-- Cria a tabela de votos
CREATE TABLE votos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    opcao_id INT NOT NULL,
    endereco_ip VARCHAR(45) NOT NULL,
    FOREIGN KEY (opcao_id) REFERENCES opcoes(id) ON DELETE CASCADE
);

-- Insere um usuário administrador (senha: admin123)
INSERT INTO usuarios (nome_usuario, email, senha) VALUES 
('admin', 'admin@example.com', '$2y$10$.MHAWDFkSxYSqaZSbJCliOi30C/nEoRfYr1zICMKWRGGa.uDiJeu.'),
('testar', 'testar@example.com', 'admin123');

-- === INSERÇÃO DE DADOS EM PORTUGUÊS ===

-- Enquete 1: Atividade de Fim de Semana
INSERT INTO enquetes (titulo, descricao, slug, status) VALUES 
('Atividade Favorita de Fim de Semana', 'O que você mais gosta de fazer nos fins de semana?', 'atividade-fim-de-semana', 'ativa');
SET @enquete_id = LAST_INSERT_ID();
INSERT INTO opcoes (enquete_id, texto) VALUES 
(@enquete_id, 'Atividades ao ar livre'),
(@enquete_id, 'Ler livros'),
(@enquete_id, 'Assistir filmes');

-- Enquete 2: Linguagem de Programação
INSERT INTO enquetes (titulo, descricao, slug, status) VALUES 
('Melhor Linguagem de Programação', 'Qual linguagem de programação você prefere?', 'linguagem-programacao', 'ativa');
SET @enquete_id = LAST_INSERT_ID();
INSERT INTO opcoes (enquete_id, texto) VALUES 
(@enquete_id, 'PHP'),
(@enquete_id, 'JavaScript'),
(@enquete_id, 'Python');

-- Enquete 3: Ambiente de Trabalho (Inativa)
INSERT INTO enquetes (titulo, descricao, slug, status) VALUES 
('Ambiente de Trabalho Preferido', 'Onde você prefere trabalhar?', 'ambiente-trabalho', 'inativa');
SET @enquete_id = LAST_INSERT_ID();
INSERT INTO opcoes (enquete_id, texto) VALUES 
(@enquete_id, 'Escritório'),
(@enquete_id, 'Casa (Remoto)');
