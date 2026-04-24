<?php
/**
 * Upload + traitement des avatars via GD.
 * Valide, center-crop carré, redimensionne à 256×256, sauvegarde en JPEG 85%.
 */

function avatar_dir(): string {
    $d = BASE_DIR . '/data/avatars';
    if (!is_dir($d)) {
        @mkdir($d, 0700, true);
        // data/ est déjà bloqué web côté .htaccess et nginx (voir CLAUDE.md)
        @file_put_contents(BASE_DIR . '/data/.htaccess', "Require all denied\n");
    }
    return $d;
}

/**
 * Traite un fichier $_FILES[...] et renvoie le nom de fichier stocké.
 * Throws RuntimeException en cas d'erreur (affichable à l'user).
 */
function save_user_avatar(int $userId, array $file): string {
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Upload invalide.');
    }
    $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Erreur upload (code ' . $err . ').');
    }
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > 3 * 1024 * 1024) {
        throw new RuntimeException('Fichier trop gros (3 Mo max).');
    }
    $info = @getimagesize($file['tmp_name']);
    if (!$info || !isset($info['mime'])) {
        throw new RuntimeException('Fichier non reconnu comme image.');
    }
    $mime = (string)$info['mime'];
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime, $allowed, true)) {
        throw new RuntimeException('Format non supporté (JPEG / PNG / WebP).');
    }
    // Garde contre les pixel bombs (PNG ratio extrême qui exploseraient
    // la mémoire pendant imagecreatefromstring)
    $sw0 = (int)($info[0] ?? 0);
    $sh0 = (int)($info[1] ?? 0);
    $MAX_SIDE = 8000;
    if ($sw0 > $MAX_SIDE || $sh0 > $MAX_SIDE) {
        throw new RuntimeException('Image trop grande (max ' . $MAX_SIDE . 'px par côté).');
    }
    // Seuil pixel total (8000×8000 = 64 Mpx ≈ 256 Mo en truecolor)
    if (($sw0 * $sh0) > 40000000) {
        throw new RuntimeException('Image trop grande (max ~40 Mpx).');
    }
    if (!function_exists('imagecreatefromstring')) {
        throw new RuntimeException('Extension GD absente du serveur.');
    }
    $raw = @file_get_contents($file['tmp_name']);
    if ($raw === false) {
        throw new RuntimeException('Lecture du fichier impossible.');
    }
    $src = @imagecreatefromstring($raw);
    if (!$src) {
        throw new RuntimeException('Image corrompue ou illisible.');
    }
    $sw = imagesx($src);
    $sh = imagesy($src);
    if ($sw < 32 || $sh < 32) {
        imagedestroy($src);
        throw new RuntimeException('Image trop petite (min. 32×32).');
    }

    // Center-crop en carré
    $side = min($sw, $sh);
    $sx = (int)(($sw - $side) / 2);
    $sy = (int)(($sh - $side) / 2);

    $outSize = 256;
    $dst = imagecreatetruecolor($outSize, $outSize);
    // Fond blanc pour PNG transparents (le JPEG ne gère pas la transparence)
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefilledrectangle($dst, 0, 0, $outSize, $outSize, $white);
    imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $outSize, $outSize, $side, $side);
    imagedestroy($src);

    $dir = avatar_dir();
    $filename = 'u' . $userId . '_' . bin2hex(random_bytes(6)) . '.jpg';
    $path = $dir . '/' . $filename;

    if (!@imagejpeg($dst, $path, 85)) {
        imagedestroy($dst);
        throw new RuntimeException('Écriture du fichier impossible.');
    }
    imagedestroy($dst);
    @chmod($path, 0600);
    return $filename;
}

/** Supprime un fichier avatar (best-effort, safe filename). */
function delete_avatar_file(?string $filename): void {
    if ($filename === null || $filename === '') return;
    $safe = basename($filename);
    if ($safe === '' || $safe === '.' || $safe === '..') return;
    $path = avatar_dir() . '/' . $safe;
    if (is_file($path)) @unlink($path);
}

/** Lit et streame un avatar (Content-Type + readfile). */
function stream_avatar(string $filename): bool {
    $safe = basename($filename);
    $path = avatar_dir() . '/' . $safe;
    if (!is_readable($path)) return false;
    header('Content-Type: image/jpeg');
    header('Content-Length: ' . (string)filesize($path));
    header('Cache-Control: private, max-age=3600');
    readfile($path);
    return true;
}
