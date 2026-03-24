<?php
/**
 * API: Pokladna – CRUD výdajů, bilance, vyrovnání, audit log
 */

require_once __DIR__ . '/../functions.php';
requireLogin();

$db = getDB();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    // ============================================================
    // SEZNAM VÝDAJŮ
    // ============================================================
    case 'list':
        $filter = $_GET['filter'] ?? 'all'; // all, mine, boat1, boat2
        $userId = currentUserId();

        $where = '';
        $params = [];

        if ($filter === 'mine') {
            $where = 'WHERE we.paid_by = ?';
            $params[] = $userId;
        } elseif ($filter === 'boat1') {
            $where = 'WHERE we.split_type IN ("boat1", "both")';
        } elseif ($filter === 'boat2') {
            $where = 'WHERE we.split_type IN ("boat2", "both")';
        }

        $sql = "
            SELECT we.*,
                   u.name AS paid_by_name,
                   u.avatar AS paid_by_avatar
            FROM wallet_expenses we
            LEFT JOIN users u ON we.paid_by = u.id
            $where
            ORDER BY we.expense_date DESC, we.id DESC
        ";

        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $expenses = $stmt->fetchAll();

            // Ke každému výdaji přidat ID uživatelů ze splitů
            try {
                $stmtSplits = $db->prepare("SELECT user_id FROM wallet_expense_splits WHERE expense_id = ?");
                foreach ($expenses as &$exp) {
                    $stmtSplits->execute([$exp['id']]);
                    $exp['split_user_ids'] = $stmtSplits->fetchAll(PDO::FETCH_COLUMN);
                }
                unset($exp);
            } catch (PDOException $e) {
                error_log('wallet_expense_splits: ' . $e->getMessage());
                foreach ($expenses as &$exp) { $exp['split_user_ids'] = []; }
                unset($exp);
            }

            // Celkové výdaje
            $totalEur = $db->query("SELECT COALESCE(SUM(amount_eur), 0) FROM wallet_expenses")->fetchColumn();

            jsonResponse(true, [
                'expenses' => $expenses,
                'total_eur' => (float) $totalEur,
            ]);
        } catch (PDOException $e) {
            error_log($e->getMessage());
            jsonResponse(false, null, 'Chyba načítání výdajů.');
        }
        break;

    // ============================================================
    // PŘIDÁNÍ VÝDAJE
    // ============================================================
    case 'add':
        requireCsrf();

        $paidBy = (int) ($_POST['paid_by'] ?? 0);
        $amount = (float) ($_POST['amount'] ?? 0);
        $currency = $_POST['currency'] ?? 'EUR';
        $description = trim($_POST['description'] ?? '');
        $category = 'ostatni';
        $expenseDateRaw = $_POST['expense_date'] ?? '';
        $splitType = $_POST['split_type'] ?? 'both';
        $splitUsers = $_POST['split_users'] ?? [];

        if (!is_array($splitUsers)) {
            $splitUsers = explode(',', $splitUsers);
        }
        $splitUsers = array_map('intval', array_filter($splitUsers));

        // Validace datumu – přijmeme "Y-m-d H:i:s", "Y-m-d H:i", "Y-m-d\TH:i:s", "Y-m-d\TH:i"
        $expenseDate = date('Y-m-d H:i:s');
        if ($expenseDateRaw !== '') {
            $parsedDate = DateTime::createFromFormat('Y-m-d H:i:s', $expenseDateRaw)
                       ?: DateTime::createFromFormat('Y-m-d H:i', $expenseDateRaw)
                       ?: DateTime::createFromFormat('Y-m-d\TH:i:s', $expenseDateRaw)
                       ?: DateTime::createFromFormat('Y-m-d\TH:i', $expenseDateRaw);
            if ($parsedDate === false) {
                jsonResponse(false, null, 'Neplatný formát datumu.');
            }
            $expenseDate = $parsedDate->format('Y-m-d H:i:s');
        }

        // Validace
        if ($paidBy < 1) jsonResponse(false, null, 'Vyberte kdo zaplatil.');
        if ($amount <= 0) jsonResponse(false, null, 'Částka musí být kladná.');
        if ($description === '') jsonResponse(false, null, 'Popište za co se platilo.');
        if (empty($splitUsers)) jsonResponse(false, null, 'Vyberte alespoň jednu osobu.');

        // Přepočet na EUR
        $rate = getExchangeRate();
        if ($currency === 'CZK') {
            $amountEur = round($amount / $rate, 2);
        } else {
            $amountEur = $amount;
        }

        // Rozpad na osoby – přesný na haléře
        $count = count($splitUsers);
        $perPerson = floor($amountEur / $count * 100) / 100;
        $remainder = round($amountEur - ($perPerson * $count), 2);

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("
                INSERT INTO wallet_expenses (paid_by, amount, currency, amount_eur, exchange_rate, description, category, expense_date, split_type, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$paidBy, $amount, $currency, $amountEur, $rate, $description, $category, $expenseDate, $splitType, currentUserId()]);
            $expenseId = $db->lastInsertId();

            $stmtSplit = $db->prepare("INSERT INTO wallet_expense_splits (expense_id, user_id, amount_eur) VALUES (?, ?, ?)");
            foreach ($splitUsers as $i => $uid) {
                $share = $perPerson;
                if ($i === 0) {
                    $share = round($perPerson + $remainder, 2);
                }
                $stmtSplit->execute([$expenseId, $uid, $share]);
            }

            $db->commit();

            // Audit log (mimo transakci – tabulka nemusí existovat)
            try {
                $newValues = [
                    'paid_by' => $paidBy, 'amount' => $amount, 'currency' => $currency,
                    'amount_eur' => $amountEur, 'description' => $description,
                    'expense_date' => $expenseDate, 'split_type' => $splitType, 'split_users' => $splitUsers,
                ];
                $db->exec("CREATE TABLE IF NOT EXISTS wallet_audit_log (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    expense_id INT NOT NULL,
                    changed_by INT DEFAULT NULL,
                    change_type VARCHAR(20) NOT NULL,
                    old_values TEXT DEFAULT NULL,
                    new_values TEXT DEFAULT NULL,
                    changed_at DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                $db->prepare("INSERT INTO wallet_audit_log (expense_id, changed_by, change_type, new_values) VALUES (?, ?, 'created', ?)")
                   ->execute([$expenseId, currentUserId(), json_encode($newValues, JSON_UNESCAPED_UNICODE)]);
            } catch (PDOException $e) {
                error_log('Audit log error: ' . $e->getMessage());
            }

            jsonResponse(true, ['id' => $expenseId]);

        } catch (PDOException $e) {
            $db->rollBack();
            error_log($e->getMessage());
            jsonResponse(false, null, 'Interní chyba serveru.');
        }
        break;

    // ============================================================
    // EDITACE VÝDAJE
    // ============================================================
    case 'edit':
        requireCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        if ($id < 1) jsonResponse(false, null, 'Neplatný výdaj.');

        $old = $db->prepare("SELECT * FROM wallet_expenses WHERE id = ?");
        $old->execute([$id]);
        $oldData = $old->fetch();
        if (!$oldData) jsonResponse(false, null, 'Výdaj nenalezen.');

        $oldSplits = $db->prepare("SELECT user_id FROM wallet_expense_splits WHERE expense_id = ?");
        $oldSplits->execute([$id]);
        $oldSplitUsers = $oldSplits->fetchAll(PDO::FETCH_COLUMN);

        $paidBy = (int) ($_POST['paid_by'] ?? 0);
        $amount = (float) ($_POST['amount'] ?? 0);
        $currency = $_POST['currency'] ?? 'EUR';
        $description = trim($_POST['description'] ?? '');
        $category = 'ostatni';
        $expenseDateRaw = $_POST['expense_date'] ?? '';
        $splitType = $_POST['split_type'] ?? 'both';
        $splitUsers = $_POST['split_users'] ?? [];

        if (!is_array($splitUsers)) {
            $splitUsers = explode(',', $splitUsers);
        }
        $splitUsers = array_map('intval', array_filter($splitUsers));

        if ($paidBy < 1 || $amount <= 0 || $description === '' || empty($splitUsers)) {
            jsonResponse(false, null, 'Neplatné údaje.');
        }

        // Validace datumu
        $expenseDate = $oldData['expense_date'];
        if ($expenseDateRaw !== '') {
            $parsedDate = DateTime::createFromFormat('Y-m-d H:i:s', $expenseDateRaw)
                       ?: DateTime::createFromFormat('Y-m-d H:i', $expenseDateRaw)
                       ?: DateTime::createFromFormat('Y-m-d\TH:i:s', $expenseDateRaw)
                       ?: DateTime::createFromFormat('Y-m-d\TH:i', $expenseDateRaw);
            if ($parsedDate === false) {
                jsonResponse(false, null, 'Neplatný formát datumu.');
            }
            $expenseDate = $parsedDate->format('Y-m-d H:i:s');
        }

        // Při editaci CZK výdaje zachovat původní kurz – jinak by se amount_eur
        // přepočítal aktuálním kurzem a bilance by se nepředvídatelně posunuly.
        if ($currency === 'CZK') {
            $rate = (float) ($oldData['exchange_rate'] ?? 0);
            if ($rate <= 0) $rate = getExchangeRate();
            $amountEur = round($amount / $rate, 2);
        } else {
            $rate = getExchangeRate();
            $amountEur = $amount;
        }

        $count = count($splitUsers);
        $perPerson = floor($amountEur / $count * 100) / 100;
        $remainder = round($amountEur - ($perPerson * $count), 2);

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("
                UPDATE wallet_expenses SET paid_by = ?, amount = ?, currency = ?, amount_eur = ?, exchange_rate = ?,
                description = ?, category = ?, expense_date = ?, split_type = ?
                WHERE id = ?
            ");
            $stmt->execute([$paidBy, $amount, $currency, $amountEur, $rate, $description, $category, $expenseDate, $splitType, $id]);

            $db->prepare("DELETE FROM wallet_expense_splits WHERE expense_id = ?")->execute([$id]);

            $stmtSplit = $db->prepare("INSERT INTO wallet_expense_splits (expense_id, user_id, amount_eur) VALUES (?, ?, ?)");
            foreach ($splitUsers as $i => $uid) {
                $share = ($i === 0) ? round($perPerson + $remainder, 2) : $perPerson;
                $stmtSplit->execute([$id, $uid, $share]);
            }

            $db->commit();

            // Audit log (mimo transakci – tabulka nemusí existovat)
            try {
                $oldValues = [
                    'paid_by' => $oldData['paid_by'], 'amount' => $oldData['amount'],
                    'currency' => $oldData['currency'], 'description' => $oldData['description'],
                    'expense_date' => $oldData['expense_date'], 'split_users' => $oldSplitUsers,
                ];
                $newValues = [
                    'paid_by' => $paidBy, 'amount' => $amount, 'currency' => $currency,
                    'description' => $description, 'expense_date' => $expenseDate, 'split_users' => $splitUsers,
                ];
                $db->prepare("INSERT INTO wallet_audit_log (expense_id, changed_by, change_type, old_values, new_values) VALUES (?, ?, 'edited', ?, ?)")
                   ->execute([$id, currentUserId(), json_encode($oldValues, JSON_UNESCAPED_UNICODE), json_encode($newValues, JSON_UNESCAPED_UNICODE)]);
            } catch (PDOException $e) {
                error_log('Audit log error: ' . $e->getMessage());
            }

            jsonResponse(true);

        } catch (PDOException $e) {
            $db->rollBack();
            error_log($e->getMessage());
            jsonResponse(false, null, 'Interní chyba serveru.');
        }
        break;

    // ============================================================
    // SMAZÁNÍ VÝDAJE
    // ============================================================
    case 'delete':
        requireCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        if ($id < 1) jsonResponse(false, null, 'Neplatný výdaj.');

        $old = $db->prepare("SELECT * FROM wallet_expenses WHERE id = ?");
        $old->execute([$id]);
        $oldData = $old->fetch();
        if (!$oldData) jsonResponse(false, null, 'Výdaj nenalezen.');

        $db->beginTransaction();
        try {
            // Smazat audit logy PŘED smazáním výdaje (FK constraint)
            $db->prepare("DELETE FROM wallet_audit_log WHERE expense_id = ?")->execute([$id]);

            // Smazat splity (CASCADE by to udělal, ale pro jistotu)
            $db->prepare("DELETE FROM wallet_expense_splits WHERE expense_id = ?")->execute([$id]);

            // Smazat výdaj
            $db->prepare("DELETE FROM wallet_expenses WHERE id = ?")->execute([$id]);

            $db->commit();
            jsonResponse(true);

        } catch (PDOException $e) {
            $db->rollBack();
            error_log($e->getMessage());
            jsonResponse(false, null, 'Interní chyba serveru.');
        }
        break;

    // ============================================================
    // BILANCE VŠECH UŽIVATELŮ
    // ============================================================
    case 'balances':
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
                'user_id' => $u['id'],
                'name' => $u['name'],
                'boat_id' => $u['boat_id'],
                'boat_name' => $u['boat_name'] ?? '',
                'paid' => round($paid, 2),
                'share' => round($share, 2),
                'balance' => round($paid - $share, 2),
            ];
        }

        jsonResponse(true, $balances);
        break;

    // ============================================================
    // OPTIMALIZOVANÉ VYROVNÁNÍ
    // ============================================================
    case 'settlements':
        $users = getAllUsers();
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
                $debts[$u['id']] = ['name' => $u['name'], 'balance' => $balance];
            }
        }

        $debtors = [];
        $creditors = [];

        foreach ($debts as $uid => $d) {
            if ($d['balance'] < 0) {
                $debtors[] = ['id' => $uid, 'name' => $d['name'], 'amount' => abs($d['balance'])];
            } else {
                $creditors[] = ['id' => $uid, 'name' => $d['name'], 'amount' => $d['balance']];
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
                    'from_id' => $debtors[$di]['id'],
                    'from_name' => $debtors[$di]['name'],
                    'to_id' => $creditors[$ci]['id'],
                    'to_name' => $creditors[$ci]['name'],
                    'amount' => $transfer,
                ];
            }

            $debtors[$di]['amount'] = round($debtors[$di]['amount'] - $transfer, 2);
            $creditors[$ci]['amount'] = round($creditors[$ci]['amount'] - $transfer, 2);

            if ($debtors[$di]['amount'] < 0.01) $di++;
            if ($creditors[$ci]['amount'] < 0.01) $ci++;
        }

        // Načíst které jsou označené jako vyrovnané
        $settled = [];
        try {
            $rows = $db->query("SELECT from_user_id, to_user_id FROM wallet_settled")->fetchAll();
            foreach ($rows as $r) {
                $settled[$r['from_user_id'] . '-' . $r['to_user_id']] = true;
            }
        } catch (PDOException $e) {
            // Tabulka ještě neexistuje – nevadí
        }

        // Přidat info o vyrovnání + kurz
        $rate = getExchangeRate();
        foreach ($settlements as &$s) {
            $key = $s['from_id'] . '-' . $s['to_id'];
            $s['settled'] = isset($settled[$key]);
            $s['amount_czk'] = round($s['amount'] * $rate, 2);
        }
        unset($s);

        jsonResponse(true, ['settlements' => $settlements, 'rate' => $rate]);
        break;

    // ============================================================
    // OZNAČIT VYROVNÁNÍ JAKO ZAPLACENÉ / NEZAPLACENÉ
    // ============================================================
    case 'settle':
        requireCsrf();

        $fromId = (int) ($_POST['from_id'] ?? 0);
        $toId = (int) ($_POST['to_id'] ?? 0);
        $settle = (int) ($_POST['settle'] ?? 1);

        if ($fromId < 1 || $toId < 1) jsonResponse(false, null, 'Neplatné údaje.');

        // Vytvořit tabulku pokud neexistuje
        $db->exec("CREATE TABLE IF NOT EXISTS wallet_settled (
            id INT AUTO_INCREMENT PRIMARY KEY,
            from_user_id INT NOT NULL,
            to_user_id INT NOT NULL,
            settled_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            settled_by INT DEFAULT NULL,
            UNIQUE KEY uniq_pair (from_user_id, to_user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        if ($settle) {
            $stmt = $db->prepare("INSERT IGNORE INTO wallet_settled (from_user_id, to_user_id, settled_by) VALUES (?, ?, ?)");
            $stmt->execute([$fromId, $toId, currentUserId()]);
        } else {
            $stmt = $db->prepare("DELETE FROM wallet_settled WHERE from_user_id = ? AND to_user_id = ?");
            $stmt->execute([$fromId, $toId]);
        }

        jsonResponse(true);
        break;

    // ============================================================
    // AUDIT LOG PRO VÝDAJ
    // ============================================================
    case 'audit':
        $expenseId = (int) ($_GET['expense_id'] ?? 0);
        if ($expenseId < 1) jsonResponse(false, null, 'Neplatný výdaj.');

        $stmt = $db->prepare("
            SELECT wal.*, u.name AS changed_by_name
            FROM wallet_audit_log wal
            LEFT JOIN users u ON wal.changed_by = u.id
            WHERE wal.expense_id = ?
            ORDER BY wal.changed_at DESC
        ");
        $stmt->execute([$expenseId]);

        jsonResponse(true, $stmt->fetchAll());
        break;

    // ============================================================
    // AKTUÁLNÍ KURZ
    // ============================================================
    case 'rate':
        $rate = getExchangeRate();
        $updated = getSetting('exchange_rate_updated', '');
        jsonResponse(true, ['rate' => $rate, 'updated' => $updated]);
        break;

    default:
        jsonResponse(false, null, 'Neznámá akce.');
}
