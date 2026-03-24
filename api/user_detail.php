<?php
/**
 * API: detail uživatele (jméno, kontakt, avatar, loď)
 */
require_once __DIR__ . '/../functions.php';
requireLogin();

$id = (int) ($_GET['id'] ?? 0);
if ($id < 1) jsonResponse(false, null, 'Neplatné ID.');

$db = getDB();
$stmt = $db->prepare("
    SELECT u.id, u.name, u.phone, u.email, u.avatar, u.boat_id, b.name AS boat_name
    FROM users u
    LEFT JOIN boats b ON u.boat_id = b.id
    WHERE u.id = ?
");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) jsonResponse(false, null, 'Uživatel nenalezen.');

// Avatar URL
$user['avatar_url'] = $user['avatar'] ? '/' . $user['avatar'] : null;

jsonResponse(true, $user);
