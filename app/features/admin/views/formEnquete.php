<?php
$tituloPagina = $modoEdicao ? 'Editar Enquete' : 'Criar Nova Enquete';
$actionUrl = $modoEdicao ? '/admin/salvar/' . $enquete['id'] : '/admin/salvar';
?>

<h1><?php echo $tituloPagina; ?></h1>

<form action="<?php echo $actionUrl; ?>" method="POST" class="form-admin">
    <div class="form-grupo">
        <label for="titulo">Título da Enquete</label>
        <input type="text" id="titulo" name="titulo" required 
               value="<?php echo htmlspecialchars($enquete['titulo'] ?? ''); ?>">
    </div>

    <div class="form-grupo">
        <label for="descricao">Descrição (Opcional)</label>
        <textarea id="descricao" name="descricao"><?php echo htmlspecialchars($enquete['descricao'] ?? ''); ?></textarea>
    </div>

    <div class="form-grupo">
        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="ativa" <?php echo (isset($enquete) && $enquete['status'] === 'ativa') ? 'selected' : ''; ?>>Ativa</option>
            <option value="inativa" <?php echo (isset($enquete) && $enquete['status'] === 'inativa') ? 'selected' : ''; ?>>Inativa</option>
        </select>
    </div>

    <hr>
    <h3>Opções de Resposta</h3>
    <div id="opcoes-container">
        <?php 
        // Se estiver editando, mostra as opções existentes
        $opcoes = $enquete['opcoes'] ?? [['texto' => ''], ['texto' => '']]; // Padrão de 2 opções vazias para criação
        foreach ($opcoes as $index => $opcao): ?>
            <div class="form-grupo">
                <label for="opcao-<?php echo $index; ?>">Opção <?php echo $index + 1; ?></label>
                <input type="text" id="opcao-<?php echo $index; ?>" name="opcoes[]" 
                       value="<?php echo htmlspecialchars($opcao['texto']); ?>"
                       placeholder="Digite o texto da opção">
            </div>
        <?php endforeach; ?>
    </div>
    <!-- No futuro, um botão "Adicionar Opção" com JavaScript pode ser colocado aqui -->

    <div class="form-acoes">
        <button type="submit" class="btn-principal">Salvar Enquete</button>
        <a href="/admin/dashboard" class="btn-secundario">Cancelar</a>
    </div>
</form>
