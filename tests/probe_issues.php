<?php
// Dočasný probe – maže se po analýze

function loginMember(int $uid = 1): array {
    $s = ['cookie_file' => tempnam(sys_get_temp_dir(), 'probe_')];
    $ch = curl_init('http://host.docker.internal:8080/index.php');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,
        CURLOPT_COOKIEJAR=>$s['cookie_file'],CURLOPT_COOKIEFILE=>$s['cookie_file'],CURLOPT_TIMEOUT=>5]);
    $html = curl_exec($ch); curl_close($ch);
    preg_match('/name="csrf_token"\s+value="([a-f0-9]+)"/', $html, $m);
    $s['csrf_token'] = $m[1] ?? '';
    $ch = curl_init('http://host.docker.internal:8080/index.php');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_FOLLOWLOCATION=>true,
        CURLOPT_POSTFIELDS=>http_build_query(['login_type'=>'member','user_id'=>$uid,'member_password'=>'crew123','csrf_token'=>$s['csrf_token']]),
        CURLOPT_COOKIEJAR=>$s['cookie_file'],CURLOPT_COOKIEFILE=>$s['cookie_file'],CURLOPT_TIMEOUT=>5]);
    curl_exec($ch); curl_close($ch);
    return $s;
}

function apiPost(array &$s, string $path, array $data): array {
    $data['csrf_token'] = $s['csrf_token'];
    $ch = curl_init('http://host.docker.internal:8080' . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>http_build_query($data),CURLOPT_FOLLOWLOCATION=>false,
        CURLOPT_COOKIEJAR=>$s['cookie_file'],CURLOPT_COOKIEFILE=>$s['cookie_file'],
        CURLOPT_HTTPHEADER=>['X-Requested-With: XMLHttpRequest'],CURLOPT_TIMEOUT=>5]);
    $body = curl_exec($ch); curl_close($ch);
    return json_decode($body, true) ?? [];
}

$s = loginMember(1);
$pdo = new PDO('mysql:host=db;dbname=vol5;charset=utf8mb4','vol5user','vol5pass',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);

// === PROBLÉM 1: Duplikatní user_id v split_users ===
echo "\n=== PROBLÉM 1: Duplikatní user_id (split_users='1,1,2') ===\n";
$res = apiPost($s, '/api/wallet.php', ['action'=>'add','paid_by'=>1,'amount'=>30.00,'currency'=>'EUR',
    'description'=>'PROBE duplikat','expense_date'=>'2025-07-20 10:00:00',
    'split_type'=>'both','split_users'=>'1,1,2']);
$id = $res['data']['id'] ?? 0;
if ($id) {
    $splits = $pdo->query("SELECT user_id, amount_eur FROM wallet_expense_splits WHERE expense_id={$id} ORDER BY id")->fetchAll();
    $sum = array_sum(array_column($splits, 'amount_eur'));
    $user1Total = array_sum(array_column(array_filter($splits, fn($sp)=>$sp['user_id']==1), 'amount_eur'));
    echo "Splits v DB: " . json_encode($splits) . "\n";
    echo "SUM = {$sum} EUR (správně by bylo 30.00)\n";
    echo "User 1 dluží celkem: {$user1Total} EUR (správně by bylo 20.00, dostane 2× podíl!)\n";
    echo $sum == 30.00 ? "SUM: OK\n" : "SUM: ŠPATNĚ – {$sum} != 30.00\n";
    echo $user1Total == 20.00 ? "User1 podíl: OK\n" : "User1 podíl: ŠPATNĚ – user 1 dluží {$user1Total} místo 20.00\n";
    apiPost($s, '/api/wallet.php', ['action'=>'delete','id'=>$id]);
} else {
    echo "Výdaj nebyl přijat: " . json_encode($res) . "\n";
}

// === PROBLÉM 2: Nulový amount (0 EUR) ===
echo "\n=== PROBLÉM 2: Nulový amount (0 EUR) ===\n";
$res2 = apiPost($s, '/api/wallet.php', ['action'=>'add','paid_by'=>1,'amount'=>0.00,'currency'=>'EUR',
    'description'=>'PROBE nula','expense_date'=>'2025-07-20 10:00:00',
    'split_type'=>'both','split_users'=>'1,2']);
echo "API vrátí success=" . var_export($res2['success'] ?? null, true) . " error=" . ($res2['error'] ?? 'none') . "\n";

// === PROBLÉM 3: Velmi malá částka (0.005 EUR = pod haléř) ===
echo "\n=== PROBLÉM 3: Částka 0.005 EUR / 2 lidi ===\n";
$res3 = apiPost($s, '/api/wallet.php', ['action'=>'add','paid_by'=>1,'amount'=>0.005,'currency'=>'EUR',
    'description'=>'PROBE pod halerz','expense_date'=>'2025-07-20 10:00:00',
    'split_type'=>'both','split_users'=>'1,2']);
$id3 = $res3['data']['id'] ?? 0;
if ($id3) {
    $sp3 = $pdo->query("SELECT user_id, amount_eur FROM wallet_expense_splits WHERE expense_id={$id3}")->fetchAll();
    $sum3 = array_sum(array_column($sp3, 'amount_eur'));
    echo "Splits: " . json_encode($sp3) . " SUM={$sum3}\n";
    echo "amount_eur v expenses: " . $pdo->query("SELECT amount_eur FROM wallet_expenses WHERE id={$id3}")->fetchColumn() . "\n";
    apiPost($s, '/api/wallet.php', ['action'=>'delete','id'=>$id3]);
} else {
    echo "Nepřijato: " . json_encode($res3) . "\n";
}

// === PROBLÉM 4: Float injection (amount="100 EUR") ===
echo "\n=== PROBLÉM 4: Nevalidní amount string ===\n";
$res4 = apiPost($s, '/api/wallet.php', ['action'=>'add','paid_by'=>1,'amount'=>'100abc','currency'=>'EUR',
    'description'=>'PROBE string amount','expense_date'=>'2025-07-20 10:00:00',
    'split_type'=>'both','split_users'=>'1,2']);
$id4 = $res4['data']['id'] ?? 0;
if ($id4) {
    $am = $pdo->query("SELECT amount, amount_eur FROM wallet_expenses WHERE id={$id4}")->fetch();
    echo "Uloženo: amount={$am['amount']} amount_eur={$am['amount_eur']}\n";
    echo "PHP (float)'100abc' = " . (float)'100abc' . "\n";
    apiPost($s, '/api/wallet.php', ['action'=>'delete','id'=>$id4]);
} else {
    echo "Nepřijato: " . json_encode($res4) . "\n";
}

foreach (glob(sys_get_temp_dir().'/probe_*') as $f) @unlink($f);
echo "\nDone.\n";
