<?php
/**
 * Stripe sends customers here when they leave its payment page without
 * paying (?payment=<payments.id>).
 *
 * The Checkout Session is expired at Stripe so it can't be paid later from
 * another tab, the payment is marked cancelled, and the customer goes back
 * to the page they started from. If Stripe won't expire it because it has
 * already been paid, the payment is left for checkout-success.php to record.
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/stripe.php';
require_login();

$payment = db_one(
    $conn,
    'SELECT id, type, items, status, stripe_session FROM payments WHERE id = ? AND uid = ?',
    'ii',
    array(query_int('payment'), current_user_id())
);
if (!$payment) {
    redirect('cart.php');
}

$items = json_decode($payment['items'], true);
if ($payment['type'] === 'repair') {
    $back = 'repair.php';
} elseif ($payment['type'] === 'product' && isset($items['lines'][0]['pid'])) {
    $back = 'payment.php?id=' . (int) $items['lines'][0]['pid'];
} else {
    $back = 'cart.php';
}

if ($payment['status'] === 'pending' && $payment['stripe_session'] !== null) {
    list($session, $error) = stripe_request('POST', 'checkout/sessions/' . rawurlencode($payment['stripe_session']) . '/expire');
    if ($error === '' && isset($session['status']) && $session['status'] === 'expired') {
        db_exec($conn, "UPDATE payments SET status = 'cancelled' WHERE id = ? AND status = 'pending'", 'i', array((int) $payment['id']));
        flash('Payment cancelled. Nothing was charged.', 'info');
    } else {
        error_log('Could not expire Stripe session for payment #' . $payment['id'] . ': ' . $error);
    }
}

redirect($back);
