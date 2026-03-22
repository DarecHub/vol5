<?php
/**
 * Checklist – Co s sebou (editovatelné všemi členy, AJAX)
 */

require_once __DIR__ . '/../functions.php';
requireLogin();

$categoryNames = [
    'povinne' => ['name' => 'Povinné', 'icon' => '&#10071;'],
    'obleceni' => ['name' => 'Oblečení', 'icon' => '&#128085;'],
    'vybaveni' => ['name' => 'Vybavení', 'icon' => '&#127890;'],
    'doporucene' => ['name' => 'Doporučené', 'icon' => '&#128161;'],
];
$categoryOrder = ['povinne', 'obleceni', 'vybaveni', 'doporucene'];

renderHeader('Co s sebou', 'checklist');
?>

<div class="d-flex-between mb-2">
    <h1 class="page-title" style="margin-bottom: 0;">
        <i data-lucide="check-square" style="width:24px;height:24px;vertical-align:middle;margin-right:6px;color:var(--primary-light);"></i>Co s sebou
    </h1>
    <button class="btn btn-success" onclick="openAddChecklist()">
        <i data-lucide="plus" style="width:16px;height:16px;"></i> Přidat
    </button>
</div>

<div id="checklistContainer">
    <div class="text-center text-muted">Načítám...</div>
</div>

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

async function loadChecklist() {
    const res = await apiCall('/api/checklist.php?action=list');
    if (!res.success) { showToast(res.error || 'Chyba.', 'error'); return; }

    const items = res.data;
    const container = document.getElementById('checklistContainer');

    if (items.length === 0) {
        container.innerHTML = '<div class="empty-state"><div class="empty-state-icon">&#9989;</div><p>Checklist je prázdný. Přidejte první položku.</p></div>';
        return;
    }

    // Seskupit podle kategorií
    const grouped = {};
    items.forEach(item => {
        if (!grouped[item.category]) grouped[item.category] = [];
        grouped[item.category].push(item);
    });

    let html = '';
    categoryOrder.forEach(catKey => {
        if (!grouped[catKey]) return;
        const catItems = grouped[catKey];
        html += `<div class="checklist-category">
            <div class="checklist-category-title">
                <span>${categoryIcons[catKey]}</span>
                ${escapeHtml(categoryNames[catKey])}
                <span class="badge badge-gray">${catItems.length}</span>
            </div>
            <div class="card" style="padding: 8px 12px;">`;

        catItems.forEach(item => {
            html += `<div class="checklist-item" style="cursor: pointer;" onclick='editChecklist(${JSON.stringify(item)})'>
                <span style="color: var(--gray-300);">&#9744;</span>
                <div style="flex: 1;">
                    <div class="checklist-item-name">${escapeHtml(item.item_name)}</div>
                    ${item.description ? '<div class="checklist-item-desc">' + escapeHtml(item.description) + '</div>' : ''}
                </div>
                <span class="text-muted text-sm">&#9998;</span>
            </div>`;
        });

        html += '</div></div>';
    });

    container.innerHTML = html;
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

async function deleteChecklist() {
    const id = document.getElementById('cl-id').value;
    if (!id || !confirm('Opravdu smazat tuto položku?')) return;

    const res = await apiCall('/api/checklist.php?action=delete', 'POST', { id: id });
    if (res.success) {
        closeModal('checklistModal');
        showToast('Položka smazána.', 'success');
        loadChecklist();
    } else {
        showToast(res.error || 'Chyba.', 'error');
    }
}

document.addEventListener('DOMContentLoaded', loadChecklist);
</script>

<?php renderFooter(); ?>
