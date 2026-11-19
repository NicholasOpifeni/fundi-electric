<?php
/**
 * Shared helpers for the storefront.
 *
 * Output escaping, redirects, prepared-statement shortcuts, the logged-in
 * customer and one-time flash messages. Loaded by includes/init.php.
 */

/* Output ------------------------------------------------------------------ */

/** Escape a value for HTML. Use it on every value printed into a page. */
function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** A trimmed string from $_POST, or '' when the field is missing or not text. */
function post_text($key)
{
    return isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : '';
}

/* Redirects --------------------------------------------------------------- */

/** Send the browser to $url and stop. Only works before any output. */
function redirect($url)
{
    header('Location: ' . $url);
    exit;
}

/**
 * Return $path if it is a page in this folder, such as "cart.php" or
 * "product.php?id=3", otherwise $fallback. Used for ?next= links so a crafted
 * URL can't send a customer to another site after they log in.
 */
function safe_local_path($path, $fallback = 'index.php')
{
    $path = is_string($path) ? $path : '';
    return preg_match('/^[a-z0-9_-]+\.php(\?[a-z0-9_=&%.+-]*)?(#[a-z0-9_-]*)?$/i', $path) ? $path : $fallback;
}

/** The page the visitor came from, if it was on this site; otherwise $fallback. */
function safe_back_url($fallback = 'index.php')
{
    $referer = isset($_SERVER['HTTP_REFERER']) ? (string) $_SERVER['HTTP_REFERER'] : '';
    $parts   = parse_url($referer);
    if (!$parts || !isset($parts['scheme'], $parts['host'])
        || !in_array(strtolower($parts['scheme']), array('http', 'https'), true)) {
        return $fallback;
    }

    $refererHost = strtolower($parts['host']) . (isset($parts['port']) ? ':' . $parts['port'] : '');
    $ownHost     = isset($_SERVER['HTTP_HOST']) ? strtolower((string) $_SERVER['HTTP_HOST']) : '';
    return $refererHost === $ownHost ? $referer : $fallback;
}

/* Database ---------------------------------------------------------------- */

/**
 * Prepare and run a query with bound parameters, for example:
 *   db_run($conn, 'SELECT * FROM products WHERE product_id = ?', 'i', array($id));
 * $types has one letter per parameter: s (string), i (integer) or d (decimal).
 * Every query goes through here, so user input is never pasted into SQL.
 */
function db_run($conn, $sql, $types = '', array $params = array())
{
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        db_fail($conn->error, $sql);
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    if (!$stmt->execute()) {
        db_fail($stmt->error, $sql);
    }
    return $stmt;
}

/** Run a SELECT and return every row as an associative array. */
function db_all($conn, $sql, $types = '', array $params = array())
{
    $stmt   = db_run($conn, $sql, $types, $params);
    $result = $stmt->get_result();
    $rows   = $result ? $result->fetch_all(MYSQLI_ASSOC) : array();
    $stmt->close();
    return $rows;
}

/** Run a SELECT and return the first row, or null when nothing matched. */
function db_one($conn, $sql, $types = '', array $params = array())
{
    $rows = db_all($conn, $sql, $types, $params);
    return $rows ? $rows[0] : null;
}

/** Run an INSERT, UPDATE or DELETE and return how many rows it changed. */
function db_exec($conn, $sql, $types = '', array $params = array())
{
    $stmt     = db_run($conn, $sql, $types, $params);
    $affected = $stmt->affected_rows;
    $stmt->close();
    return $affected;
}

/**
 * Stop on a failed query. The details go to the PHP error log rather than the
 * page, so table names and SQL are never shown to visitors.
 */
function db_fail($error, $sql)
{
    error_log('Database error: ' . $error . ' | Query: ' . $sql);
    http_response_code(500);
    exit('Sorry, something went wrong on our side. Please try again in a moment.');
}

/* Store settings and the logged-in customer ------------------------------- */

/** Store name, logo and footer text from the settings table, with defaults. */
function load_site_settings($conn)
{
    $row  = db_one($conn, 'SELECT website_name, website_logo, website_footer FROM settings LIMIT 1');
    $name = $row ? trim($row['website_name']) : '';
    return array(
        'name'   => $name !== '' ? $name : 'Fundi Electric',
        'logo'   => $row ? $row['website_logo'] : '',
        'footer' => $row ? $row['website_footer'] : '',
    );
}

/** ID of the logged-in customer, or 0 for a guest. */
function current_user_id()
{
    return isset($_SESSION['id']) ? (int) $_SESSION['id'] : 0;
}

/**
 * The logged-in customer's row, or null for a guest. It is read on every
 * request rather than copied into the session, so profile changes show
 * straight away. A session whose account has been deleted is logged out.
 */
function load_customer($conn)
{
    $id = current_user_id();
    if (!$id) {
        return null;
    }

    $row = db_one(
        $conn,
        'SELECT customer_id, customer_fname, customer_email, customer_phone, customer_address, customer_role
           FROM customer WHERE customer_id = ?',
        'i',
        array($id)
    );
    if (!$row) {
        unset($_SESSION['id']);
    }
    return $row;
}

/** Send guests to the login page, and back to this page once they have logged in. */
function require_login()
{
    if (current_user_id()) {
        return;
    }
    $page  = basename(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : 'index.php');
    $query = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? '?' . $_SERVER['QUERY_STRING'] : '';

    flash('Please log in to continue.', 'info');
    redirect('login.php?next=' . urlencode($page . $query));
}

/* Flash messages ---------------------------------------------------------- */

/**
 * Queue a message for the next page the visitor sees, typically after a form
 * redirects. $type is 'success', 'error' or 'info'.
 */
function flash($message, $type = 'success')
{
    $_SESSION['flash'] = array('message' => (string) $message, 'type' => $type);
}

/** Return the queued flash message, if any, and clear it so it shows once. */
function take_flash()
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

/* CSRF protection --------------------------------------------------------- */

/**
 * The session's CSRF token, created on first use. Every POST form carries it
 * and init.php turns away any POST without it, so another website can't make
 * a logged-in customer's browser add to their cart, change their details or
 * start a payment.
 */
function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Hidden input carrying the token. Print it inside every POST form. */
function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Turn away a POST whose token is missing or wrong. The visitor is sent back
 * with a message instead of an error page, because the usual cause is a
 * session that expired while the form was open.
 */
function verify_csrf()
{
    $sent = post_text('csrf_token');
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $sent)) {
        flash('Your session expired, so that form was not sent. Please try again.', 'error');
        redirect(safe_back_url('index.php'));
    }
}
