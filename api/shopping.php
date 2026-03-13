<?php
/**
 * API: Nákupní seznam – CRUD operace
 */

require_once __DIR__ . '/../functions.php';
requireLogin();

$db = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list':
        $boatId = (int) ($_GET['boat_id'] ?? 0);
        if ($boatId < 1) {
            jsonResponse(false, null, 'Neplatná loď.');
        }

        $stmt = $db->prepare("
            SELECT si.*,
                   u1.name AS assigned_to_name,
                   u2.name AS bought_by_name,
                   u3.name AS created_by_name
            FROM shopping_items si
            LEFT JOIN users u1 ON si.assigned_to = u1.id
            LEFT JOIN users u2 ON si.bought_by = u2.id
            LEFT JOIN users u3 ON si.created_by = u3.id
            WHERE si.boat_id = ?
            ORDER BY si.is_bought ASC, si.category, si.item_name
        ");
        $stmt->execute([$boatId]);
        $items = $stmt->fetchAll();

        // Souhrn
        $totals = $db->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN currency = 'EUR' THEN price ELSE 0 END), 0) AS total_eur,
                COALESCE(SUM(CASE WHEN currency = 'CZK' THEN price ELSE 0 END), 0) AS total_czk,
                COUNT(*) AS total_items,
                SUM(is_bought) AS bought_items
            FROM shopping_items WHERE boat_id = ?
        ");
        $totals->execute([$boatId]);
        $summary = $totals->fetch();

        jsonResponse(true, ['items' => $items, 'summary' => $summary]);
        break;

    case 'add':
        requireCsrf();
        $boatId = (int) ($_POST['boat_id'] ?? 0);
        $itemName = trim($_POST['item_name'] ?? '');
        $quantity = trim($_POST['quantity'] ?? '');
        $category = $_POST['category'] ?? 'potraviny';
        $assignedTo = (int) ($_POST['assigned_to'] ?? 0) ?: null;
        $price = $_POST['price'] !== '' ? (float) $_POST['price'] : null;
        $currency = $_POST['currency'] ?? 'EUR';
        $note = trim($_POST['note'] ?? '');

        if ($boatId < 1 || $itemName === '') {
            jsonResponse(false, null, 'Název položky je povinný.');
        }

        $stmt = $db->prepare("
            INSERT INTO shopping_items (boat_id, category, item_name, quantity, assigned_to, price, currency, note, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$boatId, $category, $itemName, $quantity ?: null, $assignedTo, $price, $currency, $note ?: null, currentUserId()]);

        jsonResponse(true, ['id' => $db->lastInsertId()]);
        break;

    case 'edit':
        requireCsrf();
        $id = (int) ($_POST['id'] ?? 0);
        $itemName = trim($_POST['item_name'] ?? '');
        $quantity = trim($_POST['quantity'] ?? '');
        $category = $_POST['category'] ?? 'potraviny';
        $assignedTo = (int) ($_POST['assigned_to'] ?? 0) ?: null;
        $price = $_POST['price'] !== '' ? (float) $_POST['price'] : null;
        $currency = $_POST['currency'] ?? 'EUR';
        $note = trim($_POST['note'] ?? '');

        if ($id < 1 || $itemName === '') {
            jsonResponse(false, null, 'Neplatné údaje.');
        }

        $stmt = $db->prepare("
            UPDATE shopping_items SET item_name = ?, quantity = ?, category = ?, assigned_to = ?, price = ?, currency = ?, note = ?
            WHERE id = ?
        ");
        $stmt->execute([$itemName, $quantity ?: null, $category, $assignedTo, $price, $currency, $note ?: null, $id]);

        jsonResponse(true);
        break;

    case 'toggle_bought':
        requireCsrf();
        $id = (int) ($_POST['id'] ?? 0);
        $isBought = (int) ($_POST['is_bought'] ?? 0);
        $price = $_POST['price'] !== '' ? (float) $_POST['price'] : null;

        if ($id < 1) {
            jsonResponse(false, null, 'Neplatná položka.');
        }

        $stmt = $db->prepare("UPDATE shopping_items SET is_bought = ?, bought_by = ?, price = COALESCE(?, price) WHERE id = ?");
        $stmt->execute([$isBought, $isBought ? currentUserId() : null, $price, $id]);

        jsonResponse(true);
        break;

    case 'delete':
        requireCsrf();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id < 1) {
            jsonResponse(false, null, 'Neplatná položka.');
        }

        $db->prepare("DELETE FROM shopping_items WHERE id = ?")->execute([$id]);
        jsonResponse(true);
        break;

    default:
        jsonResponse(false, null, 'Neznámá akce.');
}
