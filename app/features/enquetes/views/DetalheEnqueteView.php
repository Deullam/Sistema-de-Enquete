<a href="/enquetes">&laquo; Voltar para todas as enquetes</a>

<h1><?php echo htmlspecialchars($enquete['titulo']); ?></h1>
<p><?php echo htmlspecialchars($enquete['descricao']); ?></p>

<hr>

<h3>Vote em uma das opções abaixo:</h3>

<form action="/enquetes/votar" method="POST">
    <input type="hidden" name="enquete_id" value="<?php echo $enquete['id']; ?>">

    <ul class="opcoes-lista">
        <?php foreach ($enquete['opcoes'] as $opcao): ?>
            <li>
                <label>
                    <input type="radio" name="opcao_id" value="<?php echo $opcao['id']; ?>" required>
                    <?php echo htmlspecialchars($opcao['texto']); ?>
                </label>
            </li>
        <?php endforeach; ?>
    </ul>

    <button type="submit">Votar</button>
</form>
