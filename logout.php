<?php
/**
 * Odhlášení – zničení session a přesměrování na login
 */

require_once __DIR__ . '/config.php';

session_unset();
session_destroy();

// Nová session pro flash zprávu
session_start();
$_SESSION['flash']['success'] = 'Byli jste úspěšně odhlášeni.';

header('Location: /index.php');
exit;
