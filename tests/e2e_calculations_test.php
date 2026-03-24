<?php
/**
 * VOL5 – E2E kalkulační testy pokladny
 *
 * Ověřuje end-to-end matematiku přes celý stack:
 *   HTTP API → PHP logika → MySQL DB → bilance → settlement
 *
 * Každý výdaj se přidá přes skutečné HTTP POST (jako by klikl uživatel),
 * poté se přímo v DB ověří matematická správnost splitů, bilancí a settlements.
 *
 * Spuštění:
 *   docker exec -e RUNNING_IN_DOCKER=1 vol5_web php /var/www/html/tests/e2e_calculations_test.php
 */

// ============================================================
// Konfigurace
// ============================================================

$_baseUrl = (file_exists('/.dockerenv') || getenv('RUNNING_IN_DOCKER'))
    ? 'http://host.docker.internal:8080'
    : 'http://localhost:8080';
define('BASE_URL', $_baseUrl);
define('MEMBER_PASS', 'crew123');

// DB spojení přímo (z hostitele: 127.0.0.1:3307, z kontejneru: db:3306)
$_dbHost = (file_exists('/.dockerenv') || getenv('RUNNING_IN_DOCKER')) ? 'db' : '127.0.0.1';
$_dbPort = (file_exists('/.dockerenv') || getenv('RUNNING_IN_DOCKER')) ? '3306' : '3307';
define('DB_DSN', "mysql:host={$_dbHost};port={$_dbPort};dbname=vol5;charset=utf8mb4");
define('DB_USER', 'vol5user');
define('DB_PASS', 'vol5pass');

// ============================================================
// Test runner
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

function assert_true(string $label, bool $condition, string $detail = ''): void
{
    global $passed, $failed, $errors;
    if ($condition) {
        echo "\033[32m  ✓ {$label}\033[0m\n";
        $passed++;
    } else {
        echo "\033[31m  ✗ {$label}\033[0m\n";
        if ($detail) echo "      detail: {$detail}\n";
        $failed++;
        $errors[] = $label;
    }
}

function section(string $name): void
{
    echo "\n\033[1;34m▶ {$name}\033[0m\n";
}

// ============================================================
// DB helper
// ============================================================

function getTestDB(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

/**
 * Ověří v DB že SUM(splits) == amount_eur pro daný expense_id.
 * Vrátí [ok, sum_splits, amount_eur, diff].
 */
function verifySplitSumInDB(int $expenseId): array
{
    $db = getTestDB();

    $exp = $db->prepare("SELECT amount_eur FROM wallet_expenses WHERE id = ?");
    $exp->execute([$expenseId]);
    $expRow = $exp->fetch();
    if (!$expRow) return ['ok' => false, 'error' => 'expense not found'];

    $splitsSum = $db->prepare("SELECT COALESCE(SUM(amount_eur), 0) FROM wallet_expense_splits WHERE expense_id = ?");
    $splitsSum->execute([$expenseId]);
    $sum = (float) $splitsSum->fetchColumn();

    $amountEur = (float) $expRow['amount_eur'];
    $diff = abs($sum - $amountEur);

    return [
        'ok'         => $diff < 0.005,
        'sum_splits' => round($sum, 4),
        'amount_eur' => round($amountEur, 4),
        'diff'       => round($diff, 4),
    ];
}

/**
 * Vrátí všechny splity pro expense_id z DB.
 */
function getSplitsFromDB(int $expenseId): array
{
    $db = getTestDB();
    $stmt = $db->prepare("SELECT user_id, amount_eur FROM wallet_expense_splits WHERE expense_id = ? ORDER BY id");
    $stmt->execute([$expenseId]);
    return $stmt->fetchAll();
}

/**
 * Vrátí exchange_rate uložený pro výdaj v DB.
 */
function getExpenseFromDB(int $expenseId): ?array
{
    $db = getTestDB();
    $stmt = $db->prepare("SELECT * FROM wallet_expenses WHERE id = ?");
    $stmt->execute([$expenseId]);
    return $stmt->fetch() ?: null;
}

/**
 * Vrátí bilanci uživatele přímo z DB (paid - share).
 */
function getUserBalanceFromDB(int $userId): float
{
    $db = getTestDB();
    $paid = (float) $db->prepare("SELECT COALESCE(SUM(amount_eur),0) FROM wallet_expenses WHERE paid_by = ?")
        ->execute([$userId]) && $db->prepare("SELECT COALESCE(SUM(amount_eur),0) FROM wallet_expenses WHERE paid_by = ?");
    // Re-run properly
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount_eur),0) FROM wallet_expenses WHERE paid_by = ?");
    $stmt->execute([$userId]);
    $paid = (float) $stmt->fetchColumn();

    $stmt2 = $db->prepare("SELECT COALESCE(SUM(amount_eur),0) FROM wallet_expense_splits WHERE user_id = ?");
    $stmt2->execute([$userId]);
    $share = (float) $stmt2->fetchColumn();

    return round($paid - $share, 2);
}

// ============================================================
// HTTP helpers (kopiie z integration_test.php)
// ============================================================

function new_session(): array
{
    return ['cookie_file' => tempnam(sys_get_temp_dir(), 'vol5_e2e_')];
}

function http_get_raw(array &$session, string $path): array
{
    $ch = curl_init(BASE_URL . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR      => $session['cookie_file'],
        CURLOPT_COOKIEFILE     => $session['cookie_file'],
        CURLOPT_TIMEOUT        => 10,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => $body];
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
    curl_close($ch);
    return ['json' => json_decode($body, true), 'body' => $body];
}

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
        CURLOPT_HTTPHEADER     => ['X-Requested-With: XMLHttpRequest'],
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    $json = json_decode($body, true);
    return ['json' => $json, 'body' => $body];
}

function login_member(int $userId = 1): array
{
    $s = new_session();
    $get = http_get_raw($s, '/index.php');
    if (preg_match('/name="csrf_token"\s+value="([a-f0-9]+)"/', $get['body'], $m)) {
        $s['csrf_token'] = $m[1];
    }
    $r = api_post($s, '/index.php', [
        'login_type'      => 'member',
        'user_id'         => $userId,
        'member_password' => MEMBER_PASS,
    ]);
    // Po loginu CSRF se regeneruje – vezmi nový z redirected stránky
    $dash = http_get_raw($s, '/pages/dashboard.php');
    if (preg_match('/name="csrf_token"\s+value="([a-f0-9]+)"/', $dash['body'], $m)) {
        $s['csrf_token'] = $m[1];
    }
    return $s;
}

// ============================================================
// Cleanup registrace – smaže cookie soubory
// ============================================================
$createdExpenseIds = [];
register_shutdown_function(function () use (&$createdExpenseIds) {
    foreach (glob(sys_get_temp_dir() . '/vol5_e2e_*') as $f) {
        @unlink($f);
    }
});

// ============================================================
// Ověření serveru a DB dostupnosti
// ============================================================
$ping = @file_get_contents(BASE_URL . '/index.php');
if ($ping === false) {
    echo "\033[31m[FATAL] Server není dostupný na " . BASE_URL . "\033[0m\n";
    exit(1);
}
try {
    getTestDB();
    echo "\033[32m[OK] Server + DB dostupné\033[0m\n";
} catch (Exception $e) {
    echo "\033[31m[FATAL] DB nedostupná: " . $e->getMessage() . "\033[0m\n";
    exit(1);
}

// Načíst aktuální kurz z aplikace
$rateSession = login_member(1);
$rateRes = api_get($rateSession, '/api/exchange.php');
$RATE = (float)($rateRes['json']['data']['rate'] ?? 25.0);
echo "\033[36m[INFO] Aktuální kurz CZK/EUR: {$RATE}\033[0m\n";

// Načíst existující výdaje v DB (abychom je ignorovali v bilancích)
$db = getTestDB();
$existingIds = $db->query("SELECT id FROM wallet_expenses")->fetchAll(PDO::FETCH_COLUMN);
$existingExpenses = count($existingIds);
echo "\033[36m[INFO] Existující výdajů v DB: {$existingExpenses}\033[0m\n";

// ============================================================
// Přihlásíme se jednou jako user 1 (loď 1)
// ============================================================
$s = login_member(1);

// ============================================================
// SCÉNÁŘ: 6 uživatelů (ID 1–6), 5 výdajů
//
// Uživatelé z seed dat:
//   1 = Pavel   (loď 1)
//   2 = Jana    (loď 1)
//   3 = Tomáš   (loď 1)
//   4 = Lucie   (loď 2)
//   5 = Martin  (loď 2)
//   6 = Eva     (loď 2)
//
// Výdaje:
//   E1: Pavel zaplatí 60.00 EUR, split 6 lidí [1,2,3,4,5,6]
//       → 60/6 = 10.00 EUR každý (přesně dělitelné)
//   E2: Jana zaplatí 100.00 EUR, split 3 lidi [1,2,3]
//       → floor(100/3*100)/100 = 33.33, zbytek = 0.01 → [33.34, 33.33, 33.33]
//   E3: Tomáš zaplatí 50.00 EUR, split 6 lidí [1,2,3,4,5,6]
//       → floor(50/6*100)/100 = 8.33, zbytek = 0.02 → [8.35, 8.33, 8.33, 8.33, 8.33, 8.33]
//   E4: Eva zaplatí 1500.00 CZK, split 6 lidí [1,2,3,4,5,6]
//       → amount_eur = round(1500/RATE, 2), splits sum musí == amount_eur
//   E5: Martin zaplatí 0.07 EUR, split 3 lidi [4,5,6]
//       → floor(0.07/3*100)/100 = 0.02, zbytek = 0.01 → [0.03, 0.02, 0.02]
// ============================================================

section('SETUP – Přidání testovacích výdajů přes API');

$testExpenses = [
    [
        'label'       => 'E1: Pavel 60 EUR / 6 lidí (dělitelné)',
        'paid_by'     => 1,
        'amount'      => 60.00,
        'currency'    => 'EUR',
        'description' => '[E2E-TEST] Pavel nákup potravin',
        'split_users' => '1,2,3,4,5,6',
        'split_type'  => 'both',
        'expense_date'=> '2025-07-21 10:00:00',
        'expected_eur'=> 60.00,
        'expected_splits' => [10.00, 10.00, 10.00, 10.00, 10.00, 10.00],
    ],
    [
        'label'       => 'E2: Jana 100 EUR / 3 lidi (zbytek 0.01)',
        'paid_by'     => 2,
        'amount'      => 100.00,
        'currency'    => 'EUR',
        'description' => '[E2E-TEST] Jana diesel',
        'split_users' => '1,2,3',
        'split_type'  => 'boat1',
        'expense_date'=> '2025-07-21 11:00:00',
        'expected_eur'=> 100.00,
        'expected_splits' => [33.34, 33.33, 33.33],
    ],
    [
        'label'       => 'E3: Tomáš 50 EUR / 6 lidí (zbytek 0.02)',
        'paid_by'     => 3,
        'amount'      => 50.00,
        'currency'    => 'EUR',
        'description' => '[E2E-TEST] Tomáš mariina',
        'split_users' => '1,2,3,4,5,6',
        'split_type'  => 'both',
        'expense_date'=> '2025-07-21 12:00:00',
        'expected_eur'=> 50.00,
        'expected_splits' => [8.35, 8.33, 8.33, 8.33, 8.33, 8.33],
    ],
    [
        'label'       => 'E4: Eva 1500 CZK / 6 lidí (přepočet + split)',
        'paid_by'     => 6,
        'amount'      => 1500.00,
        'currency'    => 'CZK',
        'description' => '[E2E-TEST] Eva léky',
        'split_users' => '1,2,3,4,5,6',
        'split_type'  => 'both',
        'expense_date'=> '2025-07-21 13:00:00',
        'expected_eur'=> null, // dynamicky dle kurzu
        'expected_splits' => null,
    ],
    [
        'label'       => 'E5: Martin 0.07 EUR / 3 lidi (haléřový edge case)',
        'paid_by'     => 5,
        'amount'      => 0.07,
        'currency'    => 'EUR',
        'description' => '[E2E-TEST] Martin tip',
        'split_users' => '4,5,6',
        'split_type'  => 'boat2',
        'expense_date'=> '2025-07-21 14:00:00',
        'expected_eur'=> 0.07,
        'expected_splits' => [0.03, 0.02, 0.02],
    ],
];

// Dynamicky vypočítáme expected pro E4
$e4Eur = round(1500.0 / $RATE, 2);
$e4PerPerson = floor($e4Eur / 6 * 100) / 100;
$e4Remainder = round($e4Eur - ($e4PerPerson * 6), 2);
$e4Splits = array_fill(0, 6, $e4PerPerson);
$e4Splits[0] = round($e4PerPerson + $e4Remainder, 2);
$testExpenses[3]['expected_eur'] = $e4Eur;
$testExpenses[3]['expected_splits'] = $e4Splits;

// Přidat všechny výdaje přes API
$addedIds = [];
foreach ($testExpenses as $i => $exp) {
    $res = api_post($s, '/api/wallet.php', [
        'action'       => 'add',
        'paid_by'      => $exp['paid_by'],
        'amount'       => $exp['amount'],
        'currency'     => $exp['currency'],
        'description'  => $exp['description'],
        'expense_date' => $exp['expense_date'],
        'split_type'   => $exp['split_type'],
        'split_users'  => $exp['split_users'],
    ]);
    $id = $res['json']['data']['id'] ?? 0;
    assert_true("SETUP {$exp['label']} → přidán (ID={$id})", $id > 0,
        'API: ' . ($res['json']['error'] ?? $res['body']));
    $addedIds[$i] = $id;
    $createdExpenseIds[] = $id;
}

// ============================================================
// BLOK 1: Ověření splitů v DB
// ============================================================

section('SPLITS – SUM(splits v DB) == amount_eur pro každý výdaj');

foreach ($testExpenses as $i => $exp) {
    $id = $addedIds[$i];
    if (!$id) continue;

    $check = verifySplitSumInDB($id);
    assert_true(
        "{$exp['label']} – SUM(splits) == amount_eur",
        $check['ok'],
        "SUM={$check['sum_splits']}, amount_eur={$check['amount_eur']}, diff={$check['diff']}"
    );
}

// ============================================================
// BLOK 2: Ověření konkrétních hodnot splitů v DB
// ============================================================

section('SPLITS – Konkrétní hodnoty splits odpovídají očekávání');

foreach ($testExpenses as $i => $exp) {
    $id = $addedIds[$i];
    if (!$id || !$exp['expected_splits']) continue;

    $splits = getSplitsFromDB($id);
    $actualAmounts = array_column($splits, 'amount_eur');

    assert_equals(
        "{$exp['label']} – počet splitů v DB",
        count($exp['expected_splits']),
        count($actualAmounts)
    );

    foreach ($exp['expected_splits'] as $j => $expectedAmt) {
        $actualAmt = isset($actualAmounts[$j]) ? (float)$actualAmounts[$j] : null;
        assert_true(
            "{$exp['label']} – split[{$j}] == {$expectedAmt} EUR",
            $actualAmt !== null && abs($actualAmt - $expectedAmt) < 0.005,
            "expected={$expectedAmt}, actual={$actualAmt}"
        );
    }
}

// ============================================================
// BLOK 3: Ověření amount_eur v DB pro EUR výdaje
// ============================================================

section('AMOUNT_EUR – Správný přepočet v DB');

foreach ($testExpenses as $i => $exp) {
    $id = $addedIds[$i];
    if (!$id) continue;

    $row = getExpenseFromDB($id);
    $actualEur = (float)($row['amount_eur'] ?? -1);
    $expectedEur = $exp['expected_eur'];

    assert_true(
        "{$exp['label']} – amount_eur v DB",
        abs($actualEur - $expectedEur) < 0.005,
        "expected={$expectedEur}, actual={$actualEur}"
    );

    if ($exp['currency'] === 'CZK') {
        // CZK výdaj musí mít exchange_rate uložený v DB
        $rate = (float)($row['exchange_rate'] ?? 0);
        assert_true(
            "{$exp['label']} – exchange_rate uložen v DB",
            $rate > 0,
            "exchange_rate={$rate}"
        );
        // amount_eur musí souhlasit s amount/rate
        $computedEur = round((float)$row['amount'] / $rate, 2);
        assert_true(
            "{$exp['label']} – amount_eur == amount/rate",
            abs($actualEur - $computedEur) < 0.005,
            "amount={$row['amount']}, rate={$rate}, computed={$computedEur}, stored={$actualEur}"
        );
    }
}

// ============================================================
// BLOK 4: Ověření editace – splits se přepočítají
// ============================================================

section('EDIT – Editace výdaje přepočítá splits správně');

$editId = $addedIds[0]; // E1: Pavel 60 EUR / 6 lidí
if ($editId) {
    // Před editem: 6 splitů po 10.00
    $splitsBefore = getSplitsFromDB($editId);
    assert_equals('EDIT před: 6 splitů', 6, count($splitsBefore));

    // Editace: změníme na 90 EUR, 3 lidi [1,2,3]
    // Expected splits: 30.00 + 30.00 + 30.00
    $editRes = api_post($s, '/api/wallet.php', [
        'action'       => 'edit',
        'id'           => $editId,
        'paid_by'      => 1,
        'amount'       => 90.00,
        'currency'     => 'EUR',
        'description'  => '[E2E-TEST] Pavel nákup potravin (editováno)',
        'expense_date' => '2025-07-21 10:00:00',
        'split_type'   => 'both',
        'split_users'  => '1,2,3',
    ]);
    assert_equals('EDIT → success=true', true, $editRes['json']['success'] ?? false);

    // Po editu: 3 splity po 30.00
    $splitsAfter = getSplitsFromDB($editId);
    assert_equals('EDIT po: 3 splity', 3, count($splitsAfter));

    $checkAfter = verifySplitSumInDB($editId);
    assert_true(
        'EDIT – SUM(splits) == 90.00 po editaci',
        $checkAfter['ok'],
        "SUM={$checkAfter['sum_splits']}, amount_eur={$checkAfter['amount_eur']}"
    );

    // Každý split = 30.00
    foreach ($splitsAfter as $sp) {
        assert_equals('EDIT – každý split == 30.00', 30.00, (float)$sp['amount_eur']);
    }

    // Editace zpět na původní (60 EUR / 6 lidí) pro správné bilance
    api_post($s, '/api/wallet.php', [
        'action'       => 'edit',
        'id'           => $editId,
        'paid_by'      => 1,
        'amount'       => 60.00,
        'currency'     => 'EUR',
        'description'  => '[E2E-TEST] Pavel nákup potravin',
        'expense_date' => '2025-07-21 10:00:00',
        'split_type'   => 'both',
        'split_users'  => '1,2,3,4,5,6',
    ]);
}

// ============================================================
// BLOK 5: Ověření editace CZK – zachování původního kurzu
// ============================================================

section('EDIT CZK – Editace zachová původní exchange_rate');

$czkId = $addedIds[3]; // E4: Eva 1500 CZK
if ($czkId) {
    $rowBefore = getExpenseFromDB($czkId);
    $originalRate = (float)$rowBefore['exchange_rate'];
    $originalAmountEur = (float)$rowBefore['amount_eur'];

    // Editace: jen popis, částka 1500 CZK (beze změny)
    $editCzkRes = api_post($s, '/api/wallet.php', [
        'action'       => 'edit',
        'id'           => $czkId,
        'paid_by'      => 6,
        'amount'       => 1500.00,
        'currency'     => 'CZK',
        'description'  => '[E2E-TEST] Eva léky (editováno)',
        'expense_date' => '2025-07-21 13:00:00',
        'split_type'   => 'both',
        'split_users'  => '1,2,3,4,5,6',
    ]);
    assert_equals('EDIT CZK → success=true', true, $editCzkRes['json']['success'] ?? false);

    $rowAfter = getExpenseFromDB($czkId);
    $rateAfter = (float)$rowAfter['exchange_rate'];
    $eurAfter = (float)$rowAfter['amount_eur'];

    assert_equals(
        'EDIT CZK – exchange_rate se nezměnil',
        $originalRate,
        $rateAfter
    );
    assert_equals(
        'EDIT CZK – amount_eur se nezměnil',
        $originalAmountEur,
        $eurAfter
    );

    // Splits stále musí souhlasit
    $checkCzk = verifySplitSumInDB($czkId);
    assert_true(
        'EDIT CZK – SUM(splits) == amount_eur po editaci',
        $checkCzk['ok'],
        "SUM={$checkCzk['sum_splits']}, amount_eur={$checkCzk['amount_eur']}"
    );
}

// ============================================================
// BLOK 6: Globální integrita všech splits v DB
// ============================================================

section('INTEGRITA – SUM(splits) == amount_eur pro VŠECHNY výdaje v DB');

$db = getTestDB();
$allExpenses = $db->query("SELECT id, description, amount_eur FROM wallet_expenses")->fetchAll();
$integritySumOk = true;
$integrityErrors = [];

foreach ($allExpenses as $row) {
    $check = verifySplitSumInDB((int)$row['id']);
    if (!$check['ok']) {
        $integritySumOk = false;
        $integrityErrors[] = "ID={$row['id']} ({$row['description']}): diff={$check['diff']}";
    }
}

assert_true(
    'Integrita – žádný výdaj nemá rozbité splity',
    $integritySumOk,
    implode(', ', $integrityErrors)
);

// Ověř žádné výdaje bez splitů
$orphans = $db->query("
    SELECT we.id, we.description
    FROM wallet_expenses we
    LEFT JOIN wallet_expense_splits wes ON wes.expense_id = we.id
    WHERE wes.id IS NULL
")->fetchAll();
assert_true(
    'Integrita – žádný výdaj bez splitů (osiřelé záznamy)',
    count($orphans) === 0,
    implode(', ', array_column($orphans, 'description'))
);

// ============================================================
// BLOK 7: Ověření bilancí přes API vs. DB výpočet
// ============================================================

section('BILANCE – API výsledky souhlasí s přímým DB výpočtem');

$balRes = api_get($s, '/api/wallet.php', ['action' => 'balances']);
$apiBals = [];
foreach ($balRes['json']['data'] ?? [] as $b) {
    $apiBals[$b['user_id']] = $b;
}

// Ověřit pro každého ze 6 uživatelů
foreach ([1, 2, 3, 4, 5, 6] as $uid) {
    if (!isset($apiBals[$uid])) {
        assert_true("BILANCE user {$uid} – vrácen v API", false);
        continue;
    }
    $apiBalance = (float)$apiBals[$uid]['balance'];
    $dbBalance  = getUserBalanceFromDB($uid);

    assert_true(
        "BILANCE user {$uid} – API ({$apiBalance}) == DB ({$dbBalance})",
        abs($apiBalance - $dbBalance) < 0.005,
        "API={$apiBalance}, DB={$dbBalance}"
    );
}

// ============================================================
// BLOK 8: Matematická konzistence bilancí
// ============================================================

section('BILANCE – Matematická konzistence');

$allBalances = array_column($balRes['json']['data'] ?? [], 'balance', 'user_id');
$totalBalance = array_sum($allBalances);
assert_true(
    'BILANCE – SUM(všech bilancí) ≈ 0 (co jeden zaplatil, druhý dluží)',
    abs($totalBalance) < 0.10,
    "SUM bilancí = {$totalBalance}"
);

$posBalances = array_filter($allBalances, fn($b) => $b > 0.005);
$negBalances = array_filter($allBalances, fn($b) => $b < -0.005);
$sumPos = array_sum($posBalances);
$sumNeg = abs(array_sum($negBalances));
assert_true(
    'BILANCE – SUM(kladných) ≈ SUM(záporných)',
    abs($sumPos - $sumNeg) < 0.10,
    "SUM+ = {$sumPos}, SUM- = {$sumNeg}"
);

// Konkrétní ruční výpočet bilancí pro E1–E5 (pouze naše testovací výdaje):
// Ale protože DB obsahuje i seed data, ověříme jen invariant: SUM = 0
// a že API == DB (ověřeno výše v bloku 7).

// ============================================================
// BLOK 9: Ověření settlements
// ============================================================

section('SETTLEMENTS – Matematická správnost');

$setRes = api_get($s, '/api/wallet.php', ['action' => 'settlements']);
assert_equals('SETTLEMENTS – success=true', true, $setRes['json']['success'] ?? false);

$settlements = $setRes['json']['data']['settlements'] ?? [];

// SUM(settlement transakcí) musí == SUM(záporných bilancí)
$sumSettlements = array_sum(array_column($settlements, 'amount'));
assert_true(
    'SETTLEMENTS – SUM(transakcí) ≈ SUM(dluhů)',
    abs($sumSettlements - $sumNeg) < 0.10,
    "SUM settlements = {$sumSettlements}, SUM dluhů = {$sumNeg}"
);

// Každý settlement.amount musí být > 0
foreach ($settlements as $i => $st) {
    assert_true(
        "SETTLEMENTS – transakce [{$i}] má amount > 0",
        (float)$st['amount'] > 0,
        "amount={$st['amount']}"
    );
}

// Max N-1 transakcí pro N osob s nenulovou bilancí
$nWithBalance = count($posBalances) + count($negBalances);
if ($nWithBalance > 1) {
    assert_true(
        'SETTLEMENTS – počet transakcí ≤ N-1 (greedy optimalizace)',
        count($settlements) <= max(1, $nWithBalance - 1),
        'count=' . count($settlements) . ", N-1=" . ($nWithBalance - 1)
    );
}

// Simulace: po zaplacení všech settlements musí být každý na ~0
// (matematická simulace, ne skutečné platby)
$simulatedBalances = $allBalances;
foreach ($settlements as $st) {
    $from = $st['from_id'];
    $to   = $st['to_id'];
    $amt  = (float)$st['amount'];
    if (isset($simulatedBalances[$from])) $simulatedBalances[$from] = round($simulatedBalances[$from] + $amt, 2);
    if (isset($simulatedBalances[$to]))   $simulatedBalances[$to]   = round($simulatedBalances[$to] - $amt, 2);
}
$allNearZero = true;
$nearZeroErrors = [];
foreach ($simulatedBalances as $uid => $bal) {
    if (abs($bal) > 0.05) {
        $allNearZero = false;
        $nearZeroErrors[] = "user {$uid}: {$bal}";
    }
}
assert_true(
    'SETTLEMENTS – po zaplacení všech transakcí jsou všichni na ~0 EUR',
    $allNearZero,
    implode(', ', $nearZeroErrors)
);

// ============================================================
// BLOK 10: Velký scénář – 10 výdajů, různé kombinace
// ============================================================

section('STRESS – 10 výdajů, různé částky a počty lidí');

$stressExpenses = [
    ['paid_by' => 1, 'amount' => 123.45, 'users' => '1,2,3,4,5,6'],
    ['paid_by' => 2, 'amount' => 0.99,   'users' => '1,2'],
    ['paid_by' => 3, 'amount' => 777.77, 'users' => '1,2,3,4,5,6'],
    ['paid_by' => 4, 'amount' => 33.33,  'users' => '4,5,6'],
    ['paid_by' => 5, 'amount' => 1000.00,'users' => '1,2,3,4,5,6'],
    ['paid_by' => 6, 'amount' => 0.01,   'users' => '1,2,3'],
    ['paid_by' => 1, 'amount' => 456.78, 'users' => '1,2,3'],
    ['paid_by' => 2, 'amount' => 89.10,  'users' => '4,5,6'],
    ['paid_by' => 3, 'amount' => 5.55,   'users' => '1,2,3,4,5,6'],
    ['paid_by' => 4, 'amount' => 2500.00,'currency' => 'CZK', 'users' => '1,2,3,4,5,6'],
];

$stressIds = [];
foreach ($stressExpenses as $j => $se) {
    $res = api_post($s, '/api/wallet.php', [
        'action'       => 'add',
        'paid_by'      => $se['paid_by'],
        'amount'       => $se['amount'],
        'currency'     => $se['currency'] ?? 'EUR',
        'description'  => "[E2E-STRESS] výdaj {$j}",
        'expense_date' => '2025-07-22 10:00:00',
        'split_type'   => 'both',
        'split_users'  => $se['users'],
    ]);
    $sid = $res['json']['data']['id'] ?? 0;
    if ($sid > 0) {
        $stressIds[] = $sid;
        $createdExpenseIds[] = $sid;
        $check = verifySplitSumInDB($sid);
        assert_true(
            "STRESS výdaj[{$j}]: {$se['amount']} EUR / " . count(explode(',', $se['users'])) . " lidí – SUM splits OK",
            $check['ok'],
            "SUM={$check['sum_splits']}, amount_eur={$check['amount_eur']}, diff={$check['diff']}"
        );
    } else {
        assert_true("STRESS výdaj[{$j}] – přidán", false, $res['json']['error'] ?? '');
    }
}

// Finální globální integrita po stress testu
$allExpensesAfter = $db->query("SELECT id FROM wallet_expenses")->fetchAll(PDO::FETCH_COLUMN);
$stressIntegrityOk = true;
$stressIntegrityErrors = [];
foreach ($allExpensesAfter as $eid) {
    $ch = verifySplitSumInDB((int)$eid);
    if (!$ch['ok']) {
        $stressIntegrityOk = false;
        $stressIntegrityErrors[] = "ID={$eid}: diff={$ch['diff']}";
    }
}
assert_true(
    'STRESS – globální integrita splitů po stress testu',
    $stressIntegrityOk,
    implode(', ', $stressIntegrityErrors)
);

// Bilance po stresu: SUM ≈ 0
$balStress = api_get($s, '/api/wallet.php', ['action' => 'balances']);
$balStressTotal = array_sum(array_column($balStress['json']['data'] ?? [], 'balance'));
assert_true(
    'STRESS – SUM(bilancí) ≈ 0 po stress testu',
    abs($balStressTotal) < 0.20,
    "SUM = {$balStressTotal}"
);

// ============================================================
// CLEANUP – smazání všech testovacích výdajů
// ============================================================

section('CLEANUP – Smazání testovacích výdajů');

$cleanedOk = true;
foreach ($createdExpenseIds as $eid) {
    $delRes = api_post($s, '/api/wallet.php', ['action' => 'delete', 'id' => $eid]);
    if (!($delRes['json']['success'] ?? false)) {
        $cleanedOk = false;
    }
}
assert_true('CLEANUP – všechny testovací výdaje smazány', $cleanedOk);

// Ověřit že v DB zbyl původní počet výdajů
$remainingCount = (int)$db->query("SELECT COUNT(*) FROM wallet_expenses")->fetchColumn();
assert_equals(
    'CLEANUP – DB vrácena do původního stavu',
    $existingExpenses,
    $remainingCount
);

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
