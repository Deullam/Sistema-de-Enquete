# Sistema de Enquetes em PHP Puro

![Licença](https://img.shields.io/badge/Licença-MIT-blue)
![PHP](https://img.shields.io/badge/PHP-8%2B-777BB4?logo=php)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?logo=mysql)

Sistema de enquetes online escrito em PHP puro, sem framework e sem dependências
de terceiros. A organização segue o padrão MVC, com um roteador próprio, views em
PHP e acesso ao banco via PDO com prepared statements.

O projeto tem uma área pública, onde qualquer visitante lista e vota em enquetes,
e um painel administrativo protegido por login, onde as enquetes são criadas,
editadas, excluídas e têm seus resultados consultados.

Este é um projeto de demonstração, feito como prova prática. Ele **não está
preparado para uso em produção**: veja a seção *Limitações conhecidas*.

## Funcionalidades

### Área pública
* Listagem das enquetes ativas.
* Página de detalhe de cada enquete, com as opções de voto.
* Registro de voto com verificação de voto duplicado.
* URLs amigáveis por slug (ex.: `/enquetes/linguagem-programacao`).

### Painel administrativo
* Login por usuário ou e-mail, com senha verificada por `password_verify()`
  contra o hash bcrypt guardado no banco.
* Listagem de todas as enquetes, ativas e inativas.
* Criação, edição e exclusão de enquetes e de suas opções.
* Página de resultados por enquete, com contagem de votos e percentuais.

## Tecnologias

PHP 8 (sem framework, sem Composer), MySQL 8 e HTML/CSS escritos à mão.

O projeto **não usa JavaScript**: não há nenhum arquivo `.js` nem nenhuma tag
`<script>` em todo o código. Toda a interação acontece por formulários HTML e
navegação normal entre páginas.

## Pré-requisitos

* PHP 8 ou superior, com as extensões `pdo` e `pdo_mysql`.
* MySQL Server 8.
* Um cliente de banco de dados para importar o schema (linha de comando,
  DBeaver, HeidiSQL, phpMyAdmin ou equivalente).

## Como rodar

O caminho suportado é o manual, descrito abaixo. O empacotamento Docker que está
no repositório **não funciona** — veja a seção seguinte.

1. **Clone o repositório:**
   ```bash
   git clone https://github.com/Deullam/Sistema-de-Enquete.git
   cd Sistema-de-Enquete
   ```

2. **Crie o banco e importe o schema.**
   O arquivo `database.sql` cria o banco `enquete`, cria as tabelas e insere os
   dados iniciais (o usuário administrador de demonstração e três enquetes de
   exemplo). Atenção: o script começa com `DROP TABLE IF EXISTS`, então ele apaga
   e recria as tabelas a cada importação.
   ```bash
   mysql -u seu_usuario -p < database.sql
   ```

3. **Configure o `.env`.**
   Copie `.env.example` para `.env` e ajuste as variáveis para o seu banco local.
   `DB_NAME` precisa ser `enquete`, que é o banco criado pelo `database.sql`:
   ```bash
   cp .env.example .env
   ```
   ```
   DB_HOST=localhost
   DB_NAME=enquete
   DB_USER=seu_usuario
   DB_PASSWORD=sua_senha
   ```
   O arquivo `.env` é obrigatório: sem ele a aplicação para com uma mensagem de
   configuração não encontrada.

4. **Suba o servidor embutido do PHP**, a partir da raiz do projeto:
   ```bash
   php -S localhost:8000 -t public
   ```
   O `-t public` é necessário: só a pasta `public` deve ser exposta, para que o
   código em `app/` fique fora do alcance do navegador.
   Em Linux ou macOS (sistemas de arquivos sensíveis a maiúsculas) renomeie antes
   `public/Index.php` para `public/index.php`: o front controller está com `I`
   maiúsculo no repositório e o servidor embutido procura `index.php`. No Windows
   isso passa despercebido.

5. **Acesse** `http://localhost:8000`.
   O painel fica em `http://localhost:8000/admin/login`.

## Credenciais de demonstração

O `database.sql` cria um único usuário, para demonstração:

* **Usuário:** `admin`
* **Senha:** `admin123`

Essa senha é pública de propósito, porque este é um projeto de demonstração. Se
for publicar o sistema em qualquer lugar acessível, troque a senha antes: gere um
novo hash com `password_hash('sua-senha', PASSWORD_DEFAULT)` e atualize a coluna
`senha` do usuário.

## Docker: quebrado

O repositório contém um `Dockerfile` e um `docker-compose.yml`, mas **o
empacotamento Docker não sobe a aplicação**. Ele está documentado aqui como
pendência, não como alternativa de instalação. Use o método manual.

Os defeitos conhecidos, todos ainda abertos:

1. **O seed nunca é carregado.**
   O serviço `db` monta `./deullam.sql:/docker-entrypoint-initdb.d/deullam.sql`,
   mas o arquivo `deullam.sql` não existe no repositório. O Docker então cria um
   **diretório vazio** com esse nome e o monta no lugar do arquivo, de modo que
   nenhum schema é importado. O arquivo de seed que existe de verdade chama-se
   `database.sql`.

2. **Diferença de maiúsculas no nome do front controller.**
   O front controller está em `public/Index.php`, com `I` maiúsculo, enquanto o
   `public/.htaccess` reescreve para `index.php` e o `public/server.php` faz
   `require` de `index.php`. No Windows isso passa despercebido, porque o sistema
   de arquivos não diferencia maiúsculas; dentro do container Linux, diferencia,
   e o arquivo não é encontrado.

3. **A porta do MySQL não é publicada.**
   O mapeamento é `3308:3308`, mas o servidor MySQL escuta na `3306` dentro do
   container. Nada responde na porta `3308` do host.

4. **Banco, usuário e host não batem com o que a aplicação espera.**
   O compose cria o banco `mydb` com o usuário `dbroot`, enquanto o
   `database.sql` cria e usa o banco `enquete`. Além disso, o `.env.example` traz
   `DB_HOST=localhost`, que dentro do container do PHP aponta para o próprio
   container e não para o serviço de banco — precisaria ser `db`.

5. **A porta divulgada não é a porta publicada.**
   As instruções antigas mandavam acessar `http://localhost:8000`, mas o compose
   publica o serviço PHP em `8888:80`.

Consertar isso é trabalho de uma próxima rodada.

## Estrutura do projeto

```
app/
  core/                      Router, Controller base e conexão PDO (singleton)
  features/
    admin/                   Controller, repositório e views do painel
    enquetes/                Controller, repositório e views da área pública
  shared/views/layouts/      Cabeçalho e rodapé comuns
public/                      Raiz web: front controller, .htaccess e CSS
tests/DatabaseTest.php       Script de verificação da conexão com o banco
database.sql                 Schema e dados iniciais
```

## Limitações conhecidas

Além do Docker, e sendo este um projeto de demonstração:

* Não há proteção contra CSRF nos formulários do painel.
* A sessão usa a configuração padrão do PHP, sem regeneração de id no login.
* Não há cadastro nem troca de senha pela interface; o único usuário vem do seed.
* A página de erro 404 revela o nome da classe e do método procurados.
* A verificação de voto duplicado olha apenas a sessão do visitante. O endereço
  IP é gravado na tabela `votos`, mas não é consultado para bloquear repetição,
  então basta limpar os cookies para votar de novo.

## Licença

MIT. Veja o arquivo `LICENSE`.

---

Desenvolvido por **Deullam Justi**.
