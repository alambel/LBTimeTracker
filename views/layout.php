<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#fafaf7">
    <title><?= e($title ?? 'LBTimeTracker') ?></title>
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <link rel="icon" type="image/svg+xml" href="assets/icon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300;9..144,400;9..144,500&family=JetBrains+Mono:wght@400;500&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset_url('assets/style.css') ?>">
</head>
<body data-page="<?= e($page ?? '') ?>">
<?php
    $currentUserName = current_user() ?? '';
    $avatarLetter = strtoupper(mb_substr($currentUserName, 0, 1, 'UTF-8'));
    $navItems = [
        ['calendar', 'Calendrier', '01'],
        ['summary', 'Résumé', '02'],
        ['projects', 'Projets', '03'],
    ];
?>
<div class="lbtt-app">
    <aside class="lbtt-rail">
        <div>
            <div class="lbtt-brand">
                <span class="lbtt-brand-mark" aria-hidden="true"></span>
                <span class="lbtt-brand-code">LBTT · V1</span>
            </div>
            <div class="lbtt-rail-wordmark">LBTime<br>Tracker</div>
        </div>
        <nav class="lbtt-rail-nav" aria-label="Navigation principale">
            <?php foreach ($navItems as [$key, $label, $num]): ?>
                <a href="index.php?action=<?= e($key) ?>" class="lbtt-rail-nav-item<?= ($page ?? '') === $key ? ' active' : '' ?>">
                    <span class="n"><?= e($num) ?></span>
                    <span><?= e($label) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="lbtt-rail-session">
            <div class="lbtt-label" style="margin-bottom: 6px;">Session</div>
            <div class="lbtt-session-row">
                <div class="lbtt-avatar"><?= e($avatarLetter !== '' ? $avatarLetter : 'A') ?></div>
                <div>
                    <div class="lbtt-session-name"><?= e($currentUserName) ?></div>
                    <a href="index.php?action=logout" class="lbtt-session-logout">Déconnexion →</a>
                </div>
            </div>
        </div>
    </aside>
    <main class="lbtt-main">
        <?= $content ?>
        <?= format_deployment_footer() ?>
    </main>
</div>
<nav class="lbtt-tabbar" aria-label="Navigation">
    <?php foreach ($navItems as [$key, $label, $num]): ?>
        <a href="index.php?action=<?= e($key) ?>" class="lbtt-tab<?= ($page ?? '') === $key ? ' active' : '' ?>">
            <span class="ix"><?= e($num) ?></span>
            <span class="lbl"><?= e(strtolower($label)) ?>.</span>
        </a>
    <?php endforeach; ?>
    <a href="index.php?action=logout" class="lbtt-tab">
        <span class="ix">→</span>
        <span class="lbl">quitter.</span>
    </a>
</nav>
<script src="<?= asset_url('assets/app.js') ?>"></script>
</body>
</html>
