<?php
/**
 * VOL5 – Golden dataset test
 *
 * Ověřuje přepočty aplikace proti NEZÁVISLÝM referenčním hodnotám.
 * Golden values byly spočítány ručně z definice výdajů v seed_roadtrip.sql
 * a ověřeny compute_golden.php (přímý SQL výpočet bez aplikační logiky).
 *
 * Pokud tento test selže → aplikace vrací jiné číslo než ruční výpočet → BUG.
 *
 * Předpoklady:
 *   docker exec vol5_db bash -c "mysql -u vol5user -pvol5pass vol5 < /tmp/seed_roadtrip.sql"
 *   (nebo spustit prepare_golden_db() níže)
 *
 * Spuštění:
 *   docker exec -e RUNNING_IN_DOCKER=1 vol5_web php /var/www/html/tests/golden_dataset_test.php
 */

$_baseUrl = (file_exists('/.dockerenv') || getenv('RUNNING_IN_DOCKER'))
    ? 'http://host.docker.internal:8080'
    : 'http://localhost:8080';
define('BASE_URL', $_baseUrl);
// Heslo pro seed_roadtrip.sql je 'password' (Laravel default bcrypt hash)
define('MEMBER_PASS', 'password');

$_dbHost = (file_exists('/.dockerenv') || getenv('RUNNING_IN_DOCKER')) ? 'db' : '127.0.0.1';
$_dbPort = (file_exists('/.dockerenv') || getenv('RUNNING_IN_DOCKER')) ? '3306' : '3307';
define('DB_DSN', "mysql:host={$_dbHost};port={$_dbPort};dbname=vol5;charset=utf8mb4");

// ============================================================
// Golden values – vypočítány ručně + ověřeny compute_golden.php
// Nezávislý zdroj pravdy: přímý SQL výpočet bez aplikační logiky
//
// Plavba: Jadran 2025, 10 lidí, 2 lodě, 20 výdajů, kurz 25.00
// ============================================================

$GOLDEN_PAID = [
     1 =>   460.00, // Pavel Novák       (E01 nafta + E11 marina Pula)
     2 =>    95.00, // Jana Horáková     (E02 potraviny + E12 oběd)
     3 =>   440.00, // Tomáš Krejčí      (E04 marina Poreč + E14 šnorchlování)
     4 =>   546.00, // Lucie Marková     (E05 večeře + E18 závěrečná večeře)
     5 =>   115.00, // Martin Blaha      (E06 nafta L1 + E15 nápoje)
     6 =>   149.00, // Eva Procházková   (E03 potraviny + E13 oběd L2)
     7 =>   112.00, // Petr Šimánek      (E07 nafta L2 + E16 nápoje)
     8 =>   145.00, // Klára Dvořáčková  (E08 paddleboardy + E19 kajak)
     9 =>   320.00, // Ondřej Vlček      (E09 parkovné + E17 Brijuni)
    10 =>    42.08, // Tereza Nováčková  (E10 lékárna + E20 zmrzlina)
];

$GOLDEN_SHARE = [
     1 =>   230.88, // Pavel:  E01+E02+E04+E05+E06+E09+E10+E11+E12+E14+E15+E17+E18+E20(0.58)
     2 =>   230.80, // Jana:   stejné jako Pavel ale E20=0.50
     3 =>   230.80, // Tomáš:  stejné jako Jana
     4 =>   210.80, // Lucie:  bez E14(šnorchlování)
     5 =>   210.80, // Martin: bez E14
     6 =>   256.00, // Eva:    L2 výdaje + sdílené + E14
     7 =>   274.34, // Petr:   L2 výdaje + sdílené + E14 + E19(33.34)
     8 =>   289.33, // Klára:  L2 výdaje + sdílené + E14 + E19(33.33) + E08(15)
     9 =>   254.33, // Ondřej: L2 výdaje + sdílené + E19(33.33)
    10 =>   236.00, // Tereza: L2 výdaje + sdílené (bez E19, bez E14)
];

$GOLDEN_BALANCES = [
     1 =>   229.12, // Pavel zaplatil nejvíc – v plusu
     2 =>  -135.80, // Jana platila málo – dluží
     3 =>   209.20, // Tomáš zaplatil hodně – v plusu
     4 =>   335.20, // Lucie zaplatila nejvíc celkem – nejvíc v plusu
     5 =>   -95.80, // Martin platil málo – dluží
     6 =>  -107.00, // Eva platila méně než sdílený podíl
     7 =>  -162.34, // Petr platil málo vs. jeho podíl (E19 kajak)
     8 =>  -144.33, // Klára platila méně než podíl (E19)
     9 =>    65.67, // Ondřej zaplatil Brijuni + parkovné – mírně v plusu
    10 =>  -193.92, // Tereza platila nejméně – nejvíc dluží
];

// Settlements – kdo komu platí (greedy algoritmus, největší dluh první)
// Pořadí závisí na implementaci – testujeme amount_eur ne pořadí
$GOLDEN_SETTLEMENTS_AMOUNTS = [
    // from_uid => to_uid => amount
    10 => [4 => 193.92],                    // Tereza → Lucie 193.92
     7 => [4 => 141.28, 1 => 21.06],        // Petr → Lucie 141.28, Petr → Pavel 21.06
     8 => [1 => 144.33],                    // Klára → Pavel 144.33
     2 => [1 => 63.73, 3 => 72.07],         // Jana → Pavel 63.73, Jana → Tomáš 72.07
     6 => [3 => 107.00],                    // Eva → Tomáš 107.00
     5 => [3 => 30.13, 9 => 65.67],         // Martin → Tomáš 30.13, Martin → Ondřej 65.67
];

// ============================================================
// Test runner
// ============================================================
$passed = 0; $failed = 0; $errors = [];

function ok(string $label, bool $cond, string $detail = ''): void {
    global $passed, $failed, $errors;
    if ($cond) { echo "\033[32m  ✓ {$label}\033[0m\n"; $passed++; }
    else {
        echo "\033[31m  ✗ {$label}\033[0m\n";
        if ($detail) echo "      {$detail}\n";
        $failed++; $errors[] = $label;
    }
}
function near(string $label, float $exp, float $act, float $tol = 0.005): void {
    ok($label, abs($exp - $act) < $tol,
        sprintf("expected=%.2f actual=%.2f diff=%.4f", $exp, $act, abs($exp-$act)));
}
function section(string $n): void { echo "\n\033[1;34m▶ {$n}\033[0m\n"; }

// ============================================================
// DB + HTTP helpers
// ============================================================
function db(): PDO {
    static $pdo;
    if (!$pdo) $pdo = new PDO(DB_DSN, 'vol5user', 'vol5pass', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
}

function sess(): array { return ['cookie_file' => tempnam(sys_get_temp_dir(), 'vol5_gd_')]; }

function hget(array &$s, string $path): string {
    $ch = curl_init(BASE_URL . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true,
        CURLOPT_COOKIEJAR=>$s['cookie_file'], CURLOPT_COOKIEFILE=>$s['cookie_file'], CURLOPT_TIMEOUT=>10]);
    $r = curl_exec($ch); curl_close($ch); return $r;
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

register_shutdown_function(function() {
    foreach (glob(sys_get_temp_dir().'/vol5_gd_*') as $f) @unlink($f);
});

// ============================================================
// Ověření dostupnosti + správná seed data
// ============================================================
if (@file_get_contents(BASE_URL.'/index.php') === false) {
    echo "\033[31m[FATAL] Server nedostupný\033[0m\n"; exit(1);
}
try { db(); } catch (Exception $e) {
    echo "\033[31m[FATAL] DB: {$e->getMessage()}\033[0m\n"; exit(1);
}

$userCount = (int)db()->query("SELECT COUNT(*) FROM users")->fetchColumn();
$expCount  = (int)db()->query("SELECT COUNT(*) FROM wallet_expenses")->fetchColumn();
if ($userCount !== 10 || $expCount !== 20) {
    echo "\033[33m[WARN] DB nemá správná seed data (users={$userCount}, expenses={$expCount}).\033[0m\n";
    echo "\033[33m       Spusť: docker cp docker/seed_roadtrip.sql vol5_db:/tmp/ && \033[0m\n";
    echo "\033[33m              docker exec vol5_db bash -c 'mysql -u vol5user -pvol5pass vol5 < /tmp/seed_roadtrip.sql'\033[0m\n";
    exit(1);
}
echo "\033[32m[OK] DB obsahuje seed_roadtrip.sql (10 uživatelů, 20 výdajů)\033[0m\n";

$rate = (float)db()->query("SELECT setting_value FROM settings WHERE setting_key='exchange_rate'")->fetchColumn();
echo "\033[36m[INFO] Kurz v DB: {$rate} CZK/EUR\033[0m\n";
if (abs($rate - 25.0) > 0.001) {
    echo "\033[33m[INFO] Kurz není 25.00 (je {$rate}) – golden EUR hodnoty jsou fixní z seed SQL (rate 25.00),\033[0m\n";
    echo "\033[33m       CZK zobrazení v UI bude jiné, ale EUR bilance jsou nezávislé na aktuálním kurzu.\033[0m\n";
}
ok('Kurz v DB je nastaven', $rate > 0);

$s = login(1);

// ============================================================
// BLOK 1: Integrita splitů v DB – SUM == amount_eur
// ============================================================
section('INTEGRITA – SUM(splits) == amount_eur pro každý výdaj');

$allExp = db()->query("SELECT id, description, CAST(amount_eur AS DECIMAL(10,4)) as eur FROM wallet_expenses ORDER BY id")->fetchAll();
foreach ($allExp as $row) {
    $st = db()->prepare("SELECT COALESCE(SUM(amount_eur),0) FROM wallet_expense_splits WHERE expense_id=?");
    $st->execute([$row['id']]);
    $sum = round((float)$st->fetchColumn(), 2);
    $exp = round((float)$row['eur'], 2);
    ok("E{$row['id']}: SUM({$sum}) == amount_eur({$exp})", abs($sum-$exp) < 0.005,
        "diff=".round(abs($sum-$exp),4)." desc=".substr($row['description'],0,35));
}

// ============================================================
// BLOK 2: Přesné hodnoty splitů pro vybrané výdaje
// ============================================================
section('SPLITS – Přesné hodnoty klíčových výdajů');

// E01: 180 EUR / 10 = 18.00 každý (přesně dělitelné)
$e01 = db()->query("SELECT user_id, CAST(amount_eur AS DECIMAL(10,2)) as amt FROM wallet_expense_splits WHERE expense_id=1 ORDER BY user_id")->fetchAll();
ok('E01: 10 splitů', count($e01) === 10);
foreach ($e01 as $sp) {
    near("E01: user {$sp['user_id']} = 18.00 EUR", 18.00, (float)$sp['amt']);
}

// E10: 37.00 EUR / 10 = 3.70 každý (přesně dělitelné)
$e10 = db()->query("SELECT user_id, CAST(amount_eur AS DECIMAL(10,2)) as amt FROM wallet_expense_splits WHERE expense_id=10 ORDER BY user_id")->fetchAll();
ok('E10: 10 splitů', count($e10) === 10);
foreach ($e10 as $sp) {
    near("E10: user {$sp['user_id']} = 3.70 EUR", 3.70, (float)$sp['amt']);
}

// E19: 100 EUR / 3 lidi [7,8,9] – zbytek 0.01 na prvního
$e19 = db()->query("SELECT user_id, CAST(amount_eur AS DECIMAL(10,2)) as amt FROM wallet_expense_splits WHERE expense_id=19 ORDER BY id")->fetchAll();
ok('E19: 3 splity', count($e19) === 3);
near('E19: user 7 (první) = 33.34 EUR', 33.34, (float)$e19[0]['amt']);
near('E19: user 8 = 33.33 EUR', 33.33, (float)$e19[1]['amt']);
near('E19: user 9 = 33.33 EUR', 33.33, (float)$e19[2]['amt']);

// E20: 5.08 EUR / 10 – zbytek 0.08, user 1 = 0.58, ostatní = 0.50
$e20 = db()->query("SELECT user_id, CAST(amount_eur AS DECIMAL(10,2)) as amt FROM wallet_expense_splits WHERE expense_id=20 ORDER BY id")->fetchAll();
ok('E20: 10 splitů', count($e20) === 10);
near('E20: user 1 (první, zbytek 0.08) = 0.58 EUR', 0.58, (float)$e20[0]['amt']);
foreach (array_slice($e20, 1) as $sp) {
    near("E20: user {$sp['user_id']} = 0.50 EUR", 0.50, (float)$sp['amt']);
}

// ============================================================
// BLOK 3: Golden paid – kolik každý skutečně zaplatil
// ============================================================
section('GOLDEN PAID – Kolik každý zaplatil (přímý SQL vs. golden)');

foreach ($GOLDEN_PAID as $uid => $expected) {
    $st = db()->prepare("SELECT COALESCE(SUM(amount_eur),0) FROM wallet_expenses WHERE paid_by=?");
    $st->execute([$uid]); $actual = round((float)$st->fetchColumn(), 2);
    near("User {$uid} zaplatil {$expected} EUR", $expected, $actual);
}

// ============================================================
// BLOK 4: Golden share – kolik každý dluží
// ============================================================
section('GOLDEN SHARE – Kolik každý dluží (přímý SQL vs. golden)');

foreach ($GOLDEN_SHARE as $uid => $expected) {
    $st = db()->prepare("SELECT COALESCE(SUM(amount_eur),0) FROM wallet_expense_splits WHERE user_id=?");
    $st->execute([$uid]); $actual = round((float)$st->fetchColumn(), 2);
    near("User {$uid} dluží {$expected} EUR", $expected, $actual);
}

// ============================================================
// BLOK 5: Golden balances – přes API (klíčový test!)
// ============================================================
section('GOLDEN BALANCES – API vs. nezávislý ruční výpočet');

$balRes = hget_json($s, '/api/wallet.php', ['action' => 'balances']);
ok('Balances endpoint vrátí success', (bool)($balRes['success'] ?? false));

$apiBals = [];
foreach ($balRes['data'] ?? [] as $b) $apiBals[(int)$b['user_id']] = $b;

$userNames = [
    1=>'Pavel Novák', 2=>'Jana Horáková', 3=>'Tomáš Krejčí', 4=>'Lucie Marková',
    5=>'Martin Blaha', 6=>'Eva Procházková', 7=>'Petr Šimánek', 8=>'Klára Dvořáčková',
    9=>'Ondřej Vlček', 10=>'Tereza Nováčková',
];

foreach ($GOLDEN_BALANCES as $uid => $goldenBal) {
    $name = $userNames[$uid];
    ok("GOLDEN: {$name} (user {$uid}) je v API", isset($apiBals[$uid]));
    if (!isset($apiBals[$uid])) continue;

    $apiBalance = (float)$apiBals[$uid]['balance'];
    near("GOLDEN: {$name} bilance = {$goldenBal} EUR", $goldenBal, $apiBalance, 0.01);

    // Také ověřit paid a share z API
    near("GOLDEN: {$name} paid = {$GOLDEN_PAID[$uid]} EUR",
        $GOLDEN_PAID[$uid], (float)$apiBals[$uid]['paid'], 0.01);
    near("GOLDEN: {$name} share = {$GOLDEN_SHARE[$uid]} EUR",
        $GOLDEN_SHARE[$uid], (float)$apiBals[$uid]['share'], 0.01);
}

// ============================================================
// BLOK 6: Konzistence bilancí
// ============================================================
section('KONZISTENCE – Matematické invarianty');

$allBals = [];
foreach ($balRes['data'] ?? [] as $b) $allBals[(int)$b['user_id']] = (float)$b['balance'];

$sumAll = array_sum($allBals);
near('SUM(všech bilancí) = 0.00', 0.0, $sumAll, 0.05);

$sumPos = array_sum(array_filter($allBals, fn($b) => $b > 0.005));
$sumNeg = abs(array_sum(array_filter($allBals, fn($b) => $b < -0.005)));
near('SUM(kladných) = SUM(záporných)', $sumPos, $sumNeg, 0.05);

// Golden total: zaplatili dohromady
$goldenTotalPaid = array_sum($GOLDEN_PAID);
$dbTotalPaid = (float)db()->query("SELECT COALESCE(SUM(amount_eur),0) FROM wallet_expenses")->fetchColumn();
near("Celkem zaplaceno = {$goldenTotalPaid} EUR", $goldenTotalPaid, round($dbTotalPaid, 2), 0.01);

// ============================================================
// BLOK 7: Golden settlements – API vs. ruční výpočet
// ============================================================
section('GOLDEN SETTLEMENTS – API settlements vs. ruční výpočet');

$setRes = hget_json($s, '/api/wallet.php', ['action' => 'settlements']);
ok('Settlements endpoint vrátí success', (bool)($setRes['success'] ?? false));
$settlements = $setRes['data']['settlements'] ?? [];
// API přepočítává amount_czk aktuálním kurzem z ČNB (ne fixním 25.00)
// Proto bereme kurz přímo z API odpovědi
$liveRate = (float)($setRes['data']['rate'] ?? 25.00);

ok('Počet settlement transakcí = 9', count($settlements) === 9,
    'actual=' . count($settlements));

// Ověřit každou transakci – hledáme shodu bez ohledu na pořadí
foreach ($GOLDEN_SETTLEMENTS_AMOUNTS as $fromUid => $targets) {
    foreach ($targets as $toUid => $goldenAmt) {
        $fromName = $userNames[$fromUid];
        $toName   = $userNames[$toUid];

        $found = null;
        foreach ($settlements as $st) {
            if ($st['from_id'] == $fromUid && $st['to_id'] == $toUid) {
                $found = $st; break;
            }
        }
        ok("SETTLEMENT: {$fromName} → {$toName} existuje", $found !== null);
        if ($found) {
            near("SETTLEMENT: {$fromName} → {$toName} = {$goldenAmt} EUR",
                $goldenAmt, (float)$found['amount'], 0.01);
            // amount_czk se počítá dynamicky aktuálním ČNB kurzem (ne fixním 25.00)
            $expectedCzk = round($goldenAmt * $liveRate, 2);
            near("SETTLEMENT: {$fromName} → {$toName} CZK ≈ {$expectedCzk} (live kurz {$liveRate})",
                $expectedCzk, (float)($found['amount_czk'] ?? 0), 0.10);
        }
    }
}

// SUM settlements = SUM záporných bilancí
$sumSettle = array_sum(array_column($settlements, 'amount'));
near('SUM(settlements) ≈ SUM(dluhů)', $sumNeg, (float)$sumSettle, 0.05);

// Simulace: po zaplacení jsou všichni na 0
$simBals = $allBals;
foreach ($settlements as $st) {
    $f = $st['from_id']; $t = $st['to_id']; $a = (float)$st['amount'];
    if (isset($simBals[$f])) $simBals[$f] = round($simBals[$f] + $a, 2);
    if (isset($simBals[$t])) $simBals[$t] = round($simBals[$t] - $a, 2);
}
$residuals = array_map('abs', $simBals);
$maxResidual = !empty($residuals) ? max($residuals) : 0;
ok("Po zaplacení settlements: max residuum = {$maxResidual} EUR (≤ 0.05)", $maxResidual <= 0.05);

// ============================================================
// BLOK 8: CZK výdaje – přesnost přepočtu
// ============================================================
section('CZK PŘEPOČET – Přesnost EUR hodnot v DB');

$czkExpenses = db()->query("SELECT id, amount, CAST(amount_eur AS DECIMAL(10,2)) as eur, exchange_rate FROM wallet_expenses WHERE currency='CZK'")->fetchAll();
ok('V DB jsou CZK výdaje', count($czkExpenses) > 0);

foreach ($czkExpenses as $row) {
    $expectedEur = round((float)$row['amount'] / (float)$row['exchange_rate'], 2);
    near("CZK výdaj ID={$row['id']}: {$row['amount']}CZK/{$row['exchange_rate']}={$expectedEur}EUR",
        $expectedEur, (float)$row['eur'], 0.005);
}

// ============================================================
// Výsledky
// ============================================================
echo "\n" . str_repeat('=', 60) . "\n";
$total = $passed + $failed;
if ($failed === 0) {
    echo "\033[1;32m  PASS: {$passed}/{$total} testů prošlo\033[0m\n";
    echo "\033[32m  Aplikace vrací stejné výsledky jako ruční výpočet.\033[0m\n";
} else {
    echo "\033[1;31m  FAIL: {$failed} selhalo, {$passed}/{$total} prošlo\033[0m\n";
    echo "\n  Selhané testy:\n";
    foreach ($errors as $e) echo "    - {$e}\n";
}
echo str_repeat('=', 60) . "\n";
exit($failed > 0 ? 1 : 0);
