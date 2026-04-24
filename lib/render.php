<?php
function render_layout(string $title, string $page, string $content, ?PDO $db = null): void {
    include BASE_DIR . '/views/layout.php';
}

function render_logout_confirm(PDO $db): void {
    $title = 'Déconnexion — LBTimeTracker';
    $page = 'logout';
    ob_start();
    ?>
    <div class="lbtt-page-head">
        <div>
            <div class="lbtt-label">Session</div>
            <h1 class="lbtt-page-title">déconnexion.</h1>
        </div>
    </div>
    <div class="lbtt-cal-tip">
        <span class="lbtt-chip">Confirmer</span>
        <span class="lbtt-cal-tip-text">Clore la session en cours&nbsp;?</span>
    </div>
    <form method="post" action="index.php?action=logout" style="display: flex; gap: 10px; margin-top: 14px;">
        <?= csrf_field() ?>
        <button type="submit" class="lbtt-btn lbtt-btn-primary">Se déconnecter →</button>
        <a href="index.php?action=calendar" class="lbtt-btn lbtt-btn-ghost">Annuler</a>
    </form>
    <?php
    $content = ob_get_clean();
    render_layout($title, $page, $content, $db ?? null);
}

/* ================================================================
 * Calendar (personnel — entrées de l'user courant)
 * ================================================================ */
function render_calendar(PDO $db): void {
    $me = current_user_row($db);
    if (!$me) { header('Location: index.php?action=login'); exit; }
    $uid = (int)$me['id'];
    $slotMode = (string)$me['slot_mode'];

    $month = $_GET['month'] ?? date('Y-m');
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        $month = date('Y-m');
    }
    [$from, $to] = month_bounds($month);
    $entries = get_entries_between($db, $uid, $from, $to);
    $byKey = [];
    foreach ($entries as $ent) {
        $byKey[$ent['date'] . '_' . $ent['period']] = $ent;
    }
    $projects = get_projects_for_user($db, $uid, false);
    $cells = build_month_cells($month);
    [$year, $mm] = array_map('intval', explode('-', $month));
    $monthLabel = month_name_fr($mm);
    $prevMonth = shift_month($month, -1);
    $nextMonth = shift_month($month, +1);

    // Dominant project (par nb entrées)
    $byProjectCount = [];
    foreach ($entries as $ent) {
        $pid = (int)$ent['project_id'];
        $byProjectCount[$pid] = ($byProjectCount[$pid] ?? 0) + 1;
    }
    arsort($byProjectCount);
    $topId = array_key_first($byProjectCount);
    $topProject = $topId !== null ? get_project($db, (int)$topId) : null;

    // Total en heures (minutes → h)
    $totalMinutes = 0;
    foreach ($entries as $ent) {
        $totalMinutes += slot_minutes_for_code((string)$ent['period']);
    }
    $totalHours = $totalMinutes / 60.0;

    $title = 'Calendrier — ' . month_name_fr($mm) . ' ' . $year;
    $page = 'calendar';

    ob_start();
    include BASE_DIR . '/views/calendar.php';
    $content = ob_get_clean();
    render_layout($title, $page, $content, $db ?? null);
}

/* ================================================================
 * Summary (personnel, totaux en heures)
 * ================================================================ */
function render_summary(PDO $db): void {
    $me = current_user_row($db);
    if (!$me) { header('Location: index.php?action=login'); exit; }
    $uid = (int)$me['id'];
    $slotMode = (string)$me['slot_mode'];

    $from = $_GET['from'] ?? date('Y-m') . '-01';
    $to = $_GET['to'] ?? date('Y-m-t');
    if (!valid_date($from)) { $from = date('Y-m') . '-01'; }
    if (!valid_date($to)) { $to = date('Y-m-t'); }

    $rows = summary_between_for_user($db, $uid, $from, $to);
    $totalMinutes = 0;
    foreach ($rows as $r) { $totalMinutes += (int)$r['total_minutes']; }

    if (($_GET['format'] ?? '') === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="timetrack_' . $from . '_' . $to . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Projet', 'Saisies', 'Heures', 'Jours équiv. (8h)', '% du total']);
        foreach ($rows as $r) {
            $pct = $totalMinutes ? 100 * (int)$r['total_minutes'] / $totalMinutes : 0;
            $h = (int)$r['total_minutes'] / 60;
            fputcsv($out, [
                sanitize_csv_cell((string)$r['name']),
                (int)$r['entry_count'],
                number_format($h, 2, '.', ''),
                number_format($h / 8, 2, '.', ''),
                number_format($pct, 1, '.', ''),
            ]);
        }
        fclose($out);
        return;
    }

    $rangeDays = date_range($from, $to);
    $daysCount = count($rangeDays);
    $entries = get_entries_between($db, $uid, $from, $to);
    $byKey = [];
    foreach ($entries as $ent) {
        $byKey[$ent['date'] . '_' . $ent['period']] = $ent;
    }

    $totalHours = $totalMinutes / 60.0;
    $periodCodes = period_codes($slotMode);

    $fromLabel = _fr_short_date($from);
    $toLabel = _fr_short_date($to);

    $title = 'Résumé — LBTimeTracker';
    $page = 'summary';
    ob_start();
    include BASE_DIR . '/views/summary.php';
    $content = ob_get_clean();
    render_layout($title, $page, $content, $db ?? null);
}

function _fr_short_date(string $date): string {
    $abbr = ['', 'JAN.', 'FÉV.', 'MARS', 'AVR.', 'MAI', 'JUIN', 'JUIL.', 'AOÛT', 'SEPT.', 'OCT.', 'NOV.', 'DÉC.'];
    $dt = new DateTime($date);
    return $dt->format('d') . ' ' . $abbr[(int)$dt->format('n')];
}

/* ================================================================
 * Projects (liste des projets de l'user + CRUD + gestion membres)
 * ================================================================ */
function render_projects(PDO $db): void {
    $me = current_user_row($db);
    if (!$me) { header('Location: index.php?action=login'); exit; }
    $uid = (int)$me['id'];

    $error = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check_form_or_die();
        $op = $_POST['op'] ?? '';
        try {
            if ($op === 'create') {
                $name = sanitize_name((string)($_POST['name'] ?? ''));
                $color = sanitize_hex_color((string)($_POST['color'] ?? ''));
                if ($name === '') { throw new RuntimeException('Nom requis'); }
                create_project($db, $name, $color, $uid);
            } elseif ($op === 'update') {
                $id = (int)($_POST['id'] ?? 0);
                if (!user_is_project_admin($db, $id, $uid)) { throw new RuntimeException('Admin du projet requis.'); }
                $name = sanitize_name((string)($_POST['name'] ?? ''));
                $color = sanitize_hex_color((string)($_POST['color'] ?? ''));
                $archived = !empty($_POST['archived']);
                if ($id <= 0 || $name === '') { throw new RuntimeException('Paramètres invalides'); }
                update_project($db, $id, $name, $color, $archived);
            } elseif ($op === 'archive_toggle') {
                $id = (int)($_POST['id'] ?? 0);
                if (!user_is_project_admin($db, $id, $uid)) { throw new RuntimeException('Admin du projet requis.'); }
                $p = get_project($db, $id);
                if ($p) { update_project($db, $id, $p['name'], $p['color'], empty($p['archived'])); }
            } elseif ($op === 'invite') {
                $id = (int)($_POST['id'] ?? 0);
                if (!user_is_project_admin($db, $id, $uid)) { throw new RuntimeException('Admin du projet requis.'); }
                // Rate-limit : 20 invitations/h/user
                $rlBlock = invite_ratelimit_check($uid);
                if ($rlBlock !== null) { throw new RuntimeException($rlBlock); }
                $email = normalize_email((string)($_POST['invite_email'] ?? ''));
                $role = (string)($_POST['invite_role'] ?? 'member');
                if (!in_array($role, ['admin','member'], true)) $role = 'member';
                if (!valid_email($email)) { throw new RuntimeException('Adresse email invalide.'); }
                $project = get_project($db, $id);
                if (!$project) { throw new RuntimeException('Projet introuvable.'); }
                $target = get_user_by_email($db, $email);
                if ($target && !empty($target['archived'])) { throw new RuntimeException('Compte archivé, impossible d\'inviter.'); }
                invite_ratelimit_register($uid);

                if ($target) {
                    add_project_member($db, $id, (int)$target['id'], $role);
                    $projectUrl = app_url() . '?action=team&id=' . $id;
                    $sent = send_invitation_email($email, (string)$project['name'], $projectUrl, (string)$me['username'], true);
                    $_SESSION['_flash_projects'] = [
                        'kind' => 'direct_add',
                        'msg'  => 'Ajouté : ' . $email . ($sent ? ' — email envoyé.' : ' — email non envoyé (voir log).'),
                    ];
                } else {
                    $inv = create_or_refresh_invitation($db, $id, $email, $role, $uid);
                    $inviteUrl = app_url() . '?action=signup&invite=' . urlencode((string)$inv['token']);
                    $sent = send_invitation_email($email, (string)$project['name'], $inviteUrl, (string)$me['username'], false);
                    $_SESSION['_flash_projects'] = [
                        'kind' => 'invited',
                        'msg'  => 'Invitation' . ($sent ? ' envoyée à ' : ' créée pour ') . $email . ' (expire dans 7 jours).',
                        'url'  => $inviteUrl,
                    ];
                }
            } elseif ($op === 'revoke_invitation') {
                $id = (int)($_POST['id'] ?? 0);
                $invId = (int)($_POST['invitation_id'] ?? 0);
                if (!user_is_project_admin($db, $id, $uid)) { throw new RuntimeException('Admin du projet requis.'); }
                revoke_invitation($db, $invId, $id);
            } elseif ($op === 'set_member_role') {
                $id = (int)($_POST['id'] ?? 0);
                if (!user_is_project_admin($db, $id, $uid)) { throw new RuntimeException('Admin du projet requis.'); }
                $targetId = (int)($_POST['user_id'] ?? 0);
                $role = (string)($_POST['role'] ?? 'member');
                if ($targetId === $uid && $role !== 'admin' && project_admin_count($db, $id) <= 1) {
                    throw new RuntimeException('Impossible de retirer le dernier admin.');
                }
                set_project_member_role($db, $id, $targetId, $role);
            } elseif ($op === 'remove_member') {
                $id = (int)($_POST['id'] ?? 0);
                $targetId = (int)($_POST['user_id'] ?? 0);
                // L'user peut se retirer lui-même ; sinon admin requis
                if ($targetId !== $uid && !user_is_project_admin($db, $id, $uid)) {
                    throw new RuntimeException('Admin du projet requis.');
                }
                $targetRole = get_project_member_role($db, $id, $targetId);
                if ($targetRole === 'admin' && project_admin_count($db, $id) <= 1) {
                    throw new RuntimeException('Impossible de retirer le dernier admin.');
                }
                remove_project_member($db, $id, $targetId);
            }
            header('Location: index.php?action=projects');
            exit;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
    $projects = get_projects_for_user($db, $uid, true);
    $entryCounts = project_entry_counts_for_user($db, $uid);
    // Membres + invitations en attente par projet (indexé par project_id)
    $membersByProject = [];
    $invitationsByProject = [];
    foreach ($projects as $p) {
        $pid = (int)$p['id'];
        $membersByProject[$pid] = get_project_members($db, $pid);
        // Invitations affichées uniquement aux admins du projet
        if (($p['my_role'] ?? '') === 'admin') {
            $invitationsByProject[$pid] = get_pending_invitations_for_project($db, $pid);
        } else {
            $invitationsByProject[$pid] = [];
        }
    }

    // Flash (invitation résultat)
    $flash = $_SESSION['_flash_projects'] ?? null;
    unset($_SESSION['_flash_projects']);

    $title = 'Projets — LBTimeTracker';
    $page = 'projects';
    ob_start();
    include BASE_DIR . '/views/projects.php';
    $content = ob_get_clean();
    render_layout($title, $page, $content, $db ?? null);
}

/* ================================================================
 * Team view (calendrier d'un projet, toutes saisies des membres)
 * ================================================================ */
function render_project_team(PDO $db): void {
    $me = current_user_row($db);
    if (!$me) { header('Location: index.php?action=login'); exit; }
    $uid = (int)$me['id'];

    $projectId = (int)($_GET['id'] ?? 0);
    if ($projectId <= 0 || !user_is_project_member($db, $projectId, $uid)) {
        http_response_code(403);
        echo 'Accès refusé à ce projet.';
        exit;
    }
    $project = get_project($db, $projectId);
    if (!$project) { http_response_code(404); echo 'Projet introuvable.'; exit; }

    $month = $_GET['month'] ?? date('Y-m');
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) $month = date('Y-m');
    [$from, $to] = month_bounds($month);
    $entries = get_project_entries_between($db, $projectId, $from, $to);
    $members = get_project_members($db, $projectId);
    $memberSummary = project_summary_by_member($db, $projectId, $from, $to);

    // Regroupe par date : liste d'entrées (user+period) par jour
    $entriesByDate = [];
    foreach ($entries as $ent) {
        $entriesByDate[$ent['date']][] = $ent;
    }
    // Total heures du projet sur la période
    $projectTotalMinutes = 0;
    foreach ($entries as $ent) {
        $projectTotalMinutes += slot_minutes_for_code((string)$ent['period']);
    }
    $projectTotalHours = $projectTotalMinutes / 60.0;

    $cells = build_month_cells($month);
    [$year, $mm] = array_map('intval', explode('-', $month));
    $monthLabel = month_name_fr($mm);
    $prevMonth = shift_month($month, -1);
    $nextMonth = shift_month($month, +1);

    $myRole = get_project_member_role($db, $projectId, $uid);

    $title = 'Équipe — ' . $project['name'];
    $page = 'projects';
    ob_start();
    include BASE_DIR . '/views/team.php';
    $content = ob_get_clean();
    render_layout($title, $page, $content, $db ?? null);
}

/* ================================================================
 * Users admin (app admin uniquement)
 * ================================================================ */
function render_users_admin(PDO $db): void {
    require_app_admin($db);
    $me = current_user_row($db);
    $uid = (int)$me['id'];

    $error = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check_form_or_die();
        $op = $_POST['op'] ?? '';
        try {
            if ($op === 'archive_toggle') {
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) throw new RuntimeException('ID invalide');
                $target = get_user($db, $id);
                if (!$target) throw new RuntimeException('Utilisateur introuvable');
                $nextArchived = empty($target['archived']);
                // Empêche de s'archiver soi-même si dernier app admin actif
                if ($id === $uid && $nextArchived && app_admin_count($db) <= 1) {
                    throw new RuntimeException('Impossible d\'archiver le dernier app admin actif.');
                }
                set_user_archived($db, $id, $nextArchived);
            } elseif ($op === 'toggle_app_admin') {
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) throw new RuntimeException('ID invalide');
                $target = get_user($db, $id);
                if (!$target) throw new RuntimeException('Utilisateur introuvable');
                $nextFlag = empty($target['is_app_admin']);
                if (!$nextFlag && $id === $uid && app_admin_count($db) <= 1) {
                    throw new RuntimeException('Impossible de retirer le dernier app admin.');
                }
                $stmt = $db->prepare('UPDATE users SET is_app_admin = ? WHERE id = ?');
                $stmt->execute([$nextFlag ? 1 : 0, $id]);
            }
            header('Location: index.php?action=users');
            exit;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }

    $users = get_users($db, true);
    $title = 'Utilisateurs — LBTimeTracker';
    $page = 'users';
    ob_start();
    include BASE_DIR . '/views/users.php';
    $content = ob_get_clean();
    render_layout($title, $page, $content, $db ?? null);
}

/* ================================================================
 * Profile (changement slot_mode + mot de passe)
 * ================================================================ */
function render_profile(PDO $db): void {
    $me = current_user_row($db);
    if (!$me) { header('Location: index.php?action=login'); exit; }
    $uid = (int)$me['id'];

    $error = null;
    $notice = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check_form_or_die();
        $op = $_POST['op'] ?? '';
        try {
            if ($op === 'slot_mode') {
                $mode = (string)($_POST['slot_mode'] ?? '');
                if (!valid_slot_mode($mode)) throw new RuntimeException('Mode invalide.');
                $allowed = allowed_slot_modes_for_user($me);
                if (!isset($allowed[$mode])) {
                    throw new RuntimeException('Ce mode est réservé aux app admins.');
                }
                update_user_slot_mode($db, $uid, $mode);
                $notice = 'Granularité mise à jour.';
            } elseif ($op === 'email') {
                $email = normalize_email((string)($_POST['email'] ?? ''));
                if (!valid_email($email)) throw new RuntimeException('Adresse email invalide.');
                $existing = get_user_by_email($db, $email);
                if ($existing && (int)$existing['id'] !== $uid) {
                    throw new RuntimeException('Cette adresse est déjà utilisée par un autre compte.');
                }
                $emailChanged = (string)($me['email'] ?? '') !== $email;
                update_user_email($db, $uid, $email);
                // PAS d'auto-consume des invitations en attente (email non vérifié
                // via OTP → cf. audit sécu #3).
                // Si email modifié → reset du statut "vérifié" + mail de vérif.
                if ($emailChanged) {
                    try {
                        $tok = set_email_verify_token($db, $uid);
                        $verifyUrl = app_url() . '?action=verify_email&token=' . urlencode($tok);
                        send_email_verification($email, $verifyUrl, display_name($me));
                    } catch (Throwable $e) {
                        error_log('LBTT resend verify failed: ' . $e->getMessage());
                    }
                    $notice = 'Email mis à jour — un lien de vérification a été envoyé à ' . $email . '.';
                } else {
                    $notice = 'Email inchangé.';
                }
            } elseif ($op === 'resend_verify') {
                if (empty($me['email'])) throw new RuntimeException('Aucun email à vérifier.');
                $tok = set_email_verify_token($db, $uid);
                $verifyUrl = app_url() . '?action=verify_email&token=' . urlencode($tok);
                $sent = send_email_verification((string)$me['email'], $verifyUrl, display_name($me));
                $notice = $sent
                    ? 'Lien de vérification renvoyé à ' . $me['email'] . '.'
                    : 'Lien de vérification créé — mail.() n\'a pas confirmé l\'envoi, lien direct : ' . $verifyUrl;
            } elseif ($op === 'identity') {
                $fn = sanitize_name((string)($_POST['first_name'] ?? ''), 64);
                $ln = sanitize_name((string)($_POST['last_name'] ?? ''), 64);
                update_user_name($db, $uid, $fn === '' ? null : $fn, $ln === '' ? null : $ln);
                $avatarNote = '';
                if (!empty($_FILES['avatar']['name'] ?? '')) {
                    try {
                        $old = $me['avatar_path'] ?? null;
                        $newFile = save_user_avatar($uid, $_FILES['avatar']);
                        update_user_avatar($db, $uid, $newFile);
                        if ($old) delete_avatar_file((string)$old);
                        $avatarNote = ' Photo mise à jour.';
                    } catch (Throwable $e) {
                        $error = 'Photo : ' . $e->getMessage();
                    }
                }
                if (!$error) $notice = 'Identité mise à jour.' . $avatarNote;
            } elseif ($op === 'remove_avatar') {
                if (!empty($me['avatar_path'])) {
                    delete_avatar_file((string)$me['avatar_path']);
                    update_user_avatar($db, $uid, null);
                }
                $notice = 'Photo supprimée.';
            } elseif ($op === 'password') {
                $curr = (string)($_POST['current_password'] ?? '');
                $new = (string)($_POST['new_password'] ?? '');
                $new2 = (string)($_POST['new_password2'] ?? '');
                if (!password_verify($curr, (string)$me['password_hash'])) throw new RuntimeException('Mot de passe actuel invalide.');
                $pwErr = validate_password_policy($new);
                if ($pwErr !== null) throw new RuntimeException($pwErr);
                if ($new !== $new2) throw new RuntimeException('Confirmation différente.');
                update_user_password($db, $uid, password_hash($new, PASSWORD_BCRYPT));
                // Sécu : rotation CSRF + régénération de session après changement de mdp
                csrf_rotate();
                session_regenerate_id(true);
                $notice = 'Mot de passe mis à jour.';
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
        $me = get_user($db, $uid); // refresh after update
    }

    $title = 'Profil — LBTimeTracker';
    $page = 'profile';
    ob_start();
    include BASE_DIR . '/views/profile.php';
    $content = ob_get_clean();
    render_layout($title, $page, $content, $db ?? null);
}
