<?php
/**
 * Sdílené PHP funkce pro celou aplikaci
 */

require_once __DIR__ . '/config.php';

// ============================================================
// BEZPEČNOST
// ============================================================

/**
 * Escapování výstupu proti XSS
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Generování CSRF tokenu
 */
function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Ověření CSRF tokenu
 */
function verifyCsrfToken(string $token): bool
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * HTML input pro CSRF token ve formulářích
 */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(generateCsrfToken()) . '">';
}

/**
 * Ověření CSRF z POST požadavku – pokud selže, ukončí skript
 */
function requireCsrf(): void
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verifyCsrfToken($token)) {
        if (isAjax()) {
            jsonResponse(false, null, 'Neplatný bezpečnostní token. Obnovte stránku.');
        }
        setFlash('error', 'Neplatný bezpečnostní token. Zkuste to znovu.');
        redirect($_SERVER['HTTP_REFERER'] ?? '/index.php');
    }
}

// ============================================================
// AUTENTIZACE
// ============================================================

/**
 * Je uživatel přihlášen jako člen?
 */
function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

/**
 * Je uživatel admin?
 */
function isAdmin(): bool
{
    return !empty($_SESSION['is_admin']);
}

/**
 * Vyžaduje přihlášení – pokud není přihlášen, přesměruje na login.
 * Pro AJAX požadavky vrátí JSON chybu místo redirect (302 není parsovatelný).
 */
function requireLogin(): void
{
    if (!isLoggedIn() && !isAdmin()) {
        if (isAjax()) {
            jsonResponse(false, null, 'Přihlášení vypršelo. Obnovte stránku.');
        }
        setFlash('error', 'Pro přístup se musíte přihlásit.');
        redirect('/index.php');
    }
}

/**
 * Vyžaduje admin přístup
 */
function requireAdmin(): void
{
    if (!isAdmin()) {
        setFlash('error', 'Přístup pouze pro administrátora.');
        redirect('/index.php');
    }
}

/**
 * Vrátí ID aktuálně přihlášeného uživatele
 */
function currentUserId(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

/**
 * Vrátí jméno aktuálně přihlášeného uživatele
 */
function currentUserName(): string
{
    return $_SESSION['user_name'] ?? 'Host';
}

/**
 * Vrátí HTML pro avatar uživatele – foto pokud existuje, jinak initials kruh.
 * $user = array s klíči 'name' a 'avatar'
 * $size = 'sm' | 'md' | 'lg'
 * $colorClass = třída avatar-* pro barvu initials
 */
function avatarHtml(array $user, string $size = 'md', string $colorClass = 'primary'): string
{
    $initials = '';
    $parts = explode(' ', trim($user['name'] ?? ''));
    $initials .= strtoupper(mb_substr($parts[0] ?? '', 0, 1));
    if (count($parts) > 1) $initials .= strtoupper(mb_substr(end($parts), 0, 1));

    if (!empty($user['avatar'])) {
        $src = e('/' . $user['avatar']);
        return '<img src="' . $src . '" alt="' . e($user['name']) . '" class="avatar avatar-' . $size . '" style="object-fit:cover;border:2px solid var(--gray-200);">';
    }

    return '<span class="avatar avatar-' . $size . ' avatar-' . $colorClass . '">' . e($initials) . '</span>';
}

/**
 * Vrátí URL avataru aktuálně přihlášeného uživatele (nebo null)
 */
function currentUserAvatar(): ?string
{
    $uid = currentUserId();
    if (!$uid) return null;
    static $avatarCache = [];
    if (!isset($avatarCache[$uid])) {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT avatar FROM users WHERE id = ?");
            $stmt->execute([$uid]);
            $avatarCache[$uid] = $stmt->fetchColumn() ?: null;
        } catch (\Exception $e) {
            $avatarCache[$uid] = null;
        }
    }
    $path = $avatarCache[$uid];
    return $path ? '/' . $path : null;
}

/**
 * Vrátí boat_id aktuálně přihlášeného uživatele
 */
function currentBoatId(): ?int
{
    return $_SESSION['boat_id'] ?? null;
}

// ============================================================
// FLASH ZPRÁVY
// ============================================================

/**
 * Nastaví flash zprávu do session
 */
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'][$type] = $message;
}

/**
 * Získá a smaže flash zprávu
 */
function getFlash(string $type): ?string
{
    $message = $_SESSION['flash'][$type] ?? null;
    unset($_SESSION['flash'][$type]);
    return $message;
}

/**
 * Vykreslí flash zprávy jako HTML
 */
function renderFlashMessages(): string
{
    $html = '';
    foreach (['success', 'error', 'info', 'warning'] as $type) {
        $msg = getFlash($type);
        if ($msg) {
            $cssClass = $type === 'error' ? 'danger' : $type;
            $html .= '<div class="alert alert-' . $cssClass . '">' . e($msg) . '<button class="alert-close" onclick="this.parentElement.remove()">&times;</button></div>';
        }
    }
    return $html;
}

// ============================================================
// NAVIGACE A HTTP
// ============================================================

/**
 * Přesměrování
 */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/**
 * Je požadavek AJAX?
 */
function isAjax(): bool
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * JSON odpověď pro API
 */
function jsonResponse(bool $success, $data = null, ?string $error = null): never
{
    header('Content-Type: application/json; charset=utf-8');
    $response = ['success' => $success];
    if ($data !== null) {
        $response['data'] = $data;
    }
    if ($error !== null) {
        $response['error'] = $error;
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================
// DATABÁZOVÉ HELPERY
// ============================================================

/**
 * Získání jednoho nastavení z tabulky settings
 */
function getSetting(string $key, string $default = ''): string
{
    static $cache = [];
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    try {
        $db = getDB();
        $stmt = $db->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        $cache[$key] = ($val !== false) ? $val : $default;
    } catch (PDOException $e) {
        $cache[$key] = $default;
    }
    return $cache[$key];
}

/**
 * Uložení nastavení
 */
function setSetting(string $key, string $value): void
{
    $db = getDB();
    $stmt = $db->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                          ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $stmt->execute([$key, $value]);
}

/**
 * Získání všech uživatelů
 */
function getAllUsers(): array
{
    $db = getDB();
    return $db->query('SELECT u.*, b.name AS boat_name FROM users u LEFT JOIN boats b ON u.boat_id = b.id ORDER BY u.boat_id, u.name')->fetchAll();
}

/**
 * Získání uživatelů podle lodi
 */
function getUsersByBoat(int $boatId): array
{
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM users WHERE boat_id = ? ORDER BY name');
    $stmt->execute([$boatId]);
    return $stmt->fetchAll();
}

/**
 * Získání jednoho uživatele
 */
function getUserById(int $id): ?array
{
    $db = getDB();
    $stmt = $db->prepare('SELECT u.*, b.name AS boat_name FROM users u LEFT JOIN boats b ON u.boat_id = b.id WHERE u.id = ?');
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    return $user ?: null;
}

/**
 * Získání všech lodí
 */
function getAllBoats(): array
{
    $db = getDB();
    return $db->query('SELECT * FROM boats ORDER BY id')->fetchAll();
}

/**
 * Získání lodi podle ID
 */
function getBoatById(int $id): ?array
{
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM boats WHERE id = ?');
    $stmt->execute([$id]);
    $boat = $stmt->fetch();
    return $boat ?: null;
}

// ============================================================
// KURZ CZK/EUR
// ============================================================

/**
 * Získání aktuálního kurzu CZK/EUR (cache 1 den)
 */
function getExchangeRate(): float
{
    $lastUpdate = getSetting('exchange_rate_updated', '');
    $cachedRate = getSetting('exchange_rate', '');

    // Pokud je kurz stažen dnes, vrátit z cache
    if ($lastUpdate === date('Y-m-d') && $cachedRate !== '') {
        return (float) $cachedRate;
    }

    // Stáhnout nový kurz z ČNB
    $rate = fetchCnbRate();
    if ($rate > 0) {
        setSetting('exchange_rate', (string) $rate);
        setSetting('exchange_rate_updated', date('Y-m-d'));
        return $rate;
    }

    // Fallback: poslední uložený kurz
    if ($cachedRate !== '') {
        return (float) $cachedRate;
    }

    // Úplný fallback
    return 25.0;
}

/**
 * Stažení kurzu EUR z ČNB
 */
function fetchCnbRate(): float
{
    $url = 'https://www.cnb.cz/cs/financni-trhy/devizovy-trh/kurzy-devizoveho-trhu/kurzy-devizoveho-trhu/denni_kurz.txt';

    $context = stream_context_create([
        'http' => ['timeout' => 5],
        'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);

    $content = @file_get_contents($url, false, $context);
    if ($content === false) {
        return 0.0;
    }

    // Parsování textu – hledáme řádek s EUR
    $lines = explode("\n", $content);
    foreach ($lines as $line) {
        if (strpos($line, 'EUR') !== false) {
            $parts = explode('|', $line);
            if (count($parts) >= 5) {
                // Formát: země|měna|množství|kód|kurz
                $rate = str_replace(',', '.', trim($parts[4]));
                $quantity = (int) trim($parts[2]);
                if ($quantity > 0) {
                    return round((float) $rate / $quantity, 4);
                }
            }
        }
    }

    return 0.0;
}

/**
 * Přepočet CZK na EUR
 */
function czkToEur(float $czk, float $rate): float
{
    if ($rate <= 0) {
        $rate = getExchangeRate();
    }
    return round($czk / $rate, 2);
}

// ============================================================
// FORMÁTOVÁNÍ
// ============================================================

/**
 * Formátování částky
 */
function formatMoney(float $amount, string $currency = 'EUR'): string
{
    return number_format($amount, 2, ',', ' ') . ' ' . $currency;
}

/**
 * Formátování data česky
 */
function formatDate(string $date): string
{
    $d = new DateTime($date);
    return $d->format('j. n. Y');
}

/**
 * Formátování data a času česky
 */
function formatDateTime(string $datetime): string
{
    $d = new DateTime($datetime);
    return $d->format('j. n. Y H:i');
}

/**
 * Názvy dnů v týdnu česky
 */
function czechDayName(string $date): string
{
    $days = ['Neděle', 'Pondělí', 'Úterý', 'Středa', 'Čtvrtek', 'Pátek', 'Sobota'];
    $d = new DateTime($date);
    return $days[(int) $d->format('w')];
}

// ============================================================
// LAYOUT
// ============================================================

/**
 * Začátek HTML stránky s layoutem
 */
function renderHeader(string $title, string $activePage = ''): void
{
    $tripName = getSetting('trip_name', 'Plavba');
    $userName = currentUserName();
    $userAvatar = currentUserAvatar();
    $boatId = currentBoatId();
    $boatName = '';
    if ($boatId) {
        $boat = getBoatById($boatId);
        $boatName = $boat ? $boat['name'] : '';
    }
    $isAdm = isAdmin();
    $csrf = generateCsrfToken();

    include APP_ROOT . '/templates/header.php';
}

/**
 * Konec HTML stránky
 */
function renderFooter(): void
{
    include APP_ROOT . '/templates/footer.php';
}
