<?php
/**
 * Bootstraps every storefront page: settings, helpers, session and database.
 *
 * Require it on the first line of a page, before any HTML. Starting a session
 * sends a cookie, and pages redirect after handling a form; PHP can only send
 * those headers until the first byte of the page has been output.
 *
 * Sets for the page:
 *   $conn      the mysqli connection
 *   $site      store name, logo and footer text (settings table)
 *   $customer  the logged-in customer's row, or null for a guest
 */
require_once __DIR__ . '/shop-config.php';
require_once __DIR__ . '/helpers.php';

date_default_timezone_set(SHOP_TIMEZONE);

if (session_status() !== PHP_SESSION_ACTIVE) {
    // Strict mode refuses session IDs the server didn't issue (session fixation),
    // and the cookie is hidden from JavaScript and not sent on cross-site POSTs.
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params(array(
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ));
    session_start();
}

require_once __DIR__ . '/config.php';

$site     = load_site_settings($conn);
$customer = load_customer($conn);
