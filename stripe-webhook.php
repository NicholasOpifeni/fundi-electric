<?php
/**
 * Stripe webhook: Stripe calls this when a Checkout Session has been paid,
 * so the order is recorded even if the customer closes the tab before coming
 * back to checkout-success.php. Whichever of the two arrives first records
 * it; record_payment() never records a payment twice.
 *
 * Set up under Stripe Dashboard → Developers → Webhooks: send
 * checkout.session.completed and checkout.session.async_payment_succeeded to
 * https://<your shop>/stripe-webhook.php, and put the signing secret in
 * includes/secrets.php. On MAMP, the Stripe CLI forwards events to localhost:
 *   stripe listen --forward-to localhost:8888/stripe-webhook.php
 *
 * This page doesn't load init.php: Stripe has no session or CSRF token, and
 * proves the request is genuine with its signature instead.
 */
require_once __DIR__ . '/includes/shop-config.php';
require_once __DIR__ . '/includes/helpers.php';
date_default_timezone_set(SHOP_TIMEZONE);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/stripe.php';
require_once __DIR__ . '/includes/orders.php';

/** Answer Stripe and stop. Any 2xx tells Stripe not to send the event again. */
function webhook_reply($status, $message)
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    exit($message);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    webhook_reply(405, 'POST only.');
}
if (!stripe_webhook_configured()) {
    error_log('Stripe webhook received but STRIPE_WEBHOOK_SECRET is not set in includes/secrets.php.');
    webhook_reply(503, 'Webhook signing secret not configured.');
}

$payload = (string) file_get_contents('php://input');
$header  = isset($_SERVER['HTTP_STRIPE_SIGNATURE']) ? $_SERVER['HTTP_STRIPE_SIGNATURE'] : '';
$event   = stripe_webhook_event($payload, $header, STRIPE_WEBHOOK_SECRET);
if (!$event) {
    webhook_reply(400, 'Invalid or expired signature.');
}

if (!in_array($event['type'], array('checkout.session.completed', 'checkout.session.async_payment_succeeded'), true)) {
    webhook_reply(200, 'Ignored ' . $event['type'] . '.');
}

$session = $event['data']['object'];
$payment = isset($session['id']) ? db_one($conn, 'SELECT * FROM payments WHERE stripe_session = ?', 's', array((string) $session['id'])) : null;
if (!$payment) {
    webhook_reply(200, 'Not a payment started by this shop.');
}

if (!stripe_session_paid_for($session, $payment)) {
    if (isset($session['payment_status']) && $session['payment_status'] === 'paid') {
        error_log('Webhook: Stripe session ' . $session['id'] . ' was paid but did not match payment #' . $payment['id'] . '.');
        webhook_reply(200, 'Paid amount does not match; logged for the shop.');
    }
    // Slower payment methods complete first and are paid later; that sends
    // checkout.session.async_payment_succeeded.
    webhook_reply(200, 'Not paid yet.');
}

webhook_reply(200, record_payment($conn, $payment) ? 'Recorded payment #' . $payment['id'] . '.' : 'Already recorded.');
