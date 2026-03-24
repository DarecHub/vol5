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
        <i data-lucide="wallet" class="page-title-icon"></i>Pokladna
    </h1>
    <button class="btn btn-success desktop-only-btn" onclick="openAddExpenseModal()">
        <i data-lucide="plus" style="width:16px;height:16px;"></i> Přidat výdaj
    </button>
</div>

<!-- Statistiky nahoře -->
<div class="wallet-stats-row">
    <div class="stat-card brand">
        <div class="stat-card-value" id="totalExpenses">–</div>
        <div class="stat-card-label">Celkové výdaje</div>
    </div>
    <div class="stat-card" id="myBalanceCard">
        <div class="stat-card-value" id="myBalance">–</div>
        <div class="stat-card-label">Moje bilance</div>
    </div>
    <div class="stat-card info">
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
            <i data-lucide="arrow-right-left" style="width:16px;height:16px;color:var(--color-brand);"></i>
            Optimalizované vyrovnání
        </div>
        <div id="settlementsList">
            <p class="text-muted">Načítám...</p>
        </div>
    </div>
</div>

<!-- FAB pro mobil -->
<button class="fab" onclick="openAddExpenseModal()" title="Přidat výdaj">
    <i data-lucide="plus" style="width:24px;height:24px;"></i>
</button>

<!-- Modal: Přidat/Editovat výdaj -->
<div class="modal-overlay" id="expenseModal">
    <div class="modal modal-lg">
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
                    <div class="currency-options">
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

            <!-- ZA KOHO – kolapsovatelná sekce -->
            <div class="form-group">
                <label class="form-label">Za koho (kdo se podílí) *</label>
                <div class="split-users-toggle" onclick="toggleSplitUsers()">
                    <span>Vybrat účastníky <span class="split-users-toggle-count" id="splitUsersCount">0 vybráno</span></span>
                    <i data-lucide="chevron-down" class="split-users-toggle-chevron" id="splitUsersChevron" style="width:18px;height:18px;"></i>
                </div>
                <div class="split-users-panel" id="splitUsersPanel">
                    <div class="split-boat-buttons">
                        <?php foreach ($boats as $b): ?>
                            <button type="button" class="btn btn-outline btn-sm" onclick="selectBoatUsers(<?= $b['id'] ?>)"><?= e($b['name']) ?></button>
                        <?php endforeach; ?>
                        <button type="button" class="btn btn-outline btn-sm" onclick="selectAllUsers()">Všichni</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="clearUsers()">Nikdo</button>
                    </div>
                    <div id="splitUsersCheckboxes" class="split-checkboxes">
                        <?php foreach ($boats as $b):
                            $boatUsers = getUsersByBoat($b['id']);
                        ?>
                            <div class="text-sm fw-semi text-muted mb-1 split-boat-label"><?= e($b['name']) ?>:</div>
                            <?php foreach ($boatUsers as $u): ?>
                                <label class="form-check">
                                    <input type="checkbox" class="split-user-cb" value="<?= $u['id'] ?>" data-boat="<?= $b['id'] ?>" onchange="updateSplitUsersCount()">
                                    <?= e($u['name']) ?>
                                </label>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
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

// getInitials() je definováno globálně v app.js

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
        expensesBody.innerHTML = '<div class="empty-state"><i data-lucide="wallet" style="width:40px;height:40px;color:var(--color-text-tertiary);margin-bottom:8px;"></i><p>Žádné výdaje.</p></div>'; lucide.createIcons();
    } else {
        expensesBody.innerHTML = expenses.map(e => {
            const p = e.expense_date.split(/[- :]/);
            const datum = parseInt(p[2]) + '. ' + parseInt(p[1]) + '. ' + p[0];
            const pillClass = e.currency === 'CZK' ? 'amount-pill-czk' : 'amount-pill-eur';
            const castka = (e.currency === 'CZK' ? `<span class="amount-pill-sub">(${formatMoney(e.amount_eur)})</span>` : '')
                + `<span class="amount-pill ${pillClass}">${formatMoney(e.amount, e.currency)}</span>`;
            const catIcon = getCategoryIcon(e.category || 'other');
            const photoThumb = e.photo
                ? `<img src="${escapeHtml(e.photo)}" onclick="openPhotoModal('${escapeHtml(e.photo)}')" class="expense-card-photo">`
                : `<button class="icon-btn" onclick="triggerPhotoUpload(${e.id})" title="Přidat fotku">
                       <i data-lucide="camera" style="width:15px;height:15px;"></i>
                   </button>`;
            const avatarHtml = e.paid_by_avatar
                ? `<img src="/${escapeHtml(e.paid_by_avatar)}" onclick="openMemberModal(${e.paid_by})" class="expense-card-avatar-img">`
                : `<span onclick="openMemberModal(${e.paid_by})" class="expense-card-avatar" style="cursor:pointer;">${escapeHtml(getInitials(e.paid_by_name))}</span>`;
            return `<div class="expense-card">
                <div class="expense-card-header">
                    <div class="expense-card-who">
                        <div style="display:flex;align-items:center;gap:8px;">
                            ${avatarHtml}
                            <div>
                                <div class="fw-semi">${escapeHtml(e.paid_by_name)}</div>
                                <div class="text-sm text-muted">${datum}</div>
                            </div>
                        </div>
                        <span style="width:28px;height:28px;border-radius:8px;background:var(--color-bg-muted);display:inline-flex;align-items:center;justify-content:center;" title="${escapeHtml(e.category||'other')}">
                            <i data-lucide="${catIcon}" style="width:14px;height:14px;color:var(--color-text-secondary);"></i>
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
            document.getElementById('myBalanceCard').className = 'stat-card ' + (bal >= 0 ? 'positive' : 'negative');
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
        container.innerHTML = '<div class="empty-state"><i data-lucide="check-circle-2" style="width:40px;height:40px;color:var(--color-success);margin-bottom:8px;"></i><p>Všechny účty jsou vyrovnané!</p></div>';
        lucide.createIcons();
    } else {
        container.innerHTML = settlements.map(s => {
            const settledStyle = s.settled ? 'opacity:0.45;' : '';
            const settledBtnText = s.settled ? 'Zrušit' : 'Hotovo';
            const settledBtnClass = s.settled ? 'btn-outline' : 'btn-success';
            return `<div class="settlement-item-v2" style="${settledStyle}">
                <span class="avatar avatar-sm avatar-primary">${escapeHtml(getInitials(s.from_name))}</span>
                <i data-lucide="arrow-right" style="width:16px;height:16px;color:var(--color-brand);flex-shrink:0;"></i>
                <span class="avatar avatar-sm avatar-primary">${escapeHtml(getInitials(s.to_name))}</span>
                <div class="settlement-names">
                    <div class="settlement-from">${escapeHtml(s.from_name)}</div>
                    <div class="settlement-to">→ ${escapeHtml(s.to_name)}</div>
                </div>
                <div class="settlement-amount">
                    <div>${formatMoney(s.amount)}</div>
                    <div style="font-size:0.75rem;color:var(--color-text-secondary);font-weight:500;">${formatMoney(s.amount_czk, 'CZK')}</div>
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
    // Collapse the split users panel for new expense
    const _panel = document.getElementById('splitUsersPanel');
    const _chevron = document.getElementById('splitUsersChevron');
    if (_panel) _panel.classList.remove('open');
    if (_chevron) _chevron.classList.remove('open');
    updateSplitUsersCount();
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
    // Auto-open split panel when editing (users are already checked)
    const _panel = document.getElementById('splitUsersPanel');
    const _chevron = document.getElementById('splitUsersChevron');
    if (_panel) _panel.classList.add('open');
    if (_chevron) _chevron.classList.add('open');
    updateSplitUsersCount();

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

function deleteExpense(id) {
    confirmAction('Opravdu smazat tento výdaj?', async function() {
        const res = await apiCall('/api/wallet.php?action=delete', 'POST', { id: id });
        if (res.success) {
            showToast('Výdaj smazán.', 'success');
            loadExpenses();
        } else {
            showToast(res.error || 'Chyba.', 'error');
        }
    });
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
            <div style="border-bottom: 1px solid var(--color-border); padding: 10px 0;">
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
    updateSplitUsersCount();
}

function selectAllUsers() {
    document.querySelectorAll('.split-user-cb').forEach(cb => cb.checked = true);
    updateSplitUsersCount();
}

function clearUsers() {
    document.querySelectorAll('.split-user-cb').forEach(cb => cb.checked = false);
    updateSplitUsersCount();
}

function getSelectedUsers() {
    return Array.from(document.querySelectorAll('.split-user-cb:checked')).map(cb => parseInt(cb.value));
}

function toggleSplitUsers() {
    const panel = document.getElementById('splitUsersPanel');
    const chevron = document.getElementById('splitUsersChevron');
    panel.classList.toggle('open');
    chevron.classList.toggle('open');
}

function updateSplitUsersCount() {
    const count = document.querySelectorAll('.split-user-cb:checked').length;
    const el = document.getElementById('splitUsersCount');
    if (el) el.textContent = count + ' vybráno';
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
