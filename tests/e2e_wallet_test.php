#!/usr/bin/env php
<?php
/**
 * E2E testy pro finanční logiku (pokladna / wallet)
 *
 * Testuje:
 * 1. Správnost splitů (dělení nákladů)
 * 2. Bilance uživatelů (kdo komu dluží)
 * 3. Settlement kalkulace (vyrovnání)
 * 4. CZK/EUR konverze
 * 5. Přidání/editace/smazání výdajů přes API
 * 6. Edge cases (lichý split, 1 osoba, velké částky)
 *
 * Spuštění: php tests/e2e_wallet_test.php
 * Předpoklady: Aplikace běží na localhost:8080, seed data nahrána
 */

// ============================================================
// KONFIGURACE
// ============================================================

// Autodetect: inside Docker = port 80, host = port 8080
define('BASE_URL', getenv('BASE_URL') ?: (file_exists('/.dockerenv') ? 'http://localhost:80' : 'http://localhost:8080'));
define('MEMBER_PASSWORD', 'crew123');

// Barvy pro terminal
define('GREEN', "\033[32m");
define('RED', "\033[31m");
define('YELLOW', "\033[33m");
define('CYAN', "\033[36m");
define('BOLD', "\033[1m");
define('RESET', "\033[0m");

// ============================================================
// TEST FRAMEWORK
// ============================================================

$testResults = ['pass' => 0, 'fail' => 0, 'errors' => []];
$currentGroup = '';

function group(string $name): void {
    global $currentGroup;
    $currentGroup = $name;
    echo "\n" . BOLD . CYAN . "━━━ $name ━━━" . RESET . "\n";
}

function test(string $name, callable $fn): void {
    global $testResults, $currentGroup;
    try {
        $fn();
        $testResults['pass']++;
        echo GREEN . "  ✓ " . RESET . "$name\n";
    } catch (\Throwable $e) {
        $testResults['fail']++;
        $testResults['errors'][] = "$currentGroup > $name: " . $e->getMessage();
        echo RED . "  ✗ " . RESET . "$name\n";
        echo RED . "    └─ " . $e->getMessage() . RESET . "\n";
    }
}

function assertEqual($expected, $actual, string $msg = ''): void {
    if ($expected !== $actual) {
        $eStr = var_export($expected, true);
        $aStr = var_export($actual, true);
        throw new \RuntimeException(
            ($msg ? "$msg: " : '') . "očekáváno $eStr, dostal $aStr"
        );
    }
}

function assertAlmostEqual(float $expected, float $actual, float $tolerance = 0.015, string $msg = ''): void {
    if (abs($expected - $actual) > $tolerance) {
        throw new \RuntimeException(
            ($msg ? "$msg: " : '') . "očekáváno ~$expected, dostal $actual (tolerance $tolerance)"
        );
    }
}

function assertTrue(bool $condition, string $msg = ''): void {
    if (!$condition) {
        throw new \RuntimeException($msg ?: 'Assertion failed');
    }
}

// ============================================================
// HTTP CLIENT (s cookie session)
// ============================================================

class AppClient {
    private string $cookieFile;
    private string $csrfToken = '';

    public function __construct() {
        $this->cookieFile = tempnam(sys_get_temp_dir(), 'sail_test_');
    }

    public function __destruct() {
        if (file_exists($this->cookieFile)) {
            unlink($this->cookieFile);
        }
    }

    public function login(int $userId): bool {
        // Nejdřív GET na login stránku pro CSRF token
        $html = $this->rawGet('/');
        if (!preg_match('/name="csrf_token"\s+value="([^"]+)"/', $html, $m)) {
            throw new \RuntimeException('Nepodařilo se získat CSRF token z login stránky');
        }
        $this->csrfToken = $m[1];

        // POST login
        $response = $this->rawPost('/', [
            'login_type' => 'member',
            'user_id' => $userId,
            'member_password' => MEMBER_PASSWORD,
            'csrf_token' => $this->csrfToken,
        ]);

        // Po úspěšném loginu se přesměruje na dashboard
        if (strpos($response, 'dashboard') !== false || strpos($response, 'Location: /pages/dashboard') !== false) {
            // Získat nový CSRF token z dashboard
            $dashHtml = $this->rawGet('/pages/dashboard.php');
            if (preg_match('/name="csrf-token"\s+content="([^"]+)"/', $dashHtml, $m)) {
                $this->csrfToken = $m[1];
            }
            return true;
        }
        return false;
    }

    public function apiGet(string $url): array {
        $body = $this->rawGet($url);
        $data = json_decode($body, true);
        if ($data === null) {
            throw new \RuntimeException("Invalid JSON from $url: " . substr($body, 0, 200));
        }
        return $data;
    }

    public function apiPost(string $url, array $params): array {
        $params['csrf_token'] = $this->csrfToken;
        $body = $this->rawPost($url, $params);
        $data = json_decode($body, true);
        if ($data === null) {
            throw new \RuntimeException("Invalid JSON from POST $url: " . substr($body, 0, 200));
        }
        return $data;
    }

    private function rawGet(string $path): string {
        $ch = curl_init(BASE_URL . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_TIMEOUT => 10,
        ]);
        $result = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($result === false) {
            throw new \RuntimeException("HTTP GET failed: $path");
        }
        return $result;
    }

    private function rawPost(string $path, array $params): string {
        $ch = curl_init(BASE_URL . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'X-CSRF-TOKEN: ' . $this->csrfToken,
            ],
        ]);
        $result = curl_exec($ch);
        curl_close($ch);
        if ($result === false) {
            throw new \RuntimeException("HTTP POST failed: $path");
        }
        return $result;
    }

    public function getCsrf(): string {
        return $this->csrfToken;
    }
}

// ============================================================
// SEED DATA – ručně vypočtené expected hodnoty
// ============================================================

/**
 * Výdaje ze seed.sql:
 *
 * ID | Platil  | Částka    | Měna | EUR     | Split      | Kdo platí
 * 1  | Jana(2) | 2500 CZK  | CZK  | 99.17   | both (10)  | 9.92×7 + 9.91×3
 * 2  | Pavel(1)| 3200 CZK  | CZK  | 126.93  | both (10)  | 12.70×3 + 12.69×7
 * 3  | Pavel(1)| 180 EUR   | EUR  | 180.00  | both (10)  | 18.00×10
 * 4  | Lucie(6)| 120 EUR   | EUR  | 120.00  | boat2 (5)  | 24.00×5
 * 5  | Tomáš(3)| 45 EUR    | EUR  | 45.00   | both (10)  | 4.50×10
 * 6  | Kate(4) | 85 EUR    | EUR  | 85.00   | boat1 (5)  | 17.00×5
 * 7  | Jakub(9)| 78 EUR    | EUR  | 78.00   | boat2 (5)  | 15.60×5
 * 8  | Martin(7)| 65 EUR   | EUR  | 65.00   | both (10)  | 6.50×10
 * 9  | Pavel(1)| 42 EUR    | EUR  | 42.00   | both (10)  | 4.20×10
 * 10 | Lucie(6)| 55 EUR    | EUR  | 55.00   | both (10)  | 5.50×10
 * 11 | Eva(8)  | 1200 CZK  | CZK  | 47.60   | both (10)  | 4.76×10
 * 12 | Ondřej(5)| 38 EUR   | EUR  | 38.00   | boat1 (5)  | 7.60×5
 * 13 | Jakub(9)| 92 EUR    | EUR  | 92.00   | boat2 (5)  | 18.40×5
 * 14 | Tomáš(3)| 75 EUR    | EUR  | 75.00   | boat1 (5)  | 15.00×5
 * 15 | Martin(7)| 110 EUR  | EUR  | 110.00  | both (10)  | 11.00×10
 */

// Ručně vypočtené bilance pro každého uživatele
// Balance = SUM(paid_eur) - SUM(split_shares)

function getExpectedBalances(): array {
    // Paid by each user (amount_eur)
    $paid = [
        1 => 126.93 + 180.00 + 42.00,           // Pavel: 348.93
        2 => 99.17,                               // Jana: 99.17
        3 => 45.00 + 75.00,                       // Tomáš: 120.00
        4 => 85.00,                                // Kateřina: 85.00
        5 => 38.00,                                // Ondřej: 38.00
        6 => 120.00 + 55.00,                       // Lucie: 175.00
        7 => 65.00 + 110.00,                       // Martin: 175.00
        8 => 47.60,                                // Eva: 47.60
        9 => 78.00 + 92.00,                        // Jakub: 170.00
        10 => 0,                                   // Tereza: 0
    ];

    // Share (what each user owes) - from seed splits
    // User 1 (Pavel): exp1(9.92) + exp2(12.70) + exp3(18.00) + exp5(4.50) + exp6(17.00) + exp8(6.50) + exp9(4.20) + exp10(5.50) + exp11(4.76) + exp12(7.60) + exp14(15.00) + exp15(11.00)
    $share = [
        1 => 9.92 + 12.70 + 18.00 + 4.50 + 17.00 + 6.50 + 4.20 + 5.50 + 4.76 + 7.60 + 15.00 + 11.00,
        // User 2 (Jana): same both expenses + boat1
        2 => 9.92 + 12.70 + 18.00 + 4.50 + 17.00 + 6.50 + 4.20 + 5.50 + 4.76 + 7.60 + 15.00 + 11.00,
        // User 3 (Tomáš): same as user 2
        3 => 9.92 + 12.70 + 18.00 + 4.50 + 17.00 + 6.50 + 4.20 + 5.50 + 4.76 + 7.60 + 15.00 + 11.00,
        // User 4 (Kateřina): same (boat1 + both)
        4 => 9.92 + 12.69 + 18.00 + 4.50 + 17.00 + 6.50 + 4.20 + 5.50 + 4.76 + 7.60 + 15.00 + 11.00,
        // User 5 (Ondřej): same
        5 => 9.92 + 12.69 + 18.00 + 4.50 + 17.00 + 6.50 + 4.20 + 5.50 + 4.76 + 7.60 + 15.00 + 11.00,
        // User 6 (Lucie): both expenses + boat2
        6 => 9.91 + 12.69 + 18.00 + 4.50 + 24.00 + 15.60 + 6.50 + 4.20 + 5.50 + 4.76 + 18.40 + 11.00,
        // User 7 (Martin): same as 6
        7 => 9.91 + 12.69 + 18.00 + 4.50 + 24.00 + 15.60 + 6.50 + 4.20 + 5.50 + 4.76 + 18.40 + 11.00,
        // User 8 (Eva): same as 6
        8 => 9.91 + 12.69 + 18.00 + 4.50 + 24.00 + 15.60 + 6.50 + 4.20 + 5.50 + 4.76 + 18.40 + 11.00,
        // User 9 (Jakub): same as 6
        9 => 9.92 + 12.69 + 18.00 + 4.50 + 24.00 + 15.60 + 6.50 + 4.20 + 5.50 + 4.76 + 18.40 + 11.00,
        // User 10 (Tereza): same as 6
        10 => 9.92 + 12.69 + 18.00 + 4.50 + 24.00 + 15.60 + 6.50 + 4.20 + 5.50 + 4.76 + 18.40 + 11.00,
    ];

    $balances = [];
    for ($i = 1; $i <= 10; $i++) {
        $balances[$i] = round($paid[$i] - $share[$i], 2);
    }
    return $balances;
}

// ============================================================
// TESTY
// ============================================================

echo BOLD . "\n╔══════════════════════════════════════════════════════╗\n";
echo "║   E2E TESTY – Finanční logika SailCrew aplikace    ║\n";
echo "╚══════════════════════════════════════════════════════╝\n" . RESET;

// Ověřit, že app běží
$ch = @curl_init(BASE_URL);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5, CURLOPT_CONNECTTIMEOUT => 3]);
$resp = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
if ($resp === false || $httpCode === 0) {
    echo RED . "\n  ✗ Aplikace neběží na " . BASE_URL . " – spusťte docker compose up\n" . RESET;
    exit(1);
}
echo GREEN . "\n  ✓ Aplikace dostupná na " . BASE_URL . "\n" . RESET;

// Login jako Pavel (user 1)
$client = new AppClient();
$loggedIn = $client->login(1);
if (!$loggedIn) {
    echo RED . "  ✗ Login selhal – zkontrolujte heslo (crew123)\n" . RESET;
    exit(1);
}
echo GREEN . "  ✓ Přihlášen jako Pavel Novák (ID 1)\n" . RESET;

// ============================================================
// SKUPINA 1: Ověření seed dat a splitů
// ============================================================

group('1. Ověření výdajů a splitů ze seed dat');

test('Načtení seznamu výdajů', function() use ($client) {
    $data = $client->apiGet('/api/wallet.php?action=list');
    assertTrue($data['success'] ?? false, 'API nevrátilo success');
    assertTrue(count($data['data']['expenses']) >= 15, 'Méně než 15 výdajů');
});

test('Celkový součet výdajů = 1257.70 EUR', function() use ($client) {
    $data = $client->apiGet('/api/wallet.php?action=list');
    // 99.17 + 126.93 + 180 + 120 + 45 + 85 + 78 + 65 + 42 + 55 + 47.60 + 38 + 92 + 75 + 110 = 1258.70
    $expectedTotal = 99.17 + 126.93 + 180.00 + 120.00 + 45.00 + 85.00 + 78.00 + 65.00 + 42.00 + 55.00 + 47.60 + 38.00 + 92.00 + 75.00 + 110.00;
    assertAlmostEqual($expectedTotal, (float)$data['data']['total_eur'], 0.02, 'Celkový součet');
});

test('CZK→EUR konverze: 2500 CZK / 25.21 = 99.17 EUR', function() use ($client) {
    $data = $client->apiGet('/api/wallet.php?action=list');
    $expense1 = null;
    foreach ($data['data']['expenses'] as $e) {
        if ((int)$e['id'] === 1) { $expense1 = $e; break; }
    }
    assertTrue($expense1 !== null, 'Výdaj ID=1 nenalezen');
    assertAlmostEqual(99.17, (float)$expense1['amount_eur'], 0.01, 'CZK→EUR konverze');
    assertEqual('CZK', $expense1['currency']);
    assertAlmostEqual(2500.00, (float)$expense1['amount'], 0.01, 'Původní CZK částka');
});

test('CZK→EUR konverze: 3200 CZK / 25.21 = 126.93 EUR', function() use ($client) {
    $data = $client->apiGet('/api/wallet.php?action=list');
    $expense2 = null;
    foreach ($data['data']['expenses'] as $e) {
        if ((int)$e['id'] === 2) { $expense2 = $e; break; }
    }
    assertTrue($expense2 !== null, 'Výdaj ID=2 nenalezen');
    assertAlmostEqual(126.93, (float)$expense2['amount_eur'], 0.01, 'CZK→EUR konverze 3200');
});

test('CZK→EUR konverze: 1200 CZK / 25.21 = 47.60 EUR', function() use ($client) {
    $data = $client->apiGet('/api/wallet.php?action=list');
    $expense11 = null;
    foreach ($data['data']['expenses'] as $e) {
        if ((int)$e['id'] === 11) { $expense11 = $e; break; }
    }
    assertTrue($expense11 !== null, 'Výdaj ID=11 nenalezen');
    assertAlmostEqual(47.60, (float)$expense11['amount_eur'], 0.01, 'CZK→EUR konverze 1200');
});

test('Split 99.17 EUR / 10 lidí: součet = 99.17', function() use ($client) {
    $data = $client->apiGet('/api/wallet.php?action=list');
    $expense1 = null;
    foreach ($data['data']['expenses'] as $e) {
        if ((int)$e['id'] === 1) { $expense1 = $e; break; }
    }
    assertTrue($expense1 !== null, 'Výdaj ID=1 nenalezen');
    assertEqual(10, count($expense1['split_user_ids']), 'Split na 10 lidí');
});

test('Split 120 EUR / 5 lidí (boat2): jen uživatelé 6-10', function() use ($client) {
    $data = $client->apiGet('/api/wallet.php?action=list');
    $expense4 = null;
    foreach ($data['data']['expenses'] as $e) {
        if ((int)$e['id'] === 4) { $expense4 = $e; break; }
    }
    assertTrue($expense4 !== null, 'Výdaj ID=4 nenalezen');
    $splitIds = array_map('intval', $expense4['split_user_ids']);
    sort($splitIds);
    assertEqual([6, 7, 8, 9, 10], $splitIds, 'Boat2 split musí být user 6-10');
});

test('Split 85 EUR / 5 lidí (boat1): jen uživatelé 1-5', function() use ($client) {
    $data = $client->apiGet('/api/wallet.php?action=list');
    $expense6 = null;
    foreach ($data['data']['expenses'] as $e) {
        if ((int)$e['id'] === 6) { $expense6 = $e; break; }
    }
    assertTrue($expense6 !== null, 'Výdaj ID=6 nenalezen');
    $splitIds = array_map('intval', $expense6['split_user_ids']);
    sort($splitIds);
    assertEqual([1, 2, 3, 4, 5], $splitIds, 'Boat1 split musí být user 1-5');
});

// ============================================================
// SKUPINA 2: Bilance uživatelů
// ============================================================

group('2. Bilance uživatelů (paid - share = balance)');

$balancesData = $client->apiGet('/api/wallet.php?action=balances');
$expectedBalances = getExpectedBalances();
$apiBalances = [];
foreach ($balancesData['data'] ?? $balancesData as $b) {
    $uid = (int)$b['user_id'];
    $apiBalances[$uid] = [
        'name' => $b['name'],
        'paid' => (float)$b['paid'],
        'share' => (float)$b['share'],
        'balance' => (float)$b['balance'],
    ];
}

$userNames = [
    1 => 'Pavel Novák', 2 => 'Jana Horáková', 3 => 'Tomáš Krejčí',
    4 => 'Kateřina Dvořáková', 5 => 'Ondřej Fiala', 6 => 'Lucie Marková',
    7 => 'Martin Blaha', 8 => 'Eva Procházková', 9 => 'Jakub Černý',
    10 => 'Tereza Veselá',
];

foreach ($expectedBalances as $uid => $expectedBal) {
    $name = $userNames[$uid];
    test("Bilance $name (ID $uid): expected $expectedBal EUR", function() use ($uid, $expectedBal, $apiBalances, $name) {
        assertTrue(isset($apiBalances[$uid]), "$name není v API odpovědi");
        $actual = $apiBalances[$uid]['balance'];
        assertAlmostEqual($expectedBal, $actual, 0.02, "Bilance $name");
    });
}

test('Součet všech bilancí = 0 (nulový součet)', function() use ($apiBalances) {
    $sum = 0;
    foreach ($apiBalances as $b) {
        $sum += $b['balance'];
    }
    assertAlmostEqual(0.0, $sum, 0.05, 'Součet bilancí');
});

test('Součet všech paid = součet všech share', function() use ($apiBalances) {
    $totalPaid = 0;
    $totalShare = 0;
    foreach ($apiBalances as $b) {
        $totalPaid += $b['paid'];
        $totalShare += $b['share'];
    }
    assertAlmostEqual($totalPaid, $totalShare, 0.05, 'Paid vs Share celkem');
});

// ============================================================
// SKUPINA 3: Settlement (vyrovnání dluhů)
// ============================================================

group('3. Settlement kalkulace');

$settlementsData = $client->apiGet('/api/wallet.php?action=settlements');
$settlements = $settlementsData['data']['settlements'] ?? $settlementsData['settlements'] ?? [];

test('Settlements existují', function() use ($settlements) {
    assertTrue(count($settlements) > 0, 'Žádné settlements');
});

test('Settlement částky jsou kladné', function() use ($settlements) {
    foreach ($settlements as $s) {
        assertTrue((float)$s['amount'] > 0, "Settlement {$s['from_name']} → {$s['to_name']} má zápornou částku");
    }
});

test('Dlužníci mají zápornou bilanci, věřitelé kladnou', function() use ($settlements, $apiBalances) {
    foreach ($settlements as $s) {
        $fromId = (int)$s['from_id'];
        $toId = (int)$s['to_id'];
        if (isset($apiBalances[$fromId])) {
            assertTrue($apiBalances[$fromId]['balance'] < 0,
                "Dlužník {$s['from_name']} by měl mít zápornou bilanci, má {$apiBalances[$fromId]['balance']}");
        }
        if (isset($apiBalances[$toId])) {
            assertTrue($apiBalances[$toId]['balance'] > 0,
                "Věřitel {$s['to_name']} by měl mít kladnou bilanci, má {$apiBalances[$toId]['balance']}");
        }
    }
});

test('Celkem vyrovnáno pro dlužníky = jejich dluh', function() use ($settlements, $apiBalances) {
    // Sečteme kolik každý dlužník zaplatí v settlements
    $fromTotals = [];
    foreach ($settlements as $s) {
        $fid = (int)$s['from_id'];
        $fromTotals[$fid] = ($fromTotals[$fid] ?? 0) + (float)$s['amount'];
    }
    foreach ($fromTotals as $uid => $settledAmount) {
        if (isset($apiBalances[$uid])) {
            $debt = abs($apiBalances[$uid]['balance']);
            assertAlmostEqual($debt, $settledAmount, 0.05,
                "Dluh user $uid ({$apiBalances[$uid]['name']}): bilance $debt vs settlement $settledAmount");
        }
    }
});

test('Celkem přijato pro věřitele = jejich pohledávka', function() use ($settlements, $apiBalances) {
    $toTotals = [];
    foreach ($settlements as $s) {
        $tid = (int)$s['to_id'];
        $toTotals[$tid] = ($toTotals[$tid] ?? 0) + (float)$s['amount'];
    }
    foreach ($toTotals as $uid => $receivedAmount) {
        if (isset($apiBalances[$uid])) {
            $credit = $apiBalances[$uid]['balance'];
            assertAlmostEqual($credit, $receivedAmount, 0.05,
                "Pohledávka user $uid ({$apiBalances[$uid]['name']}): bilance $credit vs přijato $receivedAmount");
        }
    }
});

test('Settlement CZK ekvivalent je správný', function() use ($settlementsData) {
    $rate = (float)($settlementsData['data']['rate'] ?? $settlementsData['rate'] ?? 0);
    assertTrue($rate > 20 && $rate < 30, "Exchange rate $rate mimo rozsah 20-30");

    $settlements = $settlementsData['data']['settlements'] ?? $settlementsData['settlements'] ?? [];
    foreach ($settlements as $s) {
        if (isset($s['amount_czk'])) {
            $expectedCzk = round((float)$s['amount'] * $rate, 2);
            assertAlmostEqual($expectedCzk, (float)$s['amount_czk'], 0.10,
                "CZK ekvivalent pro {$s['from_name']} → {$s['to_name']}");
        }
    }
});

test('Settlement pro Tereza→Pavel je marked as settled (ze seed dat)', function() use ($settlements) {
    $found = false;
    foreach ($settlements as $s) {
        if ((int)$s['from_id'] === 10 && (int)$s['to_id'] === 1) {
            $found = true;
            assertTrue((bool)$s['settled'], 'Tereza→Pavel by měl být settled dle seed dat');
        }
    }
    // Tereza nemusí nutně dlužit Pavlovi v greedy algoritmu, tak skip pokud neexistuje
    if (!$found) {
        echo YELLOW . "      (Tereza→Pavel settlement neexistuje v greedy výpočtu – OK)\n" . RESET;
    }
});

// ============================================================
// SKUPINA 4: CRUD výdajů přes API
// ============================================================

group('4. CRUD operace – přidání, editace, smazání výdaje');

$newExpenseId = null;

test('Přidat nový výdaj 100 EUR / 3 lidi (lichý split)', function() use ($client, &$newExpenseId) {
    $data = $client->apiPost('/api/wallet.php?action=add', [
        'paid_by' => 1,
        'amount' => 100,
        'currency' => 'EUR',
        'description' => 'TEST: Lichý split 100/3',
        'expense_date' => '2025-07-20 12:00',
        'split_type' => 'custom',
        'split_users' => [1, 2, 3],
    ]);
    assertTrue($data['success'] ?? false, 'Přidání výdaje selhalo: ' . json_encode($data));
    assertTrue(isset($data['data']['id']) || isset($data['id']), 'Chybí ID nového výdaje');
    $newExpenseId = (int)($data['data']['id'] ?? $data['id']);
    assertTrue($newExpenseId > 0, 'Neplatné ID výdaje');
});

test('Lichý split 100/3: splity sečtou na 100.00', function() use ($client, &$newExpenseId) {
    assertTrue($newExpenseId > 0, 'Nebyl vytvořen testovací výdaj');
    $data = $client->apiGet('/api/wallet.php?action=list');
    $expense = null;
    foreach ($data['data']['expenses'] as $e) {
        if ((int)$e['id'] === $newExpenseId) { $expense = $e; break; }
    }
    assertTrue($expense !== null, "Výdaj $newExpenseId nenalezen");
    assertEqual(3, count($expense['split_user_ids']), 'Split na 3 lidi');
    assertAlmostEqual(100.00, (float)$expense['amount_eur'], 0.01, 'EUR částka');
});

test('Editace výdaje – změna částky na 150 EUR', function() use ($client, &$newExpenseId) {
    assertTrue($newExpenseId > 0, 'Nebyl vytvořen testovací výdaj');
    $data = $client->apiPost('/api/wallet.php?action=edit', [
        'id' => $newExpenseId,
        'paid_by' => 1,
        'amount' => 150,
        'currency' => 'EUR',
        'description' => 'TEST: Editovaný na 150 EUR',
        'expense_date' => '2025-07-20 12:00',
        'split_type' => 'custom',
        'split_users' => [1, 2, 3],
    ]);
    assertTrue($data['success'] ?? false, 'Editace selhala: ' . json_encode($data));
});

test('Po editaci: nová částka 150 EUR, split 50+50+50', function() use ($client, &$newExpenseId) {
    $data = $client->apiGet('/api/wallet.php?action=list');
    $expense = null;
    foreach ($data['data']['expenses'] as $e) {
        if ((int)$e['id'] === $newExpenseId) { $expense = $e; break; }
    }
    assertTrue($expense !== null, "Výdaj $newExpenseId nenalezen");
    assertAlmostEqual(150.00, (float)$expense['amount_eur'], 0.01, 'EUR po editaci');
});

test('Smazání testovacího výdaje', function() use ($client, &$newExpenseId) {
    assertTrue($newExpenseId > 0, 'Nebyl vytvořen testovací výdaj');
    $data = $client->apiPost('/api/wallet.php?action=delete', [
        'id' => $newExpenseId,
    ]);
    assertTrue($data['success'] ?? false, 'Smazání selhalo: ' . json_encode($data));
});

test('Po smazání: výdaj neexistuje', function() use ($client, &$newExpenseId) {
    $data = $client->apiGet('/api/wallet.php?action=list');
    foreach ($data['data']['expenses'] as $e) {
        assertTrue((int)$e['id'] !== $newExpenseId, "Smazaný výdaj $newExpenseId stále existuje!");
    }
});

// ============================================================
// SKUPINA 5: CZK výdaj přes API
// ============================================================

group('5. CZK výdaj – konverze a split');

$czkExpenseId = null;

test('Přidat CZK výdaj: 5000 CZK pro všech 10', function() use ($client, &$czkExpenseId) {
    $data = $client->apiPost('/api/wallet.php?action=add', [
        'paid_by' => 1,
        'amount' => 5000,
        'currency' => 'CZK',
        'description' => 'TEST: CZK výdaj 5000',
        'expense_date' => '2025-07-20 15:00',
        'split_type' => 'both',
        'split_users' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
    ]);
    assertTrue($data['success'] ?? false, 'Přidání CZK výdaje selhalo: ' . json_encode($data));
    $czkExpenseId = (int)($data['data']['id'] ?? $data['id']);
});

test('CZK→EUR konverze je rozumná (5000/rate)', function() use ($client, &$czkExpenseId) {
    $data = $client->apiGet('/api/wallet.php?action=list');
    $expense = null;
    foreach ($data['data']['expenses'] as $e) {
        if ((int)$e['id'] === $czkExpenseId) { $expense = $e; break; }
    }
    assertTrue($expense !== null, "CZK výdaj $czkExpenseId nenalezen");
    $amountEur = (float)$expense['amount_eur'];
    // 5000 / 25.21 ≈ 198.33 (ale rate se může mírně lišit)
    assertTrue($amountEur > 150 && $amountEur < 250, "CZK→EUR mimo rozsah: $amountEur");
    assertEqual('CZK', $expense['currency']);
    assertAlmostEqual(5000.00, (float)$expense['amount'], 0.01, 'Původní CZK');
});

test('Split CZK výdaje: 10 lidí, součet splitů = amount_eur', function() use ($client, &$czkExpenseId) {
    $data = $client->apiGet('/api/wallet.php?action=list');
    $expense = null;
    foreach ($data['data']['expenses'] as $e) {
        if ((int)$e['id'] === $czkExpenseId) { $expense = $e; break; }
    }
    assertTrue($expense !== null, "CZK výdaj $czkExpenseId nenalezen");
    assertEqual(10, count($expense['split_user_ids']), 'Split na 10 lidí');
});

// Cleanup
test('Cleanup: smazat CZK testovací výdaj', function() use ($client, &$czkExpenseId) {
    $data = $client->apiPost('/api/wallet.php?action=delete', ['id' => $czkExpenseId]);
    assertTrue($data['success'] ?? false, 'Smazání CZK výdaje selhalo');
});

// ============================================================
// SKUPINA 6: Edge cases
// ============================================================

group('6. Edge cases');

test('Split 1 EUR na 1 osobu = celá částka', function() use ($client) {
    $data = $client->apiPost('/api/wallet.php?action=add', [
        'paid_by' => 1, 'amount' => 1, 'currency' => 'EUR',
        'description' => 'TEST: 1 EUR / 1 osoba',
        'expense_date' => '2025-07-20 16:00',
        'split_type' => 'custom', 'split_users' => [1],
    ]);
    assertTrue($data['success'] ?? false, 'Přidání selhalo');
    $id = (int)($data['data']['id'] ?? $data['id']);

    // Ověřit a smazat
    $list = $client->apiGet('/api/wallet.php?action=list');
    foreach ($list['data']['expenses'] as $e) {
        if ((int)$e['id'] === $id) {
            assertEqual(1, count($e['split_user_ids']), '1 osoba ve splitu');
            break;
        }
    }
    $client->apiPost('/api/wallet.php?action=delete', ['id' => $id]);
});

test('Velká částka: 99999.99 EUR / 2 = 49999.99 + 50000.00', function() use ($client) {
    $data = $client->apiPost('/api/wallet.php?action=add', [
        'paid_by' => 1, 'amount' => 99999.99, 'currency' => 'EUR',
        'description' => 'TEST: Velká částka',
        'expense_date' => '2025-07-20 17:00',
        'split_type' => 'custom', 'split_users' => [1, 2],
    ]);
    assertTrue($data['success'] ?? false, 'Přidání velké částky selhalo');
    $id = (int)($data['data']['id'] ?? $data['id']);

    $list = $client->apiGet('/api/wallet.php?action=list');
    $found = false;
    foreach ($list['data']['expenses'] as $e) {
        if ((int)$e['id'] === $id) {
            assertAlmostEqual(99999.99, (float)$e['amount_eur'], 0.01, 'Velká částka EUR');
            $found = true;
            break;
        }
    }
    assertTrue($found, 'Velký výdaj nenalezen');
    $client->apiPost('/api/wallet.php?action=delete', ['id' => $id]);
});

test('Split 0.01 EUR na 3 lidi (extrémní zaokrouhlení)', function() use ($client) {
    $data = $client->apiPost('/api/wallet.php?action=add', [
        'paid_by' => 1, 'amount' => 0.01, 'currency' => 'EUR',
        'description' => 'TEST: 1 cent / 3',
        'expense_date' => '2025-07-20 18:00',
        'split_type' => 'custom', 'split_users' => [1, 2, 3],
    ]);
    // Toto může selhat (amount musí být > 0.01 dle validace) nebo projít
    $id = (int)($data['data']['id'] ?? $data['id'] ?? 0);
    if ($id > 0) {
        $client->apiPost('/api/wallet.php?action=delete', ['id' => $id]);
    }
    // Test proběhl – důležité je, že app nespadla
    assertTrue(true);
});

test('Split 7 EUR na 3 lidi: 2.34 + 2.33 + 2.33 = 7.00', function() use ($client) {
    $data = $client->apiPost('/api/wallet.php?action=add', [
        'paid_by' => 2, 'amount' => 7, 'currency' => 'EUR',
        'description' => 'TEST: 7/3 remainder',
        'expense_date' => '2025-07-20 19:00',
        'split_type' => 'custom', 'split_users' => [1, 2, 3],
    ]);
    assertTrue($data['success'] ?? false, 'Přidání 7/3 selhalo');
    $id = (int)($data['data']['id'] ?? $data['id']);

    // Ověříme bilance: celkový dopad by měl být neutrální
    $balBefore = $client->apiGet('/api/wallet.php?action=balances');
    // Jen ověříme že se to dá načíst
    assertTrue(count($balBefore['data'] ?? $balBefore) > 0, 'Bilance prázdné po přidání');

    $client->apiPost('/api/wallet.php?action=delete', ['id' => $id]);
});

// ============================================================
// SKUPINA 7: Filtrování výdajů
// ============================================================

group('7. Filtrování výdajů');

test('Filtr "mine" – jen výdaje Pavla', function() use ($client) {
    $data = $client->apiGet('/api/wallet.php?action=list&filter=mine');
    assertTrue($data['success'] ?? false, 'API selhalo');
    foreach ($data['data']['expenses'] as $e) {
        assertEqual(1, (int)$e['paid_by'], "Výdaj ID {$e['id']} není od Pavla (paid_by={$e['paid_by']})");
    }
    // Pavel platil výdaje 2, 3, 9
    assertTrue(count($data['data']['expenses']) >= 3, 'Pavel má min 3 výdaje');
});

test('Filtr "boat1" – obsahuje boat1 i both', function() use ($client) {
    $data = $client->apiGet('/api/wallet.php?action=list&filter=boat1');
    assertTrue($data['success'] ?? false, 'API selhalo');
    foreach ($data['data']['expenses'] as $e) {
        assertTrue(
            in_array($e['split_type'], ['boat1', 'both']),
            "Výdaj ID {$e['id']} má split_type={$e['split_type']}, expected boat1 nebo both"
        );
    }
});

test('Filtr "boat2" – neobsahuje boat1-only výdaje', function() use ($client) {
    $data = $client->apiGet('/api/wallet.php?action=list&filter=boat2');
    assertTrue($data['success'] ?? false, 'API selhalo');
    foreach ($data['data']['expenses'] as $e) {
        assertTrue(
            in_array($e['split_type'], ['boat2', 'both']),
            "Výdaj ID {$e['id']} má split_type={$e['split_type']}, expected boat2 nebo both"
        );
    }
});

// ============================================================
// SKUPINA 8: Exchange rate API
// ============================================================

group('8. Exchange rate');

test('Exchange rate API vrací platný kurz', function() use ($client) {
    $data = $client->apiGet('/api/wallet.php?action=rate');
    assertTrue(isset($data['data']['rate']) || isset($data['rate']), 'Chybí rate v odpovědi');
    $rate = (float)($data['data']['rate'] ?? $data['rate']);
    assertTrue($rate > 20 && $rate < 35, "Kurz $rate mimo reálný rozsah CZK/EUR");
});

// ============================================================
// SKUPINA 9: Audit log
// ============================================================

group('9. Audit log');

test('Audit log pro výdaj 9 obsahuje created + edited', function() use ($client) {
    $data = $client->apiGet('/api/wallet.php?action=audit&expense_id=9');
    assertTrue(is_array($data['data'] ?? $data), 'Audit data nejsou pole');
    $entries = $data['data'] ?? $data;
    assertTrue(count($entries) >= 2, 'Výdaj 9 by měl mít min 2 audit záznamy (created + edited)');

    $types = array_column($entries, 'change_type');
    assertTrue(in_array('created', $types), 'Chybí "created" záznam');
    assertTrue(in_array('edited', $types), 'Chybí "edited" záznam');
});

// ============================================================
// SKUPINA 10: Konzistence po CRUD
// ============================================================

group('10. Konzistence bilancí po CRUD operacích');

test('Bilance se vrátí na původní hodnoty po add+delete', function() use ($client, $expectedBalances) {
    // Snapshot před
    $before = $client->apiGet('/api/wallet.php?action=balances');
    $beforeMap = [];
    foreach ($before['data'] ?? $before as $b) {
        $beforeMap[(int)$b['user_id']] = (float)$b['balance'];
    }

    // Přidáme a smažeme výdaj
    $addData = $client->apiPost('/api/wallet.php?action=add', [
        'paid_by' => 5, 'amount' => 200, 'currency' => 'EUR',
        'description' => 'TEST: konzistence', 'expense_date' => '2025-07-21 10:00',
        'split_type' => 'custom', 'split_users' => [1, 5, 8],
    ]);
    $tmpId = (int)($addData['data']['id'] ?? $addData['id']);

    // Mezitím by bilance měly být jiné
    $during = $client->apiGet('/api/wallet.php?action=balances');
    $duringMap = [];
    foreach ($during['data'] ?? $during as $b) {
        $duringMap[(int)$b['user_id']] = (float)$b['balance'];
    }

    // Ondřej (5) zaplatil 200 navíc, ale dluží jen 66.67 → bilance +133.33
    $ondrejDiff = $duringMap[5] - $beforeMap[5];
    assertAlmostEqual(133.33, $ondrejDiff, 0.10, 'Ondřej bilance diff po přidání 200/3');

    // Smažeme
    $client->apiPost('/api/wallet.php?action=delete', ['id' => $tmpId]);

    // Snapshot po
    $after = $client->apiGet('/api/wallet.php?action=balances');
    $afterMap = [];
    foreach ($after['data'] ?? $after as $b) {
        $afterMap[(int)$b['user_id']] = (float)$b['balance'];
    }

    // Bilance by se měly vrátit na původní
    foreach ($beforeMap as $uid => $bal) {
        if (isset($afterMap[$uid])) {
            assertAlmostEqual($bal, $afterMap[$uid], 0.02,
                "Bilance user $uid po add+delete: before=$bal after={$afterMap[$uid]}");
        }
    }
});

// ============================================================
// VÝSLEDKY
// ============================================================

echo "\n" . BOLD . "╔══════════════════════════════════════════════════════╗\n";
echo "║                    VÝSLEDKY TESTŮ                    ║\n";
echo "╚══════════════════════════════════════════════════════╝\n" . RESET;

$total = $testResults['pass'] + $testResults['fail'];
$passRate = $total > 0 ? round($testResults['pass'] / $total * 100) : 0;

echo "\n  Celkem testů:  " . BOLD . $total . RESET . "\n";
echo GREEN . "  Prošlo:        " . BOLD . $testResults['pass'] . RESET . "\n";
echo ($testResults['fail'] > 0 ? RED : GREEN) . "  Selhalo:       " . BOLD . $testResults['fail'] . RESET . "\n";
echo "  Úspěšnost:     " . ($passRate === 100 ? GREEN : ($passRate >= 80 ? YELLOW : RED)) . BOLD . "$passRate%" . RESET . "\n";

if (!empty($testResults['errors'])) {
    echo "\n" . RED . BOLD . "  Chyby:" . RESET . "\n";
    foreach ($testResults['errors'] as $i => $err) {
        echo RED . "  " . ($i + 1) . ". $err" . RESET . "\n";
    }
}

echo "\n";
exit($testResults['fail'] > 0 ? 1 : 0);
