<?php
/**
 * Pokladna – hlavní stránka, AJAX CRUD, bilance, vyrovnání
 */

require_once __DIR__ . '/../functions.php';
requireLogin();

$users = getAllUsers();
$boats = getAllBoats();
$userId = currentUserId();

// Uživatelé per loď pro JS
$usersByBoat = [];
foreach ($users as $u) {
    $usersByBoat[$u['boat_id']][] = ['id' => $u['id'], 'name' => $u['name']];
}

renderHeader('Pokladna', 'wallet');
?>

<div class="d-flex-between mb-2">
    <h1 class="page-title" style="margin-bottom: 0;">
        <i data-lucide="wallet" style="width:24px;height:24px;vertical-align:middle;margin-right:6px;color:var(--accent);"></i>Pokladna
    </h1>
    <button class="btn btn-success" onclick="openAddExpenseModal()">
        <i data-lucide="plus" style="width:16px;height:16px;"></i> Přidat výdaj
    </button>
</div>

<!-- Statistiky nahoře -->
<div class="wallet-stats-row">
    <div class="stat-card accent">
        <div class="stat-card-value" id="totalExpenses">–</div>
        <div class="stat-card-label">Celkové výdaje</div>
    </div>
    <div class="stat-card" id="myBalanceCard">
        <div class="stat-card-value" id="myBalance">–</div>
        <div class="stat-card-label">Moje bilance</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-value text-sm" id="exchangeRate" style="font-size:1rem;">–</div>
        <div class="stat-card-label">Kurz EUR/CZK</div>
    </div>
</div>

<!-- Taby: Výdaje / Bilance / Vyrovnání -->
<div class="tabs">
    <button class="tab-btn active" data-tab-group="wallet" data-tab-id="tab-expenses" onclick="switchTab('wallet','tab-expenses')">Výdaje</button>
    <button class="tab-btn" data-tab-group="wallet" data-tab-id="tab-balances" onclick="switchTab('wallet','tab-balances'); loadBalances();">Bilance</button>
    <button class="tab-btn" data-tab-group="wallet" data-tab-id="tab-settlements" onclick="switchTab('wallet','tab-settlements'); loadSettlements();">Vyrovnání</button>
</div>

<!-- Panel: Výdaje -->
<div class="tab-panel active" id="tab-expenses" data-tab-panel="wallet">
    <!-- Filtr -->
    <div class="expense-filter-row mb-2">
        <select class="form-control" id="expenseFilter" onchange="loadExpenses()">
            <option value="all">Všechny výdaje</option>
            <option value="mine">Jen moje</option>
            <?php foreach ($boats as $b): ?>
                <option value="boat<?= $b['id'] ?>"><?= e($b['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <a href="/api/export.php" class="btn btn-outline btn-sm" target="_blank">
            <i data-lucide="file-text" style="width:14px;height:14px;"></i> Export
        </a>
    </div>

    <div id="expensesList" style="position: relative;">
        <div id="expensesBody">
            <p class="text-center text-muted" style="padding: 32px;">Načítám...</p>
        </div>
    </div>
</div>

<!-- Panel: Bilance -->
<div class="tab-panel" id="tab-balances" data-tab-panel="wallet">
    <div class="card" id="balancesCard">
        <div id="balancesBody">
            <p class="text-center text-muted" style="padding:24px;">Načítám...</p>
        </div>
    </div>
</div>

<!-- Panel: Vyrovnání -->
<div class="tab-panel" id="tab-settlements" data-tab-panel="wallet">
    <div class="card">
        <div class="card-header" style="display:flex;align-items:center;gap:8px;">
            <i data-lucide="arrow-right-left" style="width:16px;height:16px;color:var(--accent);"></i>
            Optimalizované vyrovnání
        </div>
        <div id="settlementsList">
            <p class="text-muted">Načítám...</p>
        </div>
    </div>
</div>

<!-- Modal: Přidat/Editovat výdaj -->
<div class="modal-overlay" id="expenseModal">
    <div class="modal" style="max-width: 600px;">
        <div class="modal-header">
            <h3 class="modal-title" id="expenseModalTitle">Přidat výdaj</h3>
            <button class="modal-close" onclick="closeModal('expenseModal')">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="exp-id">

            <div class="form-group">
                <label class="form-label">Kdo zaplatil *</label>
                <select id="exp-paid-by" class="form-control">
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $u['id'] == $userId ? 'selected' : '' ?>><?= e($u['name']) ?> (<?= e($u['boat_name'] ?? '') ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Částka *</label>
                    <input type="number" id="exp-amount" class="form-control" step="0.01" min="0.01" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Měna</label>
                    <div style="display: flex; gap: 16px; padding-top: 8px;">
                        <label class="form-check"><input type="radio" name="exp-currency" value="EUR" checked> EUR</label>
                        <label class="form-check"><input type="radio" name="exp-currency" value="CZK"> CZK</label>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Za co *</label>
                    <input type="text" id="exp-description" class="form-control" placeholder="Popis výdaje">
                </div>
                <div class="form-group">
                    <label class="form-label">Datum</label>
                    <input type="datetime-local" id="exp-date" class="form-control">
                </div>
            </div>

            <!-- ZA KOHO – celý seznam bez scrollu -->
            <div class="form-group">
                <label class="form-label">Za koho (kdo se podílí) *</label>
                <div style="display: flex; gap: 8px; margin-bottom: 10px; flex-wrap: wrap;">
                    <?php foreach ($boats as $b): ?>
                        <button type="button" class="btn btn-outline btn-sm" onclick="selectBoatUsers(<?= $b['id'] ?>)"><?= e($b['name']) ?></button>
                    <?php endforeach; ?>
                    <button type="button" class="btn btn-outline btn-sm" onclick="selectAllUsers()">Všichni</button>
                    <button type="button" class="btn btn-outline btn-sm" onclick="clearUsers()">Nikdo</button>
                </div>
                <div id="splitUsersCheckboxes" style="border: 1px solid var(--gray-200); border-radius: 8px; padding: 10px;">
                    <?php foreach ($boats as $b):
                        $boatUsers = getUsersByBoat($b['id']);
                    ?>
                        <div class="text-sm fw-semi text-muted mb-1" style="margin-top: 6px;"><?= e($b['name']) ?>:</div>
                        <?php foreach ($boatUsers as $u): ?>
                            <label class="form-check" style="padding: 3px 0;">
                                <input type="checkbox" class="split-user-cb" value="<?= $u['id'] ?>" data-boat="<?= $b['id'] ?>">
                                <?= e($u['name']) ?>
                            </label>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('expenseModal')">Zrušit</button>
            <button class="btn btn-primary" onclick="saveExpense()">Uložit</button>
        </div>
    </div>
</div>

<!-- Modal: Audit log -->
<div class="modal-overlay" id="auditModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Historie změn</h3>
            <button class="modal-close" onclick="closeModal('auditModal')">&times;</button>
        </div>
        <div class="modal-body" id="auditContent">
            <p class="text-muted">Načítám...</p>
        </div>
    </div>
</div>



<script>
const currentUserId = <?= json_encode($userId) ?>;
const usersByBoat = <?= json_encode($usersByBoat) ?>;
const allUsers = <?= json_encode(array_map(fn($u) => ['id' => $u['id'], 'name' => $u['name']], $users)) ?>;
let editingExpenseId = null;
let currentRate = 25;

// Ikony dle kategorie výdaje
const categoryIcons = {
    'fuel':     'fuel',
    'food':     'utensils',
    'marina':   'anchor',
    'shopping': 'shopping-bag',
    'other':    'circle-dot',
    'alcohol':  'wine',
    'transport':'bus',
    'activity': 'zap',
};

function getCategoryIcon(cat) {
    return categoryIcons[cat] || 'circle-dot';
}

function getInitials(name) {
    const parts = name.trim().split(' ');
    let i = parts[0].charAt(0).toUpperCase();
    if (parts.length > 1) i += parts[parts.length - 1].charAt(0).toUpperCase();
    return i;
}

// ============================================================
// NAČTENÍ VÝDAJŮ
// ============================================================
async function loadExpenses() {
    const filter = document.getElementById('expenseFilter').value;
    const container = document.getElementById('expensesList');
    showLoading(container);

    const res = await apiCall('/api/wallet.php?action=list&filter=' + filter);
    hideLoading(container);

    if (!res.success) {
        showToast(res.error || 'Chyba.', 'error');
        return;
    }

    document.getElementById('totalExpenses').textContent = formatMoney(res.data.total_eur);

    const expenses = res.data.expenses;
    const expensesBody = document.getElementById('expensesBody');
    if (expenses.length === 0) {
        expensesBody.innerHTML = '<div class="empty-state"><div class="empty-state-icon" style="font-size:2.5rem;">💸</div><p>Žádné výdaje.</p></div>';
    } else {
        expensesBody.innerHTML = expenses.map(e => {
            const p = e.expense_date.split(/[- :]/);
            const datum = parseInt(p[2]) + '. ' + parseInt(p[1]) + '. ' + p[0];
            const pillClass = e.currency === 'CZK' ? 'amount-pill-czk' : 'amount-pill-eur';
            const castka = (e.currency === 'CZK' ? `<span class="amount-pill-sub">(${formatMoney(e.amount_eur)})</span>` : '')
                + `<span class="amount-pill ${pillClass}">${formatMoney(e.amount, e.currency)}</span>`;
            const catIcon = getCategoryIcon(e.category || 'other');
            const photoThumb = e.photo
                ? `<img src="${escapeHtml(e.photo)}" onclick="openPhotoModal('${escapeHtml(e.photo)}')"
                       style="width:44px;height:44px;object-fit:cover;border-radius:8px;cursor:pointer;flex-shrink:0;border:1px solid var(--gray-200);">`
                : `<button class="icon-btn" onclick="triggerPhotoUpload(${e.id})" title="Přidat fotku">
                       <i data-lucide="camera" style="width:15px;height:15px;"></i>
                   </button>`;
            return `<div class="expense-card">
                <div class="expense-card-header">
                    <div class="expense-card-who">
                        <div style="display:flex;align-items:center;gap:6px;">
                            <span style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--primary-light));color:white;font-weight:700;font-size:.85rem;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;">${escapeHtml(getInitials(e.paid_by_name))}</span>
                            <div>
                                <div class="fw-semi">${escapeHtml(e.paid_by_name)}</div>
                                <div class="text-sm text-muted">${datum}</div>
                            </div>
                        </div>
                        <span style="width:28px;height:28px;border-radius:8px;background:var(--gray-100);display:inline-flex;align-items:center;justify-content:center;" title="${escapeHtml(e.category||'other')}">
                            <i data-lucide="${catIcon}" style="width:14px;height:14px;color:var(--gray-500);"></i>
                        </span>
                    </div>
                    <div class="expense-card-amount">${castka}</div>
                </div>
                <div class="expense-card-footer">
                    <span class="expense-card-desc">${escapeHtml(e.description)}</span>
                    <div class="expense-card-actions" style="display:flex;align-items:center;gap:4px;">
                        ${photoThumb}
                        <button class="icon-btn" onclick="editExpense(${e.id})" title="Upravit">
                            <i data-lucide="pencil" style="width:15px;height:15px;"></i>
                        </button>
                        <button class="icon-btn icon-btn-danger" onclick="deleteExpense(${e.id})" title="Smazat">
                            <i data-lucide="trash-2" style="width:15px;height:15px;"></i>
                        </button>
                        <button class="icon-btn" onclick="showAudit(${e.id})" title="Historie">
                            <i data-lucide="clock" style="width:15px;height:15px;"></i>
                        </button>
                    </div>
                </div>
            </div>`;
        }).join('');
        lucide.createIcons();
    }

    loadMyBalance();
}

async function loadMyBalance() {
    const res = await apiCall('/api/wallet.php?action=balances');
    if (res.success) {
        const me = res.data.find(b => b.user_id === currentUserId);
        if (me) {
            const bal = me.balance;
            document.getElementById('myBalance').textContent = (bal >= 0 ? '+' : '') + formatMoney(bal);
            document.getElementById('myBalance').className = 'stat-card-value ' + (bal >= 0 ? 'balance-positive' : 'balance-negative');
            document.getElementById('myBalanceCard').style.borderTop = '4px solid ' + (bal >= 0 ? 'var(--success)' : 'var(--danger)');
        }
    }
}

async function loadExchangeRate() {
    const res = await apiCall('/api/wallet.php?action=rate');
    if (res.success) {
        currentRate = parseFloat(res.data.rate);
        document.getElementById('exchangeRate').textContent = '1 EUR = ' + currentRate.toFixed(2) + ' CZK';
    }
}

// ============================================================
// BILANCE
// ============================================================
async function loadBalances() {
    const res = await apiCall('/api/wallet.php?action=balances');
    if (!res.success) return;

    const maxPaid = Math.max(...res.data.map(b => b.paid), 1);
    const container = document.getElementById('balancesBody');
    container.innerHTML = res.data.map(b => {
        const pct = Math.min(100, Math.round(b.paid / maxPaid * 100));
        const isPos = b.balance >= 0;
        const balStr = (isPos ? '+' : '') + formatMoney(b.balance);
        const initials = getInitials(b.name);
        return `<div class="balance-row">
            <span class="avatar avatar-md avatar-${b.boat_id == 2 ? 'boat2' : 'boat1'}">${escapeHtml(initials)}</span>
            <div class="balance-row-info">
                <div class="balance-row-name">${escapeHtml(b.name)}</div>
                <div class="balance-row-sub">zaplatil ${formatMoney(b.paid)} · podíl ${formatMoney(b.share)}</div>
                <div class="progress-bar-wrap">
                    <div class="progress-bar-fill ${isPos ? 'success' : 'danger'}" style="width:${pct}%;"></div>
                </div>
            </div>
            <div class="balance-row-amount ${isPos ? 'balance-positive' : 'balance-negative'}">${balStr}</div>
        </div>`;
    }).join('');
}

// ============================================================
// VYROVNÁNÍ
// ============================================================
async function loadSettlements() {
    const res = await apiCall('/api/wallet.php?action=settlements');
    if (!res.success) return;

    const container = document.getElementById('settlementsList');
    const settlements = res.data.settlements;
    const rate = res.data.rate;

    if (settlements.length === 0) {
        container.innerHTML = '<div class="empty-state"><i data-lucide="check-circle-2" style="width:40px;height:40px;color:var(--success);margin-bottom:8px;"></i><p>Všechny účty jsou vyrovnané!</p></div>';
        lucide.createIcons();
    } else {
        container.innerHTML = settlements.map(s => {
            const settledStyle = s.settled ? 'opacity:0.45;' : '';
            const settledBtnText = s.settled ? 'Zrušit' : 'Hotovo';
            const settledBtnClass = s.settled ? 'btn-outline' : 'btn-success';
            return `<div class="settlement-item-v2" style="${settledStyle}">
                <span class="avatar avatar-sm avatar-primary">${escapeHtml(getInitials(s.from_name))}</span>
                <i data-lucide="arrow-right" style="width:16px;height:16px;color:var(--accent);flex-shrink:0;"></i>
                <span class="avatar avatar-sm avatar-boat2">${escapeHtml(getInitials(s.to_name))}</span>
                <div class="settlement-names">
                    <div class="settlement-from">${escapeHtml(s.from_name)}</div>
                    <div class="settlement-to">→ ${escapeHtml(s.to_name)}</div>
                </div>
                <div class="settlement-amount">
                    <div>${formatMoney(s.amount)}</div>
                    <div style="font-size:0.75rem;color:var(--gray-500);font-weight:500;">${formatMoney(s.amount_czk, 'CZK')}</div>
                </div>
                <button class="btn ${settledBtnClass} btn-sm" onclick="toggleSettle(${s.from_id}, ${s.to_id}, ${s.settled ? 0 : 1})">${settledBtnText}</button>
            </div>`;
        }).join('');
        lucide.createIcons();
    }
}

async function toggleSettle(fromId, toId, settle) {
    const res = await apiCall('/api/wallet.php?action=settle', 'POST', {
        from_id: fromId,
        to_id: toId,
        settle: settle,
    });
    if (res.success) {
        showToast(settle ? 'Označeno jako vyrovnané.' : 'Vyrovnání zrušeno.', 'success');
        loadSettlements();
    } else {
        showToast(res.error || 'Chyba.', 'error');
    }
}

// ============================================================
// MODALY
// ============================================================
function openAddExpenseModal() {
    editingExpenseId = null;
    document.getElementById('expenseModalTitle').textContent = 'Přidat výdaj';
    document.getElementById('exp-id').value = '';
    document.getElementById('exp-paid-by').value = currentUserId;
    document.getElementById('exp-amount').value = '';
    document.querySelector('input[name="exp-currency"][value="EUR"]').checked = true;
    document.getElementById('exp-description').value = '';
    const _now = new Date();
    const _pad = n => String(n).padStart(2, '0');
    document.getElementById('exp-date').value = _now.getFullYear() + '-' + _pad(_now.getMonth()+1) + '-' + _pad(_now.getDate()) + 'T' + _pad(_now.getHours()) + ':' + _pad(_now.getMinutes());
    clearUsers(); // Všichni ODKLIKNUTI – uživatel si sám zaškrtne
    openModal('expenseModal');
}

async function editExpense(id) {
    const res = await apiCall('/api/wallet.php?action=list');
    if (!res.success) return;

    const expense = res.data.expenses.find(e => e.id == id);
    if (!expense) { showToast('Výdaj nenalezen.', 'error'); return; }

    editingExpenseId = id;
    document.getElementById('expenseModalTitle').textContent = 'Upravit výdaj';
    document.getElementById('exp-id').value = id;
    document.getElementById('exp-paid-by').value = expense.paid_by;
    document.getElementById('exp-amount').value = expense.amount;
    document.querySelector('input[name="exp-currency"][value="' + expense.currency + '"]').checked = true;
    document.getElementById('exp-description').value = expense.description;
    document.getElementById('exp-date').value = expense.expense_date.replace(' ', 'T').slice(0, 16);

    // Nastavit checkboxy podle skutečných uživatelů z DB
    clearUsers();
    if (expense.split_user_ids && expense.split_user_ids.length > 0) {
        expense.split_user_ids.forEach(uid => {
            const cb = document.querySelector('.split-user-cb[value="' + uid + '"]');
            if (cb) cb.checked = true;
        });
    }

    openModal('expenseModal');
}

async function saveExpense() {
    const amount = parseFloat(document.getElementById('exp-amount').value);
    const description = document.getElementById('exp-description').value.trim();
    const splitUsers = getSelectedUsers();

    if (!amount || amount <= 0) { showToast('Zadejte částku.', 'error'); return; }
    if (!description) { showToast('Popište výdaj.', 'error'); return; }
    if (splitUsers.length === 0) { showToast('Vyberte alespoň jednu osobu.', 'error'); return; }

    // Určit split_type
    let splitType = 'custom';
    const boat1Users = (usersByBoat[1] || []).map(u => u.id).sort().join(',');
    const boat2Users = (usersByBoat[2] || []).map(u => u.id).sort().join(',');
    const allUserIds = allUsers.map(u => u.id).sort().join(',');
    const selectedSorted = [...splitUsers].sort().join(',');

    if (selectedSorted === allUserIds) splitType = 'both';
    else if (selectedSorted === boat1Users) splitType = 'boat1';
    else if (selectedSorted === boat2Users) splitType = 'boat2';

    const data = {
        paid_by: document.getElementById('exp-paid-by').value,
        amount: amount,
        currency: document.querySelector('input[name="exp-currency"]:checked').value,
        description: description,
        expense_date: document.getElementById('exp-date').value.replace('T', ' ') + ':00',
        split_type: splitType,
        'split_users': splitUsers.join(','),
    };

    let action = 'add';
    if (editingExpenseId) {
        data.id = editingExpenseId;
        action = 'edit';
    }

    const res = await apiCall('/api/wallet.php?action=' + action, 'POST', data);
    if (res.success) {
        closeModal('expenseModal');
        showToast(editingExpenseId ? 'Výdaj upraven.' : 'Výdaj přidán.', 'success');
        loadExpenses();
    } else {
        showToast(res.error || 'Chyba uložení.', 'error');
    }
}

async function deleteExpense(id) {
    if (!confirm('Opravdu smazat tento výdaj?')) return;
    const res = await apiCall('/api/wallet.php?action=delete', 'POST', { id: id });
    if (res.success) {
        showToast('Výdaj smazán.', 'success');
        loadExpenses();
    } else {
        showToast(res.error || 'Chyba.', 'error');
    }
}

async function showAudit(expenseId) {
    const res = await apiCall('/api/wallet.php?action=audit&expense_id=' + expenseId);
    if (!res.success) { showToast('Chyba načítání.', 'error'); return; }

    const container = document.getElementById('auditContent');
    const changeTypeNames = { created: 'Vytvořeno', edited: 'Upraveno', deleted: 'Smazáno' };

    if (res.data.length === 0) {
        container.innerHTML = '<p class="text-muted">Žádná historie.</p>';
    } else {
        container.innerHTML = res.data.map(log => `
            <div style="border-bottom: 1px solid var(--gray-100); padding: 10px 0;">
                <div class="d-flex-between">
                    <span class="badge ${log.change_type === 'deleted' ? 'badge-danger' : log.change_type === 'edited' ? 'badge-accent' : 'badge-success'}">
                        ${changeTypeNames[log.change_type] || log.change_type}
                    </span>
                    <span class="text-sm text-muted">${escapeHtml(log.changed_by_name || '?')} – ${log.changed_at}</span>
                </div>
            </div>
        `).join('');
    }

    openModal('auditModal');
}

// ============================================================
// HELPERY PRO CHECKBOX VÝBĚR
// ============================================================
function selectBoatUsers(boatId) {
    document.querySelectorAll('.split-user-cb').forEach(cb => {
        if (parseInt(cb.dataset.boat) === boatId) cb.checked = true;
    });
}

function selectAllUsers() {
    document.querySelectorAll('.split-user-cb').forEach(cb => cb.checked = true);
}

function clearUsers() {
    document.querySelectorAll('.split-user-cb').forEach(cb => cb.checked = false);
}

function getSelectedUsers() {
    return Array.from(document.querySelectorAll('.split-user-cb:checked')).map(cb => parseInt(cb.value));
}

// ============================================================
// FOTO K VÝDAJI
// ============================================================

let _photoUploadExpenseId = null;

function triggerPhotoUpload(expenseId) {
    _photoUploadExpenseId = expenseId;
    let inp = document.getElementById('expensePhotoInput');
    if (!inp) {
        inp = document.createElement('input');
        inp.type = 'file';
        inp.id = 'expensePhotoInput';
        inp.accept = 'image/jpeg,image/png,image/webp,image/heic';
        inp.style.display = 'none';
        inp.onchange = uploadExpensePhoto;
        document.body.appendChild(inp);
    }
    inp.value = '';
    inp.click();
}

async function uploadExpensePhoto() {
    const inp = document.getElementById('expensePhotoInput');
    if (!inp || !inp.files[0]) return;
    const formData = new FormData();
    formData.append('photo', inp.files[0]);
    formData.append('expense_id', _photoUploadExpenseId);
    formData.append('csrf_token', getCsrfToken());
    try {
        const res = await fetch('/api/expense_photo.php?action=upload', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData,
        });
        const json = await res.json();
        if (json.success) {
            showToast('Fotka přidána.', 'success');
            loadExpenses();
        } else {
            showToast(json.error || 'Chyba nahrávání.', 'error');
        }
    } catch (e) {
        showToast('Chyba připojení.', 'error');
    }
}

function openPhotoModal(src) {
    let overlay = document.getElementById('photoLightbox');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'photoLightbox';
        overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.9);z-index:9999;display:flex;align-items:center;justify-content:center;cursor:zoom-out;';
        overlay.onclick = () => overlay.remove();
        document.body.appendChild(overlay);
    }
    overlay.innerHTML = `<img src="${escapeHtml(src)}" style="max-width:95vw;max-height:90vh;object-fit:contain;border-radius:8px;">`;
    document.body.appendChild(overlay);
}

// ============================================================
// INIT
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    loadExpenses();
    loadExchangeRate();
});
</script>

<?php renderFooter(); ?>
<script>
// Přepis po načtení app.js – bez centů pouze v pokladně
function formatMoney(amount, currency) {
    currency = currency || 'EUR';
    return Math.round(parseFloat(amount)) + ' ' + currency;
}
</script>
