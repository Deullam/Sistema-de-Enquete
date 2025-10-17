<div class="admin-header">
    <h1>Gerenciamento de Enquetes</h1>
    <a href="/admin/criar" class="btn-criar">Criar Nova Enquete</a>
</div>

<?php if (isset($enquetes) && !empty($enquetes)): ?>
    <table class="tabela-admin">
        <thead>
            <tr>
                <th>ID</th>
                <th>Título</th>
                <th>Status</th>
                <th>Data de Criação</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($enquetes as $enquete): ?>
                <tr>
                    <td><?php echo $enquete['id']; ?></td>
                    <td><?php echo htmlspecialchars($enquete['titulo']); ?></td>
                    <td>
                        <!-- Adiciona uma classe CSS baseada no status para estilização -->
                        <span class="status status-<?php echo htmlspecialchars($enquete['status']); ?>">
                            <?php echo ucfirst($enquete['status']); // Deixa a primeira letra maiúscula 
                            ?>
                        </span>
                    </td>
                    <td>
                        <?php
                        // Formata a data para o padrão brasileiro
                        $data = new DateTime($enquete['criado_em']);
                        echo $data->format('d/m/Y H:i');
                        ?>
                    </td>
                    <td class="acoes">
                        <a href="/admin/resultados/<?php echo $enquete['id']; ?>" class="btn-acao btn-resultados">Resultados</a>
                        <a href="/admin/editar/<?php echo $enquete['id']; ?>" class="btn-acao btn-editar">Editar</a>
                        <form action="/admin/excluir/<?php echo $enquete['id']; ?>" method="POST" style="display: inline;">
                            <button type="submit" class="btn-acao btn-excluir"
                                onclick="return confirm('Tem certeza que deseja excluir esta enquete? Esta ação não pode ser desfeita.');">
                                Excluir
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>Nenhuma enquete cadastrada ainda. <a href="/admin/enquetes/criar">Crie a primeira!</a></p>
<?php endif; ?>