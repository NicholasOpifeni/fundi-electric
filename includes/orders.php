<?php
/**
 * Turning a confirmed card payment into orders and repair bookings.
 *
 * Both checkout-success.php (when the customer comes back from Stripe) and
 * stripe-webhook.php (when Stripe reports the payment itself) call
 * record_payment(). Whichever arrives first records it; the other finds the
 * payment already marked paid and changes nothing, so a payment is never
 * recorded twice, even if both arrive at the same moment.
 */
require_once __DIR__ . '/repairs.php';

/**
 * Does this Checkout Session, as reported by Stripe, show that the payment
 * row was paid in full? Every detail must match what checkout.php sent.
 */
function stripe_session_paid_for($session, $payment)
{
    return is_array($session)
        && isset($session['status'], $session['payment_status'], $session['amount_total'], $session['currency'], $session['client_reference_id'])
        && $session['status'] === 'complete'
        && $session['payment_status'] === 'paid'
        && (int) $session['amount_total'] === stripe_amount($payment['amount'])
        && strtolower($session['currency']) === strtolower(BASE_CURRENCY)
        && (string) $session['client_reference_id'] === (string) $payment['id'];
}

/** The repair perk stored with a serial: a free repair above FREE_REPAIR_THRESHOLD, otherwise 50% off. */
function repair_perk_code($price)
{
    return (int) $price > FREE_REPAIR_THRESHOLD ? PERK_FREE_REPAIR : PERK_HALF_PRICE;
}

/** A new repair serial such as FE-7KQM-3XPA. Letters and digits that look alike are left out. */
function new_repair_serial($conn)
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $serial = 'FE-' . substr($code, 0, 4) . '-' . substr($code, 4);
    } while (db_one($conn, 'SELECT sid FROM servicestatus WHERE uuid = ?', 's', array($serial)));
    return $serial;
}

/**
 * Record a paid payment row (call only once Stripe has confirmed it):
 *   orders: one soldproducts row per product, one repair serial per item,
 *           stock reduced, and those products removed from the cart
 *   repairs: the booking saved and its serial used up
 * Everything happens in one transaction that starts by switching the payment
 * to 'paid'. Returns true if this call recorded it, false if it already was.
 */
function record_payment($conn, $payment)
{
    $paymentId = (int) $payment['id'];
    $uid       = (int) $payment['uid'];
    $items     = json_decode($payment['items'], true);

    $conn->begin_transaction();

    // The row lock taken here makes a second, simultaneous call wait, then
    // find the payment already paid.
    $claimed = db_exec(
        $conn,
        "UPDATE payments SET status = 'paid', paid_at = NOW() WHERE id = ? AND status <> 'paid'",
        'i',
        array($paymentId)
    );
    if ($claimed !== 1) {
        $conn->rollback();
        return false;
    }
    if ($payment['status'] === 'cancelled') {
        error_log('Payment #' . $paymentId . ' was marked cancelled but Stripe confirmed it as paid; recording it.');
    }

    if ($payment['type'] === 'repair') {
        $quote = $items['repair'];
        // The customer has paid, so the repair is booked even if the serial was
        // used elsewhere between quote and payment; that is logged for the shop.
        if ($quote['serial'] !== '' && !claim_repair_serial($conn, $quote['serial'], $uid)) {
            error_log('Serial ' . $quote['serial'] . ' was already used when payment #' . $paymentId . ' was confirmed; booked at the paid advance.');
        }
        save_repair_booking($conn, $uid, $quote);
    } else {
        $today = date('j M Y');
        foreach ($items['lines'] as $line) {
            $pid      = (int) $line['pid'];
            $quantity = (int) $line['quantity'];

            db_exec(
                $conn,
                'INSERT INTO soldproducts (uid, pid, quantity, price, date, payment_id) VALUES (?, ?, ?, ?, ?, ?)',
                'iiiisi',
                array($uid, $pid, $quantity, (int) $line['price'], $today, $paymentId)
            );

            // Each item bought gets its own serial, so two phones mean two repairs.
            for ($i = 0; $i < $quantity; $i++) {
                db_exec(
                    $conn,
                    'INSERT INTO servicestatus (uid, discount, pid, uuid, payment_id) VALUES (?, ?, ?, ?, ?)',
                    'isisi',
                    array($uid, repair_perk_code($line['price']), $pid, new_repair_serial($conn), $paymentId)
                );
            }

            $stock = db_one($conn, 'SELECT product_left FROM products WHERE product_id = ? FOR UPDATE', 'i', array($pid));
            if ($stock && (int) $stock['product_left'] < $quantity) {
                error_log('Payment #' . $paymentId . ' paid for ' . $quantity . ' of product #' . $pid . ' but only ' . (int) $stock['product_left'] . ' were in stock.');
            }
            db_exec($conn, 'UPDATE products SET product_left = GREATEST(product_left - ?, 0) WHERE product_id = ?', 'ii', array($quantity, $pid));

            // Only what was paid for leaves the cart; anything added since stays.
            if ($payment['type'] === 'cart') {
                db_exec($conn, "DELETE FROM carts WHERE uid = ? AND pid = ? AND status = 'active'", 'ii', array($uid, $pid));
            }
        }
    }

    $conn->commit();
    return true;
}
