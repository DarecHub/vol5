<?php
/**
 * Posádky lodí – přehled členů
 */

require_once __DIR__ . '/../functions.php';
requireLogin();

$boats = getAllBoats();
$db = getDB();

renderHeader('Posádky', 'crews');

function crewInitials(string $name): string {
    $parts = explode(' ', trim($name));
    $i = strtoupper(mb_substr($parts[0], 0, 1));
    if (count($parts) > 1) $i .= strtoupper(mb_substr(end($parts), 0, 1));
    return $i;
}
?>

<h1 class="page-title">
    <i data-lucide="users" style="width:24px;height:24px;vertical-align:middle;margin-right:6px;color:var(--primary-light);"></i>Posádky
</h1>

<div class="card-grid">
    <?php foreach ($boats as $b):
        $members = getUsersByBoat($b['id']);
        $colorClass = $b['id'] == 2 ? 'boat2' : 'boat1';
        $userId = currentUserId();
    ?>
        <div class="crew-card">
            <div class="crew-card-header <?= $colorClass ?>">
                <div style="display:flex;align-items:center;gap:8px;">
                    <i data-lucide="sailboat" style="width:18px;height:18px;opacity:.85;"></i>
                    <span><?= e($b['name']) ?></span>
                    <?php if ($b['description']): ?>
                        <span style="font-weight:400;font-size:.82rem;opacity:.8;">– <?= e($b['description']) ?></span>
                    <?php endif; ?>
                    <span style="margin-left:auto;font-size:.82rem;opacity:.85;"><?= count($members) ?> členů</span>
                </div>
            </div>
            <?php if (empty($members)): ?>
                <div class="empty-state" style="padding:20px;">
                    <p class="text-muted">Zatím žádní členové.</p>
                </div>
            <?php else: ?>
                <?php foreach ($members as $m):
                    $isMine = $m['id'] == $userId;
                ?>
                    <div class="crew-member" onclick="openMemberModal(<?= (int)$m['id'] ?>)" style="cursor:pointer;">
                        <?= avatarHtml($m, 'md', $isMine ? 'accent' : $colorClass) ?>
                        <div style="flex:1;min-width:0;">
                            <div class="crew-member-name"><?= e($m['name']) ?><?= $isMine ? ' <span class="badge badge-accent" style="font-size:.7rem;">já</span>' : '' ?></div>
                        </div>
                        <div class="crew-member-contact">
                            <?php if ($m['phone']): ?>
                                <a href="tel:<?= e($m['phone']) ?>" style="display:flex;align-items:center;gap:4px;">
                                    <i data-lucide="phone" style="width:13px;height:13px;"></i><?= e($m['phone']) ?>
                                </a>
                            <?php endif; ?>
                            <?php if ($m['email']): ?>
                                <a href="mailto:<?= e($m['email']) ?>" style="display:flex;align-items:center;gap:4px;">
                                    <i data-lucide="mail" style="width:13px;height:13px;"></i><?= e($m['email']) ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<?php renderFooter(); ?>
