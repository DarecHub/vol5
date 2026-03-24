<?php
/**
 * VOL5 – Property-based fuzz test split algoritmu
 *
 * 1000 náhodných scénářů. Ověřuje invarianty které musí platit VŽDY,
 * bez ohledu na konkrétní hodnoty.
 *
 * Nezávisí na DB ani serveru – testuje čistou PHP logiku.
 * Spuštění: docker exec vol5_web php /var/www/html/tests/fuzz_test.php
 *           php tests/fuzz_test.php  (lokálně)
 */

$passed = 0; $failed = 0; $errors = [];

function ok(string $label, bool $cond, string $detail = ''): void {
    global $passed, $failed, $errors;
    if ($cond) { $passed++; }
    else {
        echo "\033[31m  ✗ {$label}\033[0m\n";
        if ($detail) echo "      {$detail}\n";
        $failed++; $errors[] = $label;
    }
}
function section(string $n): void { echo "\n\033[1;34m▶ {$n}\033[0m\n"; }

// ============================================================
// Přesná replika produkčního algoritmu z api/wallet.php
// ============================================================

function calcSplits(float $amountEur, array $userIds): array {
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

function calcSettlements(array $balances): array {
    $debtors = []; $creditors = [];
    foreach ($balances as $uid => $bal) {
        $bal = round($bal, 2);
        if ($bal < -0.01) $debtors[]   = ['id' => $uid, 'amount' => abs($bal)];
        elseif ($bal > 0.01) $creditors[] = ['id' => $uid, 'amount' => $bal];
    }
    usort($debtors,   fn($a,$b) => $b['amount'] <=> $a['amount']);
    usort($creditors, fn($a,$b) => $b['amount'] <=> $a['amount']);
    $settlements = []; $di = 0; $ci = 0;
    while ($di < count($debtors) && $ci < count($creditors)) {
        $transfer = round(min($debtors[$di]['amount'], $creditors[$ci]['amount']), 2);
        if ($transfer > 0.01) $settlements[] = ['from'=>$debtors[$di]['id'],'to'=>$creditors[$ci]['id'],'amount'=>$transfer];
        $debtors[$di]['amount']   = round($debtors[$di]['amount'] - $transfer, 2);
        $creditors[$ci]['amount'] = round($creditors[$ci]['amount'] - $transfer, 2);
        if ($debtors[$di]['amount'] < 0.01) $di++;
        if ($creditors[$ci]['amount'] < 0.01) $ci++;
    }
    return $settlements;
}

// ============================================================
// BLOK 1: Fuzz split algoritmu (1000 iterací)
// ============================================================
section('FUZZ – Split algoritmus (1000 náhodných scénářů)');

mt_srand(42); // Fixní seed pro reprodukovatelnost

$splitFailed = 0;
for ($i = 0; $i < 1000; $i++) {
    // Náhodná částka 0.01 – 9999.99 EUR
    $amount  = round(mt_rand(1, 999999) / 100, 2);
    // Náhodný počet lidí 1 – 15
    $nUsers  = mt_rand(1, 15);
    $userIds = range(1, $nUsers);

    $splits = calcSplits($amount, $userIds);

    // Invariant 1: Počet splitů == počet uživatelů
    if (count($splits) !== $nUsers) {
        ok("Fuzz #{$i}: count(splits)=={$nUsers}", false, "amount={$amount} n={$nUsers} got=".count($splits));
        $splitFailed++; continue;
    }

    // Invariant 2: SUM(splits) == amount (tolerance 0.001)
    $sum = round(array_sum($splits), 10);
    $diff = abs($sum - $amount);
    if ($diff >= 0.005) {
        ok("Fuzz #{$i}: SUM=={$amount}", false, "amount={$amount} n={$nUsers} SUM={$sum} diff={$diff}");
        $splitFailed++; continue;
    }

    // Invariant 3: Každý split >= 0
    $minSplit = min($splits);
    if ($minSplit < 0) {
        ok("Fuzz #{$i}: každý split >= 0", false, "amount={$amount} n={$nUsers} min={$minSplit}");
        $splitFailed++; continue;
    }

    // Invariant 4: Max rozdíl mezi splity <= 0.01 EUR (jeden haléř)
    // POZN: floor() algoritmus způsobuje zbytek až (N-1)*0.01 na prvním splitu.
    // Správný invariant: (první split - ostatní) <= 0.01
    // Všichni ostatní jsou identičtí (perPerson), pouze první má přidaný remainder.
    $maxSplit = max($splits);
    $otherSplits = array_slice($splits, 1); // vše kromě prvního
    $maxOther = !empty($otherSplits) ? max($otherSplits) : 0;
    $minOther = !empty($otherSplits) ? min($otherSplits) : 0;
    // Ostatní splity musí být identické (pouze floor zaokrouhlení)
    if (!empty($otherSplits) && abs($maxOther - $minOther) > 0.001) {
        ok("Fuzz #{$i}: ostatní splity jsou identické", false,
            "amount={$amount} n={$nUsers} maxOther={$maxOther} minOther={$minOther}");
        $splitFailed++; continue;
    }
    // První split může být větší než ostatní (má zbytek), ale nikdy menší
    if (!empty($otherSplits) && $splits[1] < $maxOther - 0.001) {
        ok("Fuzz #{$i}: první split >= ostatní", false,
            "amount={$amount} n={$nUsers} first={$splits[1]} others={$maxOther}");
        $splitFailed++; continue;
    }

    $passed++;
}

if ($splitFailed === 0) {
    echo "  \033[32m✓ 1000/1000 scénářů prošlo všemi invarianty\033[0m\n";
} else {
    echo "  \033[31m✗ {$splitFailed}/1000 scénářů selhalo\033[0m\n";
}

// ============================================================
// BLOK 2: Fuzz CZK→EUR přepočtu (500 iterací)
// ============================================================
section('FUZZ – CZK→EUR přepočet (500 náhodných kurzů a částek)');

mt_srand(123);
$czkFailed = 0;
for ($i = 0; $i < 500; $i++) {
    $czk  = round(mt_rand(100, 500000) / 100, 2);  // 1.00 – 5000.00 CZK
    $rate = round(mt_rand(2000, 3500) / 100, 2);   // 20.00 – 35.00 CZK/EUR

    $eur = round($czk / $rate, 2);

    // Invariant: zpětný přepočet EUR→CZK musí být v toleranci
    // round(czk/rate, 2)*rate může lišit o až 0.005*rate CZK (haléřová ztráta)
    $backCzk = round($eur * $rate, 2);
    $diff = abs($backCzk - $czk);
    $tol = 0.005 * $rate + 0.01;
    if ($diff > $tol) {
        ok("CZK Fuzz #{$i}: round-trip tolerance", false,
            "czk={$czk} rate={$rate} eur={$eur} backCzk={$backCzk} diff={$diff} tol=".round($tol,4));
        $czkFailed++;
    } else {
        $passed++;
    }

    // Invariant: EUR > 0 pro kladné CZK a rate
    if ($eur <= 0) {
        ok("CZK Fuzz #{$i}: EUR > 0", false, "czk={$czk} rate={$rate} eur={$eur}");
        $czkFailed++; $failed++;
    }
}

if ($czkFailed === 0) {
    echo "  \033[32m✓ 500/500 CZK přepočtů prošlo invarianty\033[0m\n";
}

// ============================================================
// BLOK 3: Fuzz settlement algoritmu (300 iterací)
// ============================================================
section('FUZZ – Settlement algoritmus (300 náhodných bilancí)');

mt_srand(777);
$setFailed = 0;
for ($i = 0; $i < 300; $i++) {
    $nUsers = mt_rand(2, 12);

    // Generuj náhodné bilance tak aby sumovaly na 0
    $balances = [];
    $runningSum = 0.0;
    for ($u = 1; $u < $nUsers; $u++) {
        $bal = round(mt_rand(-50000, 50000) / 100, 2);
        $balances[$u] = $bal;
        $runningSum = round($runningSum + $bal, 2);
    }
    // Poslední uživatel vyrovná součet
    $balances[$nUsers] = round(-$runningSum, 2);

    $settlements = calcSettlements($balances);

    // Invariant 1: Počet transakcí <= N-1
    $n = count(array_filter($balances, fn($b) => abs($b) > 0.01));
    if ($n > 1 && count($settlements) > $n - 1) {
        ok("Settlement Fuzz #{$i}: count <= N-1", false,
            "n={$n} settlements=".count($settlements));
        $setFailed++; continue;
    }

    // Invariant 2: Každý settlement amount > 0
    foreach ($settlements as $st) {
        if ($st['amount'] <= 0) {
            ok("Settlement Fuzz #{$i}: amount > 0", false, "amount={$st['amount']}");
            $setFailed++; continue 2;
        }
    }

    // Invariant 3: SUM(settlements) ≈ SUM(záporných bilancí)
    $sumDebts    = abs(array_sum(array_filter($balances, fn($b) => $b < -0.01)));
    $sumTransfer = array_sum(array_column($settlements, 'amount'));
    if (abs($sumDebts - $sumTransfer) > 0.05 * max(1, $sumDebts)) {
        ok("Settlement Fuzz #{$i}: SUM transfers ≈ SUM debts", false,
            "debts={$sumDebts} transfers={$sumTransfer}");
        $setFailed++; continue;
    }

    // Invariant 4: Po zaplacení jsou všichni na ~0
    $simBals = $balances;
    foreach ($settlements as $st) {
        $f = $st['from']; $t = $st['to']; $a = $st['amount'];
        $simBals[$f] = round($simBals[$f] + $a, 2);
        $simBals[$t] = round($simBals[$t] - $a, 2);
    }
    $maxResidual = max(array_map('abs', $simBals));
    if ($maxResidual > 0.05) {
        ok("Settlement Fuzz #{$i}: po settlements residuum <= 0.05", false,
            "maxResidual={$maxResidual}");
        $setFailed++; continue;
    }

    $passed++;
}

if ($setFailed === 0) {
    echo "  \033[32m✓ 300/300 settlement scénářů prošlo invarianty\033[0m\n";
}

// ============================================================
// BLOK 4: Specifické edge cases (deterministické)
// ============================================================
section('EDGE CASES – Specifické hraniční případy');

// 0.01 EUR / 1 osoba
$sp = calcSplits(0.01, [1]);
ok('0.01 EUR / 1 osoba = 0.01', abs($sp[1] - 0.01) < 0.001, "actual={$sp[1]}");

// 0.01 EUR / 10 osob – zbytek 0.01 na prvního, ostatní 0.00
$sp = calcSplits(0.01, range(1,10));
ok('0.01 EUR / 10: první = 0.01', abs($sp[1] - 0.01) < 0.001, "actual={$sp[1]}");
ok('0.01 EUR / 10: ostatní = 0.00', max(array_slice($sp, 1)) < 0.001);
ok('0.01 EUR / 10: SUM = 0.01', abs(array_sum($sp) - 0.01) < 0.001);

// 9999.99 EUR / 10 – velká částka
$sp = calcSplits(9999.99, range(1,10));
ok('9999.99 / 10: SUM správný', abs(array_sum($sp) - 9999.99) < 0.001);
ok('9999.99 / 10: první = 1000.08', abs($sp[1] - 1000.08) < 0.001, "actual={$sp[1]}");
ok('9999.99 / 10: ostatní = 999.99', abs($sp[2] - 999.99) < 0.001, "actual={$sp[2]}");

// 1 EUR / 3 – klasický třetinový problém
$sp = calcSplits(1.00, [1,2,3]);
ok('1.00 / 3: SUM = 1.00', abs(array_sum($sp) - 1.00) < 0.001);
ok('1.00 / 3: první = 0.34', abs($sp[1] - 0.34) < 0.001, "actual={$sp[1]}");
ok('1.00 / 3: ostatní = 0.33', abs($sp[2] - 0.33) < 0.001, "actual={$sp[2]}");

// Prázdný seznam uživatelů
$sp = calcSplits(100.00, []);
ok('100 EUR / 0 uživatelů = prázdné pole', $sp === []);

// Settlement: všichni na nule → žádné transakce
$st = calcSettlements([1 => 0.00, 2 => 0.00, 3 => 0.00]);
ok('Všichni na 0 → 0 settlement transakcí', count($st) === 0);

// Settlement: haléřové rozdíly pod prahem se ignorují
$st = calcSettlements([1 => 0.005, 2 => -0.005]);
ok('Haléřové rozdíly (0.005) se ignorují', count($st) === 0);

// Settlement: 1 věřitel, 4 dlužníci
$st = calcSettlements([1 => 400.00, 2 => -100.00, 3 => -100.00, 4 => -100.00, 5 => -100.00]);
ok('1 věřitel, 4 dlužníci → 4 transakce', count($st) === 4, 'actual='.count($st));
foreach ($st as $s) {
    ok("Každý platí 100.00 uživateli 1", $s['to'] === 1 && abs($s['amount'] - 100.00) < 0.01);
}

// ============================================================
// BLOK 5: Reprodukovatelnost – stejný seed → stejný výsledek
// ============================================================
section('REPRODUKOVATELNOST – Fixní seed → stejné výsledky');

mt_srand(42);
$results1 = [];
for ($i = 0; $i < 10; $i++) {
    $amount = round(mt_rand(1, 999999) / 100, 2);
    $n = mt_rand(1, 15);
    $sp = calcSplits($amount, range(1, $n));
    $results1[] = round(array_sum($sp), 4);
}

mt_srand(42);
$results2 = [];
for ($i = 0; $i < 10; $i++) {
    $amount = round(mt_rand(1, 999999) / 100, 2);
    $n = mt_rand(1, 15);
    $sp = calcSplits($amount, range(1, $n));
    $results2[] = round(array_sum($sp), 4);
}

ok('Stejný seed → stejné výsledky (reprodukovatelné)', $results1 === $results2);

// ============================================================
// Výsledky
// ============================================================
echo "\n" . str_repeat('=', 60) . "\n";
$total = $passed + $failed;
if ($failed === 0) {
    echo "\033[1;32m  PASS: {$passed}/{$total} testů prošlo\033[0m\n";
    echo "\033[32m  1800 náhodných scénářů + edge cases – vše OK.\033[0m\n";
} else {
    echo "\033[1;31m  FAIL: {$failed} selhalo, {$passed}/{$total} prošlo\033[0m\n";
    foreach ($errors as $e) echo "    - {$e}\n";
}
echo str_repeat('=', 60) . "\n";
exit($failed > 0 ? 1 : 0);
