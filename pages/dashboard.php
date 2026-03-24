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

<?php
// Countdown stav
$tripInPast = false;
$tripInFuture = false;
if ($tripDateFrom) {
    $now = new DateTime();
    $target = new DateTime($tripDateFrom);
    if ($now >= $target) $tripInPast = true;
    else $tripInFuture = true;
}
$tripDateTo = getSetting('trip_date_to', '');
$tripEnded = false;
if ($tripDateTo) {
    $now2 = new DateTime();
    $end = new DateTime($tripDateTo);
    if ($now2 > $end) $tripEnded = true;
}
?>

<!-- Hero karta -->
<div class="dash-hero">
    <div class="dash-hero-top">
        <div>
            <div class="dash-hero-greeting">Ahoj, <?= e(currentUserName()) ?>!</div>
            <?php if ($boat): ?>
            <div class="dash-hero-boat">
                <i data-lucide="sailboat" style="width:12px;height:12px;flex-shrink:0;"></i>
                <?= e($boat['name']) ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="dash-hero-balance <?= $balance >= 0 ? 'positive' : 'negative' ?>">
            <span class="dash-hero-balance-label"><?= $balance >= 0 ? 'Přeplatek' : 'Dlužíte' ?></span>
            <span class="dash-hero-balance-value"><?= formatMoney(abs($balance)) ?></span>
        </div>
    </div>
    <?php if (!empty($boatMembers)): ?>
    <div class="dash-hero-crew">
        <?php foreach ($boatMembers as $m):
            $isMine = $m['id'] == $userId;
            $color = $isMine ? 'accent' : $boatColorClass;
        ?>
            <div class="dash-hero-member" onclick="openMemberModal(<?= (int)$m['id'] ?>)">
                <?= avatarHtml($m, 'sm', $color) ?>
                <span class="dash-hero-member-name" style="font-weight:<?= $isMine ? '700' : '500' ?>;"><?= e($m['name']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Status plavby -->
<?php if ($tripEnded): ?>
    <div class="dash-status ended">
        <i data-lucide="flag" style="width:15px;height:15px;flex-shrink:0;"></i> Plavba skončila
    </div>
<?php elseif ($tripInPast): ?>
    <div class="dash-status active">
        <i data-lucide="anchor" style="width:15px;height:15px;flex-shrink:0;"></i> Plavba probíhá!
    </div>
<?php elseif ($tripInFuture): ?>
<div class="countdown" id="countdown" style="margin-bottom:12px;">
    <div class="countdown-title">Do plachty zbývá</div>
    <div class="countdown-numbers">
        <div class="countdown-item"><div class="countdown-value cd-days">–</div><div class="countdown-label">dní</div></div>
        <div class="countdown-item"><div class="countdown-value cd-hours">–</div><div class="countdown-label">hodin</div></div>
        <div class="countdown-item"><div class="countdown-value cd-minutes">–</div><div class="countdown-label">minut</div></div>
        <div class="countdown-item"><div class="countdown-value cd-seconds">–</div><div class="countdown-label">sekund</div></div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    initCountdown('<?= e($tripDateFrom) ?>', 'countdown');
});
</script>
<?php endif; ?>

<!-- Navigační karty -->
<div class="dash-section-label">Sekce</div>
<div class="nav-cards">
    <?php
    $navItems = [
        ['href' => '/pages/wallet.php',    'icon' => 'wallet',       'label' => 'Pokladna',    'css' => 'nc-wallet'],
        ['href' => '/pages/shopping.php',  'icon' => 'shopping-cart','label' => 'Nákupy',      'css' => 'nc-shopping'],
        ['href' => '/pages/logbook.php',   'icon' => 'book-open',    'label' => 'Deník',       'css' => 'nc-logbook'],
        ['href' => '/pages/itinerary.php', 'icon' => 'map',          'label' => 'Itinerář',    'css' => 'nc-itinerary'],
        ['href' => '/pages/crews.php',     'icon' => 'users',        'label' => 'Posádky',     'css' => 'nc-crews'],
        ['href' => '/pages/menu.php',      'icon' => 'utensils',     'label' => 'Jídelníček',  'css' => 'nc-menu'],
        ['href' => '/pages/checklist.php', 'icon' => 'check-square', 'label' => 'Co s sebou',  'css' => 'nc-checklist'],
        ['href' => '/pages/cars.php',      'icon' => 'car',          'label' => 'Auta',        'css' => 'nc-cars'],
    ];
    foreach ($navItems as $n): ?>
        <a href="<?= $n['href'] ?>" class="nav-card">
            <span class="nav-card-icon-wrap <?= $n['css'] ?>">
                <i data-lucide="<?= $n['icon'] ?>" style="width:22px;height:22px;stroke-width:1.75;"></i>
            </span>
            <?= $n['label'] ?>
        </a>
    <?php endforeach; ?>
</div>

<?php renderFooter(); ?>
