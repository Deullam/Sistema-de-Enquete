<?php
// gerar_senha.php

// A senha que queremos criptografar
$senhaPlana = 'admin123';

// Opções para o algoritmo. BCRYPT é o padrão e recomendado.
$opcoes = [
    'cost' => 10, // O custo padrão. Não precisa ser muito alto para um teste.
];

// Gera a hash da senha
$hashDaSenha = password_hash($senhaPlana, PASSWORD_BCRYPT, $opcoes);

// Exibe a hash gerada
echo "<h1>Nova Hash de Senha Gerada</h1>";
echo "<p>Senha original: <strong>" . htmlspecialchars($senhaPlana) . "</strong></p>";
echo "<p>Copie a hash abaixo e substitua na coluna 'password' do usuário 'admin' no seu banco de dados:</p>";
echo "<hr>";
echo "<pre style='background-color: #f0f0f0; padding: 10px; border: 1px solid #ccc; word-wrap: break-word;'>";
echo htmlspecialchars($hashDaSenha);
echo "</pre>";
echo "<hr>";
echo "<p><strong>IMPORTANTE:</strong> Após atualizar o banco de dados, delete este arquivo (`gerar_senha.php`) por segurança.</p>";
