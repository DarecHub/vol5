<?php
/**
 * Dashboard – hlavní stránka po přihlášení
 */

require_once __DIR__ . '/../functions.php';
requireLogin();

$db = getDB();
$userId = currentUserId();
$boatId = currentBoatId();

$tripName = getSetting('trip_name', 'Plavba');
$tripDateFrom = getSetting('trip_date_from', '');

// Statistiky
$boat = $boatId ? getBoatById($boatId) : null;
$boatMembers = $boatId ? getUsersByBoat($boatId) : [];

// Bilance uživatele
$paidTotal = 0.0;
$shareTotal = 0.0;
if ($userId) {
    try {
        $stmt = $db->prepare("SELECT COALESCE(SUM(amount_eur), 0) FROM wallet_expenses WHERE paid_by = ?");
        $stmt->execute([$userId]);
        $paidTotal = (float) $stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COALESCE(SUM(amount_eur), 0) FROM wallet_expense_splits WHERE user_id = ?");
        $stmt->execute([$userId]);
        $shareTotal = (float) $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log($e->getMessage());
    }
}

$balance = $paidTotal - $shareTotal;

renderHeader('Dashboard', 'dashboard');
?>

<h1 class="page-title">
    <i data-lucide="anchor" style="width:22px;height:22px;vertical-align:middle;margin-right:6px;color:var(--accent);"></i>Ahoj, <?= e(currentUserName()) ?>!
</h1>

<!-- Odpočet -->
<?php if ($tripDateFrom): ?>
<div class="countdown" id="countdown">
    <div class="countdown-numbers">
        <div class="countdown-item">
            <div class="countdown-value cd-days">–</div>
            <div class="countdown-label">dní</div>
        </div>
        <div class="countdown-item">
            <div class="countdown-value cd-hours">–</div>
            <div class="countdown-label">hodin</div>
        </div>
        <div class="countdown-item">
            <div class="countdown-value cd-minutes">–</div>
            <div class="countdown-label">minut</div>
        </div>
        <div class="countdown-item">
            <div class="countdown-value cd-seconds">–</div>
            <div class="countdown-label">sekund</div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    initCountdown('<?= e($tripDateFrom) ?>', 'countdown');
});
</script>
<?php endif; ?>

<!-- Moje loď -->
<div class="card">
    <div class="card-header">&#9973; Tvoje loď: <?= e($boat['name'] ?? 'Nepřiřazena') ?></div>
    <div class="d-flex flex-wrap gap-1">
        <?php foreach ($boatMembers as $m): ?>
            <span class="badge <?= $m['id'] == $userId ? 'badge-accent' : 'badge-boat' . $boatId ?>"><?= e($m['name']) ?></span>
        <?php endforeach; ?>
    </div>
</div>

<!-- Statistiky -->
<div class="card-grid">
    <div class="stat-card <?= $balance >= 0 ? 'success' : '' ?>" style="<?= $balance < 0 ? 'border-top: 4px solid var(--danger);' : '' ?>">
        <div class="stat-card-value <?= $balance >= 0 ? 'balance-positive' : 'balance-negative' ?>">
            <?= ($balance >= 0 ? '+' : '') . formatMoney($balance) ?>
        </div>
        <div class="stat-card-label">Tvoje bilance</div>
    </div>
</div>

<!-- Navigační karty -->
<h2 class="section-title mt-3">Sekce</h2>
<div class="nav-cards">
    <a href="/pages/wallet.php" class="nav-card">
        <i data-lucide="wallet" class="nav-card-icon"></i>
        Pokladna
    </a>
    <a href="/pages/itinerary.php" class="nav-card">
        <i data-lucide="map" class="nav-card-icon"></i>
        Itinerář
    </a>
    <a href="/pages/crews.php" class="nav-card">
        <i data-lucide="users" class="nav-card-icon"></i>
        Posádky
    </a>
    <a href="/pages/shopping.php" class="nav-card">
        <i data-lucide="shopping-cart" class="nav-card-icon"></i>
        Nákupy
    </a>
    <a href="/pages/menu.php" class="nav-card">
        <i data-lucide="utensils" class="nav-card-icon"></i>
        Jídelníček
    </a>
    <a href="/pages/checklist.php" class="nav-card">
        <i data-lucide="check-square" class="nav-card-icon"></i>
        Co s sebou
    </a>
    <a href="/pages/logbook.php" class="nav-card">
        <i data-lucide="book-open" class="nav-card-icon"></i>
        Deník
    </a>
    <a href="/pages/cars.php" class="nav-card">
        <i data-lucide="car" class="nav-card-icon"></i>
        Auta
    </a>
</div>

<?php renderFooter(); ?>
