<?php
/**
 * Simple wrapper autour de mail() — envoi multipart/alternative (plain+html).
 * Pas de SMTP : compte sur la fonction mail() du serveur (PHP → MTA local).
 * Si ça échoue ou que l'hébergeur ne délivre pas, le code appelant affiche
 * toujours le lien d'invitation pour que l'admin puisse le transmettre à la main.
 */

function _mail_from_address(array $config): string {
    if (!empty($config['mail_from']) && valid_email((string)$config['mail_from'])) {
        return (string)$config['mail_from'];
    }
    // Préfère un host canonique configuré (anti host-header injection).
    $canonical = (string)($config['canonical_host'] ?? '');
    if ($canonical !== '' && preg_match('#^https?://([^/\s:]+)#i', $canonical, $m)) {
        $host = strtolower($m[1]);
    } else {
        $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;
        $host = preg_replace('/^www\./i', '', $host) ?? $host;
        $host = preg_replace('/[^a-z0-9.\-]/i', '', $host) ?: 'localhost';
    }
    return 'noreply@' . $host;
}

function _mail_from_name(array $config): string {
    return (string)($config['mail_from_name'] ?? 'LBTimeTracker');
}

/**
 * Envoie un mail multipart (plaintext + html).
 * Renvoie true si mail() a accepté le message, false sinon.
 */
function send_mail(string $to, string $subject, string $bodyHtml, string $bodyText): bool {
    if (!valid_email($to)) return false;

    global $config; // chargé dans index.php (settings app)
    $cfg = is_array($config ?? null) ? $config : [];
    $fromAddr = _mail_from_address($cfg);
    $fromName = _mail_from_name($cfg);
    $fromHeader = $fromName . ' <' . $fromAddr . '>';

    $boundary = 'lbtt_' . bin2hex(random_bytes(8));
    $eol = "\r\n";

    $headers = [
        'From: ' . $fromHeader,
        'Reply-To: ' . $fromAddr,
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        'X-Mailer: LBTimeTracker',
    ];

    $body = '--' . $boundary . $eol
          . 'Content-Type: text/plain; charset=UTF-8' . $eol
          . 'Content-Transfer-Encoding: 8bit' . $eol . $eol
          . $bodyText . $eol . $eol
          . '--' . $boundary . $eol
          . 'Content-Type: text/html; charset=UTF-8' . $eol
          . 'Content-Transfer-Encoding: 8bit' . $eol . $eol
          . $bodyHtml . $eol . $eol
          . '--' . $boundary . '--' . $eol;

    // Subject RFC 2047 (UTF-8 encoded)
    $subjectHeader = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    try {
        return @mail($to, $subjectHeader, $body, implode($eol, $headers));
    } catch (Throwable $e) {
        error_log('LBTT send_mail failed: ' . $e->getMessage());
        return false;
    }
}

function send_email_verification(string $to, string $verifyUrl, string $displayName): bool {
    $nameSafe = htmlspecialchars($displayName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $urlSafe = htmlspecialchars($verifyUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $subject = 'Confirme ton adresse email — LBTimeTracker';
    $text = "Bienvenue " . $displayName . ",\n\n"
          . "Confirme ton adresse email en cliquant ce lien :\n"
          . $verifyUrl . "\n\n"
          . "Si tu n'es pas à l'origine de cette inscription, ignore ce message.\n";
    $html = '<div style="font-family: system-ui, sans-serif; font-size: 14px; color: #1a1a1a;">'
          . '<p>Bienvenue <strong>' . $nameSafe . '</strong>,</p>'
          . '<p>Confirme ton adresse email pour activer la vérification de ton compte :</p>'
          . '<p><a href="' . $urlSafe . '" style="display:inline-block;padding:10px 14px;background:#c45a2e;color:#fff;text-decoration:none;">Confirmer mon email →</a></p>'
          . '<p style="color:#888;font-size:12px">Si tu n\'es pas à l\'origine de cette inscription, ignore ce message.</p>'
          . '</div>';
    return send_mail($to, $subject, $html, $text);
}

function send_invitation_email(string $to, string $projectName, string $inviteUrl, string $inviterName, bool $existingUser = false): bool {
    $projSafe = htmlspecialchars($projectName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $inviterSafe = htmlspecialchars($inviterName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $urlSafe = htmlspecialchars($inviteUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    if ($existingUser) {
        $subject = $inviterName . ' t\'a ajouté à « ' . $projectName . ' » sur LBTimeTracker';
        $text = "Salut,\n\n"
              . $inviterName . " t'a ajouté au projet « " . $projectName . " » sur LBTimeTracker.\n\n"
              . "Ouvrir : " . $inviteUrl . "\n";
        $html = '<div style="font-family: system-ui, sans-serif; font-size: 14px; color: #1a1a1a;">'
              . '<p>Salut,</p>'
              . '<p><strong>' . $inviterSafe . '</strong> t\'a ajouté au projet <strong>« ' . $projSafe . ' »</strong> sur LBTimeTracker.</p>'
              . '<p><a href="' . $urlSafe . '" style="display:inline-block;padding:10px 14px;background:#c45a2e;color:#fff;text-decoration:none;">Ouvrir le projet →</a></p>'
              . '</div>';
    } else {
        $subject = 'Invitation à rejoindre « ' . $projectName . ' » sur LBTimeTracker';
        $text = "Salut,\n\n"
              . $inviterName . " t'invite à rejoindre le projet « " . $projectName . " » sur LBTimeTracker.\n\n"
              . "Créer ton compte et rejoindre : " . $inviteUrl . "\n\n"
              . "Le lien expire dans 7 jours.\n";
        $html = '<div style="font-family: system-ui, sans-serif; font-size: 14px; color: #1a1a1a;">'
              . '<p>Salut,</p>'
              . '<p><strong>' . $inviterSafe . '</strong> t\'invite à rejoindre le projet <strong>« ' . $projSafe . ' »</strong> sur LBTimeTracker.</p>'
              . '<p><a href="' . $urlSafe . '" style="display:inline-block;padding:10px 14px;background:#c45a2e;color:#fff;text-decoration:none;">Créer mon compte et rejoindre →</a></p>'
              . '<p style="color:#888;font-size:12px">Le lien expire dans 7 jours.</p>'
              . '</div>';
    }

    return send_mail($to, $subject, $html, $text);
}
