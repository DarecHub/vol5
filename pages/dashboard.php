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
<div style="background:white;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.08);padding:16px;margin-bottom:12px;">
    <!-- Řádek 1: jméno + bilance -->
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;margin-bottom:10px;">
        <div>
            <div style="font-size:1rem;font-weight:700;color:var(--gray-800);">Ahoj, <?= e(currentUserName()) ?>!</div>
            <?php if ($boat): ?>
            <div style="display:flex;align-items:center;gap:4px;font-size:0.75rem;color:var(--gray-500);margin-top:2px;">
                <i data-lucide="sailboat" style="width:12px;height:12px;flex-shrink:0;"></i>
                <?= e($boat['name']) ?>
            </div>
            <?php endif; ?>
        </div>
        <div style="background:<?= $balance >= 0 ? '#dcfce7' : '#fee2e2' ?>;color:<?= $balance >= 0 ? '#166534' : '#991b1b' ?>;font-size:1rem;font-weight:800;padding:6px 12px;border-radius:20px;white-space:nowrap;flex-shrink:0;">
            <?= ($balance >= 0 ? '+' : '') . formatMoney($balance) ?>
        </div>
    </div>
    <!-- Řádek 2: avatary posádky s fotkami -->
    <?php if (!empty($boatMembers)): ?>
    <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
        <?php foreach ($boatMembers as $m):
            $isMine = $m['id'] == $userId;
            $color = $isMine ? 'accent' : $boatColorClass;
        ?>
            <div style="display:flex;align-items:center;gap:5px;">
                <?= avatarHtml($m, 'sm', $color) ?>
                <span style="font-size:0.75rem;color:var(--gray-600);font-weight:<?= $isMine ? '700' : '500' ?>;"><?= e($m['name']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Status plavby -->
<?php if ($tripEnded): ?>
    <div style="display:flex;align-items:center;gap:8px;background:var(--gray-100);color:var(--gray-500);padding:10px 14px;border-radius:10px;font-size:0.85rem;font-weight:600;margin-bottom:12px;">
        <i data-lucide="flag" style="width:15px;height:15px;flex-shrink:0;"></i> Plavba skončila
    </div>
<?php elseif ($tripInPast): ?>
    <div style="display:flex;align-items:center;gap:8px;background:#dcfce7;color:#166534;padding:10px 14px;border-radius:10px;font-size:0.85rem;font-weight:600;margin-bottom:12px;">
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
<div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:var(--gray-400);margin-bottom:8px;">Sekce</div>
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
