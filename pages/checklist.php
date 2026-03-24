<?php
/**
 * Checklist – Co s sebou (editovatelné všemi členy, AJAX)
 */

require_once __DIR__ . '/../functions.php';
requireLogin();

$categoryNames = [
    'povinne'    => ['name' => 'Povinné',     'icon' => 'alert-circle'],
    'obleceni'   => ['name' => 'Oblečení',    'icon' => 'shirt'],
    'vybaveni'   => ['name' => 'Vybavení',    'icon' => 'backpack'],
    'doporucene' => ['name' => 'Doporučené',  'icon' => 'lightbulb'],
];
$categoryOrder = ['povinne', 'obleceni', 'vybaveni', 'doporucene'];

renderHeader('Co s sebou', 'checklist');
?>

<div class="d-flex-between mb-2">
    <h1 class="page-title" style="margin-bottom: 0;">
        <i data-lucide="check-square" class="page-title-icon"></i>Co s sebou
    </h1>
    <button class="btn btn-success desktop-only-btn" onclick="openAddChecklist()">
        <i data-lucide="plus" style="width:16px;height:16px;"></i> Přidat
    </button>
</div>

<div id="checklistContainer">
    <div class="text-center text-muted" style="padding:24px;">Načítám...</div>
</div>

<!-- FAB pro mobil -->
<button class="fab" onclick="openAddChecklist()" title="Přidat položku">
    <i data-lucide="plus" style="width:24px;height:24px;"></i>
</button>

<!-- Modal -->
<div class="modal-overlay" id="checklistModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title" id="checklistModalTitle">Přidat položku</h3>
            <button class="modal-close" onclick="closeModal('checklistModal')">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="cl-id">
            <div class="form-group">
                <label class="form-label">Kategorie *</label>
                <select id="cl-category" class="form-control">
                    <?php foreach ($categoryOrder as $key): ?>
                        <option value="<?= $key ?>"><?= $categoryNames[$key]['name'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Název položky *</label>
                <input type="text" id="cl-name" class="form-control" placeholder="Např. Pas, Sluneční brýle...">
            </div>
            <div class="form-group">
                <label class="form-label">Popis</label>
                <input type="text" id="cl-description" class="form-control" placeholder="Volitelný popis...">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-danger btn-sm" id="clDeleteBtn" onclick="deleteChecklist()" style="display: none; margin-right: auto;">Smazat</button>
            <button class="btn btn-outline" onclick="closeModal('checklistModal')">Zrušit</button>
            <button class="btn btn-primary" onclick="saveChecklist()">Uložit</button>
        </div>
    </div>
</div>

<script>
const categoryNames = <?= json_encode(array_map(fn($c) => $c['name'], $categoryNames)) ?>;
const categoryIcons = <?= json_encode(array_map(fn($c) => $c['icon'], $categoryNames)) ?>;
const categoryOrder = <?= json_encode($categoryOrder) ?>;

function toggleSection(el) {
    const section = el.closest('.cl-section');
    section.classList.toggle('collapsed');
}

async function loadChecklist() {
    const res = await apiCall('/api/checklist.php?action=list');
    if (!res.success) { showToast(res.error || 'Chyba.', 'error'); return; }

    const items = res.data;
    const container = document.getElementById('checklistContainer');

    if (items.length === 0) {
        container.innerHTML = '<div class="empty-state"><i data-lucide="check-circle-2" style="width:40px;height:40px;color:var(--color-text-tertiary);margin-bottom:8px;"></i><p>Checklist je prázdný. Přidejte první položku.</p></div>';
        lucide.createIcons();
        return;
    }

    const grouped = {};
    items.forEach(item => {
        if (!grouped[item.category]) grouped[item.category] = [];
        grouped[item.category].push(item);
    });

    let html = '';
    categoryOrder.forEach(catKey => {
        if (!grouped[catKey]) return;
        const catItems = grouped[catKey];
        const icon = categoryIcons[catKey] || 'list';
        const name = categoryNames[catKey] || catKey;
        const total = catItems.length;

        html += `<div class="cl-section" id="cl-sec-${catKey}">
            <button class="cl-section-header" onclick="toggleSection(this)">
                <span class="cl-section-title">
                    <i data-lucide="${escapeHtml(icon)}" style="width:16px;height:16px;"></i>
                    ${escapeHtml(name)}
                    <span class="badge badge-gray" style="margin-left:4px;">${total}</span>
                </span>
                <span class="cl-section-meta">
                    <i data-lucide="chevron-down" class="cl-chevron" style="width:16px;height:16px;"></i>
                </span>
            </button>
            <div class="cl-section-body">`;

        catItems.forEach(item => {
            html += `<div class="cl-item" id="cl-item-${item.id}">
                <div class="cl-item-check">
                    <i data-lucide="check" style="width:12px;height:12px;display:none;"></i>
                </div>
                <div class="cl-item-content" onclick='editChecklist(${JSON.stringify(item)})' style="cursor:pointer;">
                    <div class="cl-item-name">${escapeHtml(item.item_name)}</div>
                    ${item.description ? `<div class="cl-item-desc">${escapeHtml(item.description)}</div>` : ''}
                </div>
                <div class="cl-item-actions">
                    <button class="icon-btn" onclick='editChecklist(${JSON.stringify(item)})' title="Upravit">
                        <i data-lucide="pencil" style="width:13px;height:13px;"></i>
                    </button>
                </div>
            </div>`;
        });

        html += '</div></div>';
    });

    container.innerHTML = html;
    lucide.createIcons();
}

function openAddChecklist() {
    document.getElementById('checklistModalTitle').textContent = 'Přidat položku';
    document.getElementById('cl-id').value = '';
    document.getElementById('cl-category').value = 'doporucene';
    document.getElementById('cl-name').value = '';
    document.getElementById('cl-description').value = '';
    document.getElementById('clDeleteBtn').style.display = 'none';
    openModal('checklistModal');
}

function editChecklist(item) {
    document.getElementById('checklistModalTitle').textContent = 'Upravit položku';
    document.getElementById('cl-id').value = item.id;
    document.getElementById('cl-category').value = item.category;
    document.getElementById('cl-name').value = item.item_name;
    document.getElementById('cl-description').value = item.description || '';
    document.getElementById('clDeleteBtn').style.display = 'block';
    openModal('checklistModal');
}

async function saveChecklist() {
    const id = document.getElementById('cl-id').value;
    const data = {
        category: document.getElementById('cl-category').value,
        item_name: document.getElementById('cl-name').value,
        description: document.getElementById('cl-description').value,
    };

    if (id) data.id = id;
    const action = id ? 'edit' : 'add';

    const res = await apiCall('/api/checklist.php?action=' + action, 'POST', data);
    if (res.success) {
        closeModal('checklistModal');
        showToast(id ? 'Položka upravena.' : 'Položka přidána.', 'success');
        loadChecklist();
    } else {
        showToast(res.error || 'Chyba.', 'error');
    }
}

function deleteChecklist() {
    const id = document.getElementById('cl-id').value;
    if (!id) return;
    confirmAction('Opravdu smazat tuto položku?', async function() {
        const res = await apiCall('/api/checklist.php?action=delete', 'POST', { id: id });
        if (res.success) {
            closeModal('checklistModal');
            showToast('Položka smazána.', 'success');
            loadChecklist();
        } else {
            showToast(res.error || 'Chyba.', 'error');
        }
    });
}

document.addEventListener('DOMContentLoaded', loadChecklist);
</script>

<?php renderFooter(); ?>
