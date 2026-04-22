<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'LB Time Tracker') ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <nav class="nav">
        <div class="nav-brand">⏱ LB Time Tracker</div>
        <div class="nav-links">
            <a href="index.php?action=calendar" class="<?= ($page ?? '') === 'calendar' ? 'active' : '' ?>">Calendrier</a>
            <a href="index.php?action=summary" class="<?= ($page ?? '') === 'summary' ? 'active' : '' ?>">Résumé</a>
            <a href="index.php?action=projects" class="<?= ($page ?? '') === 'projects' ? 'active' : '' ?>">Projets</a>
        </div>
        <div class="nav-user">
            <span><?= e(current_user() ?? '') ?></span>
            <a href="index.php?action=logout">Déconnexion</a>
        </div>
    </nav>
    <main class="main">
        <?= $content ?>
    </main>
    <script src="assets/app.js"></script>
</body>
</html>
