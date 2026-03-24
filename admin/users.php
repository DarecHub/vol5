<?php
/**
 * Admin – správa uživatelů (CRUD)
 */

require_once __DIR__ . '/../functions.php';
requireAdmin();

$db = getDB();
$boats = getAllBoats();
$error = '';
$success = '';

// Zpracování akcí
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $boatId = (int) ($_POST['boat_id'] ?? 0);

        if ($name === '') {
            $error = 'Jméno je povinné.';
        } elseif ($boatId < 1) {
            $error = 'Vyberte loď.';
        } else {
            $stmt = $db->prepare("INSERT INTO users (name, phone, email, boat_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $phone ?: null, $email ?: null, $boatId]);
            $success = 'Uživatel "' . $name . '" byl přidán.';
        }
    } elseif ($action === 'edit') {
        $id = (int) ($_POST['user_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $boatId = (int) ($_POST['boat_id'] ?? 0);

        if ($name === '' || $id < 1 || $boatId < 1) {
            $error = 'Neplatné údaje.';
        } else {
            $stmt = $db->prepare("UPDATE users SET name = ?, phone = ?, email = ?, boat_id = ? WHERE id = ?");
            $stmt->execute([$name, $phone ?: null, $email ?: null, $boatId, $id]);
            $success = 'Uživatel byl upraven.';
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['user_id'] ?? 0);
        if ($id > 0) {
            // Kontrola zda nemá záznamy v pokladně
            $stmt = $db->prepare("SELECT COUNT(*) FROM wallet_expenses WHERE paid_by = ? OR created_by = ?");
            $stmt->execute([$id, $id]);
            $walletCount = $stmt->fetchColumn();

            if ($walletCount > 0) {
                $error = 'Uživatel má záznamy v pokladně a nelze smazat. Můžete ho přesunout.';
            } else {
                $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $success = 'Uživatel byl smazán.';
            }
        }
    }
}

$users = getAllUsers();

renderHeader('Správa uživatelů', 'admin');
?>

<h1 class="page-title"><i data-lucide="users" style="width:24px;height:24px;vertical-align:middle;margin-right:6px;color:var(--color-brand);"></i>Správa uživatelů</h1>

<div class="admin-nav">
    <a href="/admin/index.php" class="btn btn-outline btn-sm">
        <i data-lucide="layout-dashboard" style="width:14px;height:14px;"></i> Dashboard
    </a>
    <a href="/admin/users.php" class="btn btn-primary btn-sm active">
        <i data-lucide="users" style="width:14px;height:14px;"></i> Uživatelé
    </a>
    <a href="/admin/settings.php" class="btn btn-outline btn-sm">
        <i data-lucide="sliders-horizontal" style="width:14px;height:14px;"></i> Nastavení
    </a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?><button class="alert-close" onclick="this.parentElement.remove()">&times;</button></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?><button class="alert-close" onclick="this.parentElement.remove()">&times;</button></div>
<?php endif; ?>

<!-- Přidat uživatele -->
<div class="card">
    <div class="card-header">Přidat nového člena</div>
    <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="add">
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Jméno *</label>
                <input type="text" name="name" class="form-control" required placeholder="Celé jméno">
            </div>
            <div class="form-group">
                <label class="form-label">Loď *</label>
                <select name="boat_id" class="form-control" required>
                    <option value="">– vyberte –</option>
                    <?php foreach ($boats as $b): ?>
                        <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Telefon</label>
                <input type="text" name="phone" class="form-control" placeholder="+420 ...">
            </div>
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" placeholder="email@example.com">
            </div>
        </div>
        <button type="submit" class="btn btn-success">Přidat člena</button>
    </form>
</div>

<!-- Seznam uživatelů -->
<div class="card">
    <div class="card-header">Členové posádky (<?= count($users) ?>)</div>
    <?php if (empty($users)): ?>
        <div class="empty-state">
            <i data-lucide="users" style="width:40px;height:40px;color:var(--color-text-tertiary);margin-bottom:8px;"></i>
            <p>Zatím nejsou žádní členové. Přidejte je výše.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Jméno</th>
                        <th>Loď</th>
                        <th>Telefon</th>
                        <th>Email</th>
                        <th>Akce</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td class="fw-semi"><?= e($u['name']) ?></td>
                            <td>
                                <span class="badge <?= $u['boat_id'] == 1 ? 'badge-boat1' : 'badge-boat2' ?>">
                                    <?= e($u['boat_name'] ?? 'Loď ' . $u['boat_id']) ?>
                                </span>
                            </td>
                            <td><?= $u['phone'] ? e($u['phone']) : '<span class="text-muted">–</span>' ?></td>
                            <td><?= $u['email'] ? e($u['email']) : '<span class="text-muted">–</span>' ?></td>
                            <td>
                                <button class="btn btn-outline btn-sm" onclick="editUser(<?= htmlspecialchars(json_encode($u, JSON_HEX_APOS | JSON_HEX_QUOT)) ?>)">Upravit</button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Opravdu smazat uživatele <?= e($u['name']) ?>?')">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
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

<!-- Modal pro editaci -->
<div class="modal-overlay" id="editUserModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Upravit uživatele</h3>
            <button class="modal-close" onclick="closeModal('editUserModal')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="user_id" id="edit-user-id">
                <div class="form-group">
                    <label class="form-label">Jméno</label>
                    <input type="text" name="name" id="edit-name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Loď</label>
                    <select name="boat_id" id="edit-boat-id" class="form-control" required>
                        <?php foreach ($boats as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Telefon</label>
                    <input type="text" name="phone" id="edit-phone" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" id="edit-email" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editUserModal')">Zrušit</button>
                <button type="submit" class="btn btn-primary">Uložit</button>
            </div>
        </form>
    </div>
</div>

<script>
function editUser(user) {
    document.getElementById('edit-user-id').value = user.id;
    document.getElementById('edit-name').value = user.name;
    document.getElementById('edit-boat-id').value = user.boat_id;
    document.getElementById('edit-phone').value = user.phone || '';
    document.getElementById('edit-email').value = user.email || '';
    openModal('editUserModal');
}
</script>

<?php renderFooter(); ?>
