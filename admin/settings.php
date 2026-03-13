<?php
/**
 * Admin – nastavení aplikace, itinerář, checklist
 */

require_once __DIR__ . '/../functions.php';
requireAdmin();

$db = getDB();
$boats = getAllBoats();
$success = '';
$error = '';

// Zpracování formulářů
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $section = $_POST['section'] ?? '';

    if ($section === 'general') {
        setSetting('trip_name', trim($_POST['trip_name'] ?? ''));
        setSetting('trip_date_from', trim($_POST['trip_date_from'] ?? ''));
        setSetting('trip_date_to', trim($_POST['trip_date_to'] ?? ''));
        $success = 'Nastavení plavby bylo uloženo.';
    } elseif ($section === 'boats') {
        foreach ($boats as $b) {
            $name = trim($_POST['boat_name_' . $b['id']] ?? '');
            $desc = trim($_POST['boat_desc_' . $b['id']] ?? '');
            if ($name !== '') {
                $stmt = $db->prepare("UPDATE boats SET name = ?, description = ? WHERE id = ?");
                $stmt->execute([$name, $desc, $b['id']]);
            }
        }
        $boats = getAllBoats(); // refresh
        $success = 'Názvy lodí byly uloženy.';
    } elseif ($section === 'passwords') {
        $which = $_POST['password_type'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (strlen($newPass) < 4) {
            $error = 'Heslo musí mít alespoň 4 znaky.';
        } elseif ($newPass !== $confirmPass) {
            $error = 'Hesla se neshodují.';
        } else {
            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            if ($which === 'admin') {
                setSetting('admin_password', $hash);
                $success = 'Admin heslo bylo změněno.';
            } elseif ($which === 'member') {
                setSetting('member_password', $hash);
                $success = 'Členské heslo bylo změněno.';
            }
        }
    } elseif ($section === 'itinerary_add') {
        $stmt = $db->prepare("INSERT INTO itinerary (day_number, date, title, description, location_from, location_to, type, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            (int) ($_POST['day_number'] ?? 0),
            $_POST['date'] ?: null,
            trim($_POST['title'] ?? ''),
            trim($_POST['description'] ?? ''),
            trim($_POST['location_from'] ?? ''),
            trim($_POST['location_to'] ?? ''),
            $_POST['type'] ?? 'sailing',
            (int) ($_POST['sort_order'] ?? 0),
        ]);
        $success = 'Den itineráře byl přidán.';
    } elseif ($section === 'itinerary_edit') {
        $stmt = $db->prepare("UPDATE itinerary SET day_number = ?, date = ?, title = ?, description = ?, location_from = ?, location_to = ?, type = ?, sort_order = ? WHERE id = ?");
        $stmt->execute([
            (int) ($_POST['day_number'] ?? 0),
            $_POST['date'] ?: null,
            trim($_POST['title'] ?? ''),
            trim($_POST['description'] ?? ''),
            trim($_POST['location_from'] ?? ''),
            trim($_POST['location_to'] ?? ''),
            $_POST['type'] ?? 'sailing',
            (int) ($_POST['sort_order'] ?? 0),
            (int) ($_POST['item_id'] ?? 0),
        ]);
        $success = 'Den itineráře byl upraven.';
    } elseif ($section === 'itinerary_delete') {
        $db->prepare("DELETE FROM itinerary WHERE id = ?")->execute([(int) ($_POST['item_id'] ?? 0)]);
        $success = 'Den itineráře byl smazán.';
    } elseif ($section === 'checklist_add') {
        $stmt = $db->prepare("INSERT INTO checklist (category, item_name, description, sort_order) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $_POST['category'] ?? 'doporucene',
            trim($_POST['item_name'] ?? ''),
            trim($_POST['description'] ?? ''),
            (int) ($_POST['sort_order'] ?? 0),
        ]);
        $success = 'Položka checklistu byla přidána.';
    } elseif ($section === 'checklist_delete') {
        $db->prepare("DELETE FROM checklist WHERE id = ?")->execute([(int) ($_POST['item_id'] ?? 0)]);
        $success = 'Položka checklistu byla smazána.';
    }
}

// Načtení dat
$tripName = getSetting('trip_name', '');
$tripDateFrom = getSetting('trip_date_from', '');
$tripDateTo = getSetting('trip_date_to', '');
$itinerary = $db->query("SELECT * FROM itinerary ORDER BY sort_order, day_number")->fetchAll();
$checklist = $db->query("SELECT * FROM checklist ORDER BY sort_order, id")->fetchAll();

renderHeader('Nastavení', 'admin');
?>

<h1 class="page-title">&#9881; Nastavení</h1>

<div class="admin-nav">
    <a href="/admin/index.php" class="btn btn-outline btn-sm">Dashboard</a>
    <a href="/admin/users.php" class="btn btn-outline btn-sm">Uživatelé</a>
    <a href="/admin/settings.php" class="btn btn-primary btn-sm active">Nastavení</a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?><button class="alert-close" onclick="this.parentElement.remove()">&times;</button></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?><button class="alert-close" onclick="this.parentElement.remove()">&times;</button></div>
<?php endif; ?>

<!-- Nastavení plavby -->
<div class="card">
    <div class="card-header">Nastavení plavby</div>
    <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="section" value="general">
        <div class="form-group">
            <label class="form-label">Název plavby</label>
            <input type="text" name="trip_name" class="form-control" value="<?= e($tripName) ?>">
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Datum začátku</label>
                <input type="date" name="trip_date_from" class="form-control" value="<?= e($tripDateFrom) ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Datum konce</label>
                <input type="date" name="trip_date_to" class="form-control" value="<?= e($tripDateTo) ?>">
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Uložit nastavení</button>
    </form>
</div>

<!-- Názvy lodí -->
<div class="card">
    <div class="card-header">Lodě</div>
    <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="section" value="boats">
        <?php foreach ($boats as $b): ?>
            <div class="form-row mb-2">
                <div class="form-group">
                    <label class="form-label">Loď <?= $b['id'] ?> – název</label>
                    <input type="text" name="boat_name_<?= $b['id'] ?>" class="form-control" value="<?= e($b['name']) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Popis</label>
                    <input type="text" name="boat_desc_<?= $b['id'] ?>" class="form-control" value="<?= e($b['description'] ?? '') ?>">
                </div>
            </div>
        <?php endforeach; ?>
        <button type="submit" class="btn btn-primary">Uložit lodě</button>
    </form>
</div>

<!-- Hesla -->
<div class="card">
    <div class="card-header">Změna hesel</div>
    <div class="form-row">
        <div>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="section" value="passwords">
                <input type="hidden" name="password_type" value="member">
                <div class="form-group">
                    <label class="form-label">Nové členské heslo</label>
                    <input type="password" name="new_password" class="form-control" minlength="4" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Potvrdit heslo</label>
                    <input type="password" name="confirm_password" class="form-control" minlength="4" required>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Změnit členské heslo</button>
            </form>
        </div>
        <div>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="section" value="passwords">
                <input type="hidden" name="password_type" value="admin">
                <div class="form-group">
                    <label class="form-label">Nové admin heslo</label>
                    <input type="password" name="new_password" class="form-control" minlength="4" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Potvrdit heslo</label>
                    <input type="password" name="confirm_password" class="form-control" minlength="4" required>
                </div>
                <button type="submit" class="btn btn-accent btn-sm">Změnit admin heslo</button>
            </form>
        </div>
    </div>
</div>

<!-- Itinerář -->
<div class="card" id="itinerary">
    <div class="card-header">Itinerář</div>

    <form method="POST" class="mb-2" style="background: var(--gray-50); padding: 16px; border-radius: 8px;">
        <?= csrfField() ?>
        <input type="hidden" name="section" value="itinerary_add">
        <p class="fw-semi mb-1">Přidat den:</p>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Den č.</label>
                <input type="number" name="day_number" class="form-control" min="0" required>
            </div>
            <div class="form-group">
                <label class="form-label">Datum</label>
                <input type="date" name="date" class="form-control">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Název / nadpis</label>
            <input type="text" name="title" class="form-control" required placeholder="Např. Vyplutí z mariny">
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Odkud</label>
                <input type="text" name="location_from" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">Kam</label>
                <input type="text" name="location_to" class="form-control">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Typ</label>
                <select name="type" class="form-control">
                    <option value="car">Auto</option>
                    <option value="sailing" selected>Plavba</option>
                    <option value="port">Přístav</option>
                    <option value="other">Jiný</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Pořadí řazení</label>
                <input type="number" name="sort_order" class="form-control" value="0">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Popis</label>
            <textarea name="description" class="form-control" rows="2"></textarea>
        </div>
        <button type="submit" class="btn btn-success btn-sm">Přidat den</button>
    </form>

    <?php if (!empty($itinerary)): ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Den</th>
                        <th>Datum</th>
                        <th>Název</th>
                        <th>Trasa</th>
                        <th>Typ</th>
                        <th>Akce</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($itinerary as $it): ?>
                        <tr>
                            <td><?= $it['day_number'] ?></td>
                            <td><?= $it['date'] ? formatDate($it['date']) : '–' ?></td>
                            <td class="fw-semi"><?= e($it['title']) ?></td>
                            <td><?= e($it['location_from'] ?? '') ?> → <?= e($it['location_to'] ?? '') ?></td>
                            <td><span class="badge badge-gray"><?= e($it['type']) ?></span></td>
                            <td style="white-space:nowrap;">
                                <button type="button" class="btn btn-outline btn-sm"
                                    onclick="openItineraryEdit(<?= htmlspecialchars(json_encode($it), ENT_QUOTES) ?>)">Upravit</button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Smazat tento den?')">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="section" value="itinerary_delete">
                                    <input type="hidden" name="item_id" value="<?= $it['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Smazat</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <p class="text-muted text-center mt-2">Zatím žádné dny v itineráři.</p>
    <?php endif; ?>
</div>

<!-- Checklist -->
<div class="card" id="checklist">
    <div class="card-header">Checklist – Co s sebou</div>

    <form method="POST" class="mb-2" style="background: var(--gray-50); padding: 16px; border-radius: 8px;">
        <?= csrfField() ?>
        <input type="hidden" name="section" value="checklist_add">
        <p class="fw-semi mb-1">Přidat položku:</p>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Název</label>
                <input type="text" name="item_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Kategorie</label>
                <select name="category" class="form-control">
                    <option value="povinne">Povinné</option>
                    <option value="obleceni">Oblečení</option>
                    <option value="vybaveni">Vybavení</option>
                    <option value="doporucene" selected>Doporučené</option>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Popis</label>
                <input type="text" name="description" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">Pořadí</label>
                <input type="number" name="sort_order" class="form-control" value="0">
            </div>
        </div>
        <button type="submit" class="btn btn-success btn-sm">Přidat položku</button>
    </form>

    <?php if (!empty($checklist)): ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Kategorie</th>
                        <th>Název</th>
                        <th>Popis</th>
                        <th>Akce</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $categoryNames = ['povinne' => 'Povinné', 'obleceni' => 'Oblečení', 'vybaveni' => 'Vybavení', 'doporucene' => 'Doporučené'];
                    foreach ($checklist as $cl):
                    ?>
                        <tr>
                            <td><span class="badge badge-accent"><?= e($categoryNames[$cl['category']] ?? $cl['category']) ?></span></td>
                            <td class="fw-semi"><?= e($cl['item_name']) ?></td>
                            <td class="text-sm text-muted"><?= e($cl['description'] ?? '') ?></td>
                            <td>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Smazat položku?')">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="section" value="checklist_delete">
                                    <input type="hidden" name="item_id" value="<?= $cl['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Smazat</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Upravit den itineráře -->
<div class="modal-overlay" id="itineraryEditModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
    <div class="modal" style="background:#fff; border-radius:12px; padding:24px; max-width:560px; width:100%; margin:20px; max-height:90vh; overflow-y:auto;">
        <div class="modal-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h3 style="margin:0; color:var(--primary);">Upravit den itineráře</h3>
            <button onclick="closeItineraryEdit()" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:#718096;">&times;</button>
        </div>
        <form method="POST" id="itineraryEditForm">
            <?= csrfField() ?>
            <input type="hidden" name="section" value="itinerary_edit">
            <input type="hidden" name="item_id" id="edit_item_id">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Den č.</label>
                    <input type="number" name="day_number" id="edit_day_number" class="form-control" min="0" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Datum</label>
                    <input type="date" name="date" id="edit_date" class="form-control">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Název / nadpis</label>
                <input type="text" name="title" id="edit_title" class="form-control" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Odkud</label>
                    <input type="text" name="location_from" id="edit_location_from" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Kam</label>
                    <input type="text" name="location_to" id="edit_location_to" class="form-control">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Typ</label>
                    <select name="type" id="edit_type" class="form-control">
                        <option value="car">Auto</option>
                        <option value="sailing">Plavba</option>
                        <option value="port">Přístav</option>
                        <option value="other">Jiný</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Pořadí řazení</label>
                    <input type="number" name="sort_order" id="edit_sort_order" class="form-control">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Popis</label>
                <textarea name="description" id="edit_description" class="form-control" rows="2"></textarea>
            </div>
            <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:8px;">
                <button type="button" class="btn btn-outline" onclick="closeItineraryEdit()">Zrušit</button>
                <button type="submit" class="btn btn-primary">Uložit změny</button>
            </div>
        </form>
    </div>
</div>

<script>
function openItineraryEdit(it) {
    document.getElementById('edit_item_id').value       = it.id;
    document.getElementById('edit_day_number').value    = it.day_number;
    document.getElementById('edit_date').value          = it.date || '';
    document.getElementById('edit_title').value         = it.title;
    document.getElementById('edit_location_from').value = it.location_from || '';
    document.getElementById('edit_location_to').value   = it.location_to || '';
    document.getElementById('edit_type').value          = it.type;
    document.getElementById('edit_sort_order').value    = it.sort_order;
    document.getElementById('edit_description').value   = it.description || '';
    document.getElementById('itineraryEditModal').style.display = 'flex';
}
function closeItineraryEdit() {
    document.getElementById('itineraryEditModal').style.display = 'none';
}
document.getElementById('itineraryEditModal').addEventListener('click', function(e) {
    if (e.target === this) closeItineraryEdit();
});
</script>

<?php renderFooter(); ?>
