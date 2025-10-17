<a href="/admin/dashboard">&laquo; Voltar para o Dashboard</a>

<h1>Resultados da Enquete</h1>
<h2><?php echo htmlspecialchars($enquete['titulo']); ?></h2>
<p><?php echo htmlspecialchars($enquete['descricao']); ?></p>

<div class="info-total-votos">
    <strong>Total de Votos Registrados:</strong> <?php echo $enquete['total_geral_votos']; ?>
</div>

<hr>

<div class="lista-resultados">
    <?php if (empty($enquete['resultados'])): ?>
        <p>Ainda não há votos para esta enquete.</p>
    <?php else: ?>
        <?php foreach ($enquete['resultados'] as $resultado): ?>
            <div class="resultado-item">
                <div class="resultado-info">
                    <span class="opcao-texto"><?php echo htmlspecialchars($resultado['texto']); ?></span>
                    <span class="votos-contagem">
                        <?php echo $resultado['total_votos']; ?> voto(s) (<?php echo $resultado['percentual']; ?>%)
                    </span>
                </div>
                <div class="barra-progresso">
                    <div class="barra-progresso-preenchimento" style="width: <?php echo $resultado['percentual']; ?>%;"></div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
