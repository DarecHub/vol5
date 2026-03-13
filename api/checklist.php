<?php
/**
 * API: Checklist – CRUD operace (přístupné všem členům)
 */

require_once __DIR__ . '/../functions.php';
requireLogin();

$db = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list':
        $items = $db->query("SELECT * FROM checklist ORDER BY sort_order, id")->fetchAll();
        jsonResponse(true, $items);
        break;

    case 'add':
        requireCsrf();
        $name = trim($_POST['item_name'] ?? '');
        $category = $_POST['category'] ?? 'doporucene';
        $description = trim($_POST['description'] ?? '');

        if ($name === '') {
            jsonResponse(false, null, 'Název položky je povinný.');
        }

        $validCats = ['povinne', 'obleceni', 'vybaveni', 'doporucene'];
        if (!in_array($category, $validCats)) $category = 'doporucene';

        $maxSort = $db->query("SELECT COALESCE(MAX(sort_order), 0) FROM checklist")->fetchColumn();

        $stmt = $db->prepare("INSERT INTO checklist (category, item_name, description, sort_order) VALUES (?, ?, ?, ?)");
        $stmt->execute([$category, $name, $description ?: null, $maxSort + 1]);

        jsonResponse(true, ['id' => $db->lastInsertId()]);
        break;

    case 'edit':
        requireCsrf();
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['item_name'] ?? '');
        $category = $_POST['category'] ?? 'doporucene';
        $description = trim($_POST['description'] ?? '');

        if (!$id || $name === '') {
            jsonResponse(false, null, 'Neplatná data.');
        }

        $validCats = ['povinne', 'obleceni', 'vybaveni', 'doporucene'];
        if (!in_array($category, $validCats)) $category = 'doporucene';

        $stmt = $db->prepare("UPDATE checklist SET category = ?, item_name = ?, description = ? WHERE id = ?");
        $stmt->execute([$category, $name, $description ?: null, $id]);

        jsonResponse(true);
        break;

    case 'delete':
        requireCsrf();
        $id = (int)($_POST['id'] ?? 0);

        if (!$id) {
            jsonResponse(false, null, 'Neplatné ID.');
        }

        $db->prepare("DELETE FROM checklist WHERE id = ?")->execute([$id]);
        jsonResponse(true);
        break;

    default:
        jsonResponse(false, null, 'Neznámá akce.');
}
