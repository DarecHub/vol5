<?php
/**
 * Jídelníček – kdo vaří co (AJAX)
 */

require_once __DIR__ . '/../functions.php';
requireLogin();

$boats = getAllBoats();
$users = getAllUsers();
$currentBoatId = currentBoatId() ?? ($boats[0]['id'] ?? null);
$tripDateFrom = getSetting('trip_date_from', '');
$tripDateTo = getSetting('trip_date_to', '');

// Generování seznamu dní plavby
$tripDays = [];
if ($tripDateFrom && $tripDateTo) {
    $start = new DateTime($tripDateFrom);
    $end = new DateTime($tripDateTo);
    while ($start <= $end) {
        $tripDays[] = $start->format('Y-m-d');
        $start->modify('+1 day');
    }
}

$mealTypes = [
    'obed' => 'Oběd',
];

renderHeader('Jídelníček', 'menu');
?>

<div class="d-flex-between mb-2">
    <h1 class="page-title" style="margin-bottom: 0;">
        <i data-lucide="utensils" style="width:24px;height:24px;vertical-align:middle;margin-right:6px;color:var(--primary-light);"></i>Jídelníček
    </h1>
</div>

<!-- Záložky lodí s ikonou -->
<div class="tabs">
    <?php foreach ($boats as $b): ?>
        <button class="tab-btn boat<?= $b['id'] ?> <?= $b['id'] == $currentBoatId ? 'active' : '' ?>"
                data-tab-group="menu" data-tab-id="menuboat-<?= $b['id'] ?>"
                onclick="switchMenuBoat(<?= $b['id'] ?>)">
            <i data-lucide="sailboat" style="width:14px;height:14px;vertical-align:middle;margin-right:4px;"></i><?= e($b['name']) ?>
        </button>
    <?php endforeach; ?>
</div>

<!-- Day cards -->
<div id="menuCard" style="position:relative;">
    <?php if (empty($tripDays)): ?>
        <div class="empty-state">
            <i data-lucide="calendar" style="width:36px;height:36px;color:var(--gray-300);margin-bottom:8px;"></i>
            <p>Nastavte datum plavby v administraci pro zobrazení jídelníčku.</p>
        </div>
    <?php else: ?>
        <div id="menuBody">
            <?php foreach ($tripDays as $day):
                $d = new DateTime($day);
                $dayNum = $d->format('j');
                $monthShort = $d->format('M');
                $dayName = czechDayName($day);
            ?>
                <div class="menu-day-card">
                    <div class="menu-day-header">
                        <i data-lucide="calendar" style="width:14px;height:14px;color:var(--primary-light);"></i>
                        <span><?= e($dayName) ?>, <?= e(formatDate($day)) ?></span>
                    </div>
                    <div class="menu-day-body">
                        <i data-lucide="utensils" style="width:16px;height:16px;color:var(--gray-400);flex-shrink:0;"></i>
                        <div class="menu-day-cook menu-cell" id="cell-<?= $day ?>-obed"
                             onclick="openMenuModal('<?= $day ?>', 'obed')"
                             style="cursor:pointer;flex:1;">
                            <span class="menu-day-empty">Kdo vaří? Klikni pro zápis</span>
                        </div>
                        <button class="icon-btn" onclick="openMenuModal('<?= $day ?>', 'obed')" title="Zapsat">
                            <i data-lucide="pencil" style="width:13px;height:13px;"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal pro zápis jídla -->
<div class="modal-overlay" id="menuModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title" id="menuModalTitle">Zapsat jídlo</h3>
            <button class="modal-close" onclick="closeModal('menuModal')">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="menu-id">
            <input type="hidden" id="menu-date">
            <input type="hidden" id="menu-meal-type">
            <div class="form-group">
                <label class="form-label">Kdo vaří</label>
                <select id="menu-cook" class="form-control">
                    <option value="">– nikdo –</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= e($u['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Co se vaří</label>
                <textarea id="menu-description" class="form-control" rows="3" placeholder="Popis jídla..."></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Poznámka</label>
                <input type="text" id="menu-note" class="form-control">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-danger btn-sm" id="menuDeleteBtn" onclick="deleteMenuEntry()" style="display: none; margin-right: auto;">Smazat</button>
            <button class="btn btn-outline" onclick="closeModal('menuModal')">Zrušit</button>
            <button class="btn btn-primary" onclick="saveMenuEntry()">Uložit</button>
        </div>
    </div>
</div>

<script>
const mealTypeNames = <?= json_encode($mealTypes) ?>;
let currentMenuBoat = <?= json_encode($currentBoatId) ?>;
let menuData = {};

function switchMenuBoat(boatId) {
    currentMenuBoat = boatId;
    document.querySelectorAll('[data-tab-group="menu"]').forEach(b => b.classList.remove('active'));
    document.querySelector('[data-tab-id="menuboat-' + boatId + '"]').classList.add('active');
    loadMenuData();
}

async function loadMenuData() {
    const res = await apiCall('/api/menu.php?action=list&boat_id=' + currentMenuBoat);
    if (!res.success) {
        showToast(res.error || 'Chyba načítání.', 'error');
        return;
    }

    menuData = {};
    res.data.forEach(item => {
        const key = item.date + '-' + item.meal_type;
        menuData[key] = item;
    });

    // Aktualizovat day-cards buňky
    document.querySelectorAll('.menu-cell').forEach(cell => {
        const parts = cell.id.replace('cell-', '').split(/-(?=[^-]+$)/);
        const date = parts[0];
        const meal = parts[1];
        const key = date + '-' + meal;

        if (menuData[key]) {
            const entry = menuData[key];
            const initials = entry.cook_name
                ? entry.cook_name.trim().split(' ').map(p=>p[0]).join('').toUpperCase().slice(0,2)
                : '?';
            cell.innerHTML =
                `<span class="avatar avatar-sm avatar-primary" style="flex-shrink:0;">${escapeHtml(initials)}</span>
                 <div>
                   <span class="fw-semi" style="font-size:.88rem;">${escapeHtml(entry.cook_name || '?')}</span>
                   ${entry.meal_description ? `<div style="font-size:.78rem;color:var(--gray-500);">${escapeHtml(entry.meal_description)}</div>` : ''}
                 </div>`;
        } else {
            cell.innerHTML = '<span class="menu-day-empty">Kdo vaří? Klikni pro zápis</span>';
        }
    });
    lucide.createIcons();
}

function openMenuModal(date, mealType) {
    const key = date + '-' + mealType;
    const entry = menuData[key] || null;

    document.getElementById('menu-date').value = date;
    document.getElementById('menu-meal-type').value = mealType;
    document.getElementById('menuModalTitle').textContent =
        (entry ? 'Upravit' : 'Zapsat') + ' – ' + (mealTypeNames[mealType] || mealType);

    if (entry) {
        document.getElementById('menu-id').value = entry.id;
        document.getElementById('menu-cook').value = entry.cook_user_id || '';
        document.getElementById('menu-description').value = entry.meal_description || '';
        document.getElementById('menu-note').value = entry.note || '';
        document.getElementById('menuDeleteBtn').style.display = 'block';
    } else {
        document.getElementById('menu-id').value = '';
        document.getElementById('menu-cook').value = '<?= currentUserId() ?>';
        document.getElementById('menu-description').value = '';
        document.getElementById('menu-note').value = '';
        document.getElementById('menuDeleteBtn').style.display = 'none';
    }

    openModal('menuModal');
}

async function saveMenuEntry() {
    const id = document.getElementById('menu-id').value;
    const data = {
        boat_id: currentMenuBoat,
        date: document.getElementById('menu-date').value,
        meal_type: document.getElementById('menu-meal-type').value,
        cook_user_id: document.getElementById('menu-cook').value,
        meal_description: document.getElementById('menu-description').value,
        note: document.getElementById('menu-note').value,
    };

    let action = 'add';
    if (id) {
        data.id = id;
        action = 'edit';
    }

    const res = await apiCall('/api/menu.php?action=' + action, 'POST', data);
    if (res.success) {
        closeModal('menuModal');
        showToast('Jídelníček uložen.', 'success');
        loadMenuData();
    } else {
        showToast(res.error || 'Chyba uložení.', 'error');
    }
}

async function deleteMenuEntry() {
    const id = document.getElementById('menu-id').value;
    if (!id || !confirm('Opravdu smazat tento záznam?')) return;

    const res = await apiCall('/api/menu.php?action=delete', 'POST', { id: id });
    if (res.success) {
        closeModal('menuModal');
        showToast('Záznam smazán.', 'success');
        loadMenuData();
    } else {
        showToast(res.error || 'Chyba mazání.', 'error');
    }
}

document.addEventListener('DOMContentLoaded', loadMenuData);
</script>

<?php renderFooter(); ?>
