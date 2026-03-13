<?php
/**
 * API: Jídelníček – CRUD operace
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
            SELECT mp.*,
                   u.name AS cook_name
            FROM menu_plan mp
            LEFT JOIN users u ON mp.cook_user_id = u.id
            WHERE mp.boat_id = ?
            ORDER BY mp.date, FIELD(mp.meal_type, 'snidane', 'obed', 'vecere')
        ");
        $stmt->execute([$boatId]);
        $items = $stmt->fetchAll();

        jsonResponse(true, $items);
        break;

    case 'add':
        requireCsrf();
        $boatId = (int) ($_POST['boat_id'] ?? 0);
        $date = $_POST['date'] ?? '';
        $mealType = $_POST['meal_type'] ?? '';
        $cookUserId = (int) ($_POST['cook_user_id'] ?? 0) ?: null;
        $mealDescription = trim($_POST['meal_description'] ?? '');
        $note = trim($_POST['note'] ?? '');

        if ($boatId < 1 || !$date || !$mealType) {
            jsonResponse(false, null, 'Neplatné údaje (loď, datum a typ jídla jsou povinné).');
        }

        // Kontrola duplicity
        $check = $db->prepare("SELECT id FROM menu_plan WHERE boat_id = ? AND date = ? AND meal_type = ?");
        $check->execute([$boatId, $date, $mealType]);
        if ($check->fetchColumn()) {
            jsonResponse(false, null, 'Pro tento den a typ jídla již existuje záznam. Použijte editaci.');
        }

        $stmt = $db->prepare("
            INSERT INTO menu_plan (boat_id, date, meal_type, cook_user_id, meal_description, note, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$boatId, $date, $mealType, $cookUserId, $mealDescription ?: null, $note ?: null, currentUserId()]);

        jsonResponse(true, ['id' => $db->lastInsertId()]);
        break;

    case 'edit':
        requireCsrf();
        $id = (int) ($_POST['id'] ?? 0);
        $cookUserId = (int) ($_POST['cook_user_id'] ?? 0) ?: null;
        $mealDescription = trim($_POST['meal_description'] ?? '');
        $note = trim($_POST['note'] ?? '');

        if ($id < 1) {
            jsonResponse(false, null, 'Neplatný záznam.');
        }

        $stmt = $db->prepare("UPDATE menu_plan SET cook_user_id = ?, meal_description = ?, note = ? WHERE id = ?");
        $stmt->execute([$cookUserId, $mealDescription ?: null, $note ?: null, $id]);

        jsonResponse(true);
        break;

    case 'delete':
        requireCsrf();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id < 1) {
            jsonResponse(false, null, 'Neplatný záznam.');
        }

        $db->prepare("DELETE FROM menu_plan WHERE id = ?")->execute([$id]);
        jsonResponse(true);
        break;

    default:
        jsonResponse(false, null, 'Neznámá akce.');
}
