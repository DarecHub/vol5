<?php
/**
 * API: Auta – CRUD (řidiči, spolujezdci)
 */

require_once __DIR__ . '/../functions.php';
requireLogin();

$db = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list':
        $cars = $db->query("
            SELECT c.*, u.name AS driver_name
            FROM cars c
            LEFT JOIN users u ON c.driver_user_id = u.id
            ORDER BY c.id
        ")->fetchAll();

        // Přidat spolujezdce ke každému autu
        foreach ($cars as &$car) {
            $stmt = $db->prepare("
                SELECT cp.id AS passenger_id, cp.user_id, u.name
                FROM car_passengers cp
                LEFT JOIN users u ON cp.user_id = u.id
                WHERE cp.car_id = ?
                ORDER BY u.name
            ");
            $stmt->execute([$car['id']]);
            $car['passengers'] = $stmt->fetchAll();
        }
        unset($car);

        // Uživatelé bez auta
        $assignedUserIds = [];
        foreach ($cars as $c) {
            $assignedUserIds[] = $c['driver_user_id'];
            foreach ($c['passengers'] as $p) {
                $assignedUserIds[] = $p['user_id'];
            }
        }

        $allUsers = getAllUsers();
        $unassigned = array_filter($allUsers, fn($u) => !in_array($u['id'], $assignedUserIds));

        jsonResponse(true, [
            'cars' => $cars,
            'unassigned' => array_values($unassigned),
        ]);
        break;

    case 'add_car':
        requireCsrf();
        $driverId = (int) ($_POST['driver_user_id'] ?? 0);
        $carName = trim($_POST['car_name'] ?? '');
        $seats = (int) ($_POST['seats'] ?? 5);
        $note = trim($_POST['note'] ?? '');

        if ($driverId < 1) jsonResponse(false, null, 'Vyberte řidiče.');

        $stmt = $db->prepare("INSERT INTO cars (driver_user_id, car_name, seats, note) VALUES (?, ?, ?, ?)");
        $stmt->execute([$driverId, $carName ?: null, $seats, $note ?: null]);

        jsonResponse(true, ['id' => $db->lastInsertId()]);
        break;

    case 'delete_car':
        requireCsrf();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id < 1) jsonResponse(false, null, 'Neplatné auto.');

        $db->prepare("DELETE FROM cars WHERE id = ?")->execute([$id]);
        jsonResponse(true);
        break;

    case 'add_passenger':
        requireCsrf();
        $carId = (int) ($_POST['car_id'] ?? 0);
        $userId = (int) ($_POST['user_id'] ?? 0);

        if ($carId < 1 || $userId < 1) jsonResponse(false, null, 'Neplatné údaje.');

        // Kontrola kapacity
        $car = $db->prepare("SELECT seats FROM cars WHERE id = ?")->execute([$carId]);
        $car = $db->prepare("SELECT seats, (SELECT COUNT(*) FROM car_passengers WHERE car_id = ?) AS current_count FROM cars WHERE id = ?");
        $car->execute([$carId, $carId]);
        $carData = $car->fetch();

        if ($carData && ($carData['current_count'] + 1) >= $carData['seats']) {
            // +1 protože řidič taky zabírá místo
            jsonResponse(false, null, 'Auto je plné (max ' . $carData['seats'] . ' míst včetně řidiče).');
        }

        $stmt = $db->prepare("INSERT INTO car_passengers (car_id, user_id) VALUES (?, ?)");
        $stmt->execute([$carId, $userId]);

        jsonResponse(true);
        break;

    case 'remove_passenger':
        requireCsrf();
        $passengerId = (int) ($_POST['passenger_id'] ?? 0);
        if ($passengerId < 1) jsonResponse(false, null, 'Neplatný spolujezdec.');

        $db->prepare("DELETE FROM car_passengers WHERE id = ?")->execute([$passengerId]);
        jsonResponse(true);
        break;

    default:
        jsonResponse(false, null, 'Neznámá akce.');
}
