<?php
/**
 * Log out. Posted from the account menu; init.php has already checked the
 * CSRF token, so another site can't sign customers out.
 *
 * Everything in the session is cleared and the session gets a new ID, so the
 * old ID is worthless even if it ever leaked. The fresh session then carries
 * the goodbye message to the home page.
 */
require_once __DIR__ . '/includes/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

$_SESSION = array();
session_regenerate_id(true);

flash('You have been logged out. See you soon!');
redirect('index.php');
