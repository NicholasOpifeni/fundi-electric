<?php
/**
 * Saves the currency picked in the footer switcher (?currency=KES) in a
 * cookie for 30 days, then returns to the page the visitor was on.
 * "auto" removes the cookie so the detected currency is used again.
 *
 * This only changes how prices are displayed; payments are always in UGX.
 */
require_once __DIR__ . '/includes/init.php';

$code = isset($_GET['currency']) && is_string($_GET['currency']) ? strtoupper(trim($_GET['currency'])) : '';
$cookie = array('path' => '/', 'httponly' => true, 'samesite' => 'Lax');

if ($code === 'AUTO') {
    setcookie('currency', '', array('expires' => time() - 3600) + $cookie);
} elseif (currency_supported($code)) {
    setcookie('currency', $code, array('expires' => time() + 30 * 24 * 3600) + $cookie);
}

redirect(safe_back_url('index.php'));
