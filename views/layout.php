<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#1f2937">
    <title><?= e($title ?? 'LB Time Tracker') ?></title>
    <link rel="icon" type="image/svg+xml" href="assets/icon.svg">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body data-page="<?= e($page ?? '') ?>">
    <nav class="nav">
        <div class="nav-brand">
            <?= render_app_icon(22) ?>
            <span>LB Time Tracker</span>
        </div>
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
    <?= format_deployment_footer() ?>
    <nav class="bottom-nav" aria-label="Navigation principale">
        <a href="index.php?action=calendar" class="bnav-item <?= ($page ?? '') === 'calendar' ? 'active' : '' ?>">
            <?= render_nav_icon('calendar') ?>
            <span>Calendrier</span>
        </a>
        <a href="index.php?action=summary" class="bnav-item <?= ($page ?? '') === 'summary' ? 'active' : '' ?>">
            <?= render_nav_icon('summary') ?>
            <span>Résumé</span>
        </a>
        <a href="index.php?action=projects" class="bnav-item <?= ($page ?? '') === 'projects' ? 'active' : '' ?>">
            <?= render_nav_icon('projects') ?>
            <span>Projets</span>
        </a>
        <a href="index.php?action=logout" class="bnav-item">
            <?= render_nav_icon('logout') ?>
            <span>Quitter</span>
        </a>
    </nav>
    <script src="assets/app.js"></script>
</body>
</html>
