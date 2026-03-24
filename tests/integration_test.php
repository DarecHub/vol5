<?php
/**
 * VOL5 – Integration testy (vyžadují běžící DB)
 *
 * Každý test spustí skutečné HTTP požadavky na lokální instanci.
 * Spuštění: php tests/integration_test.php
 *
 * Předpoklady:
 *   - Docker kontejnery běží (docker-compose up -d)
 *   - Aplikace dostupná na http://localhost:8080
 *   - Seed data naplněna (docker/seed.sql)
 */

// Při spuštění z Docker kontejneru použij host.docker.internal, jinak localhost
$_baseUrl = (file_exists('/.dockerenv') || getenv('RUNNING_IN_DOCKER'))
    ? 'http://host.docker.internal:8080'
    : 'http://localhost:8080';
define('BASE_URL', $_baseUrl);
define('MEMBER_PASS', 'crew123');
define('ADMIN_PASS',  'admin123');

// ============================================================
// Mini test runner
// ============================================================

$passed = 0;
$failed = 0;
$errors = [];

function assert_equals(string $label, $expected, $actual): void
{
    global $passed, $failed, $errors;
    if ($expected === $actual) {
        echo "\033[32m  ✓ {$label}\033[0m\n";
        $passed++;
    } else {
        echo "\033[31m  ✗ {$label}\033[0m\n";
        echo "      expected: " . var_export($expected, true) . "\n";
        echo "      actual:   " . var_export($actual, true) . "\n";
        $failed++;
        $errors[] = $label;
    }
}

function assert_true(string $label, bool $condition): void
{
    assert_equals($label, true, $condition);
}

function assert_contains(string $label, string $needle, string $haystack): void
{
    assert_true($label, str_contains($haystack, $needle));
}

function section(string $name): void
{
    echo "\n\033[1;34m▶ {$name}\033[0m\n";
}

// ============================================================
// HTTP helper – curl wrapper se session cookies
// ============================================================

/**
 * Vrátí nový curl handle se sdíleným cookie jar (tmp soubor).
 * Každé volání session() vrátí nový izolovaný handle.
 */
function new_session(): array
{
    $cookieFile = tempnam(sys_get_temp_dir(), 'vol5_cookies_');
    return ['cookie_file' => $cookieFile];
}

function http_get(array &$session, string $path, array $query = []): array
{
    $url = BASE_URL . $path;
    if ($query) $url .= '?' . http_build_query($query);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR      => $session['cookie_file'],
        CURLOPT_COOKIEFILE     => $session['cookie_file'],
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HEADERFUNCTION => function($ch, $header) use (&$session) {
            if (stripos($header, 'Location:') === 0) {
                $session['last_redirect'] = trim(substr($header, 9));
            }
            return strlen($header);
        },
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => $body];
}

function http_post(array &$session, string $path, array $data = []): array
{
    // Nejdřív načteme CSRF token z GET (nebo z cookie)
    $ch = curl_init(BASE_URL . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR      => $session['cookie_file'],
        CURLOPT_COOKIEFILE     => $session['cookie_file'],
        CURLOPT_TIMEOUT        => 10,
    ]);
    $preBody = curl_exec($ch);
    curl_close($ch);

    // Extrahovat CSRF z hidden inputu nebo session
    $csrfToken = '';
    if (preg_match('/name="csrf_token"\s+value="([a-f0-9]+)"/', $preBody, $m)) {
        $csrfToken = $m[1];
    } elseif (!empty($session['csrf_token'])) {
        $csrfToken = $session['csrf_token'];
    }

    if ($csrfToken) {
        $session['csrf_token'] = $csrfToken;
        $data['csrf_token'] = $csrfToken;
    }

    $ch = curl_init(BASE_URL . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR      => $session['cookie_file'],
        CURLOPT_COOKIEFILE     => $session['cookie_file'],
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/x-www-form-urlencoded',
        ],
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => $body];
}

/**
 * POST na API endpoint – přidá hlavičku X-Requested-With: XMLHttpRequest
 * a X-CSRF-Token z uložené session.
 */
function api_post(array &$session, string $path, array $data = []): array
{
    if (!empty($session['csrf_token'])) {
        $data['csrf_token'] = $session['csrf_token'];
    }

    $ch = curl_init(BASE_URL . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR      => $session['cookie_file'],
        CURLOPT_COOKIEFILE     => $session['cookie_file'],
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => [
            'X-Requested-With: XMLHttpRequest',
            'Content-Type: application/x-www-form-urlencoded',
        ],
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = json_decode($body, true);
    return ['code' => $code, 'body' => $body, 'json' => $json];
}

function api_get(array &$session, string $path, array $query = []): array
{
    $url = BASE_URL . $path;
    if ($query) $url .= '?' . http_build_query($query);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR      => $session['cookie_file'],
        CURLOPT_COOKIEFILE     => $session['cookie_file'],
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => ['X-Requested-With: XMLHttpRequest'],
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = json_decode($body, true);
    return ['code' => $code, 'body' => $body, 'json' => $json];
}

/**
 * Přihlásí se jako člen (user_id=1, boat_id=1) a vrátí session.
 */
function login_member(int $userId = 1): array
{
    $s = new_session();
    // GET login stránky – získáme CSRF token
    $get = http_get($s, '/index.php');
    if (preg_match('/name="csrf_token"\s+value="([a-f0-9]+)"/', $get['body'], $m)) {
        $s['csrf_token'] = $m[1];
    }
    // POST přihlášení
    http_post($s, '/index.php', [
        'login_type'      => 'member',
        'user_id'         => $userId,
        'member_password' => MEMBER_PASS,
    ]);
    return $s;
}

/**
 * Přihlásí se jako admin a vrátí session.
 */
function login_admin(): array
{
    $s = new_session();
    $get = http_get($s, '/index.php');
    if (preg_match('/name="csrf_token"\s+value="([a-f0-9]+)"/', $get['body'], $m)) {
        $s['csrf_token'] = $m[1];
    }
    http_post($s, '/index.php', [
        'login_type'     => 'admin',
        'admin_password' => ADMIN_PASS,
    ]);
    return $s;
}

// ============================================================
// Cleanup helper – smaže tmp cookie soubory po testu
// ============================================================
register_shutdown_function(function () {
    foreach (glob(sys_get_temp_dir() . '/vol5_cookies_*') as $f) {
        @unlink($f);
    }
});

// ============================================================
// Ověření dostupnosti serveru
// ============================================================
$ping = @file_get_contents(BASE_URL . '/index.php');
if ($ping === false) {
    echo "\033[31m[FATAL] Server není dostupný na " . BASE_URL . "\033[0m\n";
    echo "Spusťte: docker-compose up -d\n";
    exit(1);
}
echo "\033[32m[OK] Server běží na " . BASE_URL . "\033[0m\n";

// ============================================================
// AUTH TESTY
// ============================================================

section('AUTH – Login / Logout');

// AUTH-1: Přihlášení člena se správným heslem
$s = new_session();
$get = http_get($s, '/index.php');
if (preg_match('/name="csrf_token"\s+value="([a-f0-9]+)"/', $get['body'], $m)) {
    $s['csrf_token'] = $m[1];
}
$res = http_post($s, '/index.php', [
    'login_type'      => 'member',
    'user_id'         => 1,
    'member_password' => MEMBER_PASS,
]);
$dashboard = http_get($s, '/pages/dashboard.php');
assert_equals('AUTH-1: Člen se přihlásí správným heslem → dashboard HTTP 200', 200, $dashboard['code']);
assert_contains('AUTH-1: Dashboard obsahuje nav element', 'bottom-nav', $dashboard['body']);

// AUTH-2: Přihlášení člena se špatným heslem
$s2 = new_session();
$get2 = http_get($s2, '/index.php');
if (preg_match('/name="csrf_token"\s+value="([a-f0-9]+)"/', $get2['body'], $m)) {
    $s2['csrf_token'] = $m[1];
}
$res2 = http_post($s2, '/index.php', [
    'login_type'      => 'member',
    'user_id'         => 1,
    'member_password' => 'wrongpassword',
]);
assert_contains('AUTH-2: Špatné heslo → chybová hláška', 'Nesprávné heslo', $res2['body']);
// Bez session: FOLLOWLOCATION=true → redirect na login (vrátí login stránku s HTTP 200)
// Ověříme že dashboard NENÍ dostupný – login stránka neobsahuje 'bottom-nav'
$dash2 = http_get($s2, '/pages/dashboard.php');
assert_true('AUTH-2: Bez přihlášení → vrátí login stránku (ne dashboard)', 
    !str_contains($dash2['body'], 'bottom-nav'));

// AUTH-3: Přihlášení admina se správným heslem
$sa = login_admin();
$adminDash = http_get($sa, '/admin/index.php');
assert_equals('AUTH-3: Admin správné heslo → admin panel HTTP 200', 200, $adminDash['code']);

// AUTH-4: Přihlášení admina se špatným heslem
$sa2 = new_session();
$getA = http_get($sa2, '/index.php');
if (preg_match('/name="csrf_token"\s+value="([a-f0-9]+)"/', $getA['body'], $m)) {
    $sa2['csrf_token'] = $m[1];
}
$resA = http_post($sa2, '/index.php', [
    'login_type'     => 'admin',
    'admin_password' => 'badpassword',
]);
assert_contains('AUTH-4: Admin špatné heslo → chybová hláška', 'Nesprávné admin heslo', $resA['body']);

// AUTH-5: Přihlášení neexistujícího user_id
$s3 = new_session();
$get3 = http_get($s3, '/index.php');
if (preg_match('/name="csrf_token"\s+value="([a-f0-9]+)"/', $get3['body'], $m)) {
    $s3['csrf_token'] = $m[1];
}
$res3 = http_post($s3, '/index.php', [
    'login_type'      => 'member',
    'user_id'         => 9999,
    'member_password' => MEMBER_PASS,
]);
assert_true('AUTH-5: Neexistující user_id → chyba nebo stále na login stránce', 
    str_contains($res3['body'], 'Uživatel') || str_contains($res3['body'], 'csrf_token'));

// AUTH-6: API bez session → JSON chyba (ne redirect)
$s4 = new_session();
$res4 = api_get($s4, '/api/wallet.php', ['action' => 'list']);
assert_equals('AUTH-6: API bez session → HTTP 200 s JSON chybou', 200, $res4['code']);
assert_equals('AUTH-6: JSON success=false', false, $res4['json']['success'] ?? true);

// AUTH-7: CSRF – falešný token odmítnut
// api_post přidává uložený token, proto ho musíme explicitně přepsat PŘED voláním
$sLogged = login_member(1);
// Přepíšeme uložený token na falešný
$sLogged['csrf_token'] = 'totalne_spatny_token_abc123';
$ch7 = curl_init(BASE_URL . '/api/shopping.php');
curl_setopt_array($ch7, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query([
        'action'     => 'add',
        'boat_id'    => 1,
        'item_name'  => 'Test CSRF',
        'csrf_token' => 'totalne_spatny_token_abc123',
    ]),
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_COOKIEJAR      => $sLogged['cookie_file'],
    CURLOPT_COOKIEFILE     => $sLogged['cookie_file'],
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_HTTPHEADER     => ['X-Requested-With: XMLHttpRequest'],
]);
$body7 = curl_exec($ch7);
curl_close($ch7);
$json7 = json_decode($body7, true);
assert_equals('AUTH-7: Falešný CSRF token odmítnut → success=false', false, $json7['success'] ?? true);

// AUTH-8: Logout zničí session
$sLogout = login_member(1);
$before = http_get($sLogout, '/pages/dashboard.php');
assert_equals('AUTH-8: Před odhlášením dashboard dostupný', 200, $before['code']);
http_get($sLogout, '/logout.php');
$after = http_get($sLogout, '/pages/dashboard.php');
assert_true('AUTH-8: Po odhlášení dashboard nepřístupný (redirect nebo 302)', 
    $after['code'] !== 200 || str_contains($after['body'], 'csrf_token') || str_contains($after['body'], 'přihlásit'));

// ============================================================
// WALLET TESTY
// ============================================================

section('WALLET – Výdaje');

$sw = login_member(1);

// WALLET-1: Přidání EUR výdaje
$addRes = api_post($sw, '/api/wallet.php', [
    'action'       => 'add',
    'paid_by'      => 1,
    'amount'       => 30.00,
    'currency'     => 'EUR',
    'description'  => 'Test integration EUR',
    'expense_date' => '2025-07-20 10:00:00',
    'split_type'   => 'both',
    'split_users'  => '1,2,3',
]);
assert_equals('WALLET-1: Přidání EUR výdaje → success=true', true, $addRes['json']['success'] ?? false);
$newExpenseId = $addRes['json']['data']['id'] ?? 0;
assert_true('WALLET-1: Vráceno ID nového výdaje', $newExpenseId > 0);

// WALLET-2: Přidání CZK výdaje – přepočet na EUR
// Nejdřív zjistíme aktuální kurz z aplikace
$exchRate = (float)(api_get($sw, '/api/exchange.php')['json']['data']['rate'] ?? 25.0);
$addCzk = api_post($sw, '/api/wallet.php', [
    'action'       => 'add',
    'paid_by'      => 2,
    'amount'       => 500.00,
    'currency'     => 'CZK',
    'description'  => 'Test CZK integration',
    'expense_date' => '2025-07-20 11:00:00',
    'split_type'   => 'boat1',
    'split_users'  => '1,2,3',
]);
assert_equals('WALLET-2: Přidání CZK výdaje → success=true', true, $addCzk['json']['success'] ?? false);
$czkExpenseId = $addCzk['json']['data']['id'] ?? 0;

// Ověřit přepočet: 500 CZK / aktuální kurz
$expectedEur = round(500.00 / $exchRate, 2);
$listRes = api_get($sw, '/api/wallet.php', ['action' => 'list']);
$foundCzk = null;
foreach (($listRes['json']['data']['expenses'] ?? []) as $exp) {
    if (($exp['id'] ?? 0) == $czkExpenseId) {
        $foundCzk = $exp;
        break;
    }
}
$actualEur = (float)($foundCzk['amount_eur'] ?? -1);
assert_true('WALLET-2: CZK výdaj přepočten na EUR (500/kurz=' . $expectedEur . ')', abs($actualEur - $expectedEur) < 0.02);

// WALLET-3: Neplatný datum → chyba
$badDate = api_post($sw, '/api/wallet.php', [
    'action'       => 'add',
    'paid_by'      => 1,
    'amount'       => 10.00,
    'currency'     => 'EUR',
    'description'  => 'Test neplatný datum',
    'expense_date' => 'neni-datum-20-25',
    'split_type'   => 'both',
    'split_users'  => '1',
]);
assert_equals('WALLET-3: Neplatný datum → success=false', false, $badDate['json']['success'] ?? true);
assert_contains('WALLET-3: Chybová hláška o datumu', 'datum', strtolower($badDate['json']['error'] ?? ''));

// WALLET-4: Záporná částka → chyba
$negAmount = api_post($sw, '/api/wallet.php', [
    'action'       => 'add',
    'paid_by'      => 1,
    'amount'       => -50.00,
    'currency'     => 'EUR',
    'description'  => 'Test záporná',
    'expense_date' => '2025-07-20 10:00:00',
    'split_type'   => 'both',
    'split_users'  => '1',
]);
assert_equals('WALLET-4: Záporná částka → success=false', false, $negAmount['json']['success'] ?? true);

// WALLET-5: Prázdný popis → chyba
$emptyDesc = api_post($sw, '/api/wallet.php', [
    'action'       => 'add',
    'paid_by'      => 1,
    'amount'       => 10.00,
    'currency'     => 'EUR',
    'description'  => '',
    'expense_date' => '2025-07-20 10:00:00',
    'split_type'   => 'both',
    'split_users'  => '1',
]);
assert_equals('WALLET-5: Prázdný popis → success=false', false, $emptyDesc['json']['success'] ?? true);

// WALLET-6: Žádní split_users → chyba
$noSplit = api_post($sw, '/api/wallet.php', [
    'action'       => 'add',
    'paid_by'      => 1,
    'amount'       => 10.00,
    'currency'     => 'EUR',
    'description'  => 'Test bez splitu',
    'expense_date' => '2025-07-20 10:00:00',
    'split_type'   => 'both',
    'split_users'  => '',
]);
assert_equals('WALLET-6: Prázdní split_users → success=false', false, $noSplit['json']['success'] ?? true);

// WALLET-7: Smazání výdaje smaže i splits → list vrátí kratší pole
if ($newExpenseId > 0) {
    $listBefore = api_get($sw, '/api/wallet.php', ['action' => 'list']);
    $countBefore = count($listBefore['json']['data']['expenses'] ?? []);

    $delRes = api_post($sw, '/api/wallet.php', [
        'action' => 'delete',
        'id'     => $newExpenseId,
    ]);
    assert_equals('WALLET-7: Smazání výdaje → success=true', true, $delRes['json']['success'] ?? false);

    $listAfter = api_get($sw, '/api/wallet.php', ['action' => 'list']);
    $countAfter = count($listAfter['json']['data']['expenses'] ?? []);
    assert_equals('WALLET-7: Po smazání je o jeden výdaj méně', $countBefore - 1, $countAfter);
}

// WALLET-8: Audit log – po přidání existuje záznam
if ($czkExpenseId > 0) {
    // Výdaj jsme přidali, editujeme ho – audit log by měl mít 'created' záznam
    // Ověříme nepřímo: edit zachová původní kurz CZK
    $editRes = api_post($sw, '/api/wallet.php', [
        'action'       => 'edit',
        'id'           => $czkExpenseId,
        'paid_by'      => 2,
        'amount'       => 600.00,
        'currency'     => 'CZK',
        'description'  => 'Test CZK edit',
        'expense_date' => '2025-07-20 11:00:00',
        'split_type'   => 'boat1',
        'split_users'  => '1,2,3',
    ]);
    assert_equals('WALLET-8: Editace CZK výdaje → success=true', true, $editRes['json']['success'] ?? false);

    // Ověřit novou EUR hodnotu: 600 / původní kurz (stejný jako byl při přidání = $exchRate)
    $expectedEditEur = round(600.00 / $exchRate, 2);
    $listAfterEdit = api_get($sw, '/api/wallet.php', ['action' => 'list']);
    $editedExp = null;
    foreach (($listAfterEdit['json']['data']['expenses'] ?? []) as $exp) {
        if (($exp['id'] ?? 0) == $czkExpenseId) {
            $editedExp = $exp;
            break;
        }
    }
    $actualEditEur = (float)($editedExp['amount_eur'] ?? -1);
    assert_true('WALLET-8: CZK edit přepočten původním kurzem (600/kurz=' . $expectedEditEur . ')', abs($actualEditEur - $expectedEditEur) < 0.02);
}

// WALLET-9: Bilance – total_eur se vrátí
$balRes = api_get($sw, '/api/wallet.php', ['action' => 'balances']);
assert_equals('WALLET-9: Bilance → success=true', true, $balRes['json']['success'] ?? false);
assert_true('WALLET-9: Bilance obsahuje záznamy', count($balRes['json']['data'] ?? []) > 0);
$firstBalance = $balRes['json']['data'][0] ?? [];
assert_true('WALLET-9: Každý záznam bilance má pole paid, share, balance',
    isset($firstBalance['paid'], $firstBalance['share'], $firstBalance['balance']));

// WALLET-10: Settlements – vrátí pole vyrovnání
$setRes = api_get($sw, '/api/wallet.php', ['action' => 'settlements']);
assert_equals('WALLET-10: Settlements → success=true', true, $setRes['json']['success'] ?? false);

// Cleanup CZK expense
if ($czkExpenseId > 0) {
    api_post($sw, '/api/wallet.php', ['action' => 'delete', 'id' => $czkExpenseId]);
}

// ============================================================
// SHOPPING TESTY
// ============================================================

section('SHOPPING – Nákupní seznam');

$ss1 = login_member(1); // loď 1, user 1
$ss2 = login_member(4); // loď 2, user 4

// SHOP-1: Přidání položky pro loď 1
$shopAdd = api_post($ss1, '/api/shopping.php', [
    'action'    => 'add',
    'boat_id'   => 1,
    'item_name' => 'Test položka integrace',
    'category'  => 'potraviny',
    'price'     => 5.99,
    'currency'  => 'EUR',
]);
assert_equals('SHOP-1: Přidání položky → success=true', true, $shopAdd['json']['success'] ?? false);
$shopItemId = $shopAdd['json']['data']['id'] ?? 0;
assert_true('SHOP-1: Vráceno ID', $shopItemId > 0);

// SHOP-2: List obsahuje novou položku
$shopList = api_get($ss1, '/api/shopping.php', ['action' => 'list', 'boat_id' => 1]);
assert_equals('SHOP-2: List položek → success=true', true, $shopList['json']['success'] ?? false);
$foundShop = false;
foreach (($shopList['json']['data']['items'] ?? []) as $item) {
    if (($item['id'] ?? 0) == $shopItemId) { $foundShop = true; break; }
}
assert_true('SHOP-2: Přidaná položka se zobrazí v listu', $foundShop);

// SHOP-3: Toggle bought – označení koupeno
$toggleRes = api_post($ss1, '/api/shopping.php', [
    'action'    => 'toggle_bought',
    'id'        => $shopItemId,
    'is_bought' => 1,
    'price'     => 5.99,
]);
assert_equals('SHOP-3: Toggle bought=1 → success=true', true, $toggleRes['json']['success'] ?? false);

// Ověřit že is_bought=1 v listu
$shopList2 = api_get($ss1, '/api/shopping.php', ['action' => 'list', 'boat_id' => 1]);
$shopBoughtOk = false;
foreach (($shopList2['json']['data']['items'] ?? []) as $item) {
    if (($item['id'] ?? 0) == $shopItemId && (int)$item['is_bought'] === 1) {
        $shopBoughtOk = true;
        break;
    }
}
assert_true('SHOP-3: Položka je označena jako koupená', $shopBoughtOk);

// SHOP-4: Toggle bought zpět na 0
$toggleBack = api_post($ss1, '/api/shopping.php', [
    'action'    => 'toggle_bought',
    'id'        => $shopItemId,
    'is_bought' => 0,
    'price'     => '',
]);
assert_equals('SHOP-4: Toggle bought=0 → success=true', true, $toggleBack['json']['success'] ?? false);

// SHOP-5: SEC – uživatel lodi 2 nemůže smazat položku lodi 1
$secDel = api_post($ss2, '/api/shopping.php', [
    'action' => 'delete',
    'id'     => $shopItemId,
]);
assert_equals('SHOP-5: SEC – cizí delete → success=false', false, $secDel['json']['success'] ?? true);
assert_contains('SHOP-5: SEC – chybová hláška o přístupu', 'odepřen', $secDel['json']['error'] ?? '');

// SHOP-6: SEC – uživatel lodi 2 nemůže editovat položku lodi 1
$secEdit = api_post($ss2, '/api/shopping.php', [
    'action'    => 'edit',
    'id'        => $shopItemId,
    'item_name' => 'Hack',
    'category'  => 'potraviny',
    'currency'  => 'EUR',
    'price'     => 0,
]);
assert_equals('SHOP-6: SEC – cizí edit → success=false', false, $secEdit['json']['success'] ?? true);

// SHOP-7: SEC – uživatel lodi 2 nemůže toggle bought položku lodi 1
$secToggle = api_post($ss2, '/api/shopping.php', [
    'action'    => 'toggle_bought',
    'id'        => $shopItemId,
    'is_bought' => 1,
    'price'     => '',
]);
assert_equals('SHOP-7: SEC – cizí toggle_bought → success=false', false, $secToggle['json']['success'] ?? true);

// SHOP-8: Prázdné jméno → chyba
// Posíláme price='' explicitně aby nedošlo k PHP Notice z $_POST['price'] !== ''
$emptyName = api_post($ss1, '/api/shopping.php', [
    'action'    => 'add',
    'boat_id'   => 1,
    'item_name' => '',
    'category'  => 'potraviny',
    'price'     => '',
    'currency'  => 'EUR',
]);
assert_equals('SHOP-8: Prázdné jméno → success=false', false, $emptyName['json']['success'] ?? true);

// SHOP-9: Vlastní smazání vlastní položky funguje
$ownDel = api_post($ss1, '/api/shopping.php', [
    'action' => 'delete',
    'id'     => $shopItemId,
]);
assert_equals('SHOP-9: Vlastní delete → success=true', true, $ownDel['json']['success'] ?? false);

// Cleanup – ověřit že položka zmizela
$shopList3 = api_get($ss1, '/api/shopping.php', ['action' => 'list', 'boat_id' => 1]);
$stillExists = false;
foreach (($shopList3['json']['data']['items'] ?? []) as $item) {
    if (($item['id'] ?? 0) == $shopItemId) { $stillExists = true; break; }
}
assert_true('SHOP-9: Po smazání položka zmizí z listu', !$stillExists);

// ============================================================
// LOGBOOK TESTY
// ============================================================

section('LOGBOOK – Deník plavby');

$sl1 = login_member(1); // loď 1
$sl2 = login_member(4); // loď 2

// LOG-1: Přidání záznamu pro loď 1
$logAdd = api_post($sl1, '/api/logbook.php', [
    'action'        => 'add',
    'boat_id'       => 1,
    'date'          => '2025-07-22',
    'location_from' => 'Rovinj',
    'location_to'   => 'Pula',
    'nautical_miles' => 25.5,
    'departure_time' => '08:00',
    'arrival_time'   => '13:30',
    'skipper_user_id' => 1,
    'note'           => 'Test integrace',
]);
assert_equals('LOG-1: Přidání záznamu → success=true', true, $logAdd['json']['success'] ?? false);
$logId = $logAdd['json']['data']['id'] ?? 0;
assert_true('LOG-1: Vráceno ID', $logId > 0);

// LOG-2: List obsahuje nový záznam a NM jsou správně
$logList = api_get($sl1, '/api/logbook.php', ['action' => 'list', 'boat_id' => 1]);
assert_equals('LOG-2: List záznamu → success=true', true, $logList['json']['success'] ?? false);
$foundLog = false;
foreach (($logList['json']['data']['entries'] ?? []) as $entry) {
    if (($entry['id'] ?? 0) == $logId) { $foundLog = true; break; }
}
assert_true('LOG-2: Nový záznam se zobrazí v listu', $foundLog);

// LOG-3: Statistiky obsahují NM
$stats = $logList['json']['data']['stats'] ?? [];
assert_true('LOG-3: Statistiky obsahují total_nm', isset($stats['total_nm']));
assert_true('LOG-3: total_nm > 0', (float)($stats['total_nm'] ?? 0) > 0);

// LOG-4: SEC – uživatel lodi 2 nemůže editovat záznam lodi 1
$logSecEdit = api_post($sl2, '/api/logbook.php', [
    'action'         => 'edit',
    'id'             => $logId,
    'date'           => '2025-07-22',
    'location_from'  => 'Hack',
    'location_to'    => 'Hack',
    'nautical_miles' => 0,
]);
assert_equals('LOG-4: SEC – cizí edit záznamu → success=false', false, $logSecEdit['json']['success'] ?? true);
assert_contains('LOG-4: SEC – chybová hláška o přístupu', 'odepřen', $logSecEdit['json']['error'] ?? '');

// LOG-5: SEC – uživatel lodi 2 nemůže smazat záznam lodi 1
$logSecDel = api_post($sl2, '/api/logbook.php', [
    'action' => 'delete',
    'id'     => $logId,
]);
assert_equals('LOG-5: SEC – cizí delete záznamu → success=false', false, $logSecDel['json']['success'] ?? true);

// LOG-6: Chybí povinné pole (date) → chyba
$logMissing = api_post($sl1, '/api/logbook.php', [
    'action'        => 'add',
    'boat_id'       => 1,
    'date'          => '',
    'location_from' => 'A',
    'location_to'   => 'B',
]);
assert_equals('LOG-6: Chybí datum → success=false', false, $logMissing['json']['success'] ?? true);

// LOG-7: Vlastní smazání funguje
$logOwnDel = api_post($sl1, '/api/logbook.php', [
    'action' => 'delete',
    'id'     => $logId,
]);
assert_equals('LOG-7: Vlastní delete záznamu → success=true', true, $logOwnDel['json']['success'] ?? false);

// ============================================================
// MENU TESTY
// ============================================================

section('MENU – Jídelníček');

$sm1 = login_member(1);
$sm2 = login_member(4);

// MENU-1: Přidání jídla
$menuAdd = api_post($sm1, '/api/menu.php', [
    'action'           => 'add',
    'boat_id'          => 1,
    'date'             => '2025-07-23',
    'meal_type'        => 'obed',
    'cook_user_id'     => 1,
    'meal_description' => 'Test těstoviny',
    'note'             => '',
]);
assert_equals('MENU-1: Přidání jídla → success=true', true, $menuAdd['json']['success'] ?? false);
$menuId = $menuAdd['json']['data']['id'] ?? 0;
assert_true('MENU-1: Vráceno ID', $menuId > 0);

// MENU-2: Duplicita – stejný den+typ → chyba
$menuDup = api_post($sm1, '/api/menu.php', [
    'action'    => 'add',
    'boat_id'   => 1,
    'date'      => '2025-07-23',
    'meal_type' => 'obed',
]);
assert_equals('MENU-2: Duplicita oběd/den → success=false', false, $menuDup['json']['success'] ?? true);
assert_contains('MENU-2: Hláška o duplicitě', 'existuje', $menuDup['json']['error'] ?? '');

// MENU-3: SEC – uživatel lodi 2 nemůže editovat menu lodi 1
$menuSecEdit = api_post($sm2, '/api/menu.php', [
    'action'           => 'edit',
    'id'               => $menuId,
    'meal_description' => 'Hack',
]);
assert_equals('MENU-3: SEC – cizí edit menu → success=false', false, $menuSecEdit['json']['success'] ?? true);

// MENU-4: SEC – uživatel lodi 2 nemůže smazat menu lodi 1
$menuSecDel = api_post($sm2, '/api/menu.php', [
    'action' => 'delete',
    'id'     => $menuId,
]);
assert_equals('MENU-4: SEC – cizí delete menu → success=false', false, $menuSecDel['json']['success'] ?? true);

// MENU-5: Vlastní editace jídla
$menuEdit = api_post($sm1, '/api/menu.php', [
    'action'           => 'edit',
    'id'               => $menuId,
    'cook_user_id'     => 2,
    'meal_description' => 'Upravené těstoviny',
    'note'             => 'Bonus',
]);
assert_equals('MENU-5: Vlastní edit → success=true', true, $menuEdit['json']['success'] ?? false);

// MENU-6: Smazání vlastního záznamu
$menuDel = api_post($sm1, '/api/menu.php', [
    'action' => 'delete',
    'id'     => $menuId,
]);
assert_equals('MENU-6: Vlastní delete menu → success=true', true, $menuDel['json']['success'] ?? false);

// ============================================================
// CARS TESTY
// ============================================================

section('CARS – Auta a spolujezdci');

$sc = login_member(1);

// CARS-1: Přidání auta
$carAdd = api_post($sc, '/api/cars.php', [
    'action'        => 'add_car',
    'driver_user_id' => 3,
    'car_name'      => 'Testovací Škoda',
    'seats'         => 4,
    'note'          => 'Test integrace',
]);
assert_equals('CARS-1: Přidání auta → success=true', true, $carAdd['json']['success'] ?? false);
$carId = $carAdd['json']['data']['id'] ?? 0;
assert_true('CARS-1: Vráceno ID auta', $carId > 0);

// CARS-2: Přidání spolujezdce
$pasAdd = api_post($sc, '/api/cars.php', [
    'action'  => 'add_passenger',
    'car_id'  => $carId,
    'user_id' => 2,
]);
assert_equals('CARS-2: Přidání spolujezdce → success=true', true, $pasAdd['json']['success'] ?? false);

// CARS-3: List obsahuje nové auto se správnými daty
$carList = api_get($sc, '/api/cars.php', ['action' => 'list']);
assert_equals('CARS-3: List aut → success=true', true, $carList['json']['success'] ?? false);
$foundCar = null;
foreach (($carList['json']['data']['cars'] ?? []) as $car) {
    if (($car['id'] ?? 0) == $carId) { $foundCar = $car; break; }
}
assert_true('CARS-3: Nové auto v listu', $foundCar !== null);
assert_equals('CARS-3: Auto má 1 spolujezdce', 1, count($foundCar['passengers'] ?? []));

// CARS-4: Kapacita – auto s seats=2 (1 řidič + 1 spolujezdec = plné)
// Přidání 2. spolujezdce by mělo selhat (seats=4, ale přidáme 3 spolujezdce → plné)
$pasAdd2 = api_post($sc, '/api/cars.php', ['action' => 'add_passenger', 'car_id' => $carId, 'user_id' => 6]);
$pasAdd3 = api_post($sc, '/api/cars.php', ['action' => 'add_passenger', 'car_id' => $carId, 'user_id' => 5]);
// Seats=4: řidič(1) + 3 spolujezdci = plné. 4. spolujezdec musí selhat.
$pasOver = api_post($sc, '/api/cars.php', ['action' => 'add_passenger', 'car_id' => $carId, 'user_id' => 4]);
assert_equals('CARS-4: Kapacita překročena → success=false', false, $pasOver['json']['success'] ?? true);
assert_contains('CARS-4: Hláška o kapacitě', 'plné', $pasOver['json']['error'] ?? '');

// CARS-5: Odebrání spolujezdce
$passList = api_get($sc, '/api/cars.php', ['action' => 'list']);
$testCar = null;
foreach (($passList['json']['data']['cars'] ?? []) as $car) {
    if (($car['id'] ?? 0) == $carId) { $testCar = $car; break; }
}
$firstPassengerId = $testCar['passengers'][0]['passenger_id'] ?? 0;
if ($firstPassengerId > 0) {
    $pasRem = api_post($sc, '/api/cars.php', [
        'action'       => 'remove_passenger',
        'passenger_id' => $firstPassengerId,
    ]);
    assert_equals('CARS-5: Odebrání spolujezdce → success=true', true, $pasRem['json']['success'] ?? false);
}

// CARS-6: Smazání auta
$carDel = api_post($sc, '/api/cars.php', [
    'action' => 'delete_car',
    'id'     => $carId,
]);
assert_equals('CARS-6: Smazání auta → success=true', true, $carDel['json']['success'] ?? false);

// ============================================================
// CHECKLIST TESTY
// ============================================================

section('CHECKLIST – Položky');

$sch = login_member(1);

// CHECK-1: Přidání položky
$chAdd = api_post($sch, '/api/checklist.php', [
    'action'      => 'add',
    'item_name'   => 'Test integrace',
    'category'    => 'vybaveni',
    'description' => 'Popis testu',
]);
assert_equals('CHECK-1: Přidání položky → success=true', true, $chAdd['json']['success'] ?? false);
$chId = $chAdd['json']['data']['id'] ?? 0;
assert_true('CHECK-1: Vráceno ID', $chId > 0);

// CHECK-2: Nevalidní kategorie → fallback na 'doporucene'
$chBadCat = api_post($sch, '/api/checklist.php', [
    'action'    => 'add',
    'item_name' => 'Test špatná kategorie',
    'category'  => 'INVALID_CATEGORY',
]);
assert_equals('CHECK-2: Nevalidní kategorie → success=true (s fallback)', true, $chBadCat['json']['success'] ?? false);
$chIdBad = $chBadCat['json']['data']['id'] ?? 0;

// CHECK-3: List vrátí položky
$chList = api_get($sch, '/api/checklist.php', ['action' => 'list']);
assert_equals('CHECK-3: List checklist → success=true', true, $chList['json']['success'] ?? false);
assert_true('CHECK-3: List není prázdný', count($chList['json']['data'] ?? []) > 0);

// CHECK-4: Editace položky
$chEdit = api_post($sch, '/api/checklist.php', [
    'action'    => 'edit',
    'id'        => $chId,
    'item_name' => 'Upravená položka',
    'category'  => 'povinne',
]);
assert_equals('CHECK-4: Editace → success=true', true, $chEdit['json']['success'] ?? false);

// CHECK-5: Smazání vlastní položky
$chDel = api_post($sch, '/api/checklist.php', [
    'action' => 'delete',
    'id'     => $chId,
]);
assert_equals('CHECK-5: Smazání → success=true', true, $chDel['json']['success'] ?? false);

// Cleanup
if ($chIdBad > 0) {
    api_post($sch, '/api/checklist.php', ['action' => 'delete', 'id' => $chIdBad]);
}

// ============================================================
// EXCHANGE RATE
// ============================================================

section('EXCHANGE RATE – Kurz');

$se = login_member(1);

// EXCH-1: Vrátí aktuální kurz
$exchRes = api_get($se, '/api/exchange.php');
assert_equals('EXCH-1: Kurz → success=true', true, $exchRes['json']['success'] ?? false);
assert_true('EXCH-1: Rate > 0', (float)($exchRes['json']['data']['rate'] ?? 0) > 0);

// ============================================================
// STRÁNKY – HTTP 200
// ============================================================

section('PAGES – HTTP status');

$sp = login_member(1);
$pages = [
    '/pages/dashboard.php'  => 'Dashboard',
    '/pages/wallet.php'     => 'Wallet',
    '/pages/shopping.php'   => 'Shopping',
    '/pages/logbook.php'    => 'Logbook',
    '/pages/menu.php'       => 'Menu',
    '/pages/cars.php'       => 'Cars',
    '/pages/checklist.php'  => 'Checklist',
    '/pages/itinerary.php'  => 'Itinerary',
    '/pages/crews.php'      => 'Crews',
];
foreach ($pages as $path => $name) {
    $r = http_get($sp, $path);
    assert_equals("PAGE: {$name} → HTTP 200", 200, $r['code']);
}

// Admin stránky
$spa = login_admin();
$adminPages = [
    '/admin/index.php'    => 'Admin dashboard',
    '/admin/users.php'    => 'Admin users',
    '/admin/settings.php' => 'Admin settings',
];
foreach ($adminPages as $path => $name) {
    $r = http_get($spa, $path);
    assert_equals("PAGE: {$name} → HTTP 200", 200, $r['code']);
}

// ============================================================
// Výsledky
// ============================================================

echo "\n";
echo str_repeat('=', 60) . "\n";
$total = $passed + $failed;
if ($failed === 0) {
    echo "\033[1;32m  PASS: {$passed}/{$total} testů prošlo\033[0m\n";
} else {
    echo "\033[1;31m  FAIL: {$failed} selhalo, {$passed}/{$total} prošlo\033[0m\n";
    echo "\n  Selhané testy:\n";
    foreach ($errors as $e) {
        echo "    - {$e}\n";
    }
}
echo str_repeat('=', 60) . "\n";

exit($failed > 0 ? 1 : 0);
