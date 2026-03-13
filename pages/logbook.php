<?php
/**
 * Deník plavby – záznamy po dnech, AJAX
 */

require_once __DIR__ . '/../functions.php';
requireLogin();

$boats = getAllBoats();
$currentBoatId = currentBoatId() ?? ($boats[0]['id'] ?? null);

renderHeader('Deník plavby', 'logbook');
?>

<div class="d-flex-between mb-2">
    <h1 class="page-title" style="margin-bottom: 0;">&#128214; Deník plavby</h1>
    <button class="btn btn-success" onclick="openAddLogModal()">+ Přidat zápis</button>
</div>

<!-- Záložky lodí -->
<div class="tabs">
    <?php foreach ($boats as $b): ?>
        <button class="tab-btn boat<?= $b['id'] ?> <?= $b['id'] == $currentBoatId ? 'active' : '' ?>"
                data-tab-group="logbook" data-tab-id="logboat-<?= $b['id'] ?>"
                onclick="switchLogBoat(<?= $b['id'] ?>)">
            <?= e($b['name']) ?>
        </button>
    <?php endforeach; ?>
</div>

<!-- Statistiky -->
<div class="card-grid" style="grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));">
    <div class="stat-card boat1">
        <div class="stat-card-value" id="stat-total-nm">–</div>
        <div class="stat-card-label">Celkem NM</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-value" id="stat-avg-nm">–</div>
        <div class="stat-card-label">Průměr/den</div>
    </div>
    <div class="stat-card accent">
        <div class="stat-card-value" id="stat-max-nm">–</div>
        <div class="stat-card-label">Nejdelší etapa</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-value" id="stat-days">–</div>
        <div class="stat-card-label">Dní na moři</div>
    </div>
</div>

<!-- Tabulka -->
<div class="card" id="logbookCard" style="position: relative;">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Odkud</th>
                    <th>Kam</th>
                    <th>NM</th>
                    <th>Akce</th>
                </tr>
            </thead>
            <tbody id="logbookBody">
                <tr><td colspan="5" class="text-center text-muted">Načítám...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="logModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title" id="logModalTitle">Přidat zápis</h3>
            <button class="modal-close" onclick="closeModal('logModal')">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="log-id">
            <div class="form-group">
                <label class="form-label">Datum *</label>
                <input type="date" id="log-date" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Námořní míle *</label>
                <input type="number" id="log-nm" class="form-control" step="0.1" min="0">
            </div>
            <div class="form-group">
                <label class="form-label">Odkud *</label>
                <input type="text" id="log-from" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Kam *</label>
                <input type="text" id="log-to" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Poznámka</label>
                <textarea id="log-note" class="form-control" rows="3" placeholder="Zážitky, počasí, zajímavosti..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-danger btn-sm" id="logDeleteBtn" onclick="deleteLog()" style="display: none; margin-right: auto;">Smazat</button>
            <button class="btn btn-outline" onclick="closeModal('logModal')">Zrušit</button>
            <button class="btn btn-primary" onclick="saveLog()">Uložit</button>
        </div>
    </div>
</div>

<script>
let currentLogBoat = <?= json_encode($currentBoatId) ?>;
let editingLogId = null;

function switchLogBoat(boatId) {
    currentLogBoat = boatId;
    document.querySelectorAll('[data-tab-group="logbook"]').forEach(b => b.classList.remove('active'));
    document.querySelector('[data-tab-id="logboat-' + boatId + '"]').classList.add('active');
    loadLogbook();
}

async function loadLogbook() {
    const container = document.getElementById('logbookCard');
    showLoading(container);

    const res = await apiCall('/api/logbook.php?action=list&boat_id=' + currentLogBoat);
    hideLoading(container);

    if (!res.success) { showToast(res.error || 'Chyba.', 'error'); return; }

    const entries = res.data.entries;
    const stats = res.data.stats;

    // Statistiky
    document.getElementById('stat-total-nm').textContent = parseFloat(stats.total_nm).toFixed(1);
    document.getElementById('stat-avg-nm').textContent = parseFloat(stats.avg_nm).toFixed(1);
    document.getElementById('stat-max-nm').textContent = parseFloat(stats.max_nm).toFixed(1);
    document.getElementById('stat-days').textContent = stats.total_days;

    const tbody = document.getElementById('logbookBody');
    if (entries.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Žádné záznamy.</td></tr>';
    } else {
        tbody.innerHTML = entries.map(e => `
            <tr>
                <td class="fw-semi">${formatDate(e.date)}</td>
                <td>${escapeHtml(e.location_from)}</td>
                <td>${escapeHtml(e.location_to)}</td>
                <td class="fw-bold">${parseFloat(e.nautical_miles).toFixed(1)}</td>
                <td>
                    <button class="btn btn-outline btn-sm" onclick='editLog(${JSON.stringify(e)})'>&#9998;</button>
                </td>
            </tr>
            ${e.note ? `<tr><td colspan="5" class="text-sm text-muted" style="border-top: none; padding-top: 0;">&#128172; ${escapeHtml(e.note)}</td></tr>` : ''}
        `).join('');
    }
}

function openAddLogModal() {
    editingLogId = null;
    document.getElementById('logModalTitle').textContent = 'Přidat zápis';
    document.getElementById('log-id').value = '';
    document.getElementById('log-date').value = new Date().toISOString().slice(0, 10);
    document.getElementById('log-nm').value = '';
    document.getElementById('log-from').value = '';
    document.getElementById('log-to').value = '';
    document.getElementById('log-note').value = '';
    document.getElementById('logDeleteBtn').style.display = 'none';
    openModal('logModal');
}

function editLog(entry) {
    editingLogId = entry.id;
    document.getElementById('logModalTitle').textContent = 'Upravit zápis';
    document.getElementById('log-id').value = entry.id;
    document.getElementById('log-date').value = entry.date;
    document.getElementById('log-nm').value = entry.nautical_miles;
    document.getElementById('log-from').value = entry.location_from;
    document.getElementById('log-to').value = entry.location_to;
    document.getElementById('log-note').value = entry.note || '';
    document.getElementById('logDeleteBtn').style.display = 'block';
    openModal('logModal');
}

async function saveLog() {
    const data = {
        boat_id: currentLogBoat,
        date: document.getElementById('log-date').value,
        location_from: document.getElementById('log-from').value,
        location_to: document.getElementById('log-to').value,
        nautical_miles: document.getElementById('log-nm').value || 0,
        note: document.getElementById('log-note').value,
    };

    let action = 'add';
    if (editingLogId) {
        data.id = editingLogId;
        action = 'edit';
    }

    const res = await apiCall('/api/logbook.php?action=' + action, 'POST', data);
    if (res.success) {
        closeModal('logModal');
        showToast(editingLogId ? 'Zápis upraven.' : 'Zápis přidán.', 'success');
        loadLogbook();
    } else {
        showToast(res.error || 'Chyba.', 'error');
    }
}

async function deleteLog() {
    if (!editingLogId || !confirm('Opravdu smazat tento zápis?')) return;
    const res = await apiCall('/api/logbook.php?action=delete', 'POST', { id: editingLogId });
    if (res.success) {
        closeModal('logModal');
        showToast('Zápis smazán.', 'success');
        loadLogbook();
    }
}

document.addEventListener('DOMContentLoaded', loadLogbook);
</script>

<?php renderFooter(); ?>
