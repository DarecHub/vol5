<?php
/**
 * Itinerář plavby – read-only timeline
 */

require_once __DIR__ . '/../functions.php';
requireLogin();

$db = getDB();
$itinerary = $db->query("SELECT * FROM itinerary ORDER BY sort_order, day_number")->fetchAll();
$tripName = getSetting('trip_name', 'Plavba');

$typeIcons = [
    'car' => '&#128663;',
    'sailing' => '&#9973;',
    'port' => '&#9875;',
    'other' => '&#128204;',
];

renderHeader('Itinerář', 'itinerary');
?>

<h1 class="page-title">&#128506; Itinerář</h1>

<?php if (empty($itinerary)): ?>
    <div class="empty-state">
        <div class="empty-state-icon">&#128506;</div>
        <p>Itinerář zatím nebyl naplánován.</p>
    </div>
<?php else: ?>
    <div class="timeline">
        <?php foreach ($itinerary as $it): ?>
            <div class="timeline-item">
                <div class="timeline-dot <?= e($it['type']) ?>">
                    <?= $typeIcons[$it['type']] ?? '&#128204;' ?>
                </div>
                <div class="timeline-content">
                    <div class="timeline-date">
                        Den <?= $it['day_number'] ?>
                        <?php if ($it['date']): ?>
                            – <?= czechDayName($it['date']) ?> <?= formatDate($it['date']) ?>
                        <?php endif; ?>
                    </div>
                    <div class="timeline-title"><?= e($it['title']) ?></div>
                    <?php if ($it['location_from'] || $it['location_to']): ?>
                        <div class="timeline-route">
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
