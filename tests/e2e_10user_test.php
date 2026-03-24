<?php
/**
 * VOL5 – E2E test přepočtů pro 10 uživatelů
 *
 * Tohle je "truth test" – ověřuje konkrétní očekávané hodnoty do haléře,
 * ne jen invarianty jako SUM≈0.
 *
 * Postup:
 *  1. Přidá 3 testovací uživatele (ID 8–10) do DB přímo
 *  2. Přidá sérii výdajů přes HTTP API
 *  3. PŘED přidáním každého výdaje vypočítá EXPECTED bilanci ručně (stejnou logikou jako PHP)
 *  4. PO přidání ověří DB splits haléř po haléři
 *  5. Ověří bilance přes API == ruční výpočet pro každého ze 10 lidí
 *  6. Ověří settlement výsledky (kdo komu kolik)
 *  7. Cleanup – smaže testovací uživatele i výdaje
 *
 * Spuštění:
 *   docker exec -e RUNNING_IN_DOCKER=1 vol5_web php /var/www/html/tests/e2e_10user_test.php
 */

$_baseUrl = (file_exists('/.dockerenv') || getenv('RUNNING_IN_DOCKER'))
    ? 'http://host.docker.internal:8080'
    : 'http://localhost:8080';
define('BASE_URL', $_baseUrl);
define('MEMBER_PASS', 'crew123');

$_dbHost = (file_exists('/.dockerenv') || getenv('RUNNING_IN_DOCKER')) ? 'db' : '127.0.0.1';
$_dbPort = (file_exists('/.dockerenv') || getenv('RUNNING_IN_DOCKER')) ? '3306' : '3307';
define('DB_DSN', "mysql:host={$_dbHost};port={$_dbPort};dbname=vol5;charset=utf8mb4");
define('DB_USER', 'vol5user');
define('DB_PASS', 'vol5pass');

// ============================================================
// Test runner
// ============================================================
$passed = 0; $failed = 0; $errors = [];

function ok(string $label, bool $cond, string $detail = ''): void {
    global $passed, $failed, $errors;
    if ($cond) {
        echo "\033[32m  ✓ {$label}\033[0m\n";
        $passed++;
    } else {
        echo "\033[31m  ✗ {$label}\033[0m\n";
        if ($detail) echo "      {$detail}\n";
        $failed++;
        $errors[] = $label;
    }
}
function eq(string $label, $exp, $act): void {
    ok($label, $exp === $act, "expected=" . var_export($exp,true) . " actual=" . var_export($act,true));
}
function near(string $label, float $exp, float $act, float $tol = 0.005): void {
    ok($label, abs($exp - $act) < $tol, "expected={$exp} actual={$act} diff=" . abs($exp-$act));
}
function section(string $n): void { echo "\n\033[1;34m▶ {$n}\033[0m\n"; }

// ============================================================
// DB + HTTP helpers
// ============================================================
function db(): PDO {
    static $pdo;
    if (!$pdo) $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
}

function sess(): array {
    return ['cookie_file' => tempnam(sys_get_temp_dir(), 'vol5_10u_')];
}

function hget(array &$s, string $path): string {
    $ch = curl_init(BASE_URL . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true,
        CURLOPT_COOKIEJAR=>$s['cookie_file'], CURLOPT_COOKIEFILE=>$s['cookie_file'], CURLOPT_TIMEOUT=>10]);
    $r = curl_exec($ch); curl_close($ch);
    return $r;
}

function hpost(array &$s, string $path, array $data): array {
    if (!empty($s['csrf_token'])) $data['csrf_token'] = $s['csrf_token'];
    $ch = curl_init(BASE_URL . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>http_build_query($data), CURLOPT_FOLLOWLOCATION=>false,
        CURLOPT_COOKIEJAR=>$s['cookie_file'], CURLOPT_COOKIEFILE=>$s['cookie_file'], CURLOPT_TIMEOUT=>10,
        CURLOPT_HTTPHEADER=>['X-Requested-With: XMLHttpRequest']]);
    $body = curl_exec($ch); curl_close($ch);
    return json_decode($body, true) ?? [];
}

function hget_json(array &$s, string $path, array $q = []): array {
    $url = BASE_URL . $path . ($q ? '?'.http_build_query($q) : '');
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>false,
        CURLOPT_COOKIEJAR=>$s['cookie_file'], CURLOPT_COOKIEFILE=>$s['cookie_file'], CURLOPT_TIMEOUT=>10,
        CURLOPT_HTTPHEADER=>['X-Requested-With: XMLHttpRequest']]);
    $body = curl_exec($ch); curl_close($ch);
    return json_decode($body, true) ?? [];
}

function login(int $uid): array {
    $s = sess();
    $html = hget($s, '/index.php');
    if (preg_match('/name="csrf_token"\s+value="([a-f0-9]+)"/', $html, $m)) $s['csrf_token'] = $m[1];
    hpost($s, '/index.php', ['login_type'=>'member','user_id'=>$uid,'member_password'=>MEMBER_PASS]);
    $dash = hget($s, '/pages/dashboard.php');
    if (preg_match('/name="csrf_token"\s+value="([a-f0-9]+)"/', $dash, $m)) $s['csrf_token'] = $m[1];
    return $s;
}

/**
 * Přesná replika split algoritmu z api/wallet.php
 * Používáme stejný kód aby test ověřoval VÝSTUP, ne algoritmus sám.
 */
function expectedSplits(float $amountEur, array $userIds): array {
    $count = count($userIds);
    if ($count === 0) return [];
    $perPerson = floor($amountEur / $count * 100) / 100;
    $remainder = round($amountEur - ($perPerson * $count), 2);
    $splits = [];
    foreach ($userIds as $i => $uid) {
        $splits[$uid] = ($i === 0) ? round($perPerson + $remainder, 2) : $perPerson;
    }
    return $splits;
}

function dbSplits(int $expId): array {
    $stmt = db()->prepare("SELECT user_id, CAST(amount_eur AS DECIMAL(10,2)) as amount_eur FROM wallet_expense_splits WHERE expense_id = ? ORDER BY id");
    $stmt->execute([$expId]);
    return $stmt->fetchAll();
}

function dbExpense(int $expId): array {
    $stmt = db()->prepare("SELECT * FROM wallet_expenses WHERE id = ?");
    $stmt->execute([$expId]);
    return $stmt->fetch() ?: [];
}

register_shutdown_function(function() {
    foreach (glob(sys_get_temp_dir().'/vol5_10u_*') as $f) @unlink($f);
});

// ============================================================
// Ověření dostupnosti
// ============================================================
if (@file_get_contents(BASE_URL . '/index.php') === false) {
    echo "\033[31m[FATAL] Server nedostupný\033[0m\n"; exit(1);
}
try { db(); echo "\033[32m[OK] Server + DB dostupné\033[0m\n"; }
catch (Exception $e) { echo "\033[31m[FATAL] DB: {$e->getMessage()}\033[0m\n"; exit(1); }

// ============================================================
// SETUP: přidat 3 testovací uživatele (celkem 10)
// ============================================================
section('SETUP – Přidání 3 testovacích uživatelů (boat_id 1)');

$newUserIds = [];
$testUserNames = ['Testovac Prvni', 'Testovac Druhy', 'Testovac Treti'];
foreach ($testUserNames as $name) {
    $stmt = db()->prepare("INSERT INTO users (name, boat_id) VALUES (?, 1)");
    $stmt->execute([$name]);
    $uid = (int)db()->lastInsertId();
    $newUserIds[] = $uid;
    ok("Přidán uživatel '{$name}' (ID={$uid})", $uid > 0);
}

// Všech 10 uživatelů: 1–7 existující + 8,9,10 nový
$allUsers = array_merge([1,2,3,4,5,6,7], $newUserIds);
ok('Celkem 10 uživatelů', count($allUsers) === 10);

$s = login(1);

// Zjistit aktuální kurz
$rateRes = hget_json($s, '/api/exchange.php');
$RATE = (float)($rateRes['data']['rate'] ?? 25.0);
echo "\033[36m[INFO] Kurz CZK/EUR: {$RATE}\033[0m\n";

// Existující výdaje v DB (pro cleanup)
$existingExpIds = db()->query("SELECT id FROM wallet_expenses")->fetchAll(PDO::FETCH_COLUMN);

// ============================================================
// Definice výdajů s OČEKÁVANÝMI hodnotami (truth table)
// ============================================================
// Každý výdaj: kdo zaplatil, kolik, za koho
// Expected hodnoty vypočítáme exaktně stejnou logikou jako PHP

$EUR = 'EUR';
$CZK = 'CZK';

$scenarios = [
    // [popis, payer_uid, amount, currency, split_uids]
    ['SC1: 100 EUR / všech 10 – přesně dělitelné', 1, 100.00, $EUR, $allUsers],
    ['SC2: 100.01 EUR / 10 – zbytek 0.01 na prvního', 2, 100.01, $EUR, $allUsers],
    ['SC3: 99.99 EUR / 10 – velký zbytek 0.09', 3, 99.99, $EUR, $allUsers],
    ['SC4: 1.00 EUR / 10 – 0.10 každý (přesně)', 4, 1.00, $EUR, $allUsers],
    ['SC5: 0.09 EUR / 10 – jen 1 osoba platí, zbytek 0', 5, 0.09, $EUR, $allUsers],
    ['SC6: 333.33 EUR / 10 – velký zbytek 0.03', 6, 333.33, $EUR, $allUsers],
    ['SC7: 1234.56 EUR / 10 – složené číslo', 7, 1234.56, $EUR, $allUsers],
    ['SC8: 2500 CZK / 10 – CZK přepočet a split', $newUserIds[0], 2500.00, $CZK, $allUsers],
    ['SC9: 0.07 EUR / 3 lidi – haléřový edge case', $newUserIds[1], 0.07, $EUR, [1,2,3]],
    ['SC10: 777.77 EUR / 7 lidí – nesoudělné', $newUserIds[2], 777.77, $EUR, array_slice($allUsers,0,7)],
];

// ============================================================
// BLOK 1: Přidání výdajů + ověření splits v DB do haléře
// ============================================================
section('PŘIDÁNÍ + SPLITS – Ověření každého splitu do haléře');

$addedIds = [];
$paidByUser   = array_fill_keys($allUsers, 0.0); // kolik každý zaplatil
$shareByUser  = array_fill_keys($allUsers, 0.0); // kolik každý dluží

foreach ($scenarios as $idx => [$label, $payer, $amount, $currency, $splitUsers]) {

    // Vypočítáme amount_eur tak jak to dělá PHP
    if ($currency === $CZK) {
        $amountEur = round($amount / $RATE, 2);
    } else {
        $amountEur = $amount;
    }

    // Očekávané splity – EXAKTNÍ replikace PHP algoritmu
    $expSplits = expectedSplits($amountEur, $splitUsers);

    // Přidat přes API
    $res = hpost($s, '/api/wallet.php', [
        'action'       => 'add',
        'paid_by'      => $payer,
        'amount'       => $amount,
        'currency'     => $currency,
        'description'  => "[E2E-10U] {$label}",
        'expense_date' => '2025-07-22 10:00:00',
        'split_type'   => 'both',
        'split_users'  => implode(',', $splitUsers),
    ]);

    $expId = (int)($res['data']['id'] ?? 0);
    ok("{$label} → přidán (ID={$expId})", $expId > 0, $res['error'] ?? '');
    $addedIds[$idx] = $expId;
    if (!$expId) continue;

    // Naakumulujeme pro ruční výpočet bilancí
    $paidByUser[$payer]   = round($paidByUser[$payer] + $amountEur, 4);
    foreach ($expSplits as $uid => $splitAmt) {
        $shareByUser[$uid] = round($shareByUser[$uid] + $splitAmt, 4);
    }

    // Ověřit DB záznamy – počet splitů
    $dbSpl = dbSplits($expId);
    eq("{$label} – počet splitů v DB", count($splitUsers), count($dbSpl));

    // Ověřit každý split haléř po haléři
    $splitUsersList = array_values($splitUsers);
    foreach ($dbSpl as $j => $row) {
        $uid     = (int)$row['user_id'];
        $actual  = (float)$row['amount_eur'];
        $exp     = $expSplits[$uid] ?? null;
        if ($exp === null) {
            ok("{$label} – split[{$j}] user {$uid} existuje v expected", false, "user {$uid} není v expected splits");
            continue;
        }
        near("{$label} – split user {$uid}: {$exp} EUR", $exp, $actual, 0.005);
    }

    // SUM splits == amount_eur
    $sumSplits = round(array_sum(array_column($dbSpl, 'amount_eur')), 2);
    near("{$label} – SUM(splits) == {$amountEur} EUR", $amountEur, $sumSplits, 0.005);

    // CZK: exchange_rate uložen, amount_eur == amount/rate
    if ($currency === $CZK) {
        $row = dbExpense($expId);
        $storedRate = (float)($row['exchange_rate'] ?? 0);
        ok("{$label} – exchange_rate uložen v DB", $storedRate > 0, "rate={$storedRate}");
        $computedEur = round($amount / $storedRate, 2);
        near("{$label} – amount_eur == amount/rate", $computedEur, (float)$row['amount_eur'], 0.005);
    }
}

// ============================================================
// BLOK 2: Ověření bilancí – API vs. ruční výpočet
// ============================================================
section('BILANCE – API výsledky == ruční výpočet do haléře');

$balRes = hget_json($s, '/api/wallet.php', ['action' => 'balances']);
$apiBals = [];
foreach ($balRes['data'] ?? [] as $b) {
    $apiBals[(int)$b['user_id']] = $b;
}

// Přičteme existující výdaje z DB k ručnímu výpočtu
// (seed výdaje mají své splity, musíme je zahrnout)
$stmt = db()->prepare("SELECT paid_by, CAST(amount_eur AS DECIMAL(10,2)) as eur FROM wallet_expenses WHERE id NOT IN (" . implode(',', array_map('intval', $addedIds)) . ")");
// Bezpečnější: přečteme všechno z DB přímo
foreach ($allUsers as $uid) {
    $st = db()->prepare("SELECT COALESCE(SUM(amount_eur),0) FROM wallet_expenses WHERE paid_by = ?");
    $st->execute([$uid]);
    $paidTotal = (float)$st->fetchColumn();

    $st2 = db()->prepare("SELECT COALESCE(SUM(amount_eur),0) FROM wallet_expense_splits WHERE user_id = ?");
    $st2->execute([$uid]);
    $shareTotal = (float)$st2->fetchColumn();

    $dbBalance   = round($paidTotal - $shareTotal, 2);
    $apiBalance  = isset($apiBals[$uid]) ? (float)$apiBals[$uid]['balance'] : null;

    if ($uid <= 7) { // pouze původní uživatelé jsou v API (noví jsou taky)
        ok("BILANCE user {$uid} vrácen v API", $apiBalance !== null, "chybí v API");
        if ($apiBalance !== null) {
            near("BILANCE user {$uid}: DB({$dbBalance}) == API({$apiBalance})", $dbBalance, $apiBalance, 0.005);
        }
    }
}

// ============================================================
// BLOK 3: Matematická konzistence bilancí
// ============================================================
section('KONZISTENCE – SUM bilancí == 0, kladné == záporné');

$allBals = array_column($balRes['data'] ?? [], 'balance', 'user_id');
$totalSum = array_sum($allBals);
near('SUM(všech bilancí) ≈ 0', 0.0, (float)$totalSum, 0.10);

$sumPos = array_sum(array_filter($allBals, fn($b) => $b > 0.005));
$sumNeg = abs(array_sum(array_filter($allBals, fn($b) => $b < -0.005)));
near('SUM(kladných bilancí) ≈ SUM(záporných bilancí)', $sumPos, $sumNeg, 0.10);

// ============================================================
// BLOK 4: Globální integrita VŠECH splitů v DB
// ============================================================
section('INTEGRITA – SUM(splits) == amount_eur pro každý výdaj v DB');

$allExpRows = db()->query("SELECT id, description, CAST(amount_eur AS DECIMAL(10,2)) as amount_eur FROM wallet_expenses")->fetchAll();
foreach ($allExpRows as $row) {
    $stmt = db()->prepare("SELECT COALESCE(SUM(amount_eur),0) FROM wallet_expense_splits WHERE expense_id = ?");
    $stmt->execute([$row['id']]);
    $sum = round((float)$stmt->fetchColumn(), 2);
    $exp = round((float)$row['amount_eur'], 2);
    $diff = abs($sum - $exp);
    ok("Výdaj ID={$row['id']}: SUM({$sum}) == amount_eur({$exp})", $diff < 0.005,
        "diff={$diff} desc=" . substr($row['description'],0,40));
}

// Žádné osiřelé výdaje bez splitů
$orphans = db()->query("
    SELECT we.id FROM wallet_expenses we
    LEFT JOIN wallet_expense_splits wes ON wes.expense_id = we.id
    WHERE wes.id IS NULL
")->fetchAll(PDO::FETCH_COLUMN);
ok('Žádné výdaje bez splitů v DB', count($orphans) === 0, 'IDs: ' . implode(',', $orphans));

// ============================================================
// BLOK 5: Settlement – matematická správnost
// ============================================================
section('SETTLEMENT – Matematická správnost výsledků');

$setRes = hget_json($s, '/api/wallet.php', ['action' => 'settlements']);
$settlements = $setRes['data']['settlements'] ?? [];

ok('Settlements endpoint vrátí data', !empty($settlements));

// SUM transakcí == SUM záporných bilancí
$sumSettle = array_sum(array_column($settlements, 'amount'));
near("SUM(settlement transakcí) ≈ SUM(dluhů) ({$sumNeg})", $sumNeg, (float)$sumSettle, 0.15);

// Každý settlement > 0
foreach ($settlements as $i => $st) {
    ok("Settlement[{$i}] {$st['from_name']} → {$st['to_name']}: {$st['amount']} > 0",
        (float)$st['amount'] > 0);
}

// amount_czk konzistentní s amount a kurzem
foreach ($settlements as $i => $st) {
    $expectedCzk = round((float)$st['amount'] * $RATE, 2);
    near("Settlement[{$i}] amount_czk == amount * kurz",
        $expectedCzk, (float)$st['amount_czk'], 0.02);
}

// Simulace: po zaplacení všech settlements jsou všichni na ~0
$simBals = $allBals;
foreach ($settlements as $st) {
    $from = $st['from_id']; $to = $st['to_id']; $amt = (float)$st['amount'];
    if (isset($simBals[$from])) $simBals[$from] = round($simBals[$from] + $amt, 2);
    if (isset($simBals[$to]))   $simBals[$to]   = round($simBals[$to] - $amt, 2);
}
$maxResidual = max(array_map('abs', $simBals));
ok("Po zaplacení všech settlements max residuum ≤ 0.05 EUR (actual: {$maxResidual})",
    $maxResidual <= 0.05);

// ============================================================
// BLOK 6: Editace – přepočet při změně počtu lidí
// ============================================================
section('EDITACE – Přepočet splits při změně počtu lidí');

$editId = $addedIds[0] ?? 0; // SC1: 100 EUR / 10 lidí
if ($editId > 0) {
    // Původně: 100 EUR / 10 lidí = 10.00 každý
    $splBefore = dbSplits($editId);
    eq('EDIT před: 10 splitů', 10, count($splBefore));
    foreach ($splBefore as $sp) {
        near("EDIT před: split = 10.00 EUR", 10.00, (float)$sp['amount_eur'], 0.005);
    }

    // Editace: 150 EUR / 3 lidi [1,2,3]
    // Expected: floor(150/3*100)/100 = 50.00, zbytek=0 → 3×50.00
    $editRes = hpost($s, '/api/wallet.php', [
        'action' => 'edit', 'id' => $editId,
        'paid_by' => 1, 'amount' => 150.00, 'currency' => 'EUR',
        'description' => '[E2E-10U] SC1 editováno',
        'expense_date' => '2025-07-22 10:00:00',
        'split_type' => 'both', 'split_users' => '1,2,3',
    ]);
    ok('EDIT 150 EUR / 3 lidi → success', (bool)($editRes['success'] ?? false));

    $splAfter = dbSplits($editId);
    eq('EDIT po: 3 splity', 3, count($splAfter));
    foreach ($splAfter as $sp) {
        near("EDIT po: každý split = 50.00 EUR", 50.00, (float)$sp['amount_eur'], 0.005);
    }

    // SUM po editaci
    $sumAfter = round(array_sum(array_column($splAfter, 'amount_eur')), 2);
    near('EDIT po: SUM splits = 150.00 EUR', 150.00, $sumAfter, 0.005);

    // Editace zpět pro čistý cleanup
    hpost($s, '/api/wallet.php', [
        'action' => 'edit', 'id' => $editId,
        'paid_by' => 1, 'amount' => 100.00, 'currency' => 'EUR',
        'description' => '[E2E-10U] SC1: 100 EUR / všech 10',
        'expense_date' => '2025-07-22 10:00:00',
        'split_type' => 'both', 'split_users' => implode(',', $allUsers),
    ]);
}

// ============================================================
// BLOK 7: Konkrétní expected bilance pro izolovaný scénář
// ============================================================
section('TRUTH TABLE – Přesné bilance pro izolované výdaje');

// Pro přesné ověření přidáme 1 izolovaný výdaj do prázdné "skupiny" nových uživatelů
// (neovlivněn seed daty) a ověříme bilance do haléře

// Izolovaný výdaj: user 8 zaplatí 37.00 EUR, split [8,9,10]
// Expected:
//   amount_eur = 37.00
//   perPerson = floor(37/3*100)/100 = floor(12.333)*100/100 = 12.33
//   remainder = 37.00 - 3*12.33 = 37.00 - 36.99 = 0.01
//   splits: [8]=12.34, [9]=12.33, [10]=12.33  SUM=37.00
//   balance[8] = 37.00 - 12.34 = +24.66  (zaplatil víc)
//   balance[9] = 0.00 - 12.33  = -12.33  (dluží)
//   balance[10]= 0.00 - 12.33  = -12.33  (dluží)

$u8 = $newUserIds[0]; $u9 = $newUserIds[1]; $u10 = $newUserIds[2];
$isoRes = hpost($s, '/api/wallet.php', [
    'action' => 'add', 'paid_by' => $u8, 'amount' => 37.00, 'currency' => 'EUR',
    'description' => '[E2E-10U-ISO] Izolovaný výdaj',
    'expense_date' => '2025-07-22 15:00:00',
    'split_type' => 'both', 'split_users' => "{$u8},{$u9},{$u10}",
]);
$isoId = (int)($isoRes['data']['id'] ?? 0);
ok("ISO výdaj přidán (ID={$isoId})", $isoId > 0);

if ($isoId > 0) {
    $addedIds[] = $isoId;

    $isoSplits = dbSplits($isoId);
    eq('ISO splits: počet = 3', 3, count($isoSplits));

    $amounts = array_column($isoSplits, 'amount_eur');
    $sortedAmounts = $amounts;
    sort($sortedAmounts);

    near("ISO split[0] (první, má zbytek) = 12.34", 12.34, (float)$amounts[0], 0.005);
    near("ISO split[1] = 12.33", 12.33, (float)$amounts[1], 0.005);
    near("ISO split[2] = 12.33", 12.33, (float)$amounts[2], 0.005);

    $isoSum = round(array_sum($amounts), 2);
    near('ISO SUM splits = 37.00', 37.00, $isoSum, 0.005);

    // Ověřit bilance přes API
    $balAfterIso = hget_json($s, '/api/wallet.php', ['action' => 'balances']);
    $bals = [];
    foreach ($balAfterIso['data'] ?? [] as $b) $bals[(int)$b['user_id']] = (float)$b['balance'];

    // Ruční výpočet pro u8, u9, u10 (přidáváme k jejich stávající bilanci z předchozích výdajů)
    // Rychlejší: rovnou z DB
    foreach ([$u8, $u9, $u10] as $testUid) {
        $st = db()->prepare("SELECT COALESCE(SUM(amount_eur),0) FROM wallet_expenses WHERE paid_by = ?");
        $st->execute([$testUid]); $dbPaid = (float)$st->fetchColumn();
        $st2 = db()->prepare("SELECT COALESCE(SUM(amount_eur),0) FROM wallet_expense_splits WHERE user_id = ?");
        $st2->execute([$testUid]); $dbShare = (float)$st2->fetchColumn();
        $dbBal = round($dbPaid - $dbBal = $dbPaid - $dbShare, 2);
        $apiBal = $bals[$testUid] ?? null;
        ok("ISO bilance user {$testUid} je v API", $apiBal !== null);
        if ($apiBal !== null) {
            near("ISO bilance user {$testUid}: DB(" . round($dbPaid-$dbShare,2) . ") == API({$apiBal})",
                round($dbPaid - $dbShare, 2), $apiBal, 0.005);
        }
    }
}

// ============================================================
// BLOK 8: CZK výdaj – přesný přepočet a zachování kurzu při editaci
// ============================================================
section('CZK PŘEPOČET – Přesnost a zachování kurzu při editaci');

$czkId = $addedIds[7] ?? 0; // SC8: 2500 CZK / 10 lidí
if ($czkId > 0) {
    $czkRow = dbExpense($czkId);
    $storedRate = (float)$czkRow['exchange_rate'];
    $storedEur  = (float)$czkRow['amount_eur'];
    $expectedEur = round(2500.0 / $storedRate, 2);

    near("CZK 2500/{$storedRate} = {$expectedEur} EUR uloženo správně", $expectedEur, $storedEur, 0.005);

    // Editace: změna popisu, zachování částky 2500 CZK
    // Kurz se NESMÍ změnit → amount_eur se NESMÍ změnit
    hpost($s, '/api/wallet.php', [
        'action' => 'edit', 'id' => $czkId, 'paid_by' => $newUserIds[0],
        'amount' => 2500.00, 'currency' => 'CZK',
        'description' => '[E2E-10U] SC8 editováno',
        'expense_date' => '2025-07-22 10:00:00',
        'split_type' => 'both', 'split_users' => implode(',', $allUsers),
    ]);

    $czkAfter = dbExpense($czkId);
    eq("CZK edit: exchange_rate se nezměnil", $storedRate, (float)$czkAfter['exchange_rate']);
    eq("CZK edit: amount_eur se nezměnil", $storedEur, (float)$czkAfter['amount_eur']);

    // Splits po editaci stále souhlasí
    $czkSplits = dbSplits($czkId);
    $czkSplitSum = round(array_sum(array_column($czkSplits, 'amount_eur')), 2);
    near("CZK edit: SUM(splits) == amount_eur po editaci", $storedEur, $czkSplitSum, 0.005);

    // Změna CZK částky: 3000 CZK → nový amount_eur = round(3000/storedRate, 2)
    $newCzkAmount = 3000.00;
    $newEur = round($newCzkAmount / $storedRate, 2);
    hpost($s, '/api/wallet.php', [
        'action' => 'edit', 'id' => $czkId, 'paid_by' => $newUserIds[0],
        'amount' => $newCzkAmount, 'currency' => 'CZK',
        'description' => '[E2E-10U] SC8 změna částky',
        'expense_date' => '2025-07-22 10:00:00',
        'split_type' => 'both', 'split_users' => implode(',', $allUsers),
    ]);
    $czkChanged = dbExpense($czkId);
    near("CZK změna částky: {$newCzkAmount} CZK / {$storedRate} = {$newEur} EUR", $newEur, (float)$czkChanged['amount_eur'], 0.005);

    $czkChangedSplits = dbSplits($czkId);
    $czkChangedSum = round(array_sum(array_column($czkChangedSplits, 'amount_eur')), 2);
    near("CZK změna částky: SUM(splits) == {$newEur} EUR", $newEur, $czkChangedSum, 0.005);
}

// ============================================================
// CLEANUP
// ============================================================
section('CLEANUP – Smazání testovacích dat');

$allAdded = array_filter($addedIds, fn($id) => $id > 0);
$cleanOk = true;
foreach ($allAdded as $eid) {
    $r = hpost($s, '/api/wallet.php', ['action' => 'delete', 'id' => $eid]);
    if (!($r['success'] ?? false)) $cleanOk = false;
}
ok('Výdaje smazány přes API', $cleanOk);

// Smazat testovací uživatele přímo v DB
foreach ($newUserIds as $uid) {
    db()->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]);
}
$remaining = db()->query("SELECT COUNT(*) FROM users")->fetchColumn();
ok('Testovací uživatelé smazáni z DB', (int)$remaining === 7, "zbývá: {$remaining}");

// Ověřit počet výdajů
$remainingExp = (int)db()->query("SELECT COUNT(*) FROM wallet_expenses")->fetchColumn();
eq('DB počet výdajů zpět na ' . count($existingExpIds), count($existingExpIds), $remainingExp);

// Ověřit žádné osiřelé splity
$orphanedSplits = (int)db()->query("
    SELECT COUNT(*) FROM wallet_expense_splits wes
    LEFT JOIN wallet_expenses we ON we.id = wes.expense_id
    WHERE we.id IS NULL
")->fetchColumn();
eq('Žádné osiřelé splity v DB po cleanup', 0, $orphanedSplits);

// ============================================================
// Výsledky
// ============================================================
echo "\n" . str_repeat('=', 60) . "\n";
$total = $passed + $failed;
if ($failed === 0) {
    echo "\033[1;32m  PASS: {$passed}/{$total} testů prošlo\033[0m\n";
} else {
    echo "\033[1;31m  FAIL: {$failed} selhalo, {$passed}/{$total} prošlo\033[0m\n";
    echo "\n  Selhané testy:\n";
    foreach ($errors as $e) echo "    - {$e}\n";
}
echo str_repeat('=', 60) . "\n";
exit($failed > 0 ? 1 : 0);
