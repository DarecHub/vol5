<?php
/**
 * API: upload / smazání fotky výdaje
 */
require_once __DIR__ . '/../functions.php';
requireLogin();

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$userId = currentUserId();
$db = getDB();

// ============================================================
// UPLOAD
// ============================================================
if ($action === 'upload') {
    requireCsrf();

    $expenseId = (int) ($_POST['expense_id'] ?? 0);
    if ($expenseId < 1) jsonResponse(false, null, 'Neplatné ID výdaje.');

    // Ověř vlastnictví – created_by nebo paid_by
    $stmt = $db->prepare("SELECT id, photo FROM wallet_expenses WHERE id = ? AND (created_by = ? OR paid_by = ?)");
    $stmt->execute([$expenseId, $userId, $userId]);
    $expense = $stmt->fetch();
    if (!$expense) jsonResponse(false, null, 'Výdaj nenalezen nebo nemáte oprávnění.');

    if (empty($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(false, null, 'Chyba nahrávání souboru.');
    }

    $file = $_FILES['photo'];

    if ($file['size'] > 8 * 1024 * 1024) {
        jsonResponse(false, null, 'Soubor je příliš velký (max 8 MB).');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/heic'];
    if (!in_array($mime, $allowed, true)) {
        jsonResponse(false, null, 'Povolené typy: JPG, PNG, WebP, HEIC.');
    }

    $ext = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/heic' => 'heic',
        default      => 'jpg',
    };

    $photoDir = __DIR__ . '/../assets/expense_photos/';
    if (!is_dir($photoDir)) mkdir($photoDir, 0755, true);

    // Smaž starou fotku
    if ($expense['photo'] && file_exists(__DIR__ . '/../' . $expense['photo'])) {
        @unlink(__DIR__ . '/../' . $expense['photo']);
    }

    $filename = 'exp_' . $expenseId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $dest = $photoDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        jsonResponse(false, null, 'Nepodařilo se uložit soubor.');
    }

    $relativePath = 'assets/expense_photos/' . $filename;
    $db->prepare("UPDATE wallet_expenses SET photo = ? WHERE id = ?")
       ->execute([$relativePath, $expenseId]);

    jsonResponse(true, ['photo' => '/' . $relativePath . '?v=' . time()]);
}

// ============================================================
// SMAZÁNÍ FOTKY
// ============================================================
if ($action === 'delete') {
    requireCsrf();

    $expenseId = (int) ($_POST['expense_id'] ?? 0);
    $stmt = $db->prepare("SELECT id, photo FROM wallet_expenses WHERE id = ? AND (created_by = ? OR paid_by = ?)");
    $stmt->execute([$expenseId, $userId, $userId]);
    $expense = $stmt->fetch();
    if (!$expense) jsonResponse(false, null, 'Výdaj nenalezen.');

    if ($expense['photo'] && file_exists(__DIR__ . '/../' . $expense['photo'])) {
        @unlink(__DIR__ . '/../' . $expense['photo']);
    }
    $db->prepare("UPDATE wallet_expenses SET photo = NULL WHERE id = ?")->execute([$expenseId]);

    jsonResponse(true, ['photo' => null]);
}

jsonResponse(false, null, 'Neznámá akce.');
