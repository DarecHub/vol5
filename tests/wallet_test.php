<?php
/**
 * VOL5 – Unit testy přepočtů pokladny
 *
 * Žádný framework, žádná DB – testuje čistou logiku.
 * Spuštění: php tests/wallet_test.php
 */

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

function section(string $name): void
{
    echo "\n\033[1;34m▶ {$name}\033[0m\n";
}

// ============================================================
// Extrahované funkce z api/wallet.php (bez DB závislosti)
// ============================================================

/**
 * Vypočítá split částek pro každého uživatele.
 * Vrátí pole [user_id => amount_eur].
 * Zbytek po haléřích jde na prvního uživatele v poli.
 */
function calculateSplits(float $amountEur, array $userIds): array
{
    $count = count($userIds);
    if ($count === 0) return [];

    $perPerson = floor($amountEur / $count * 100) / 100;
    $remainder = round($amountEur - ($perPerson * $count), 2);

    $splits = [];
    foreach ($userIds as $i => $uid) {
        $share = ($i === 0) ? round($perPerson + $remainder, 2) : $perPerson;
        $splits[$uid] = $share;
    }
    return $splits;
}

/**
 * Přepočet CZK → EUR.
 */
function czkToEur(float $czk, float $rate): float
{
    if ($rate <= 0) return 0.0;
    return round($czk / $rate, 2);
}

/**
 * Greedy settlement algoritmus (minimalizace počtu transakcí).
 * Vrátí pole [['from' => id, 'to' => id, 'amount' => float]].
 */
function calculateSettlements(array $balances): array
{
    $debtors = [];
    $creditors = [];

    foreach ($balances as $uid => $balance) {
        $balance = round($balance, 2);
        if ($balance < -0.01) {
            $debtors[] = ['id' => $uid, 'amount' => abs($balance)];
        } elseif ($balance > 0.01) {
            $creditors[] = ['id' => $uid, 'amount' => $balance];
        }
    }

    usort($debtors, fn($a, $b) => $b['amount'] <=> $a['amount']);
    usort($creditors, fn($a, $b) => $b['amount'] <=> $a['amount']);

    $settlements = [];
    $di = 0;
    $ci = 0;

    while ($di < count($debtors) && $ci < count($creditors)) {
        $transfer = min($debtors[$di]['amount'], $creditors[$ci]['amount']);
        $transfer = round($transfer, 2);

        if ($transfer > 0.01) {
            $settlements[] = [
                'from' => $debtors[$di]['id'],
                'to' => $creditors[$ci]['id'],
                'amount' => $transfer,
            ];
        }

        $debtors[$di]['amount'] = round($debtors[$di]['amount'] - $transfer, 2);
        $creditors[$ci]['amount'] = round($creditors[$ci]['amount'] - $transfer, 2);

        if ($debtors[$di]['amount'] < 0.01) $di++;
        if ($creditors[$ci]['amount'] < 0.01) $ci++;
    }

    return $settlements;
}

/**
 * Ověří integritu splitů – SUM(splits) musí == amount_eur.
 */
function verifySplitSum(float $amountEur, array $splits): bool
{
    $sum = array_sum($splits);
    return abs($sum - $amountEur) < 0.001;
}

// ============================================================
// TESTY: Split algoritmus
// ============================================================

section('Split algoritmus – základní případy');

$splits = calculateSplits(10.00, [1, 2, 3]);
assert_true('10.00 / 3 – SUM == 10.00', verifySplitSum(10.00, $splits));
assert_equals('10.00 / 3 – osoba[0] má zbytek', 3.34, $splits[1]);
assert_equals('10.00 / 3 – osoba[1]', 3.33, $splits[2]);
assert_equals('10.00 / 3 – osoba[2]', 3.33, $splits[3]);

$splits = calculateSplits(100.00, [1, 2, 3]);
assert_true('100.00 / 3 – SUM == 100.00', verifySplitSum(100.00, $splits));
assert_equals('100.00 / 3 – osoba[0]', 33.34, $splits[1]);
assert_equals('100.00 / 3 – osoba[1]', 33.33, $splits[2]);

$splits = calculateSplits(100.00, [1, 2, 3, 4, 5, 6]);
assert_true('100.00 / 6 – SUM == 100.00', verifySplitSum(100.00, $splits));
// floor(100/6 * 100)/100 = floor(16.666)*100/100 = 16.66, zbytek = 0.04 → osoba[0] = 16.70
assert_equals('100.00 / 6 – osoba[0] má zbytek (16.70)', 16.70, $splits[1]);
assert_equals('100.00 / 6 – ostatní (16.66)', 16.66, $splits[2]);

$splits = calculateSplits(120.00, [1, 2, 3, 4, 5, 6]);
assert_true('120.00 / 6 – SUM == 120.00', verifySplitSum(120.00, $splits));
assert_equals('120.00 / 6 – každý', 20.00, $splits[1]);

section('Split algoritmus – edge cases');

$splits = calculateSplits(0.01, [1, 2]);
assert_true('0.01 / 2 – SUM == 0.01', verifySplitSum(0.01, $splits));
assert_equals('0.01 / 2 – osoba[0] dostane celý cent', 0.01, $splits[1]);
assert_equals('0.01 / 2 – osoba[1] dostane 0', 0.00, $splits[2]);

$splits = calculateSplits(0.01, [1, 2, 3]);
assert_true('0.01 / 3 – SUM == 0.01', verifySplitSum(0.01, $splits));

$splits = calculateSplits(99.99, [1, 2, 3]);
assert_true('99.99 / 3 – SUM == 99.99', verifySplitSum(99.99, $splits));
assert_equals('99.99 / 3 – každý', 33.33, $splits[1]);

$splits = calculateSplits(100.00, [1, 2, 3, 4, 5, 6, 7]);
assert_true('100.00 / 7 – SUM == 100.00', verifySplitSum(100.00, $splits));
// 100 / 7 = 14.285... → floor = 14.28, 7×14.28 = 99.96, remainder = 0.04
// Osoba[0] = 14.28 + 0.04 = 14.32
assert_equals('100.00 / 7 – osoba[0] má zbytek', 14.32, $splits[1]);
assert_equals('100.00 / 7 – ostatní', 14.28, $splits[2]);

$splits = calculateSplits(1.00, [1]);
assert_true('1.00 / 1 – SUM == 1.00', verifySplitSum(1.00, $splits));
assert_equals('1.00 / 1 – celá částka', 1.00, $splits[1]);

section('Split algoritmus – velké částky');

$splits = calculateSplits(5000.00, [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);
assert_true('5000.00 / 10 – SUM == 5000.00', verifySplitSum(5000.00, $splits));
assert_equals('5000.00 / 10 – každý', 500.00, $splits[1]);

$splits = calculateSplits(1234.56, [1, 2, 3, 4, 5]);
assert_true('1234.56 / 5 – SUM == 1234.56', verifySplitSum(1234.56, $splits));

$splits = calculateSplits(333.33, [1, 2, 3]);
assert_true('333.33 / 3 – SUM == 333.33', verifySplitSum(333.33, $splits));

// ============================================================
// TESTY: Přepočet CZK → EUR
// ============================================================

section('Přepočet CZK → EUR');

assert_equals('1000 CZK @ 25.00 = 40.00 EUR', 40.00, czkToEur(1000.00, 25.00));
assert_equals('500 CZK @ 25.00 = 20.00 EUR', 20.00, czkToEur(500.00, 25.00));
assert_equals('1 CZK @ 25.00 = 0.04 EUR', 0.04, czkToEur(1.00, 25.00));
assert_equals('100 CZK @ 24.505 = 4.08 EUR', 4.08, czkToEur(100.00, 24.505));
assert_equals('2500 CZK @ 25.10 = 99.60 EUR', 99.60, czkToEur(2500.00, 25.10));
assert_equals('0 CZK = 0.00 EUR', 0.00, czkToEur(0.00, 25.00));
assert_equals('Kurz 0 vrátí 0.00', 0.00, czkToEur(1000.00, 0.00));

section('Přepočet – zpětná kontrola (EUR → CZK)');

$czk = 1000.00;
$rate = 25.00;
$eur = czkToEur($czk, $rate);
$backToCzk = round($eur * $rate, 2);
// Zpětný přepočet musí být v toleranci 1 haléř
assert_true('1000 CZK → EUR → CZK (tolerance ±0.01)', abs($backToCzk - $czk) <= 0.01);

// ============================================================
// TESTY: Settlement algoritmus
// ============================================================

section('Settlement algoritmus – základní případy');

// A platil 100, B platil 0, oba mají podíl 50 → B dluží A 50
$settlements = calculateSettlements([
    'A' => 50.00,   // A zaplatil 50 víc než jeho podíl
    'B' => -50.00,  // B zaplatil 50 méně
]);
assert_equals('A→B dluh – počet transakcí', 1, count($settlements));
assert_equals('A→B dluh – from', 'B', $settlements[0]['from']);
assert_equals('A→B dluh – to', 'A', $settlements[0]['to']);
assert_equals('A→B dluh – amount', 50.00, $settlements[0]['amount']);

// Všichni vyrovnaní
$settlements = calculateSettlements([
    'A' => 0.00,
    'B' => 0.00,
    'C' => 0.00,
]);
assert_equals('Všichni vyrovnaní – 0 transakcí', 0, count($settlements));

// 3 osoby – A platil nejvíc, B a C dluží
$settlements = calculateSettlements([
    'A' => 80.00,
    'B' => -30.00,
    'C' => -50.00,
]);
assert_equals('3 osoby – počet transakcí', 2, count($settlements));
$totalTransferred = array_sum(array_column($settlements, 'amount'));
assert_equals('3 osoby – celkem převedeno', 80.00, $totalTransferred);

section('Settlement algoritmus – optimalizace počtu transakcí');

// Klasický příklad: A dluží B a B dluží C → 2 transakce (ne 3)
$settlements = calculateSettlements([
    'A' => -50.00,
    'B' => 20.00,
    'C' => 30.00,
]);
// B+C jsou v plusu (zaplatili víc), A je v mínusu
assert_true('Greedy – max 2 transakce pro 3 osoby', count($settlements) <= 2);
$totalFrom = array_sum(array_map(fn($s) => $s['amount'], array_filter($settlements, fn($s) => true)));
assert_equals('Greedy – suma transakcí == suma dluhů', 50.00, round($totalFrom, 2));

// 6 osob – různé bilance
$balances6 = [
    1 => 53.31,
    2 => 33.34,
    3 => -66.66,
    4 => 46.67,
    5 => -33.33,
    6 => -33.33,
];
$settlements6 = calculateSettlements($balances6);
$totalPos = array_sum(array_filter($balances6, fn($b) => $b > 0));
$totalNeg = abs(array_sum(array_filter($balances6, fn($b) => $b < 0)));
assert_true('6 osob – suma kladných ≈ suma záporných', abs($totalPos - $totalNeg) < 0.05);

$sumTransferred6 = array_sum(array_column($settlements6, 'amount'));
// Tolerance 0.02 – greedy algoritmus může mít haléřový rozdíl při opakovaném round()
assert_true('6 osob – převedeno ≈ celkový dluh (tolerance ±0.02)',
    abs($totalNeg - $sumTransferred6) < 0.02);
// Greedy dá max N-1 transakcí pro N osob
assert_true('6 osob – max 5 transakcí', count($settlements6) <= 5);

section('Settlement algoritmus – edge cases');

// Jeden obrovský věřitel
$settlements = calculateSettlements([
    'A' => 300.00,
    'B' => -100.00,
    'C' => -100.00,
    'D' => -100.00,
]);
assert_equals('1 věřitel, 3 dlužníci – 3 transakce', 3, count($settlements));
foreach ($settlements as $s) {
    assert_equals('Každý platí A', 'A', $s['to']);
    assert_equals('Každý platí 100.00', 100.00, $s['amount']);
}

// Bilance < 0.01 jsou ignorovány (haléřové rozdíly)
$settlements = calculateSettlements([
    'A' => 0.005,
    'B' => -0.005,
]);
assert_equals('Haléřové rozdíly – 0 transakcí', 0, count($settlements));

// ============================================================
// TESTY: Integrita dat – SUM kontrola
// ============================================================

section('Integrita splitů – SUM(splits) == amount_eur');

$testCases = [
    [10.00, [1, 2, 3]],
    [100.00, [1, 2, 3, 4, 5, 6]],
    [33.33, [1, 2, 3]],
    [0.01, [1, 2, 3, 4, 5]],
    [999.99, [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]],
    [1.00, [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12]],
    [5000.00, [1, 2]],
];

foreach ($testCases as [$amount, $users]) {
    $splits = calculateSplits($amount, $users);
    $sum = round(array_sum($splits), 2);
    $label = "{$amount} EUR / " . count($users) . " osob – SUM={$sum}";
    assert_true($label, verifySplitSum($amount, $splits));
}

// ============================================================
// Výsledky
// ============================================================

$total = $passed + $failed;
echo "\n";
echo str_repeat('─', 50) . "\n";
if ($failed === 0) {
    echo "\033[1;32m✓ Všechny testy prošly ({$passed}/{$total})\033[0m\n";
} else {
    echo "\033[1;31m✗ Selhalo {$failed}/{$total} testů\033[0m\n";
    echo "\nSelhané testy:\n";
    foreach ($errors as $e) {
        echo "  - {$e}\n";
    }
}
echo str_repeat('─', 50) . "\n";

exit($failed > 0 ? 1 : 0);
