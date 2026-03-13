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
    <h1 class="page-title" style="margin-bottom: 0;">&#128176; Pokladna</h1>
    <button class="btn btn-success" onclick="openAddExpenseModal()">+ Přidat výdaj</button>
</div>

<!-- Statistiky nahoře -->
<div class="card-grid" style="grid-template-columns: 1fr;">
    <div class="stat-card accent">
        <div class="stat-card-value" id="totalExpenses">–</div>
        <div class="stat-card-label">Celkové výdaje</div>
    </div>
    <div class="stat-card" id="myBalanceCard">
        <div class="stat-card-value" id="myBalance">–</div>
        <div class="stat-card-label">Moje bilance</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-value text-sm" id="exchangeRate">–</div>
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
    <div class="mb-2">
        <select class="form-control" id="expenseFilter" onchange="loadExpenses()" style="max-width: 200px; display: inline-block;">
            <option value="all">Všechny výdaje</option>
            <option value="mine">Jen moje</option>
            <?php foreach ($boats as $b): ?>
                <option value="boat<?= $b['id'] ?>"><?= e($b['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <a href="/api/export.php" class="btn btn-outline btn-sm" style="margin-left: 8px;" target="_blank">&#128196; Export PDF</a>
    </div>

    <div class="card" id="expensesList" style="position: relative; padding: 0;">
        <div id="expensesBody">
            <p class="text-center text-muted" style="padding: 16px;">Načítám...</p>
        </div>
    </div>
</div>

<!-- Panel: Bilance -->
<div class="tab-panel" id="tab-balances" data-tab-panel="wallet">
    <div class="card" id="balancesCard">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Jméno</th>
                        <th class="text-right">Zaplatil</th>
                        <th class="text-right">Jeho podíl</th>
                        <th class="text-right">Bilance</th>
                    </tr>
                </thead>
                <tbody id="balancesBody">
                    <tr><td colspan="4" class="text-center text-muted">Načítám...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Panel: Vyrovnání -->
<div class="tab-panel" id="tab-settlements" data-tab-panel="wallet">
    <div class="card">
        <div class="card-header">Optimalizované vyrovnání</div>
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

<style>
.amount-pill {
    display: inline-block;
    padding: 3px 12px;
    border-radius: 20px;
    font-weight: 700;
    font-size: 0.85rem;
    white-space: nowrap;
    line-height: 1.6;
}
.amount-pill-eur {
    background: var(--primary);
    color: #fff;
}
.amount-pill-czk {
    background: #276749;
    color: #fff;
}
.amount-pill-sub {
    font-size: 0.72rem;
    color: var(--gray-500);
    margin-right: 6px;
}
</style>

<script>
const currentUserId = <?= json_encode($userId) ?>;
const usersByBoat = <?= json_encode($usersByBoat) ?>;
const allUsers = <?= json_encode(array_map(fn($u) => ['id' => $u['id'], 'name' => $u['name']], $users)) ?>;
let editingExpenseId = null;
let currentRate = 25;

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
        expensesBody.innerHTML = '<p class="text-center text-muted" style="padding: 16px;">Žádné výdaje.</p>';
    } else {
        const expensesBody = document.getElementById('expensesBody');
        expensesBody.innerHTML = expenses.map(e => {
            const p = e.expense_date.split(/[- :]/);
            const datum = parseInt(p[2]) + '. ' + parseInt(p[1]) + '. ' + p[3] + ':' + p[4];
            const pillClass = e.currency === 'CZK' ? 'amount-pill-czk' : 'amount-pill-eur';
            const castka = (e.currency === 'CZK' ? `<span class="amount-pill-sub">(${formatMoney(e.amount_eur)})</span>` : '')
                + `<span class="amount-pill ${pillClass}">${formatMoney(e.amount, e.currency)}</span>`;
            return `<div style="padding: 6px 14px; border-bottom: 1px solid #cbd5e0;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-weight:600;">${escapeHtml(e.paid_by_name)}</span>
                    <span style="display:flex; gap:3px;">
                        <button class="btn btn-outline btn-sm" onclick="editExpense(${e.id})">&#9998;</button>
                        <button class="btn btn-danger btn-sm" onclick="deleteExpense(${e.id})">&#10005;</button>
                        <button class="btn btn-outline btn-sm" onclick="showAudit(${e.id})" title="Historie">&#128340;</button>
                    </span>
                </div>
                <div style="font-size:0.85rem; color:#718096;">${datum}</div>
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-size:0.85rem; color:#000;">${escapeHtml(e.description)}</span>
                    <span style="text-align:right;">${castka}</span>
                </div>
            </div>`;
        }).join('');
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

    const tbody = document.getElementById('balancesBody');
    tbody.innerHTML = res.data.map(b => `
        <tr>
            <td class="fw-semi">${escapeHtml(b.name).replace(' ', '<br>')}</td>
            <td class="text-right">${formatMoney(b.paid)}</td>
            <td class="text-right">${formatMoney(b.share)}</td>
            <td class="text-right fw-bold ${b.balance >= 0 ? 'balance-positive' : 'balance-negative'}">
                ${(b.balance >= 0 ? '+' : '') + formatMoney(b.balance)}
            </td>
        </tr>
    `).join('');
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
        container.innerHTML = '<div class="empty-state"><p>Všechny účty jsou vyrovnané!</p></div>';
    } else {
        container.innerHTML = settlements.map(s => {
            const settledClass = s.settled ? 'opacity: 0.4; text-decoration: line-through;' : '';
            const settledBtnText = s.settled ? 'Zrušit' : 'Vyrovnáno';
            const settledBtnClass = s.settled ? 'btn-outline' : 'btn-success';
            return `<div class="settlement-item" style="${settledClass}">
                <div style="flex: 1;">
                    <div>
                        <span class="fw-semi">${escapeHtml(s.from_name)}</span>
                        <span class="settlement-arrow">&rarr;</span>
                        <span class="fw-semi">${escapeHtml(s.to_name)}</span>
                    </div>
                    <div class="mt-1">
                        <span class="fw-bold text-accent">${formatMoney(s.amount)}</span>
                        <span class="text-muted text-sm" style="margin-left: 8px;">(${formatMoney(s.amount_czk, 'CZK')})</span>
                    </div>
                </div>
                <button class="btn ${settledBtnClass} btn-sm" onclick="toggleSettle(${s.from_id}, ${s.to_id}, ${s.settled ? 0 : 1})">${settledBtnText}</button>
            </div>`;
        }).join('');
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
