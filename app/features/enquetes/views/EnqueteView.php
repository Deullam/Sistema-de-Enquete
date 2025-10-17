<h1><?php echo htmlspecialchars($pageTitle); ?></h1>
<p>Escolha uma das enquetes abaixo para participar.</p>

<?php if (isset($enquetes) && !empty($enquetes)): ?>
    <ul class="enquete-lista">
        <?php foreach ($enquetes as $itemDaEnquete): ?>
            <li>
                <a href="/enquetes/<?php echo htmlspecialchars($itemDaEnquete['slug']); ?>">
                    <?php echo htmlspecialchars($itemDaEnquete['titulo']); ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php else: ?>
    <p>Nenhuma enquete encontrada no momento.</p>
<?php endif; ?>
