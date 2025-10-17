
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle ?? 'Sistema de Enquetes'); ?></title>
    <link rel="stylesheet" href="/css/estilo.css">
</head>
<body>

    <header class="navbar">
        <div class="container">
            <a href="/" class="navbar-brand">Sistema de Enquetes</a>
            <nav class="navbar-menu">
                <a href="/enquetes">Enquetes Públicas</a>
                 <a href="/admin/dashboard">Painel Admin</a>

                <!-- Lógica para alternar entre Login e Logout -->
                <?php if (isset($_SESSION['usuario_id'])): ?>
                    <a href="/admin/logout">Sair</a>
                <?php else: ?>
                    <a href="/admin/login">Login</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main class="container">
        <!-- O conteúdo principal de cada página será inserido aqui -->
