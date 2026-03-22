<?php
/**
 * API: Deník plavby – CRUD
 */

require_once __DIR__ . '/../functions.php';
requireLogin();

$db = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list':
        $boatId = (int) ($_GET['boat_id'] ?? 0);
        if ($boatId < 1) jsonResponse(false, null, 'Neplatná loď.');

        $stmt = $db->prepare("
            SELECT l.*, u.name AS skipper_name, u2.name AS created_by_name
            FROM logbook l
            LEFT JOIN users u ON l.skipper_user_id = u.id
            LEFT JOIN users u2 ON l.created_by = u2.id
            WHERE l.boat_id = ?
            ORDER BY l.date ASC
        ");
        $stmt->execute([$boatId]);
        $entries = $stmt->fetchAll();

        // Statistiky
        $stats = $db->prepare("
            SELECT
                COALESCE(SUM(nautical_miles), 0) AS total_nm,
                COUNT(*) AS total_days,
                COALESCE(MAX(nautical_miles), 0) AS max_nm,
                COALESCE(AVG(nautical_miles), 0) AS avg_nm
            FROM logbook WHERE boat_id = ?
        ");
        $stats->execute([$boatId]);

        jsonResponse(true, ['entries' => $entries, 'stats' => $stats->fetch()]);
        break;

    case 'add':
        requireCsrf();
        $boatId = (int) ($_POST['boat_id'] ?? 0);
        $date = $_POST['date'] ?? '';
        $locationFrom = trim($_POST['location_from'] ?? '');
        $locationTo = trim($_POST['location_to'] ?? '');
        $nm = (float) ($_POST['nautical_miles'] ?? 0);
        $departure = ($_POST['departure_time'] ?? '') ?: null;
        $arrival = ($_POST['arrival_time'] ?? '') ?: null;
        $skipper = (int) ($_POST['skipper_user_id'] ?? 0) ?: null;
        $note = trim($_POST['note'] ?? '');

        if ($boatId < 1 || !$date || $locationFrom === '' || $locationTo === '') {
            jsonResponse(false, null, 'Loď, datum, odkud a kam jsou povinné.');
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO logbook (boat_id, date, location_from, location_to, nautical_miles, departure_time, arrival_time, skipper_user_id, note, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$boatId, $date, $locationFrom, $locationTo, $nm, $departure, $arrival, $skipper, $note ?: null, currentUserId()]);
            jsonResponse(true, ['id' => $db->lastInsertId()]);
        } catch (PDOException $e) {
            error_log($e->getMessage());
            jsonResponse(false, null, 'Chyba při ukládání záznamu: ' . $e->getMessage());
        }
        break;

    case 'edit':
        requireCsrf();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id < 1) jsonResponse(false, null, 'Neplatný záznam.');

        // Ověřit vlastnictví
        $check = $db->prepare("SELECT boat_id FROM logbook WHERE id = ?");
        $check->execute([$id]);
        $logRow = $check->fetch();
        if (!$logRow || (currentBoatId() !== null && $logRow['boat_id'] !== currentBoatId())) {
            jsonResponse(false, null, 'Přístup odepřen.');
        }

        try {
            $stmt = $db->prepare("
                UPDATE logbook SET date = ?, location_from = ?, location_to = ?, nautical_miles = ?,
                departure_time = ?, arrival_time = ?, skipper_user_id = ?, note = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $_POST['date'] ?? '',
                trim($_POST['location_from'] ?? ''),
                trim($_POST['location_to'] ?? ''),
                (float) ($_POST['nautical_miles'] ?? 0),
                $_POST['departure_time'] ?: null,
                $_POST['arrival_time'] ?: null,
                (int) ($_POST['skipper_user_id'] ?? 0) ?: null,
                trim($_POST['note'] ?? '') ?: null,
                $id,
            ]);
            jsonResponse(true);
        } catch (PDOException $e) {
            error_log($e->getMessage());
            jsonResponse(false, null, 'Chyba při úpravě záznamu: ' . $e->getMessage());
        }
        break;

    case 'delete':
        requireCsrf();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id < 1) jsonResponse(false, null, 'Neplatný záznam.');

        // Ověřit vlastnictví
        $check = $db->prepare("SELECT boat_id FROM logbook WHERE id = ?");
        $check->execute([$id]);
        $logRow = $check->fetch();
        if (!$logRow || (currentBoatId() !== null && $logRow['boat_id'] !== currentBoatId())) {
            jsonResponse(false, null, 'Přístup odepřen.');
        }

        try {
            $db->prepare("DELETE FROM logbook WHERE id = ?")->execute([$id]);
            jsonResponse(true);
        } catch (PDOException $e) {
            error_log($e->getMessage());
            jsonResponse(false, null, 'Chyba při mazání záznamu.');
        }
        break;

    default:
        jsonResponse(false, null, 'Neznámá akce.');
}
