<?php
/**
 * API: Export pokladny do PDF (jednoduchý HTML-to-PDF)
 * Pokud TCPDF není dostupný, generuje HTML pro tisk
 */

require_once __DIR__ . '/../functions.php';
requireLogin();

$db = getDB();

// Načíst data
$expenses = $db->query("
    SELECT we.*, u.name AS paid_by_name
    FROM wallet_expenses we
    LEFT JOIN users u ON we.paid_by = u.id
    ORDER BY we.expense_date DESC
")->fetchAll();

$users = getAllUsers();
$balances = [];
foreach ($users as $u) {
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount_eur), 0) FROM wallet_expenses WHERE paid_by = ?");
    $stmt->execute([$u['id']]);
    $paid = (float) $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COALESCE(SUM(amount_eur), 0) FROM wallet_expense_splits WHERE user_id = ?");
    $stmt->execute([$u['id']]);
    $share = (float) $stmt->fetchColumn();

    $balances[] = [
        'name' => $u['name'],
        'boat_name' => $u['boat_name'] ?? '',
        'paid' => round($paid, 2),
        'share' => round($share, 2),
        'balance' => round($paid - $share, 2),
    ];
}

// Vyrovnání
$debts = [];
foreach ($users as $u) {
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount_eur), 0) FROM wallet_expenses WHERE paid_by = ?");
    $stmt->execute([$u['id']]);
    $paid = (float) $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COALESCE(SUM(amount_eur), 0) FROM wallet_expense_splits WHERE user_id = ?");
    $stmt->execute([$u['id']]);
    $share = (float) $stmt->fetchColumn();

    $balance = round($paid - $share, 2);
    if (abs($balance) > 0.01) {
        $debts[] = ['name' => $u['name'], 'balance' => $balance];
    }
}

$debtors = [];
$creditors = [];
foreach ($debts as $d) {
    if ($d['balance'] < 0) $debtors[] = ['name' => $d['name'], 'amount' => abs($d['balance'])];
    else $creditors[] = ['name' => $d['name'], 'amount' => $d['balance']];
}
usort($debtors, fn($a, $b) => $b['amount'] <=> $a['amount']);
usort($creditors, fn($a, $b) => $b['amount'] <=> $a['amount']);

$settlements = [];
$di = 0; $ci = 0;
while ($di < count($debtors) && $ci < count($creditors)) {
    $transfer = min($debtors[$di]['amount'], $creditors[$ci]['amount']);
    $transfer = round($transfer, 2);
    if ($transfer > 0.01) {
        $settlements[] = $debtors[$di]['name'] . ' → ' . $creditors[$ci]['name'] . ': ' . number_format($transfer, 2, ',', ' ') . ' EUR';
    }
    $debtors[$di]['amount'] = round($debtors[$di]['amount'] - $transfer, 2);
    $creditors[$ci]['amount'] = round($creditors[$ci]['amount'] - $transfer, 2);
    if ($debtors[$di]['amount'] < 0.01) $di++;
    if ($creditors[$ci]['amount'] < 0.01) $ci++;
}

$tripName = getSetting('trip_name', 'Plavba');
$totalEur = array_sum(array_column($expenses, 'amount_eur'));

// Generovat tisknutelné HTML (univerzální, funguje všude)
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Pokladna – <?= e($tripName) ?></title>
    <style>
        body { font-family: 'Open Sans', Arial, sans-serif; font-size: 11px; color: #111827; margin: 20px; }
        h1 { font-size: 18px; color: #111827; border-bottom: 2px solid #e5e7eb; padding-bottom: 5px; }
        h2 { font-size: 14px; color: #4338ca; margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        th, td { border: 1px solid #e5e7eb; padding: 5px 8px; text-align: left; }
        th { background: #f9fafb; font-weight: bold; }
        .text-right { text-align: right; }
        .positive { color: #16a34a; }
        .negative { color: #dc2626; }
        .footer { margin-top: 30px; font-size: 10px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 5px; }
        @media print { body { margin: 10mm; } }
    </style>
</head>
<body onload="window.print()">
    <h1>&#9875; Pokladna – <?= e($tripName) ?></h1>
    <p>Vygenerováno: <?= date('j. n. Y H:i') ?> | Celkové výdaje: <strong><?= number_format($totalEur, 2, ',', ' ') ?> EUR</strong></p>

    <h2>Všechny výdaje</h2>
    <table>
        <thead>
            <tr>
                <th>Datum</th>
                <th>Kdo zaplatil</th>
                <th>Částka</th>
                <th>EUR</th>
                <th>Za co</th>
                <th>Kategorie</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($expenses as $exp): ?>
                <tr>
                    <td><?= formatDate($exp['expense_date']) ?></td>
                    <td><?= e($exp['paid_by_name']) ?></td>
                    <td class="text-right"><?= number_format($exp['amount'], 2, ',', ' ') ?> <?= $exp['currency'] ?></td>
                    <td class="text-right"><?= number_format($exp['amount_eur'], 2, ',', ' ') ?> EUR</td>
                    <td><?= e($exp['description']) ?></td>
                    <td><?= e($exp['category']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2>Bilance</h2>
    <table>
        <thead>
            <tr><th>Jméno</th><th>Loď</th><th class="text-right">Zaplatil</th><th class="text-right">Podíl</th><th class="text-right">Bilance</th></tr>
        </thead>
        <tbody>
            <?php foreach ($balances as $b): ?>
                <tr>
                    <td><?= e($b['name']) ?></td>
                    <td><?= e($b['boat_name']) ?></td>
                    <td class="text-right"><?= number_format($b['paid'], 2, ',', ' ') ?> EUR</td>
                    <td class="text-right"><?= number_format($b['share'], 2, ',', ' ') ?> EUR</td>
                    <td class="text-right <?= $b['balance'] >= 0 ? 'positive' : 'negative' ?>">
                        <?= ($b['balance'] >= 0 ? '+' : '') . number_format($b['balance'], 2, ',', ' ') ?> EUR
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2>Vyrovnání</h2>
    <?php if (empty($settlements)): ?>
        <p>Všechny účty jsou vyrovnané.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($settlements as $s): ?>
                <li><strong><?= e($s) ?></strong></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <div class="footer">Sailing App – <?= e($tripName) ?> – Export <?= date('j. n. Y') ?></div>
</body>
</html>
