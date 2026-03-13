<?php
/**
 * Konfigurace aplikace – DB připojení, session, konstanty
 *
 * Zkopíruj tento soubor jako config.php a vyplň hodnoty,
 * nebo použij .env soubor s těmito klíči.
 */

// Režim: 'development' nebo 'production'
define('APP_ENV', 'production');

// Zobrazení chyb podle prostředí
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// Načíst credentials z .env souboru (pokud existuje)
$_env = [];
$_envFile = __DIR__ . '/.env';
if (file_exists($_envFile)) {
    foreach (file($_envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $_envLine) {
        if (strpos(trim($_envLine), '#') === 0) continue;
        $_envParts = explode('=', $_envLine, 2);
        if (count($_envParts) === 2) {
            $_env[trim($_envParts[0])] = trim($_envParts[1]);
        }
    }
}
unset($_envFile, $_envLine, $_envParts);

// Databázové připojení – vyplň hodnoty nebo nastav v .env
define('DB_HOST', $_env['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_env['DB_NAME'] ?? 'nazev_databaze');
define('DB_USER', $_env['DB_USER'] ?? 'uzivatel');
define('DB_PASS', $_env['DB_PASS'] ?? 'heslo');
unset($_env);
define('DB_CHARSET', 'utf8mb4');

// Session – timeout 24 hodin
define('SESSION_TIMEOUT', 86400);

// Session – timeout 7 dní pro "zapamatovat si přihlášení"
define('REMEMBER_TIMEOUT', 604800);

// Název aplikace
define('APP_NAME', 'Sailing App');

// Verze
define('APP_VERSION', '1.0.0');

// Absolutní cesta k root adresáři aplikace
define('APP_ROOT', __DIR__);

/**
 * PDO připojení k databázi (singleton)
 */
function getDB(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }
    return $pdo;
}

/**
 * Inicializace session
 */
function initSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start([
            'cookie_httponly' => true,
            'cookie_secure'   => isset($_SERVER['HTTPS']),
            'cookie_samesite' => 'Lax',
            'gc_maxlifetime' => REMEMBER_TIMEOUT,
        ]);
    }

    // Kontrola timeoutu session – 7 dní pro "zapamatovat", jinak 24h
    $timeout = !empty($_SESSION['remember_me']) ? REMEMBER_TIMEOUT : SESSION_TIMEOUT;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
        session_unset();
        session_destroy();
        session_start();
    }
    $_SESSION['last_activity'] = time();

    // Obnovit cookie pokud je remember_me aktivní (aby nevypršela při opakovaných návštěvách)
    if (!empty($_SESSION['remember_me'])) {
        setcookie(session_name(), session_id(), time() + REMEMBER_TIMEOUT, '/', '', isset($_SERVER['HTTPS']), true);
    }
}

// Spustit session
initSession();
