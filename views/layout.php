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
    $isAppAdmin = false;
    $needsEmail = false;
    $meRow = null;
    if (isset($db) && $db instanceof PDO) {
        $meRow = current_user_row($db);
        $isAppAdmin = $meRow && !empty($meRow['is_app_admin']);
        $needsEmail = $meRow && empty($meRow['email']);
    }
    $displayName = $meRow ? display_name($meRow) : $currentUserName;
    $initials = user_initials($displayName);
    $myAvatar = $meRow ? avatar_url($meRow) : null;
    $navItems = [
        ['calendar', 'Calendrier', '01'],
        ['summary', 'Résumé', '02'],
        ['projects', 'Projets', '03'],
    ];
    if ($isAppAdmin) {
        $navItems[] = ['users', 'Utilisateurs', '04'];
    }
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
                <a href="<?= e(url($key)) ?>" class="lbtt-rail-nav-item<?= ($page ?? '') === $key ? ' active' : '' ?>">
                    <span class="n"><?= e($num) ?></span>
                    <span><?= e($label) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <a href="<?= e(url('profile')) ?>" class="lbtt-rail-session lbtt-rail-session-link<?= ($page ?? '') === 'profile' ? ' active' : '' ?>">
            <div class="lbtt-label" style="margin-bottom: 6px;">Session</div>
            <div class="lbtt-session-row">
                <div class="lbtt-avatar<?= $myAvatar ? ' has-image' : '' ?>">
                    <?php if ($myAvatar): ?>
                        <img src="<?= e($myAvatar) ?>" alt="">
                    <?php else: ?>
                        <?= e($initials !== '' ? $initials : 'A') ?>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="lbtt-session-name"><?= e($displayName) ?></div>
                    <div class="lbtt-session-logout">Profil →</div>
                </div>
            </div>
        </a>
    </aside>
    <main class="lbtt-main">
        <?php if ($needsEmail && ($page ?? '') !== 'profile'): ?>
            <div class="lbtt-cal-tip" style="border-color: var(--lbtt-accent);">
                <span class="lbtt-chip lbtt-chip-accent">Action requise</span>
                <span class="lbtt-cal-tip-text">
                    Ton compte n'a pas encore d'email — <a href="<?= e(url('profile')) ?>" style="text-decoration: underline;">ajoute-le ici</a> pour pouvoir recevoir des invitations.
                </span>
            </div>
        <?php endif; ?>
        <?= $content ?>
        <?= format_deployment_footer() ?>
    </main>
</div>
<nav class="lbtt-tabbar" aria-label="Navigation">
    <?php foreach ($navItems as [$key, $label, $num]): ?>
        <a href="<?= e(url($key)) ?>" class="lbtt-tab<?= ($page ?? '') === $key ? ' active' : '' ?>">
            <span class="ix"><?= e($num) ?></span>
            <span class="lbl"><?= e(strtolower($label)) ?>.</span>
        </a>
    <?php endforeach; ?>
    <a href="<?= e(url('profile')) ?>" class="lbtt-tab<?= ($page ?? '') === 'profile' ? ' active' : '' ?>">
        <span class="ix">
            <?php if ($myAvatar): ?>
                <img src="<?= e($myAvatar) ?>" alt="" class="lbtt-tab-avatar">
            <?php else: ?>
                <?= e($initials !== '' ? $initials : 'A') ?>
            <?php endif; ?>
        </span>
        <span class="lbl">profil.</span>
    </a>
</nav>
<script src="<?= asset_url('assets/app.js') ?>"></script>
</body>
</html>
