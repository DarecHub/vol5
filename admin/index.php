<?php
/**
 * Admin dashboard – přehled aplikace
 */

require_once __DIR__ . '/../functions.php';
requireAdmin();

$db = getDB();

// Statistiky
try {
    $totalUsers = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $boat1Count = $db->query("SELECT COUNT(*) FROM users WHERE boat_id = 1")->fetchColumn();
    $boat2Count = $db->query("SELECT COUNT(*) FROM users WHERE boat_id = 2")->fetchColumn();
    $totalExpenses = $db->query("SELECT COALESCE(SUM(amount_eur), 0) FROM wallet_expenses")->fetchColumn();
    $totalShoppingItems = $db->query("SELECT COUNT(*) FROM shopping_items")->fetchColumn();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $totalUsers = $boat1Count = $boat2Count = $totalShoppingItems = 0;
    $totalExpenses = 0.0;
}

$boats = getAllBoats();
$tripName = getSetting('trip_name', 'Plavba');

renderHeader('Admin', 'admin');
?>

<h1 class="page-title">&#9881; Administrace</h1>

<div class="admin-nav">
    <a href="/admin/index.php" class="btn btn-primary btn-sm active">Dashboard</a>
    <a href="/admin/users.php" class="btn btn-outline btn-sm">Uživatelé</a>
    <a href="/admin/settings.php" class="btn btn-outline btn-sm">Nastavení</a>
</div>

<div class="card-grid">
    <div class="stat-card accent">
        <div class="stat-card-value"><?= $totalUsers ?></div>
        <div class="stat-card-label">Členů posádky</div>
    </div>
    <div class="stat-card boat1">
        <div class="stat-card-value"><?= $boat1Count ?></div>
        <div class="stat-card-label"><?= e($boats[0]['name'] ?? 'Loď 1') ?></div>
    </div>
    <div class="stat-card boat2">
        <div class="stat-card-value"><?= $boat2Count ?></div>
        <div class="stat-card-label"><?= e($boats[1]['name'] ?? 'Loď 2') ?></div>
    </div>
    <div class="stat-card success">
        <div class="stat-card-value"><?= formatMoney($totalExpenses) ?></div>
        <div class="stat-card-label">Celkové výdaje</div>
    </div>
</div>

<div class="mt-3">
    <div class="card">
        <div class="card-header">Rychlé akce</div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="/admin/users.php" class="btn btn-primary">Správa uživatelů</a>
            <a href="/admin/settings.php" class="btn btn-outline">Nastavení plavby</a>
            <a href="/admin/settings.php#itinerary" class="btn btn-outline">Itinerář</a>
            <a href="/admin/settings.php#checklist" class="btn btn-outline">Checklist</a>
            <a href="/pages/dashboard.php" class="btn btn-accent">Zobrazit jako člen</a>
        </div>
    </div>
</div>

<?php renderFooter(); ?>
