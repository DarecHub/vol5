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
    'car'     => ['icon' => 'car',      'color' => 'var(--color-warning)', 'bg' => 'var(--color-warning-subtle)'],
    'sailing' => ['icon' => 'sailboat', 'color' => 'var(--color-brand)',   'bg' => 'var(--color-brand-subtle)'],
    'port'    => ['icon' => 'anchor',   'color' => 'var(--color-success)', 'bg' => 'var(--color-success-subtle)'],
    'other'   => ['icon' => 'map-pin',  'color' => 'var(--color-info)',    'bg' => 'var(--color-info-subtle)'],
];

renderHeader('Itinerář', 'itinerary');
?>

<h1 class="page-title">
    <i data-lucide="map" class="page-title-icon"></i>Itinerář
</h1>

<?php if (empty($itinerary)): ?>
    <div class="empty-state">
        <i data-lucide="map" style="width:40px;height:40px;color:var(--color-text-tertiary);margin-bottom:8px;"></i>
        <p>Itinerář zatím nebyl naplánován.</p>
    </div>
<?php else: ?>
    <div class="timeline">
        <?php foreach ($itinerary as $it):
            $cfg = $typeConfig[$it['type']] ?? $typeConfig['other'];
        ?>
            <div class="timeline-item">
                <div class="timeline-dot <?= e($it['type']) ?>">
                    <i data-lucide="<?= $cfg['icon'] ?>" style="width:14px;height:14px;"></i>
                </div>
                <div class="timeline-content">
                    <div class="timeline-date" style="display:flex;align-items:center;gap:6px;">
                        <span>Den <?= $it['day_number'] ?></span>
                        <?php if ($it['date']): ?>
                            <span>– <?= czechDayName($it['date']) ?> <?= formatDate($it['date']) ?></span>
                        <?php endif; ?>
                        <span class="badge badge-<?= e($it['type']) ?> ml-auto">
                            <i data-lucide="<?= $cfg['icon'] ?>" style="width:10px;height:10px;vertical-align:middle;margin-right:3px;"></i><?= ucfirst(e($it['type'])) ?>
                        </span>
                    </div>
                    <div class="timeline-title"><?= e($it['title']) ?></div>
                    <?php if ($it['location_from'] || $it['location_to']): ?>
                        <div class="timeline-route" style="display:flex;align-items:center;gap:6px;">
                            <i data-lucide="map-pin" style="width:13px;height:13px;color:var(--color-text-tertiary);flex-shrink:0;"></i>
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
