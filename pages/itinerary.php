<?php
/**
 * Itinerář plavby – read-only timeline
 */

require_once __DIR__ . '/../functions.php';
requireLogin();

$db = getDB();
$itinerary = $db->query("SELECT * FROM itinerary ORDER BY sort_order, day_number")->fetchAll();
$tripName = getSetting('trip_name', 'Plavba');

// Lucide ikony a barvy pro typy
$typeConfig = [
    'car'     => ['icon' => 'car',      'color' => '#d69e2e', 'bg' => '#fefcbf'],
    'sailing' => ['icon' => 'sailboat', 'color' => '#2b6cb0', 'bg' => '#dbeafe'],
    'port'    => ['icon' => 'anchor',   'color' => '#2f855a', 'bg' => '#dcfce7'],
    'other'   => ['icon' => 'map-pin',  'color' => '#6b46c1', 'bg' => '#ede9fe'],
];

renderHeader('Itinerář', 'itinerary');
?>

<h1 class="page-title">
    <i data-lucide="map" style="width:24px;height:24px;vertical-align:middle;margin-right:6px;color:var(--primary-light);"></i>Itinerář
</h1>

<?php if (empty($itinerary)): ?>
    <div class="empty-state">
        <i data-lucide="map" style="width:40px;height:40px;color:var(--gray-300);margin-bottom:8px;"></i>
        <p>Itinerář zatím nebyl naplánován.</p>
    </div>
<?php else: ?>
    <div class="timeline">
        <?php foreach ($itinerary as $it):
            $cfg = $typeConfig[$it['type']] ?? $typeConfig['other'];
        ?>
            <div class="timeline-item">
                <div class="timeline-dot <?= e($it['type']) ?>"
                     style="background:<?= $cfg['bg'] ?>;width:28px;height:28px;border:2px solid <?= $cfg['color'] ?>;">
                    <i data-lucide="<?= $cfg['icon'] ?>" style="width:14px;height:14px;color:<?= $cfg['color'] ?>;"></i>
                </div>
                <div class="timeline-content">
                    <div class="timeline-date" style="display:flex;align-items:center;gap:6px;">
                        <span>Den <?= $it['day_number'] ?></span>
                        <?php if ($it['date']): ?>
                            <span>– <?= czechDayName($it['date']) ?> <?= formatDate($it['date']) ?></span>
                        <?php endif; ?>
                        <span class="badge" style="background:<?= $cfg['bg'] ?>;color:<?= $cfg['color'] ?>;margin-left:auto;">
                            <i data-lucide="<?= $cfg['icon'] ?>" style="width:10px;height:10px;vertical-align:middle;margin-right:3px;"></i><?= ucfirst(e($it['type'])) ?>
                        </span>
                    </div>
                    <div class="timeline-title"><?= e($it['title']) ?></div>
                    <?php if ($it['location_from'] || $it['location_to']): ?>
                        <div class="timeline-route" style="display:flex;align-items:center;gap:6px;">
                            <i data-lucide="map-pin" style="width:13px;height:13px;color:var(--gray-400);flex-shrink:0;"></i>
                            <?= e($it['location_from'] ?? '?') ?> &rarr; <?= e($it['location_to'] ?? '?') ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($it['description']): ?>
                        <p class="text-sm text-muted mt-1"><?= nl2br(e($it['description'])) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php renderFooter(); ?>
