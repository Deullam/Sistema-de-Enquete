<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($pageTitle ?? 'Enquetes'); ?></title>
</head>
<body>
    <h1><?php echo htmlspecialchars($pageTitle ?? 'Enquetes'); ?></h1>

    <?php if (isset($enquetes) && !empty($enquetes)): ?>
        <ul>
            <?php 
            foreach ($enquetes as $itemDaEnquete): 
            ?>
                <li>
                    <a href="/enquetes/<?php echo htmlspecialchars($itemDaEnquete['slug']); ?>">
                        <?php echo htmlspecialchars($itemDaEnquete['titulo']); ?>
                    </a>
                </li>
            <?php 
            endforeach; 
            ?>
        </ul>
    <?php else: ?>
        <p>Nenhuma enquete encontrada no momento.</p>
    <?php endif; ?>
</body>
</html>
