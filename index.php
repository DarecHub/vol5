<?php
/**
 * Přihlašovací stránka – admin nebo člen posádky
 */

require_once __DIR__ . '/functions.php';

// Pokud je už přihlášen, přesměrovat
if (isLoggedIn()) {
    redirect('/pages/dashboard.php');
}
if (isAdmin()) {
    redirect('/admin/index.php');
}

// Kontrola instalace
$installedCheck = getSetting('installed', '0');
if ($installedCheck !== '1') {
    redirect('/install.php');
}

$error = '';
$loginType = $_POST['login_type'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    // Rate limiting – max 10 pokusů za 15 minut
    $attempts = $_SESSION['login_attempts'] ?? 0;
    $lastAttempt = $_SESSION['login_last_attempt'] ?? 0;
    if (time() - $lastAttempt > 900) {
        $_SESSION['login_attempts'] = 0;
        $attempts = 0;
    }
    if ($attempts >= 10) {
        $waitMin = ceil((900 - (time() - $lastAttempt)) / 60);
        $error = 'Příliš mnoho pokusů. Zkuste to znovu za ' . $waitMin . ' min.';
    } elseif ($loginType === 'admin') {
        // Admin přihlášení
        $password = $_POST['admin_password'] ?? '';
        $adminHash = getSetting('admin_password', '');

        if ($adminHash && password_verify($password, $adminHash)) {
            unset($_SESSION['login_attempts'], $_SESSION['login_last_attempt']);
            session_regenerate_id(true);
            $_SESSION['is_admin'] = true;
            $_SESSION['user_name'] = 'Administrátor';
            $_SESSION['last_activity'] = time();
            redirect('/admin/index.php');
        } else {
            $_SESSION['login_attempts'] = ($attempts + 1);
            $_SESSION['login_last_attempt'] = time();
            $error = 'Nesprávné admin heslo.';
        }
    } elseif ($loginType === 'member') {
        // Členské přihlášení
        $userId = (int) ($_POST['user_id'] ?? 0);
        $password = $_POST['member_password'] ?? '';
        $memberHash = getSetting('member_password', '');

        if ($userId <= 0) {
            $error = 'Vyberte své jméno.';
        } elseif (!$memberHash || !password_verify($password, $memberHash)) {
            $_SESSION['login_attempts'] = ($attempts + 1);
            $_SESSION['login_last_attempt'] = time();
            $error = 'Nesprávné heslo.';
        } else {
            $user = getUserById($userId);
            if (!$user) {
                $error = 'Uživatel nenalezen.';
            } else {
                unset($_SESSION['login_attempts'], $_SESSION['login_last_attempt']);
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['boat_id'] = $user['boat_id'];
                $_SESSION['last_activity'] = time();
                if (!empty($_POST['remember_me'])) {
                    $_SESSION['remember_me'] = true;
                    setcookie(session_name(), session_id(), time() + REMEMBER_TIMEOUT, '/', '', isset($_SERVER['HTTPS']), true);
                }
                redirect('/pages/dashboard.php');
            }
        }
    }
}

// Načíst uživatele pro dropdown
$users = [];
try {
    $db = getDB();
    $stmt = $db->query("SELECT u.id, u.name, b.name AS boat_name FROM users u LEFT JOIN boats b ON u.boat_id = b.id ORDER BY u.boat_id, u.name");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    // ticho
}

$tripName = getSetting('trip_name', 'Plavba');
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($tripName) ?></title>
    <link rel="icon" type="image/png" href="/assets/img/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <script>
    (function(){
        var t = localStorage.getItem('theme');
        if (!t) t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', t);
    })();
    </script>
    <style>
        body {
            font-family: var(--font-sans);
            background: var(--color-bg-subtle);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: var(--color-text);
        }
        .login-container {
            background: var(--color-bg);
            border-radius: var(--radius-lg);
            padding: 40px 35px;
            max-width: 420px;
            width: 100%;
            border: 1px solid var(--color-border);
        }
        .logo {
            text-align: center;
            margin-bottom: 10px;
            font-size: 3.5rem;
        }
        h1 {
            color: var(--color-text);
            text-align: center;
            margin-bottom: 28px;
            font-size: 1.5rem;
        }
        .trip-name {
            text-align: center;
            color: var(--color-brand);
            font-weight: 600;
            margin-bottom: 25px;
            font-size: 0.95rem;
        }
        .tab-buttons {
            display: flex;
            gap: 0;
            margin-bottom: 25px;
            border-radius: var(--radius);
            overflow: hidden;
            border: 1px solid var(--color-border);
        }
        .tab-btn {
            flex: 1;
            padding: 12px;
            text-align: center;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.95rem;
            background: var(--color-bg);
            color: var(--color-text-secondary);
            border: none;
            transition: all 0.2s;
            font-family: inherit;
        }
        .tab-btn.active {
            background: var(--color-brand);
            color: var(--color-brand-text);
        }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .form-group { margin-bottom: 18px; }
        label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: var(--color-text);
            font-size: 0.9rem;
        }
        select, input[type="password"] {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--color-border);
            border-radius: var(--radius);
            font-size: 1rem;
            font-family: inherit;
            transition: border-color 0.2s;
            background: var(--color-bg);
            color: var(--color-text);
        }
        select:focus, input:focus {
            outline: none;
            border-color: var(--color-brand);
            box-shadow: 0 0 0 3px var(--color-brand-subtle);
        }
        .btn {
            display: block;
            width: 100%;
            padding: 12px;
            background: var(--color-brand);
            color: var(--color-brand-text);
            border: none;
            border-radius: var(--radius);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
        }
        .btn:hover { background: var(--color-brand-hover); }
        .btn-gold {
            background: var(--color-brand);
        }
        .btn-gold:hover { background: var(--color-brand-hover); }
        .error-msg {
            background: var(--color-danger-subtle);
            border: 1px solid var(--color-danger);
            color: var(--color-danger);
            padding: 12px;
            border-radius: var(--radius);
            margin-bottom: 18px;
            text-align: center;
            font-size: 0.9rem;
        }
        .no-users {
            text-align: center;
            color: var(--color-text-secondary);
            padding: 20px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo"><img src="/assets/img/logo.png" alt="Logo" style="width: 80px; height: 80px; object-fit: contain;"></div>
        <h1>Loď vol.5 – Itálie</h1>

        <?php if ($error): ?>
            <div class="error-msg"><?= e($error) ?></div>
        <?php endif; ?>

        <div class="tab-buttons">
            <button class="tab-btn active" onclick="switchTab('member', this)">Posádka</button>
            <button class="tab-btn" onclick="switchTab('admin', this)">Admin</button>
        </div>

        <!-- Členské přihlášení -->
        <div class="tab-content active" id="tab-member">
            <?php if (empty($users)): ?>
                <div class="no-users">
                    Zatím nejsou přidáni žádní členové posádky.<br>
                    Přihlaste se jako admin a přidejte uživatele.
                </div>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="login_type" value="member">
                    <?= csrfField() ?>
                    <div class="form-group">
                        <label>Vyberte své jméno</label>
                        <select name="user_id" required>
                            <option value="">– vyberte –</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= $u['id'] ?>" <?= (isset($_POST['user_id']) && $_POST['user_id'] == $u['id']) ? 'selected' : '' ?>>
                                    <?= e($u['name']) ?> (<?= e($u['boat_name'] ?? 'bez lodi') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Heslo posádky</label>
                        <input type="password" name="member_password" required placeholder="Zadejte společné heslo">
                    </div>
                    <div class="form-group" style="margin-bottom: 14px;">
                        <label style="display:flex; align-items:center; gap:8px; font-weight:normal; cursor:pointer; color:var(--color-text-secondary);">
                            <input type="checkbox" name="remember_me" value="1" style="width:auto; accent-color:var(--color-brand);">
                            Zapamatovat si přihlášení na 7 dní
                        </label>
                    </div>
                    <button type="submit" class="btn">Přihlásit se</button>
                </form>
            <?php endif; ?>
        </div>

        <!-- Admin přihlášení -->
        <div class="tab-content" id="tab-admin">
            <form method="POST">
                <input type="hidden" name="login_type" value="admin">
                <?= csrfField() ?>
                <div class="form-group">
                    <label>Admin heslo</label>
                    <input type="password" name="admin_password" required placeholder="Zadejte admin heslo">
                </div>
                <button type="submit" class="btn btn-gold">Přihlásit jako admin</button>
            </form>
        </div>
    </div>

    <script>
    function switchTab(tab, el) {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        document.getElementById('tab-' + tab).classList.add('active');
        el.classList.add('active');
    }
    </script>
</body>
</html>
