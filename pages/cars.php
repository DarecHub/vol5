<?php
/**
 * Auta – kdo s kým jede (AJAX)
 */

require_once __DIR__ . '/../functions.php';
requireLogin();

$users = getAllUsers();

renderHeader('Auta', 'cars');
?>

<div class="d-flex-between mb-2">
    <h1 class="page-title" style="margin-bottom: 0;">
        <i data-lucide="car" class="page-title-icon"></i>Auta
    </h1>
    <button class="btn btn-success desktop-only-btn" onclick="openAddCarModal()">
        <i data-lucide="plus" style="width:16px;height:16px;"></i> Přidat auto
    </button>
</div>

<!-- Seznam aut -->
<div id="carsContainer" class="card-grid">
    <div class="text-center text-muted">Načítám...</div>
</div>

<!-- Nepřiřazení -->
<div class="card mt-2" id="unassignedCard" style="display: none;">
    <div class="card-header" style="display:flex;align-items:center;gap:8px;">
        <i data-lucide="user-x" style="width:16px;height:16px;color:var(--color-danger);"></i>
        Bez přiřazeného auta
    </div>
    <div id="unassignedList" style="display:flex;flex-wrap:wrap;gap:8px;padding-top:4px;"></div>
</div>

<!-- FAB pro mobil -->
<button class="fab" onclick="openAddCarModal()" title="Přidat auto">
    <i data-lucide="plus" style="width:24px;height:24px;"></i>
</button>

<!-- Modal: Přidat auto -->
<div class="modal-overlay" id="carModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Přidat auto</h3>
            <button class="modal-close" onclick="closeModal('carModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Řidič *</label>
                <select id="car-driver" class="form-control">
                    <option value="">– vyberte –</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= e($u['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Název auta</label>
                    <input type="text" id="car-name" class="form-control" placeholder="Např. Škoda Octavia">
                </div>
                <div class="form-group">
                    <label class="form-label">Počet míst</label>
                    <input type="number" id="car-seats" class="form-control" value="5" min="2" max="9">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Poznámka</label>
                <input type="text" id="car-note" class="form-control">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('carModal')">Zrušit</button>
            <button class="btn btn-primary" onclick="saveCar()">Přidat</button>
        </div>
    </div>
</div>

<script>
async function loadCars() {
    const res = await apiCall('/api/cars.php?action=list');
    if (!res.success) { showToast(res.error || 'Chyba.', 'error'); return; }

    const container = document.getElementById('carsContainer');
    const cars = res.data.cars;
    const unassigned = res.data.unassigned;

    if (cars.length === 0) {
        container.innerHTML = '<div class="empty-state"><i data-lucide="car" style="width:40px;height:40px;color:var(--color-text-tertiary);margin-bottom:8px;"></i><p>Zatím žádná auta. Přidejte první.</p></div>';
        lucide.createIcons();
    } else {
        container.innerHTML = cars.map(car => {
            const occupied = car.passengers.length + 1;
            const freeSeats = car.seats - occupied;
            const pct = Math.round(occupied / car.seats * 100);
            const full = freeSeats <= 0;

            const driverInitials = car.driver_name.trim().split(' ').map(p=>p[0]).join('').toUpperCase().slice(0,2);

            return `<div class="card car-card">
                <div class="car-card-header">
                    <span class="car-card-icon">
                        <i data-lucide="car" style="width:20px;height:20px;"></i>
                    </span>
                    <div class="car-card-info">
                        <div class="car-card-title">${escapeHtml(car.car_name || 'Auto')}</div>
                        <div class="car-card-meta">${occupied}/${car.seats} míst · ${full ? '<span class="full">plné</span>' : freeSeats + ' volných'}</div>
                    </div>
                    <button class="icon-btn icon-btn-danger" onclick="deleteCar(${car.id})" title="Smazat auto">
                        <i data-lucide="trash-2" style="width:14px;height:14px;"></i>
                    </button>
                </div>
                <div class="progress-bar-wrap mb-1">
                    <div class="progress-bar-fill ${full ? 'danger' : 'primary'}" style="width:${pct}%;"></div>
                </div>
                <div class="car-card-driver">
                    <i data-lucide="steering-wheel" style="width:15px;height:15px;color:var(--color-brand);flex-shrink:0;"></i>
                    <span class="avatar avatar-sm avatar-accent">${escapeHtml(driverInitials)}</span>
                    <span class="car-card-driver-name">${escapeHtml(car.driver_name)}</span>
                    <span class="badge badge-accent ml-auto">Řidič</span>
                </div>
                ${car.passengers.length > 0 ? `
                    <div class="car-card-passengers">
                        ${car.passengers.map(p => {
                            const pi = p.name.trim().split(' ').map(x=>x[0]).join('').toUpperCase().slice(0,2);
                            return `<div class="car-card-passenger">
                                <span class="avatar avatar-sm avatar-primary">${escapeHtml(pi)}</span>
                                <span class="car-card-passenger-name">${escapeHtml(p.name)}</span>
                                <button class="icon-btn" onclick="removePassenger(${p.passenger_id})" title="Odebrat">
                                    <i data-lucide="x" style="width:12px;height:12px;"></i>
                                </button>
                            </div>`;
                        }).join('')}
                    </div>
                ` : ''}
                ${!full && unassigned.length > 0 ? `
                    <div class="car-card-add-row">
                        <select class="form-control" id="add-passenger-${car.id}" style="flex:1;">
                            <option value="">Přidat spolujezdce...</option>
                            ${unassigned.map(u => `<option value="${u.id}">${escapeHtml(u.name)}</option>`).join('')}
                        </select>
                        <button class="btn btn-success btn-sm" onclick="addPassenger(${car.id})">
                            <i data-lucide="plus" style="width:14px;height:14px;"></i>
                        </button>
                    </div>
                ` : ''}
                ${car.note ? `<div class="car-card-note"><i data-lucide="message-circle" style="width:12px;height:12px;flex-shrink:0;"></i>${escapeHtml(car.note)}</div>` : ''}
            </div>`;
        }).join('');
        lucide.createIcons();
    }

    // Nepřiřazení
    const unassignedCard = document.getElementById('unassignedCard');
    if (unassigned.length > 0) {
        unassignedCard.style.display = 'block';
        document.getElementById('unassignedList').innerHTML = unassigned.map(u => {
            const ui = u.name.trim().split(' ').map(p=>p[0]).join('').toUpperCase().slice(0,2);
            return `<div class="car-card-passenger">
                <span class="avatar avatar-sm avatar-gray">${escapeHtml(ui)}</span>
                <span class="car-card-passenger-name">${escapeHtml(u.name)}</span>
            </div>`;
        }).join('');
    } else {
        unassignedCard.style.display = 'none';
    }
}

function openAddCarModal() {
    document.getElementById('car-driver').value = '';
    document.getElementById('car-name').value = '';
    document.getElementById('car-seats').value = '5';
    document.getElementById('car-note').value = '';
    openModal('carModal');
}

async function saveCar() {
    const driverId = document.getElementById('car-driver').value;
    if (!driverId) { showToast('Vyberte řidiče.', 'error'); return; }

    const res = await apiCall('/api/cars.php?action=add_car', 'POST', {
        driver_user_id: driverId,
        car_name: document.getElementById('car-name').value,
        seats: document.getElementById('car-seats').value,
        note: document.getElementById('car-note').value,
    });

    if (res.success) {
        closeModal('carModal');
        showToast('Auto přidáno.', 'success');
        loadCars();
    } else {
        showToast(res.error || 'Chyba.', 'error');
    }
}

function deleteCar(id) {
    confirmAction('Opravdu smazat toto auto?', async function() {
        const res = await apiCall('/api/cars.php?action=delete_car', 'POST', { id: id });
        if (res.success) {
            showToast('Auto smazáno.', 'success');
            loadCars();
        }
    });
}

async function addPassenger(carId) {
    const select = document.getElementById('add-passenger-' + carId);
    const userId = select.value;
    if (!userId) return;

    const res = await apiCall('/api/cars.php?action=add_passenger', 'POST', {
        car_id: carId,
        user_id: userId,
    });

    if (res.success) {
        showToast('Spolujezdec přidán.', 'success');
        loadCars();
    } else {
        showToast(res.error || 'Chyba.', 'error');
    }
}

async function removePassenger(passengerId) {
    const res = await apiCall('/api/cars.php?action=remove_passenger', 'POST', {
        passenger_id: passengerId,
    });

    if (res.success) {
        loadCars();
    }
}

document.addEventListener('DOMContentLoaded', loadCars);
</script>

<?php renderFooter(); ?>
