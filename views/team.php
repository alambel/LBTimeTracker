<?php
// Initiales + couleur par membre
$initialsFor = function(string $name): string {
    $parts = preg_split('/[\s._-]+/u', $name) ?: [$name];
    $out = '';
    foreach ($parts as $p) {
        if ($p === '') continue;
        $out .= mb_strtoupper(mb_substr($p, 0, 1, 'UTF-8'), 'UTF-8');
        if (mb_strlen($out, 'UTF-8') >= 2) break;
    }
    return $out !== '' ? $out : mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8');
};
// Couleur déterministe par user_id (teinte HSL)
$userColor = function(int $uid): string {
    $h = (int)($uid * 137 % 360);
    return 'hsl(' . $h . ' 55% 48%)';
};
?>
<div class="lbtt-page-head">
    <div>
        <div class="lbtt-label">Équipe · <?= e($project['name']) ?></div>
        <h1 class="lbtt-page-title"><?= e($monthLabel) ?>.</h1>
    </div>
    <div class="lbtt-cal-head-right">
        <div class="lbtt-cal-head-stat">
            <div class="lbtt-label">Total équipe</div>
            <div class="val"><?= number_format($projectTotalHours, 1, ',', ' ') ?><span class="unit"> h</span></div>
        </div>
        <div class="lbtt-rule-v" style="height: 40px;"></div>
        <div>
            <div class="lbtt-label">Membres</div>
            <div class="lbtt-cal-head-dom">
                <span class="nm"><?= count($members) ?></span>
            </div>
        </div>
        <div class="lbtt-rule-v" style="height: 40px;"></div>
        <div class="lbtt-cal-head-pager">
            <a class="lbtt-btn lbtt-btn-ghost lbtt-btn-icon" href="index.php?action=team&id=<?= (int)$project['id'] ?>&month=<?= e($prevMonth) ?>">‹</a>
            <a class="lbtt-btn lbtt-btn-ghost lbtt-btn-icon" href="index.php?action=team&id=<?= (int)$project['id'] ?>">AUJ.</a>
            <a class="lbtt-btn lbtt-btn-ghost lbtt-btn-icon" href="index.php?action=team&id=<?= (int)$project['id'] ?>&month=<?= e($nextMonth) ?>">›</a>
        </div>
    </div>
</div>

<div class="lbtt-cal-tip">
    <span class="lbtt-chip">Lecture seule</span>
    <span class="lbtt-cal-tip-text">Vue d'équipe : chaque jour affiche les initiales des membres ayant saisi. Pour éditer ta journée, retourne au <a href="index.php?action=calendar" style="text-decoration: underline;">calendrier</a>.</span>
</div>

<!-- Légende membres -->
<div class="lbtt-cal-legend">
    <?php foreach ($members as $m): ?>
        <span class="lbtt-tag">
            <span class="lbtt-tag-dot lbtt-team-avatar" style="background: <?= e($userColor((int)$m['id'])) ?>;"><?= e($initialsFor((string)$m['username'])) ?></span>
            <?= e($m['username']) ?>
            <?php if ($m['role'] === 'admin'): ?><span class="lbtt-role-badge lbtt-role-admin">admin</span><?php endif; ?>
        </span>
    <?php endforeach; ?>
</div>

<!-- Grille mois : un "tuile jour" avec initiales empilées des contributions -->
<div class="lbtt-team-grid">
    <div class="lbtt-cal-weekdays">
        <?php foreach (weekday_names_fr_long() as $wd): ?>
            <div><?= e($wd) ?></div>
        <?php endforeach; ?>
    </div>
    <div class="lbtt-team-cells">
        <?php foreach ($cells as $cell):
            if ($cell === null):
        ?>
            <div class="lbtt-team-cell empty"></div>
        <?php else:
            $d = $cell['date'];
            $dayEntries = $entriesByDate[$d] ?? [];
            // Group entries by user and sum minutes
            $byUser = [];
            foreach ($dayEntries as $e) {
                $uid2 = (int)$e['user_id'];
                if (!isset($byUser[$uid2])) {
                    $byUser[$uid2] = ['user' => $e, 'minutes' => 0];
                }
                $byUser[$uid2]['minutes'] += slot_minutes_for_code((string)$e['period']);
            }
            $cellCls = 'lbtt-team-cell' . ($cell['today'] ? ' today' : '') . ($cell['weekend'] ? ' weekend' : '');
        ?>
            <div class="<?= $cellCls ?>">
                <div class="lbtt-team-cell-head">
                    <span class="n"><?= $cell['day'] ?></span>
                    <?php if (!empty($byUser)):
                        $dayTotal = 0;
                        foreach ($byUser as $u) { $dayTotal += (int)$u['minutes']; }
                    ?>
                        <span class="h"><?= number_format($dayTotal / 60, 1, ',', '') ?> h</span>
                    <?php endif; ?>
                </div>
                <div class="lbtt-team-cell-users">
                    <?php foreach ($byUser as $u):
                        $uid2 = (int)$u['user']['user_id'];
                        $col = $userColor($uid2);
                    ?>
                        <span class="lbtt-team-avatar" style="background: <?= e($col) ?>;"
                              title="<?= e((string)$u['user']['username']) ?> — <?= number_format($u['minutes']/60, 1, ',', '') ?>h">
                            <?= e($initialsFor((string)$u['user']['username'])) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; endforeach; ?>
    </div>
</div>

<!-- Résumé par membre -->
<h2 class="lbtt-section-title">Par membre — <?= e($monthLabel) ?></h2>
<?php if (empty($memberSummary)): ?>
    <div class="lbtt-cal-tip">
        <span class="lbtt-chip">Vide</span>
        <span class="lbtt-cal-tip-text">Aucune saisie d'équipe sur la période.</span>
    </div>
<?php else: ?>
<div class="lbtt-table">
    <div class="lbtt-table-head">
        <div class="lbtt-label">Membre</div>
        <div class="lbtt-label">Saisies</div>
        <div class="lbtt-label">Heures</div>
        <div class="lbtt-label">Mode</div>
        <div class="lbtt-label">%</div>
    </div>
    <?php foreach ($memberSummary as $r):
        $pct = $projectTotalMinutes ? 100 * (int)$r['total_minutes'] / $projectTotalMinutes : 0;
        $h = (int)$r['total_minutes'] / 60;
    ?>
        <div class="lbtt-table-row">
            <div class="lbtt-proj-cell">
                <span class="lbtt-team-avatar" style="background: <?= e($userColor((int)$r['user_id'])) ?>;"><?= e($initialsFor((string)$r['username'])) ?></span>
                <span class="nm"><?= e($r['username']) ?><?php if (!empty($r['archived'])): ?> <span class="lbtt-chip" style="margin-left: 6px;">archivé</span><?php endif; ?></span>
            </div>
            <div class="lbtt-num lbtt-num-md"><?= (int)$r['entry_count'] ?></div>
            <div class="lbtt-num lbtt-num-md"><?= number_format($h, 2, ',', ' ') ?></div>
            <div class="lbtt-num lbtt-num-md" style="font-family: var(--mono); font-size: 11px; color: var(--lbtt-muted);"><?= e($r['slot_mode']) ?></div>
            <div class="lbtt-pct"><?= number_format($pct, 1) ?>%</div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
