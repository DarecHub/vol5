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
    <h1 class="page-title" style="margin-bottom: 0;">&#128663; Auta</h1>
    <button class="btn btn-success" onclick="openAddCarModal()">+ Přidat auto</button>
</div>

<!-- Seznam aut -->
<div id="carsContainer" class="card-grid">
    <div class="text-center text-muted">Načítám...</div>
</div>

<!-- Nepřiřazení -->
<div class="card mt-2" id="unassignedCard" style="display: none;">
    <div class="card-header">Bez přiřazeného auta</div>
    <div id="unassignedList"></div>
</div>

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
        container.innerHTML = '<div class="empty-state"><div class="empty-state-icon">&#128663;</div><p>Zatím žádná auta. Přidejte první.</p></div>';
    } else {
        container.innerHTML = cars.map(car => {
            const passengerCount = car.passengers.length + 1; // +řidič
            const freeSeats = car.seats - passengerCount;

            return `<div class="card" style="margin-bottom: 0;">
                <div class="d-flex-between mb-1">
                    <div>
                        <span class="fw-bold text-lg">${escapeHtml(car.car_name || 'Auto')}</span>
                        <span class="badge badge-gray">${passengerCount}/${car.seats} míst</span>
                    </div>
                    <button class="btn btn-danger btn-sm" onclick="deleteCar(${car.id})" title="Smazat auto">&#10005;</button>
                </div>
                <div class="mb-1">
                    <span class="badge badge-accent">&#128100; Řidič: ${escapeHtml(car.driver_name)}</span>
                </div>
                ${car.passengers.length > 0 ? `
                    <div class="mb-1">
                        ${car.passengers.map(p => `
                            <div class="d-flex-between" style="padding: 4px 0; border-bottom: 1px solid var(--gray-100);">
                                <span>${escapeHtml(p.name)}</span>
                                <button class="btn btn-outline btn-sm" onclick="removePassenger(${p.passenger_id})" title="Odebrat">&#10005;</button>
                            </div>
                        `).join('')}
                    </div>
                ` : ''}
                ${freeSeats > 0 ? `
                    <div class="mt-1">
                        <select class="form-control" id="add-passenger-${car.id}" style="display: inline-block; width: auto; max-width: 180px;">
                            <option value="">Přidat spolujezdce...</option>
                            ${unassigned.map(u => `<option value="${u.id}">${escapeHtml(u.name)}</option>`).join('')}
                        </select>
                        <button class="btn btn-success btn-sm" onclick="addPassenger(${car.id})">+</button>
                    </div>
                ` : '<div class="text-sm text-muted mt-1">Auto je plné</div>'}
                ${car.note ? `<div class="text-sm text-muted mt-1">&#128172; ${escapeHtml(car.note)}</div>` : ''}
            </div>`;
        }).join('');
    }

    // Nepřiřazení
    const unassignedCard = document.getElementById('unassignedCard');
    if (unassigned.length > 0) {
        unassignedCard.style.display = 'block';
        document.getElementById('unassignedList').innerHTML = unassigned.map(u =>
            `<span class="badge badge-danger" style="margin: 2px;">${escapeHtml(u.name)}</span>`
        ).join(' ');
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

async function deleteCar(id) {
    if (!confirm('Opravdu smazat toto auto?')) return;
    const res = await apiCall('/api/cars.php?action=delete_car', 'POST', { id: id });
    if (res.success) {
        showToast('Auto smazáno.', 'success');
        loadCars();
    }
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
