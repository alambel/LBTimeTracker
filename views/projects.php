<div class="page-header">
    <h1>Projets</h1>
</div>

<?php if ($error ?? null): ?><div class="error"><?= e($error) ?></div><?php endif; ?>

<form method="post" class="project-new">
    <input type="hidden" name="op" value="create">
    <label>Couleur <input type="color" name="color" value="#4a90e2"></label>
    <label>Nom <input type="text" name="name" required placeholder="Nom du projet"></label>
    <button type="submit" class="primary">Ajouter</button>
</form>

<?php if (empty($projects)): ?>
    <div class="empty-state"><p>Aucun projet. Ajoutez-en un ci-dessus.</p></div>
<?php else: ?>

<div class="project-list">
    <?php foreach ($projects as $p): ?>
        <form method="post" class="project-row<?= $p['archived'] ? ' archived' : '' ?>">
            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <input type="color" name="color" value="<?= e($p['color']) ?>">
            <input type="text" name="name" value="<?= e($p['name']) ?>" required>
            <label><input type="checkbox" name="archived" value="1" <?= $p['archived'] ? 'checked' : '' ?>> Archivé</label>
            <button type="submit" name="op" value="update">Enregistrer</button>
            <button type="submit" name="op" value="delete" class="danger"
                    onclick="return confirm('Supprimer « <?= e(addslashes($p['name'])) ?> » et toutes ses saisies ?')">Supprimer</button>
        </form>
    <?php endforeach; ?>
</div>

<?php endif; ?>
