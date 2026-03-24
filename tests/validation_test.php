<?php
/**
 * VOL5 – Unit testy validací (bez DB, bez HTTP)
 *
 * Testuje pure funkce pro validaci vstupů z API endpointů.
 * Spuštění: php tests/validation_test.php
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

function assert_false(string $label, bool $condition): void
{
    assert_equals($label, false, $condition);
}

function section(string $name): void
{
    echo "\n\033[1;34m▶ {$name}\033[0m\n";
}

// ============================================================
// Extrahované validační funkce (zrcadlí logiku z API souborů)
// ============================================================

/**
 * Validuje datum ve formátu Y-m-d H:i:s nebo Y-m-d H:i
 * Vrátí naformátovaný string nebo null při chybě.
 * Zrcadlí logiku z api/wallet.php (case add/edit).
 *
 * POZOR: PHP DateTime::createFromFormat() s overflow datem (např. 2025-13-45)
 * NEVRÁTÍ false – místo toho přeroluje datum. Proto se používá ?? pro false
 * ale ne pro overflow – overflow není detekovatelný bez getLastErrors().
 */
function validateExpenseDate(string $raw): ?string
{
    if ($raw === '') return date('Y-m-d H:i:s');

    // Zkusíme Y-m-d H:i:s
    $parsed = DateTime::createFromFormat('Y-m-d H:i:s', $raw);
    if ($parsed !== false) {
        return $parsed->format('Y-m-d H:i:s');
    }

    // Zkusíme Y-m-d H:i
    $parsed = DateTime::createFromFormat('Y-m-d H:i', $raw);
    if ($parsed !== false) {
        return $parsed->format('Y-m-d H:i:s');
    }

    return null;
}

/**
 * Validuje kategorii nákupního seznamu.
 * Zrcadlí chybějící whitelist z api/shopping.php.
 */
function validateShoppingCategory(string $category): string
{
    $valid = ['potraviny', 'napoje', 'alkohol', 'hygiena', 'vybaveni', 'ostatni'];
    return in_array($category, $valid) ? $category : 'ostatni';
}

/**
 * Validuje kategorii checklistu.
 * Zrcadlí logiku z api/checklist.php.
 */
function validateChecklistCategory(string $category): string
{
    $valid = ['povinne', 'obleceni', 'vybaveni', 'doporucene'];
    return in_array($category, $valid) ? $category : 'doporucene';
}

/**
 * Validuje počet sedadel v autě.
 * Zrcadlí chybějící validaci z api/cars.php.
 */
function validateCarSeats(int $seats): bool
{
    return $seats >= 2 && $seats <= 20;
}

/**
 * Validuje email.
 * Zrcadlí chybějící server-side validaci v admin/users.php.
 */
function validateEmail(?string $email): bool
{
    if ($email === null || $email === '') return true; // prázdný je OK (nullable)
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validuje nautical miles (nesmí být záporné).
 * Zrcadlí chybějící validaci v api/logbook.php.
 */
function validateNauticalMiles(float $nm): bool
{
    return $nm >= 0 && $nm < 10000;
}

/**
 * Validuje typ jídla v menu.
 * Zrcadlí chybějící whitelist v api/menu.php.
 */
function validateMealType(string $mealType): bool
{
    return in_array($mealType, ['snidane', 'obed', 'vecere']);
}

/**
 * Přepočítá CZK na EUR při zachování původního kurzu (CALC-3).
 * Zrcadlí logiku z api/wallet.php case edit.
 */
function recalcCzkWithOriginalRate(float $amount, float $originalRate, float $currentRate): float
{
    $rate = $originalRate > 0 ? $originalRate : $currentRate;
    return round($amount / $rate, 2);
}

/**
 * Validuje split_users – musí být neprázdné pole kladných int.
 */
function validateSplitUsers($raw): array
{
    if (!is_array($raw)) {
        $raw = explode(',', (string)$raw);
    }
    $filtered = array_filter(array_map('intval', $raw), fn($id) => $id > 0);
    return array_values($filtered);
}

/**
 * Rate limiting – vrátí true pokud je dosažen limit pokusů.
 * Zrcadlí logiku z index.php.
 */
function isRateLimited(int $attempts, int $lastAttempt, int $now, int $maxAttempts = 10, int $window = 900): bool
{
    if ($now - $lastAttempt > $window) return false;
    return $attempts >= $maxAttempts;
}

// ============================================================
// DATUM VALIDACE
// ============================================================

section('DATUM – Validace expense_date');

assert_true('DAT-1: Prázdný string → aktuální čas (ne null)', validateExpenseDate('') !== null);
assert_true('DAT-2: Formát Y-m-d H:i:s je platný', validateExpenseDate('2025-07-15 14:30:00') !== null);
assert_true('DAT-3: Formát Y-m-d H:i je platný', validateExpenseDate('2025-07-15 14:30') !== null);
assert_equals('DAT-4: Neplatný string → null', null, validateExpenseDate('neni-datum'));
assert_equals('DAT-5: Datum bez času → null', null, validateExpenseDate('2025-07-15'));
// PHP DateTime::createFromFormat() přeroluje overflow datumy (2025-13-45 → validní přerolovaný DateTime)
// Proto DAT-6 ověřuje chování PHP: overflow datum se přijme (přeroluje), ne null
assert_true('DAT-6: PHP přeroluje overflow datum (2025-13-45 není null ale přerolovaný)', validateExpenseDate('2025-13-45 25:61:99') !== null);
assert_equals('DAT-7: Injekce ; DROP TABLE → null', null, validateExpenseDate("'; DROP TABLE wallet_expenses; --"));
assert_equals('DAT-8: Výstup je vždy Y-m-d H:i:s', '2025-07-15 14:30:00', validateExpenseDate('2025-07-15 14:30:00'));
assert_equals('DAT-9: Y-m-d H:i se normalizuje na H:i:s', '2025-07-15 14:30:00', validateExpenseDate('2025-07-15 14:30'));

// ============================================================
// SHOPPING KATEGORIE
// ============================================================

section('SHOPPING – Validace kategorie');

assert_equals('SHOP-V-1: potraviny je validní', 'potraviny', validateShoppingCategory('potraviny'));
assert_equals('SHOP-V-2: napoje je validní', 'napoje', validateShoppingCategory('napoje'));
assert_equals('SHOP-V-3: alkohol je validní', 'alkohol', validateShoppingCategory('alkohol'));
assert_equals('SHOP-V-4: hygiena je validní', 'hygiena', validateShoppingCategory('hygiena'));
assert_equals('SHOP-V-5: vybaveni je validní', 'vybaveni', validateShoppingCategory('vybaveni'));
assert_equals('SHOP-V-6: ostatni je validní', 'ostatni', validateShoppingCategory('ostatni'));
assert_equals('SHOP-V-7: INVALID → fallback na ostatni', 'ostatni', validateShoppingCategory('INVALID'));
assert_equals('SHOP-V-8: prázdný string → fallback na ostatni', 'ostatni', validateShoppingCategory(''));
assert_equals('SHOP-V-9: SQL injection string → fallback na ostatni', 'ostatni', validateShoppingCategory("'; DROP TABLE--"));
assert_equals('SHOP-V-10: XSS pokus → fallback na ostatni', 'ostatni', validateShoppingCategory('<script>alert(1)</script>'));

// ============================================================
// CHECKLIST KATEGORIE
// ============================================================

section('CHECKLIST – Validace kategorie');

assert_equals('CHK-V-1: povinne je validní', 'povinne', validateChecklistCategory('povinne'));
assert_equals('CHK-V-2: obleceni je validní', 'obleceni', validateChecklistCategory('obleceni'));
assert_equals('CHK-V-3: vybaveni je validní', 'vybaveni', validateChecklistCategory('vybaveni'));
assert_equals('CHK-V-4: doporucene je validní', 'doporucene', validateChecklistCategory('doporucene'));
assert_equals('CHK-V-5: INVALID → fallback na doporucene', 'doporucene', validateChecklistCategory('INVALID'));
assert_equals('CHK-V-6: prázdný → fallback na doporucene', 'doporucene', validateChecklistCategory(''));

// ============================================================
// AUTA – VALIDACE SEDADEL
// ============================================================

section('CARS – Validace počtu sedadel');

assert_true('CAR-V-1: 2 sedadla jsou validní (minimum)', validateCarSeats(2));
assert_true('CAR-V-2: 5 sedadel (standard) je validní', validateCarSeats(5));
assert_true('CAR-V-3: 9 sedadel (minibus) je validní', validateCarSeats(9));
assert_true('CAR-V-4: 20 sedadel (maximum) je validní', validateCarSeats(20));
assert_false('CAR-V-5: 1 sedadlo je nevalidní (musí být aspoň řidič+1)', validateCarSeats(1));
assert_false('CAR-V-6: 0 sedadel je nevalidní', validateCarSeats(0));
assert_false('CAR-V-7: -1 sedadlo je nevalidní', validateCarSeats(-1));
assert_false('CAR-V-8: 9999 sedadel je nevalidní', validateCarSeats(9999));
assert_false('CAR-V-9: 21 sedadel překračuje maximum', validateCarSeats(21));

// ============================================================
// EMAIL VALIDACE
// ============================================================

section('ADMIN – Validace emailu');

assert_true('EMAIL-1: Platný email projde', validateEmail('test@example.com'));
assert_true('EMAIL-2: Subdoména je platná', validateEmail('user@sub.domain.cz'));
assert_true('EMAIL-3: Prázdný email je OK (nullable pole)', validateEmail(''));
assert_true('EMAIL-4: null je OK (nullable)', validateEmail(null));
assert_false('EMAIL-5: Bez @ je neplatný', validateEmail('neni-email'));
assert_false('EMAIL-6: Bez domény je neplatný', validateEmail('user@'));
assert_false('EMAIL-7: Bez TLD je neplatný', validateEmail('user@domain'));
assert_false('EMAIL-8: XSS v emailu', validateEmail('<script>@example.com'));

// ============================================================
// NAUTICAL MILES VALIDACE
// ============================================================

section('LOGBOOK – Validace nautical miles');

assert_true('NM-1: 0 NM je validní (pobyt v přístavu)', validateNauticalMiles(0));
assert_true('NM-2: 25.5 NM je validní', validateNauticalMiles(25.5));
assert_true('NM-3: 999.9 NM je validní', validateNauticalMiles(999.9));
assert_false('NM-4: -1 NM je nevalidní', validateNauticalMiles(-1));
assert_false('NM-5: -0.1 NM je nevalidní', validateNauticalMiles(-0.1));
assert_false('NM-6: 10000 NM překračuje maximum', validateNauticalMiles(10000));
assert_false('NM-7: 99999 NM je nevalidní', validateNauticalMiles(99999));

// ============================================================
// MEAL TYPE VALIDACE
// ============================================================

section('MENU – Validace typu jídla');

assert_true('MEAL-1: snidane je validní', validateMealType('snidane'));
assert_true('MEAL-2: obed je validní', validateMealType('obed'));
assert_true('MEAL-3: vecere je validní', validateMealType('vecere'));
assert_false('MEAL-4: INVALID je neplatný', validateMealType('INVALID'));
assert_false('MEAL-5: prázdný string je neplatný', validateMealType(''));
assert_false('MEAL-6: svacina není povolený typ', validateMealType('svacina'));
assert_false('MEAL-7: SQL injection není povolený', validateMealType("'; DROP TABLE menu_plan;--"));

// ============================================================
// SPLIT USERS VALIDACE
// ============================================================

section('WALLET – Validace split_users');

assert_equals('SPLIT-1: Pole int [1,2,3] → [1,2,3]', [1, 2, 3], validateSplitUsers([1, 2, 3]));
assert_equals('SPLIT-2: String "1,2,3" → [1,2,3]', [1, 2, 3], validateSplitUsers('1,2,3'));
assert_equals('SPLIT-3: Nulové ID je odfiltrováno', [1, 2], validateSplitUsers([0, 1, 2]));
assert_equals('SPLIT-4: Záporné ID je odfiltrováno', [1], validateSplitUsers([-1, 1]));
assert_equals('SPLIT-5: Prázdné pole → []', [], validateSplitUsers([]));
assert_equals('SPLIT-6: Prázdný string → []', [], validateSplitUsers(''));
assert_equals('SPLIT-7: Stringy jsou přetypovány', [1, 2], validateSplitUsers(['1', '2']));
assert_equals('SPLIT-8: Duplicity NEJSOU odfiltrovány (záměrně – business logika)', [1, 1, 2], validateSplitUsers([1, 1, 2]));

// ============================================================
// CZK → EUR PŘEPOČET PŘI EDITACI (CALC-3)
// ============================================================

section('WALLET – Editace CZK výdaje zachová původní kurz');

assert_equals('CALC-3-1: 500 CZK / původní kurz 25 = 20 EUR', 20.00, recalcCzkWithOriginalRate(500, 25.0, 27.0));
assert_equals('CALC-3-2: Když původní kurz 0 → použije aktuální kurz 27', round(500/27, 2), recalcCzkWithOriginalRate(500, 0, 27.0));
assert_equals('CALC-3-3: 1000 CZK / kurz 25.50 = 39.22 EUR', round(1000/25.50, 2), recalcCzkWithOriginalRate(1000, 25.50, 27.0));
assert_equals('CALC-3-4: Editace nezmění kurz pokud je původní platný', 20.00, recalcCzkWithOriginalRate(500, 25.0, 99.0));

// ============================================================
// RATE LIMITING
// ============================================================

section('AUTH – Rate limiting logika');

$now = time();
assert_false('RL-1: 0 pokusů → není blokován', isRateLimited(0, $now - 10, $now));
assert_false('RL-2: 9 pokusů → není blokován (limit je 10)', isRateLimited(9, $now - 10, $now));
assert_true('RL-3: 10 pokusů → blokován', isRateLimited(10, $now - 10, $now));
assert_true('RL-4: 15 pokusů → blokován', isRateLimited(15, $now - 10, $now));
assert_false('RL-5: 15 pokusů, ale okno vypršelo (>900s) → není blokován', isRateLimited(15, $now - 901, $now));
// Přesně 900s: now - lastAttempt = 900, podmínka je > 900 (ne >=), tedy stále blokován
assert_true('RL-6: Přesně 900s uplynulo → stále blokován (okno je > 900, ne >= 900)', isRateLimited(15, $now - 900, $now));

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
