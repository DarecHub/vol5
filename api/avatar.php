<?php
/**
 * API: upload + smazání profilového avataru
 */
require_once __DIR__ . '/../functions.php';
requireLogin();

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$userId = currentUserId();

if ($action === 'upload') {
    requireCsrf();

    if (empty($_FILES['avatar'])) {
        jsonResponse(false, null, 'Žádný soubor.');
    }

    $file = $_FILES['avatar'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(false, null, 'Chyba nahrávání souboru.');
    }

    // Max 3 MB
    if ($file['size'] > 3 * 1024 * 1024) {
        jsonResponse(false, null, 'Soubor je příliš velký (max 3 MB).');
    }

    // Ověř MIME typ ze skutečného obsahu souboru
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($mime, $allowed, true)) {
        jsonResponse(false, null, 'Povolené typy: JPG, PNG, WebP, GIF.');
    }

    $ext = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    };

    $avatarDir = __DIR__ . '/../assets/avatars/';
    if (!is_dir($avatarDir)) {
        mkdir($avatarDir, 0755, true);
    }

    // Smaž starý avatar pokud existuje
    $db = getDB();
    $stmt = $db->prepare("SELECT avatar FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $old = $stmt->fetchColumn();
    if ($old && file_exists(__DIR__ . '/../' . $old)) {
        @unlink(__DIR__ . '/../' . $old);
    }

    // Unikátní název souboru
    $filename = 'user_' . $userId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $dest = $avatarDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        jsonResponse(false, null, 'Nepodařilo se uložit soubor.');
    }

    $relativePath = 'assets/avatars/' . $filename;
    $stmt = $db->prepare("UPDATE users SET avatar = ? WHERE id = ?");
    $stmt->execute([$relativePath, $userId]);

    jsonResponse(true, ['avatar' => '/' . $relativePath . '?v=' . time()]);
}

if ($action === 'delete') {
    requireCsrf();

    $db = getDB();
    $stmt = $db->prepare("SELECT avatar FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $old = $stmt->fetchColumn();
    if ($old && file_exists(__DIR__ . '/../' . $old)) {
        @unlink(__DIR__ . '/../' . $old);
    }

    $stmt = $db->prepare("UPDATE users SET avatar = NULL WHERE id = ?");
    $stmt->execute([$userId]);

    jsonResponse(true, ['avatar' => null]);
}

jsonResponse(false, null, 'Neznámá akce.');
