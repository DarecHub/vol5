<?php
/**
 * Posádky lodí – přehled členů
 */

require_once __DIR__ . '/../functions.php';
requireLogin();

$boats = getAllBoats();
$db = getDB();

renderHeader('Posádky', 'crews');
?>

<h1 class="page-title">&#9973; Posádky</h1>

<div class="card-grid">
    <?php foreach ($boats as $b):
        $members = getUsersByBoat($b['id']);
        $colorClass = $b['id'] == 1 ? 'boat1' : 'boat2';
    ?>
        <div class="crew-card">
            <div class="crew-card-header <?= $colorClass ?>">
                <?= e($b['name']) ?>
                <?php if ($b['description']): ?>
                    <span style="font-weight: 400; font-size: 0.85rem; opacity: 0.8;"> – <?= e($b['description']) ?></span>
                <?php endif; ?>
                <span style="float: right; font-size: 0.85rem;"><?= count($members) ?> členů</span>
            </div>
            <?php if (empty($members)): ?>
                <div class="empty-state" style="padding: 20px;">
                    <p class="text-muted">Zatím žádní členové.</p>
                </div>
            <?php else: ?>
                <?php foreach ($members as $m): ?>
                    <div class="crew-member">
                        <span class="crew-member-name"><?= e($m['name']) ?></span>
                        <div class="crew-member-contact">
                            <?php if ($m['phone']): ?>
                                <a href="tel:<?= e($m['phone']) ?>">&#128222; <?= e($m['phone']) ?></a>
                            <?php endif; ?>
                            <?php if ($m['email']): ?>
                                <a href="mailto:<?= e($m['email']) ?>">&#9993; <?= e($m['email']) ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<?php renderFooter(); ?>
