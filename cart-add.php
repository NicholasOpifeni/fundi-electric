<?php
/**
 * Adds a product to the logged-in customer's cart.
 *
 * Posted from product cards and the product page (pid, quantity). Adding a
 * product that is already in the cart raises that line's quantity rather than
 * adding a second line, never past what is in stock or MAX_CART_QUANTITY.
 * Then it returns to the page the customer came from.
 */
require_once __DIR__ . '/includes/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

$pid      = (int) post_text('pid');
$quantity = max(1, (int) post_text('quantity'));
$back     = safe_back_url('product.php?id=' . $pid);

if (!current_user_id()) {
    flash('Log in to add items to your cart.', 'info');
    redirect('login.php?next=' . urlencode('product.php?id=' . $pid));
}

$product = db_one(
    $conn,
    'SELECT product_id, product_title, product_price, product_left FROM products WHERE product_id = ?',
    'i',
    array($pid)
);
if (!$product) {
    flash('That product is no longer available.', 'error');
    redirect('index.php');
}
$title = trim($product['product_title']);
$limit = cart_line_limit($product['product_left']);

if ($limit === 0) {
    flash('Sorry, ' . $title . ' is sold out.', 'error');
    redirect($back);
}

$line = db_one(
    $conn,
    "SELECT sid, quantity FROM carts WHERE uid = ? AND pid = ? AND status = 'active' LIMIT 1",
    'ii',
    array(current_user_id(), $pid)
);
$inCart      = $line ? (int) $line['quantity'] : 0;
$wanted      = $inCart + $quantity;
$newQuantity = min($limit, $wanted);

if ($line) {
    db_exec($conn, 'UPDATE carts SET quantity = ? WHERE sid = ?', 'ii', array($newQuantity, (int) $line['sid']));
} else {
    db_exec(
        $conn,
        'INSERT INTO carts (pid, uid, product, price, quantity) VALUES (?, ?, ?, ?, ?)',
        'iisii',
        array($pid, current_user_id(), $title, (int) $product['product_price'], $newQuantity)
    );
}

if ($newQuantity < $wanted) {
    flash(cart_limit_message($title, $limit), 'info');
} else {
    flash($title . ' was added to your cart.');
}
redirect($back);
