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
    <h1 class="page-title" style="margin-bottom: 0;">
        <i data-lucide="book-open" class="page-title-icon"></i>Deník plavby
    </h1>
    <button class="btn btn-success desktop-only-btn" onclick="openAddLogModal()">
        <i data-lucide="plus" style="width:16px;height:16px;"></i> Přidat zápis
    </button>
</div>

<!-- Záložky lodí s ikonou -->
<div class="tabs">
    <?php foreach ($boats as $b): ?>
        <button class="tab-btn boat<?= $b['id'] ?> <?= $b['id'] == $currentBoatId ? 'active' : '' ?>"
                data-tab-group="logbook" data-tab-id="logboat-<?= $b['id'] ?>"
                onclick="switchLogBoat(<?= $b['id'] ?>)">
            <i data-lucide="sailboat" style="width:14px;height:14px;vertical-align:middle;margin-right:4px;"></i><?= e($b['name']) ?>
        </button>
    <?php endforeach; ?>
</div>

<!-- Statistiky s ikonami -->
<div class="logbook-stats">
    <div class="stat-card brand">
        <div class="stat-card-inner">
            <div class="stat-card-icon-wrap brand"><i data-lucide="navigation" style="width:20px;height:20px;"></i></div>
            <div class="stat-card-info">
                <div class="stat-card-value" id="stat-total-nm">–</div>
                <div class="stat-card-label">Celkem NM</div>
            </div>
        </div>
    </div>
    <div class="stat-card info">
        <div class="stat-card-inner">
            <div class="stat-card-icon-wrap info"><i data-lucide="trending-up" style="width:20px;height:20px;"></i></div>
            <div class="stat-card-info">
                <div class="stat-card-value" id="stat-avg-nm">–</div>
                <div class="stat-card-label">Průměr/den</div>
            </div>
        </div>
    </div>
    <div class="stat-card accent">
        <div class="stat-card-inner">
            <div class="stat-card-icon-wrap accent"><i data-lucide="zap" style="width:20px;height:20px;"></i></div>
            <div class="stat-card-info">
                <div class="stat-card-value" id="stat-max-nm">–</div>
                <div class="stat-card-label">Nejdelší etapa</div>
            </div>
        </div>
    </div>
    <div class="stat-card success">
        <div class="stat-card-inner">
            <div class="stat-card-icon-wrap success"><i data-lucide="calendar" style="width:20px;height:20px;"></i></div>
            <div class="stat-card-info">
                <div class="stat-card-value" id="stat-days">–</div>
                <div class="stat-card-label">Dní na moři</div>
            </div>
        </div>
    </div>
</div>

<!-- Log entry cards -->
<div id="logbookCard" style="position:relative;">
    <div id="logbookBody"><p class="text-center text-muted" style="padding:24px;">Načítám...</p></div>
</div>

<!-- FAB pro mobil -->
<button class="fab" onclick="openAddLogModal()" title="Přidat zápis">
    <i data-lucide="plus" style="width:24px;height:24px;"></i>
</button>

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

    document.getElementById('stat-total-nm').textContent = parseFloat(stats.total_nm).toFixed(1);
    document.getElementById('stat-avg-nm').textContent = parseFloat(stats.avg_nm).toFixed(1);
    document.getElementById('stat-max-nm').textContent = parseFloat(stats.max_nm).toFixed(1);
    document.getElementById('stat-days').textContent = stats.total_days;

    const body = document.getElementById('logbookBody');
    if (entries.length === 0) {
        body.innerHTML = '<div class="empty-state"><i data-lucide="book-open" style="width:36px;height:36px;color:var(--color-text-tertiary);margin-bottom:8px;"></i><p>Žádné záznamy.</p></div>';
        lucide.createIcons();
        return;
    }

    body.innerHTML = entries.map(e => {
        const d = new Date(e.date);
        const day = d.getDate();
        const month = d.toLocaleString('cs', { month: 'short' });
        return `<div class="log-entry-card">
            <div class="log-entry-top">
                <div class="log-entry-date-col">
                    <div class="log-entry-day">${day}</div>
                    <div class="log-entry-month">${month}</div>
                </div>
                <div class="log-entry-main">
                    <div class="log-entry-route">
                        <i data-lucide="anchor" style="width:13px;height:13px;vertical-align:middle;color:var(--color-brand);"></i>
                        ${escapeHtml(e.location_from)} → ${escapeHtml(e.location_to)}
                    </div>
                    <div class="log-entry-meta">
                        <span class="log-entry-nm">
                            <i data-lucide="navigation" style="width:11px;height:11px;"></i>
                            ${parseFloat(e.nautical_miles).toFixed(1)} NM
                        </span>
                    </div>
                    ${e.note ? `<div class="log-entry-note">${escapeHtml(e.note)}</div>` : ''}
                </div>
                <div style="display:flex;gap:4px;flex-shrink:0;">
                    <button class="icon-btn" onclick='editLog(${JSON.stringify(e)})' title="Upravit">
                        <i data-lucide="pencil" style="width:14px;height:14px;"></i>
                    </button>
                </div>
            </div>
        </div>`;
    }).join('');
    lucide.createIcons();
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

function deleteLog() {
    if (!editingLogId) return;
    confirmAction('Opravdu smazat tento zápis?', async function() {
        const res = await apiCall('/api/logbook.php?action=delete', 'POST', { id: editingLogId });
        if (res.success) {
            closeModal('logModal');
            showToast('Zápis smazán.', 'success');
            loadLogbook();
        }
    });
}

document.addEventListener('DOMContentLoaded', loadLogbook);
</script>

<?php renderFooter(); ?>
