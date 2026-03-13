<?php
/**
 * Nákupní seznamy – záložky per loď, AJAX CRUD
 */

require_once __DIR__ . '/../functions.php';
requireLogin();

$boats = getAllBoats();
$users = getAllUsers();
$currentBoatId = currentBoatId() ?? ($boats[0]['id'] ?? null);

$categoryNames = [
    'potraviny' => 'Potraviny',
    'napoje' => 'Nápoje',
    'alkohol' => 'Alkohol',
    'hygiena' => 'Hygiena',
    'lekarna' => 'Lékárnička',
    'ostatni' => 'Ostatní',
];

renderHeader('Nákupní seznamy', 'shopping');
?>

<div class="d-flex-between mb-2">
    <h1 class="page-title" style="margin-bottom: 0;">&#128722; Nákupní seznam</h1>
    <button class="btn btn-success" onclick="openAddItemModal()">+ Přidat</button>
</div>

<!-- Záložky lodí -->
<div class="tabs">
    <?php foreach ($boats as $b): ?>
        <button class="tab-btn boat<?= $b['id'] ?> <?= $b['id'] == $currentBoatId ? 'active' : '' ?>"
                data-tab-group="shopping" data-tab-id="boat-<?= $b['id'] ?>"
                onclick="switchBoat(<?= $b['id'] ?>)">
            <?= e($b['name']) ?>
        </button>
    <?php endforeach; ?>
</div>

<!-- Filtr kategorie -->
<div class="mb-2">
    <select class="form-control" id="categoryFilter" onchange="loadItems()" style="max-width: 200px;">
        <option value="">Všechny kategorie</option>
        <?php foreach ($categoryNames as $k => $v): ?>
            <option value="<?= $k ?>"><?= e($v) ?></option>
        <?php endforeach; ?>
    </select>
</div>

<!-- Seznam položek -->
<div class="card" id="shoppingList" style="position: relative;">
    <div class="table-responsive">
        <table class="table" id="shoppingTable">
            <thead>
                <tr>
                    <th style="width: 40px;">&#10004;</th>
                    <th>Položka</th>
                    <th>Množství</th>
                    <th>Kategorie</th>
                    <th>Kdo kupuje</th>
                    <th>Cena</th>
                    <th>Akce</th>
                </tr>
            </thead>
            <tbody id="shoppingBody">
                <tr><td colspan="7" class="text-center text-muted">Načítám...</td></tr>
            </tbody>
        </table>
    </div>
    <div id="shoppingSummary" class="mt-1 text-sm text-muted"></div>
</div>

<!-- Modal přidání/editace -->
<div class="modal-overlay" id="itemModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title" id="itemModalTitle">Přidat položku</h3>
            <button class="modal-close" onclick="closeModal('itemModal')">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="item-id">
            <div class="form-group">
                <label class="form-label">Název položky *</label>
                <input type="text" id="item-name" class="form-control" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Množství</label>
                    <input type="text" id="item-quantity" class="form-control" placeholder="např. 2 kg">
                </div>
                <div class="form-group">
                    <label class="form-label">Kategorie</label>
                    <select id="item-category" class="form-control">
                        <?php foreach ($categoryNames as $k => $v): ?>
                            <option value="<?= $k ?>"><?= e($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Kdo kupuje</label>
                <select id="item-assigned" class="form-control">
                    <option value="">– nikdo –</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= e($u['name']) ?> (<?= e($u['boat_name'] ?? '') ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Cena</label>
                    <input type="number" id="item-price" class="form-control" step="0.01" min="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Měna</label>
                    <select id="item-currency" class="form-control">
                        <option value="EUR">EUR</option>
                        <option value="CZK">CZK</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Poznámka</label>
                <textarea id="item-note" class="form-control" rows="2"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('itemModal')">Zrušit</button>
            <button class="btn btn-primary" onclick="saveItem()">Uložit</button>
        </div>
    </div>
</div>

<script>
const categoryNames = <?= json_encode($categoryNames) ?>;
let currentBoat = <?= json_encode($currentBoatId) ?>;
let editingItemId = null;

function switchBoat(boatId) {
    currentBoat = boatId;
    // Aktualizuj taby vizuálně
    document.querySelectorAll('[data-tab-group="shopping"]').forEach(b => b.classList.remove('active'));
    document.querySelector('[data-tab-id="boat-' + boatId + '"]').classList.add('active');
    loadItems();
}

async function loadItems() {
    const category = document.getElementById('categoryFilter').value;
    const container = document.getElementById('shoppingList');
    showLoading(container);

    const res = await apiCall('/api/shopping.php?action=list&boat_id=' + currentBoat);
    hideLoading(container);

    if (!res.success) {
        showToast(res.error || 'Chyba načítání', 'error');
        return;
    }

    let items = res.data.items;
    if (category) {
        items = items.filter(i => i.category === category);
    }

    const tbody = document.getElementById('shoppingBody');
    if (items.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Žádné položky.</td></tr>';
    } else {
        tbody.innerHTML = items.map(item => {
            const boughtClass = item.is_bought == 1 ? 'style="opacity: 0.5; text-decoration: line-through;"' : '';
            return `<tr ${boughtClass}>
                <td><input type="checkbox" ${item.is_bought == 1 ? 'checked' : ''} onchange="toggleBought(${item.id}, this.checked)"></td>
                <td class="fw-semi">${escapeHtml(item.item_name)}</td>
                <td>${escapeHtml(item.quantity || '–')}</td>
                <td><span class="badge badge-gray">${escapeHtml(categoryNames[item.category] || item.category)}</span></td>
                <td>${item.assigned_to_name ? escapeHtml(item.assigned_to_name) : '<span class="text-muted">–</span>'}</td>
                <td>${item.price ? formatMoney(item.price, item.currency) : '–'}</td>
                <td>
                    <button class="btn btn-outline btn-sm" onclick='editItem(${JSON.stringify(item)})'>&#9998;</button>
                    <button class="btn btn-danger btn-sm" onclick="deleteItem(${item.id})">&#10005;</button>
                </td>
            </tr>`;
        }).join('');
    }

    // Souhrn
    const s = res.data.summary;
    document.getElementById('shoppingSummary').innerHTML =
        `Celkem: ${s.total_items} položek (${s.bought_items} koupeno) | EUR: ${parseFloat(s.total_eur).toFixed(2)} | CZK: ${parseFloat(s.total_czk).toFixed(2)}`;
}

function openAddItemModal() {
    editingItemId = null;
    document.getElementById('itemModalTitle').textContent = 'Přidat položku';
    document.getElementById('item-id').value = '';
    document.getElementById('item-name').value = '';
    document.getElementById('item-quantity').value = '';
    document.getElementById('item-category').value = 'potraviny';
    document.getElementById('item-assigned').value = '';
    document.getElementById('item-price').value = '';
    document.getElementById('item-currency').value = 'EUR';
    document.getElementById('item-note').value = '';
    openModal('itemModal');
}

function editItem(item) {
    editingItemId = item.id;
    document.getElementById('itemModalTitle').textContent = 'Upravit položku';
    document.getElementById('item-id').value = item.id;
    document.getElementById('item-name').value = item.item_name;
    document.getElementById('item-quantity').value = item.quantity || '';
    document.getElementById('item-category').value = item.category;
    document.getElementById('item-assigned').value = item.assigned_to || '';
    document.getElementById('item-price').value = item.price || '';
    document.getElementById('item-currency').value = item.currency;
    document.getElementById('item-note').value = item.note || '';
    openModal('itemModal');
}

async function saveItem() {
    const name = document.getElementById('item-name').value.trim();
    if (!name) {
        showToast('Název je povinný.', 'error');
        return;
    }

    const data = {
        boat_id: currentBoat,
        item_name: name,
        quantity: document.getElementById('item-quantity').value,
        category: document.getElementById('item-category').value,
        assigned_to: document.getElementById('item-assigned').value,
        price: document.getElementById('item-price').value,
        currency: document.getElementById('item-currency').value,
        note: document.getElementById('item-note').value,
    };

    let action = 'add';
    if (editingItemId) {
        data.id = editingItemId;
        action = 'edit';
    }

    const res = await apiCall('/api/shopping.php?action=' + action, 'POST', data);

    if (res.success) {
        closeModal('itemModal');
        showToast(editingItemId ? 'Položka upravena.' : 'Položka přidána.', 'success');
        loadItems();
    } else {
        showToast(res.error || 'Chyba uložení.', 'error');
    }
}

async function toggleBought(id, bought) {
    const res = await apiCall('/api/shopping.php?action=toggle_bought', 'POST', {
        id: id,
        is_bought: bought ? 1 : 0,
        price: ''
    });
    if (res.success) {
        loadItems();
    }
}

async function deleteItem(id) {
    if (!confirm('Opravdu smazat tuto položku?')) return;
    const res = await apiCall('/api/shopping.php?action=delete', 'POST', { id: id });
    if (res.success) {
        showToast('Položka smazána.', 'success');
        loadItems();
    } else {
        showToast(res.error || 'Chyba mazání.', 'error');
    }
}

// Načíst při startu
document.addEventListener('DOMContentLoaded', loadItems);
</script>

<?php renderFooter(); ?>
