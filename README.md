# Sistema de Enquetes em PHP Puro

Este projeto é a implementação de um sistema de enquetes online, desenvolvido como parte de uma prova prática. O sistema foi construído utilizando PHP puro, seguindo o padrão de arquitetura MVC (Model-View-Controller) e princípios de Orientação a Objetos.

O projeto inclui uma área pública para visualização e votação em enquetes, e um painel administrativo protegido por senha para gerenciamento completo das enquetes (CRUD - Criar, Ler, Atualizar, Excluir) e visualização dos resultados.

## Funcionalidades

### Área Pública
*   Listagem de enquetes ativas.
*   Página de detalhes para cada enquete com opções de voto.
*   Sistema de votação com validação para evitar votos duplicados por sessão.
*   URLs amigáveis (ex: `/enquetes/qual-sua-cor-favorita`).

### Área Administrativa
*   Acesso protegido por login e senha.
*   Dashboard com a listagem de todas as enquetes (ativas e inativas).
*   Funcionalidades CRUD completas para enquetes e suas opções.
*   Página de visualização dos resultados de cada enquete, com contagem de votos e percentuais.

## Tecnologias Utilizadas

*   **Back-end:** PHP 8+ (Puro, sem frameworks)
*   **Front-end:** HTML5, CSS3, JavaScript (com jQuery para Ajax)
*   **Banco de Dados:** MySQL
*   **Servidor:** Apache (via `.htaccess`) ou Servidor Embutido do PHP
*   **Containerização:** Docker e Docker Compose

## Pré-requisitos

Antes de começar, garanta que você tem os seguintes softwares instalados:

*   **Para rodar sem Docker:**
    *   PHP 8 ou superior
    *   MySQL Server
    *   Um cliente de banco de dados (como DBeaver, HeidiSQL ou phpMyAdmin)
*   **Para rodar com Docker:**
    *   Docker
    *   Docker Compose

## Como Rodar o Projeto

Você pode executar o projeto de duas maneiras: utilizando Docker (recomendado para facilidade) ou manualmente com um ambiente PHP local.

### Método 1: Com Docker (Recomendado)

Esta é a forma mais simples de rodar o projeto, pois o Docker cuida de toda a configuração do ambiente.

1.  **Clone o Repositório:**
    ```bash
    git clone https://github.com/seu-usuario/seu-repositorio.git
    cd seu-repositorio
    ```

2.  **Configure o Ambiente:**
    Renomeie o arquivo `.env.example` para `.env`. As credenciais padrão já estão configuradas para funcionar com o `docker-compose.yml`.
    ```bash
    cp .env.example .env
    ```

3.  **Suba os Containers:**
    Execute o Docker Compose. Este comando irá construir a imagem do PHP, baixar a imagem do MySQL e iniciar os dois serviços em segundo plano.
    ```bash
    docker-compose up -d --build
    ```

4.  **Acesse o Projeto:**
    O sistema estará disponível no seu navegador no endereço:
    `http://localhost:8000`

5.  **Acessar o Banco de Dados (Opcional ):**
    O banco de dados MySQL estará rodando e acessível na porta `3306` da sua máquina local. Você pode usar um cliente de banco de dados para se conectar com as credenciais do arquivo `.env`.

6.  **Para Parar o Projeto:**
    ```bash
    docker-compose down
    ```

### Método 2: Manualmente (Sem Docker)

Se preferir não usar Docker, siga estes passos para configurar um ambiente local.

1.  **Clone o Repositório:**
    ```bash
    git clone https://github.com/seu-usuario/seu-repositorio.git
    cd seu-repositorio
    ```

2.  **Configure o Banco de Dados:**
    *   Crie um novo banco de dados no seu servidor MySQL.
    *   Importe a estrutura e os dados iniciais utilizando o arquivo `database.sql` fornecido no projeto.
    *   Exemplo usando o cliente MySQL no terminal:
        ```bash
        mysql -u seu_usuario -p seu_banco_de_dados < database.sql
        ```

3.  **Configure o Ambiente (`.env` ):**
    *   Renomeie o arquivo `.env.example` para `.env`.
    *   Abra o arquivo `.env` e atualize as variáveis `DB_HOST`, `DB_NAME`, `DB_USER`, e `DB_PASSWORD` com as credenciais do seu banco de dados local.

4.  **Inicie o Servidor Embutido do PHP:**
    O projeto está configurado para usar um script de roteamento (`public/server.php`) que funciona com o servidor embutido do PHP e simula o comportamento do `.htaccess`.
    
    Na **raiz do projeto**, execute o seguinte comando:
    ```bash
    php -S localhost:8000 -t public
    ```
    *   `localhost:8000`: Endereço e porta do servidor.
    *   `-t public`: Define a pasta `public` como a raiz do documento, o que é crucial para a segurança e para que os caminhos de CSS/JS funcionem.

5.  **Acesse o Projeto:**
    Abra seu navegador e acesse:
    `http://localhost:8000`

## Credenciais de Acesso

Para acessar o painel administrativo, utilize as seguintes credenciais:

*   **Usuário:** `admin`
*   **Senha:** `admin123`

O acesso ao painel pode ser feito através do link no menu de navegação ou diretamente pela URL `/admin/login`.

---

Desenvolvido por **[Deullam Justi]**.
