<?php
/**
 * API: Kurz CZK/EUR
 */

require_once __DIR__ . '/../functions.php';
requireLogin();

$rate = getExchangeRate();
$updated = getSetting('exchange_rate_updated', '');

jsonResponse(true, [
    'rate' => $rate,
    'updated' => $updated,
]);
