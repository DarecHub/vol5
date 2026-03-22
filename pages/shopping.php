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

$categoryIcons = [
    'potraviny' => 'apple',
    'napoje'    => 'coffee',
    'alkohol'   => 'wine',
    'hygiena'   => 'heart',
    'lekarna'   => 'pill',
    'ostatni'   => 'package',
];

renderHeader('Nákupní seznamy', 'shopping');
?>

<div class="d-flex-between mb-2">
    <h1 class="page-title" style="margin-bottom: 0;">
        <i data-lucide="shopping-cart" style="width:24px;height:24px;vertical-align:middle;margin-right:6px;color:var(--primary-light);"></i>Nákupní seznam
    </h1>
    <!-- Tlačítko Přidat jen na desktopu; mobil má FAB -->
    <button class="btn btn-success" style="display:none;" id="addItemBtnDesktop" onclick="openAddItemModal()">
        <i data-lucide="plus" style="width:16px;height:16px;"></i> Přidat
    </button>
</div>

<!-- Záložky lodí s ikonou -->
<div class="tabs">
    <?php foreach ($boats as $b): ?>
        <button class="tab-btn boat<?= $b['id'] ?> <?= $b['id'] == $currentBoatId ? 'active' : '' ?>"
                data-tab-group="shopping" data-tab-id="boat-<?= $b['id'] ?>"
                onclick="switchBoat(<?= $b['id'] ?>)">
            <i data-lucide="sailboat" style="width:14px;height:14px;vertical-align:middle;margin-right:4px;"></i><?= e($b['name']) ?>
        </button>
    <?php endforeach; ?>
</div>

<!-- Filtr kategorie -->
<div class="mb-2" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
    <select class="form-control" id="categoryFilter" onchange="loadItems()" style="max-width:200px;">
        <option value="">Všechny kategorie</option>
        <?php foreach ($categoryNames as $k => $v): ?>
            <option value="<?= $k ?>"><?= e($v) ?></option>
        <?php endforeach; ?>
    </select>
    <label style="display:flex;align-items:center;gap:6px;font-size:.85rem;color:var(--gray-600);">
        <input type="checkbox" id="hideBoughtFilter" onchange="loadItems()">
        Skrýt koupené
    </label>
</div>

<!-- Seznam položek jako cards -->
<div id="shoppingList" style="position:relative;">
    <div id="shoppingBody"><p class="text-center text-muted" style="padding:24px;">Načítám...</p></div>
    <div id="shoppingSummary" class="text-sm text-muted mt-1"></div>
</div>

<!-- FAB pro mobil -->
<button class="fab" onclick="openAddItemModal()" title="Přidat položku">
    <i data-lucide="plus" style="width:24px;height:24px;"></i>
</button>

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
const categoryIcons = <?= json_encode($categoryIcons) ?>;
let currentBoat = <?= json_encode($currentBoatId) ?>;
let editingItemId = null;

// Ukázat desktop tlačítko Přidat (FAB je pro mobil, ale na desktopu FAB skrytý)
document.addEventListener('DOMContentLoaded', function() {
    var btn = document.getElementById('addItemBtnDesktop');
    if (btn) btn.style.display = '';
    lucide.createIcons();
});

function switchBoat(boatId) {
    currentBoat = boatId;
    document.querySelectorAll('[data-tab-group="shopping"]').forEach(b => b.classList.remove('active'));
    document.querySelector('[data-tab-id="boat-' + boatId + '"]').classList.add('active');
    loadItems();
}

async function loadItems() {
    const category = document.getElementById('categoryFilter').value;
    const hideBought = document.getElementById('hideBoughtFilter').checked;
    const container = document.getElementById('shoppingList');
    showLoading(container);

    const res = await apiCall('/api/shopping.php?action=list&boat_id=' + currentBoat);
    hideLoading(container);

    if (!res.success) {
        showToast(res.error || 'Chyba načítání', 'error');
        return;
    }

    let items = res.data.items;
    if (category) items = items.filter(i => i.category === category);
    if (hideBought) items = items.filter(i => i.is_bought != 1);

    const container2 = document.getElementById('shoppingBody');

    if (items.length === 0) {
        container2.innerHTML = '<div class="empty-state"><i data-lucide="shopping-cart" style="width:36px;height:36px;color:var(--gray-300);margin-bottom:8px;"></i><p>Žádné položky.</p></div>';
        lucide.createIcons();
    } else {
        // Seskupit dle kategorie
        const grouped = {};
        items.forEach(item => {
            const cat = item.category || 'ostatni';
            if (!grouped[cat]) grouped[cat] = [];
            grouped[cat].push(item);
        });

        let html = '';
        for (const [cat, catItems] of Object.entries(grouped)) {
            const catName = categoryNames[cat] || cat;
            const catIcon = categoryIcons[cat] || 'package';
            html += `<div class="shop-category-header">
                <i data-lucide="${catIcon}" style="width:14px;height:14px;"></i>
                ${escapeHtml(catName)}
                <span style="margin-left:auto;font-weight:400;font-size:0.75rem;">${catItems.filter(i=>i.is_bought==1).length}/${catItems.length}</span>
            </div>`;
            html += catItems.map(item => {
                const bought = item.is_bought == 1;
                return `<div class="shop-item-card ${bought ? 'bought' : ''}" id="shop-item-${item.id}">
                    <div class="shop-item-checkbox ${bought ? 'checked' : ''}" onclick="toggleBought(${item.id}, ${bought ? 0 : 1})">
                        ${bought ? '<i data-lucide="check" style="width:12px;height:12px;"></i>' : ''}
                    </div>
                    <span class="shop-item-name">${escapeHtml(item.item_name)}</span>
                    ${item.quantity ? `<span class="shop-item-qty">${escapeHtml(item.quantity)}</span>` : ''}
                    ${item.assigned_to_name ? `<span style="font-size:.75rem;color:var(--gray-500);">${escapeHtml(item.assigned_to_name)}</span>` : ''}
                    ${item.price ? `<span style="font-size:.78rem;font-weight:600;color:var(--primary);">${formatMoney(item.price,item.currency)}</span>` : ''}
                    <button class="icon-btn" onclick='editItem(${JSON.stringify(item)})' title="Upravit">
                        <i data-lucide="pencil" style="width:13px;height:13px;"></i>
                    </button>
                    <button class="icon-btn icon-btn-danger" onclick="deleteItem(${item.id})" title="Smazat">
                        <i data-lucide="trash-2" style="width:13px;height:13px;"></i>
                    </button>
                </div>`;
            }).join('');
        }
        container2.innerHTML = html;
        lucide.createIcons();
    }

    const s = res.data.summary;
    document.getElementById('shoppingSummary').innerHTML =
        `${s.total_items} položek · ${s.bought_items} koupeno · EUR: ${parseFloat(s.total_eur).toFixed(2)} · CZK: ${parseFloat(s.total_czk).toFixed(2)}`;
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
        is_bought: bought,
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
