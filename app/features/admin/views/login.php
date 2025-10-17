<div class="login-container-integrado">
    <h1>Login do Painel Administrativo</h1>

    <?php if (isset($erro)): ?>
        <div class="error-message"><?php echo htmlspecialchars($erro); ?></div>
    <?php endif; ?>

    <form action="/admin/autenticar" method="POST" class="form-admin">
        <div class="form-grupo">
            <label for="nome_usuario">Usuário ou Email</label>
            <input type="text" id="nome_usuario" name="nome_usuario" required>
        </div>
        <div class="form-grupo">
            <label for="senha">Senha</label>
            <input type="password" id="senha" name="senha" required>
        </div>
        <div class="form-acoes">
            <button type="submit" class="btn-principal" style="width: 100%;">Entrar</button>
        </div>
    </form>
</div>
