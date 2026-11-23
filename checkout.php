<?php
/**
 * Starts a card payment with Stripe Checkout.
 *
 * Posted from the cart (type=cart), the Buy now page (type=product with pid
 * and quantity) and a repair quote (type=repair). What is being bought and
 * the amount are always worked out here, from the database or the
 * server-side repair quote, never taken from the form.
 *
 * A pending row in the payments table records what is being paid for, then
 * the customer goes to Stripe's hosted payment page. Nothing is recorded as
 * sold until Stripe confirms the payment (checkout-success.php).
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/stripe.php';
require_once __DIR__ . '/includes/repairs.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('cart.php');
}

/** Go back with a message. Nothing has been charged at this point. */
function checkout_back($url, $message)
{
    flash($message, 'error');
    redirect($url);
}

$uid      = current_user_id();
$type     = post_text('type');
$lines    = array();   // pid, title, quantity, price (UGX each)
$delivery = 0;

if ($type === 'cart') {
    $back = 'cart.php';
    $rows = db_all(
        $conn,
        "SELECT c.quantity, p.product_id, p.product_title, p.product_price, p.product_left
           FROM carts c LEFT JOIN products p ON p.product_id = c.pid
          WHERE c.uid = ? AND c.status = 'active'
          ORDER BY c.sid",
        'i',
        array($uid)
    );
    if (!$rows) {
        checkout_back($back, 'Your cart is empty.');
    }
    foreach ($rows as $row) {
        // A product removed, sold out or low on stock since it was added: the
        // cart page puts that right and explains it.
        if ($row['product_id'] === null || (int) $row['quantity'] > cart_line_limit($row['product_left'])) {
            checkout_back($back, 'Something in your cart has changed. Please check it and try again.');
        }
        $lines[] = array(
            'pid'      => (int) $row['product_id'],
            'title'    => trim($row['product_title']),
            'quantity' => (int) $row['quantity'],
            'price'    => (int) $row['product_price'],
        );
    }
    $delivery = SHIPPING_FEE;
} elseif ($type === 'product') {
    $pid     = (int) post_text('pid');
    $back    = 'payment.php?id=' . $pid;
    $product = db_one($conn, 'SELECT product_id, product_title, product_price, product_left FROM products WHERE product_id = ?', 'i', array($pid));
    if (!$product) {
        checkout_back('index.php', 'That product is no longer available.');
    }
    $title    = trim($product['product_title']);
    $limit    = cart_line_limit($product['product_left']);
    $quantity = max(1, (int) post_text('quantity'));
    if ($limit === 0) {
        checkout_back('product.php?id=' . $pid, 'Sorry, ' . $title . ' is sold out.');
    }
    if ($quantity > $limit) {
        checkout_back($back, 'You can buy up to ' . $limit . ' of ' . $title . ' at a time.');
    }
    $lines[] = array('pid' => $pid, 'title' => $title, 'quantity' => $quantity, 'price' => (int) $product['product_price']);
    $delivery = SHIPPING_FEE;
} elseif ($type === 'repair') {
    $back  = 'repair.php';
    $quote = isset($_SESSION['repair_quote']) ? $_SESSION['repair_quote'] : null;
    if (!$quote || $quote['advance'] <= 0) {
        checkout_back($back, 'Your quote has expired. Please fill in the form again.');
    }
    // The serial may have been used since the quote was given.
    if ($quote['serial'] !== '') {
        $found = find_repair_serial($conn, $quote['serial'], $uid, $quote['category']);
        if (isset($found['error'])) {
            unset($_SESSION['repair_quote']);
            checkout_back($back, $found['error'] . ' Please get a new quote.');
        }
    }
} else {
    redirect('cart.php');
}

if (!stripe_configured()) {
    error_log('Checkout is unavailable: add a Stripe secret key to includes/secrets.php (see includes/secrets.example.php).');
    checkout_back($back, 'Card payments aren’t available right now, so nothing was charged. Please try again later.');
}

/* What Stripe shows on its payment page, and the total in UGX. */
$currency  = strtolower(BASE_CURRENCY);
$lineItems = array();
$total     = $delivery;
foreach ($lines as $line) {
    $lineItems[] = array(
        'quantity'   => $line['quantity'],
        'price_data' => array('currency' => $currency, 'unit_amount' => stripe_amount($line['price']), 'product_data' => array('name' => $line['title'])),
    );
    $total += $line['price'] * $line['quantity'];
}
if ($delivery > 0) {
    $lineItems[] = array(
        'quantity'   => 1,
        'price_data' => array('currency' => $currency, 'unit_amount' => stripe_amount($delivery), 'product_data' => array('name' => 'Delivery')),
    );
}
if ($type === 'repair') {
    $name = 'Repair advance: ' . $quote['product'] . ' (' . $quote['damage'] . ')';
    $lineItems[] = array(
        'quantity'   => 1,
        'price_data' => array('currency' => $currency, 'unit_amount' => stripe_amount($quote['advance']), 'product_data' => array('name' => $name)),
    );
    $total = $quote['advance'];
}

$snapshot = $type === 'repair' ? array('repair' => $quote) : array('lines' => $lines, 'delivery' => $delivery);
db_exec(
    $conn,
    'INSERT INTO payments (uid, type, items, amount) VALUES (?, ?, ?, ?)',
    'issi',
    array($uid, $type, json_encode($snapshot), $total)
);
$paymentId = (int) $conn->insert_id;

// The idempotency key is unique to this payment row, so if the request is
// retried Stripe returns the same Checkout Session instead of a second one.
list($session, $error) = stripe_request('POST', 'checkout/sessions', array(
    'mode'                => 'payment',
    'line_items'          => $lineItems,
    'customer_email'      => $customer['customer_email'],
    'client_reference_id' => (string) $paymentId,
    'metadata'            => array('payment_id' => $paymentId),
    'payment_intent_data' => array(
        'description' => $site['name'] . ' payment #' . $paymentId,
        'metadata'    => array('payment_id' => $paymentId),
    ),
    'success_url'         => site_url('checkout-success.php') . '?session_id={CHECKOUT_SESSION_ID}',
    'cancel_url'          => site_url('checkout-cancel.php?payment=' . $paymentId),
    // Stripe needs at least 30 minutes; prices and stock are re-checked when it's paid.
    'expires_at'          => time() + 31 * 60,
), 'fundi-payment-' . $paymentId);

if ($error !== '' || empty($session['id']) || empty($session['url']) || !filter_var($session['url'], FILTER_VALIDATE_URL)) {
    error_log('Stripe Checkout failed for payment #' . $paymentId . ': ' . ($error !== '' ? $error : 'no session URL in the reply'));
    db_exec($conn, "UPDATE payments SET status = 'cancelled' WHERE id = ?", 'i', array($paymentId));
    checkout_back($back, 'We couldn’t start the card payment, so nothing was charged. Please try again in a moment.');
}

db_exec($conn, 'UPDATE payments SET stripe_session = ? WHERE id = ?', 'si', array($session['id'], $paymentId));
redirect($session['url']);
