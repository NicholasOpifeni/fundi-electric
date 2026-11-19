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

/** An integer from the query string, or $default when it is missing or not a number. */
function query_int($key, $default = 0)
{
    return isset($_GET[$key]) && is_string($_GET[$key]) && is_numeric($_GET[$key]) ? (int) $_GET[$key] : $default;
}

/**
 * An icon from the sprite in headerNav.php, e.g. icon('bag'). Icons are
 * decorative, so they are hidden from screen readers; buttons that only show
 * an icon carry their own aria-label.
 */
function icon($name)
{
    return '<svg class="icon" aria-hidden="true"><use href="#i-' . e($name) . '"/></svg>';
}

/**
 * The store name as brand markup: the first word plain and the rest in the
 * serif italic, so "Fundi Electric" becomes Fundi<em>Electric</em>.
 */
function brand_markup($name)
{
    $parts = preg_split('/[\s-]+/', trim((string) $name), 2);
    return e($parts[0]) . (isset($parts[1]) ? '<em>' . e($parts[1]) . '</em>' : '');
}

/** First letter of a name in capitals, for avatars. Safe for accented names. */
function name_initial($name)
{
    $name = trim((string) $name);
    if (function_exists('mb_substr')) {
        return mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8');
    }
    return strtoupper(substr($name, 0, 1));
}

/**
 * Path to a CSS or JS file with its modification time appended, so browsers
 * fetch the new version as soon as the file changes instead of a cached copy.
 */
function asset_version($path)
{
    $file = __DIR__ . '/../' . $path;
    return $path . (is_file($file) ? '?v=' . filemtime($file) : '');
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

/** Number of items in the logged-in customer's cart, for the header badge. */
function cart_count($conn)
{
    if (!current_user_id()) {
        return 0;
    }
    $row = db_one(
        $conn,
        "SELECT COALESCE(SUM(quantity), 0) AS total FROM carts WHERE uid = ? AND status = 'active'",
        'i',
        array(current_user_id())
    );
    return $row ? (int) $row['total'] : 0;
}

/* Products ---------------------------------------------------------------- */

/** An amount in BASE_CURRENCY for display, e.g. "UGX 4,200,000". */
function base_money($amount)
{
    return BASE_CURRENCY . ' ' . number_format((int) round((float) $amount));
}

/** Collapse whitespace and cut text to $length characters, ending with "…" if cut. */
function short_text($text, $length = 90)
{
    $clean = trim(preg_replace('/\s+/u', ' ', (string) $text));
    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($clean, 0, $length, '…', 'UTF-8');
    }
    return strlen($clean) > $length ? substr($clean, 0, $length - 1) . '…' : $clean;
}

/** A product's category in lower case, e.g. "laptop". The admin data isn't always tidy. */
function product_category($product)
{
    return strtolower(trim((string) $product['product_catag']));
}

/**
 * Web path to a product photo, or '' when the file isn't in PRODUCT_IMAGE_DIR
 * yet, so pages can show a placeholder instead of a broken image.
 */
function product_image($file)
{
    $file = basename((string) $file);
    if ($file === '' || !is_file(__DIR__ . '/../' . PRODUCT_IMAGE_DIR . $file)) {
        return '';
    }
    return PRODUCT_IMAGE_DIR . rawurlencode($file);
}

/**
 * Whether a product photo is a cutout (product on a plain white or transparent
 * background) rather than a full scene. Cutouts are shown whole and blended
 * into the grey card; scenes fill the frame. The answer is kept in the session
 * per file and modification time, so each photo is only inspected once.
 */
function image_is_cutout($file)
{
    $path = __DIR__ . '/../' . PRODUCT_IMAGE_DIR . basename((string) $file);
    if (!is_file($path)) {
        return false;
    }

    $key = basename($path) . ':' . filemtime($path);
    if (isset($_SESSION['img-cutout'][$key])) {
        return $_SESSION['img-cutout'][$key];
    }

    $cutout = true;
    $img    = function_exists('imagecreatefromstring') ? @imagecreatefromstring((string) file_get_contents($path)) : false;
    if ($img && imagesx($img) >= 6 && imagesy($img) >= 6) {
        // Sample the corners and edge midpoints; five of six must be near-white
        // or transparent. No imagedestroy(): the image is freed when $img goes out
        // of scope, and the call is deprecated as of PHP 8.5.
        $w = imagesx($img);
        $h = imagesy($img);
        $points = array(
            array(2, 2), array($w - 3, 2), array(2, $h - 3), array($w - 3, $h - 3),
            array((int) ($w / 2), 2), array(2, (int) ($h / 2)),
        );
        $plain = 0;
        foreach ($points as $point) {
            $rgba = imagecolorsforindex($img, imagecolorat($img, $point[0], $point[1]));
            $isWhite = $rgba['red'] > 225 && $rgba['green'] > 225 && $rgba['blue'] > 225;
            if ($isWhite || $rgba['alpha'] > 100) {
                $plain++;
            }
        }
        $cutout = $plain >= 5;
    }

    $_SESSION['img-cutout'][$key] = $cutout;
    return $cutout;
}

/** CSS class for a product image frame: cutout, photo, or empty (placeholder). */
function media_class($file)
{
    if (product_image($file) === '') {
        return 'media--empty';
    }
    return image_is_cutout($file) ? 'media--cutout' : 'media--photo';
}

/** The small label on the top-left of a product card. */
function product_badge($product, $isNewest = false)
{
    $stock = (int) $product['product_left'];
    if ($stock <= 0) {
        return 'Sold out';
    }
    if ($isNewest) {
        return 'New';
    }
    if ($stock <= LOW_STOCK_THRESHOLD) {
        return 'Only ' . $stock . ' left';
    }
    if ((int) $product['product_price'] > FREE_REPAIR_THRESHOLD) {
        return 'Free repair';
    }
    return ucfirst(product_category($product));
}

/**
 * Numbered page links. $url is a function that takes a page number and
 * returns its link, so each listing keeps its own query string.
 */
function pagination_links($page, $totalPages, $url, $label = 'Pages')
{
    if ($totalPages <= 1) {
        return '';
    }
    $html = '<nav class="pagination" aria-label="' . e($label) . '">';
    for ($i = 1; $i <= $totalPages; $i++) {
        $current = $i === $page ? ' aria-current="page"' : '';
        $html .= '<a href="' . e($url($i)) . '"' . $current . '>' . $i . '</a>';
    }
    return $html . '</nav>';
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
