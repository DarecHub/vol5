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
$paidPct = $shareTotal > 0 ? min(100, round($paidTotal / $shareTotal * 100)) : ($paidTotal > 0 ? 100 : 0);

// Barva a ikona lodi dle boat_id
$boatColorClass = $boatId == 2 ? 'boat2' : 'boat1';

renderHeader('Dashboard', 'dashboard');

// helper: initials z jména
function initials(string $name): string {
    $parts = explode(' ', trim($name));
    $i = strtoupper(mb_substr($parts[0], 0, 1));
    if (count($parts) > 1) $i .= strtoupper(mb_substr(end($parts), 0, 1));
    return $i;
}
?>

<h1 class="page-title">
    <i data-lucide="anchor" style="width:22px;height:22px;vertical-align:middle;margin-right:6px;color:var(--accent);"></i>Ahoj, <?= e(currentUserName()) ?>!
</h1>

<!-- Odpočet -->
<?php if ($tripDateFrom): ?>
<div class="countdown" id="countdown">
    <div class="countdown-title" id="countdown-label">Do plachty zbývá</div>
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
    var target = new Date('<?= e($tripDateFrom) ?>');
    var now = new Date();
    if (now >= target) {
        var label = document.getElementById('countdown-label');
        if (label) label.textContent = 'Plavba probíhá';
    }
    initCountdown('<?= e($tripDateFrom) ?>', 'countdown');
});
</script>
<?php endif; ?>

<!-- Bilance -->
<div class="card" style="margin-bottom: 12px;">
    <div class="stat-card-inner" style="padding: 0;">
        <div class="stat-card-icon-wrap <?= $balance >= 0 ? 'success' : 'danger' ?>">
            <i data-lucide="<?= $balance >= 0 ? 'trending-up' : 'trending-down' ?>" style="width:22px;height:22px;"></i>
        </div>
        <div class="stat-card-info">
            <div class="stat-card-value <?= $balance >= 0 ? 'balance-positive' : 'balance-negative' ?>" style="font-size:1.6rem;">
                <?= ($balance >= 0 ? '+' : '') . formatMoney($balance) ?>
            </div>
            <div class="stat-card-label">Tvoje bilance · zaplaceno <?= formatMoney($paidTotal) ?></div>
            <div class="progress-bar-wrap">
                <div class="progress-bar-fill <?= $balance >= 0 ? 'success' : 'danger' ?>" style="width:<?= $paidPct ?>%;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Moje loď -->
<?php if ($boat): ?>
<div class="card" style="margin-bottom: 16px;">
    <div class="card-header" style="display:flex;align-items:center;gap:8px;border-bottom:none;margin-bottom:10px;padding-bottom:0;">
        <i data-lucide="sailboat" style="width:18px;height:18px;color:var(--<?= $boatColorClass ?>);"></i>
        Tvoje loď: <strong><?= e($boat['name']) ?></strong>
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:8px;">
        <?php foreach ($boatMembers as $m):
            $isMine = $m['id'] == $userId;
        ?>
            <div style="display:flex;align-items:center;gap:6px;">
                <span class="avatar avatar-sm avatar-<?= $isMine ? 'accent' : $boatColorClass ?>"><?= initials($m['name']) ?></span>
                <span style="font-size:0.82rem;font-weight:<?= $isMine ? '700' : '500' ?>;color:var(--gray-700);"><?= e($m['name']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Navigační karty -->
<h2 class="section-title">Sekce</h2>
<div class="nav-cards">
    <?php
    $navItems = [
        ['href' => '/pages/wallet.php',    'icon' => 'wallet',       'label' => 'Pokladna',    'color' => '#38a169'],
        ['href' => '/pages/shopping.php',  'icon' => 'shopping-cart','label' => 'Nákupy',      'color' => '#3182ce'],
        ['href' => '/pages/logbook.php',   'icon' => 'book-open',    'label' => 'Deník',       'color' => '#6b46c1'],
        ['href' => '/pages/itinerary.php', 'icon' => 'map',          'label' => 'Itinerář',    'color' => '#d69e2e'],
        ['href' => '/pages/crews.php',     'icon' => 'users',        'label' => 'Posádky',     'color' => '#2b6cb0'],
        ['href' => '/pages/menu.php',      'icon' => 'utensils',     'label' => 'Jídelníček',  'color' => '#e53e3e'],
        ['href' => '/pages/checklist.php', 'icon' => 'check-square', 'label' => 'Co s sebou',  'color' => '#319795'],
        ['href' => '/pages/cars.php',      'icon' => 'car',          'label' => 'Auta',        'color' => '#744210'],
    ];
    foreach ($navItems as $n): ?>
        <a href="<?= $n['href'] ?>" class="nav-card">
            <span style="width:40px;height:40px;border-radius:12px;background:<?= $n['color'] ?>18;display:flex;align-items:center;justify-content:center;margin-bottom:2px;">
                <i data-lucide="<?= $n['icon'] ?>" style="width:22px;height:22px;color:<?= $n['color'] ?>;stroke-width:1.75;"></i>
            </span>
            <?= $n['label'] ?>
        </a>
    <?php endforeach; ?>
</div>

<?php renderFooter(); ?>
