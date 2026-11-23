<?php
/**
 * Stripe sends customers here after paying (?session_id=cs_...).
 *
 * The session ID in the URL is only used to look the payment up: the
 * session itself is fetched from Stripe, and the order is recorded only when
 * Stripe confirms the full amount was paid (see includes/orders.php).
 * Reloading the page, or arriving after the webhook has already recorded the
 * payment, just shows the confirmation again.
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/stripe.php';
require_once __DIR__ . '/includes/orders.php';
require_login();

$sessionId = isset($_GET['session_id']) && is_string($_GET['session_id']) ? $_GET['session_id'] : '';
$findPayment = function () use ($conn, $sessionId) {
    return db_one($conn, 'SELECT * FROM payments WHERE stripe_session = ? AND uid = ?', 'si', array($sessionId, current_user_id()));
};

$payment = $sessionId !== '' ? $findPayment() : null;
if (!$payment) {
    flash('We couldn’t find that payment on your account.', 'error');
    redirect('profile.php#orders');
}

if ($payment['status'] !== 'paid') {
    list($session, $error) = stripe_request('GET', 'checkout/sessions/' . rawurlencode($sessionId));
    if ($error !== '') {
        error_log('Could not check Stripe session for payment #' . $payment['id'] . ': ' . $error);
    } elseif (stripe_session_paid_for($session, $payment)) {
        record_payment($conn, $payment);
        $payment = $findPayment();
    } elseif (isset($session['payment_status']) && $session['payment_status'] === 'paid') {
        // Paid, but not what this payment row expected: leave it for the shop to check.
        error_log('Stripe session ' . $sessionId . ' for payment #' . $payment['id'] . ' was paid but did not match it (amount ' . (isset($session['amount_total']) ? $session['amount_total'] : '?') . ').');
    }
}

$paid  = $payment['status'] === 'paid';
$items = json_decode($payment['items'], true);

if ($paid && $payment['type'] === 'repair') {
    unset($_SESSION['repair_quote']);
}

// Serials issued with this order, grouped by product.
$serials = array();
if ($paid && $payment['type'] !== 'repair') {
    foreach (db_all($conn, 'SELECT pid, uuid FROM servicestatus WHERE payment_id = ? ORDER BY sid', 'i', array((int) $payment['id'])) as $row) {
        $serials[(int) $row['pid']][] = $row['uuid'];
    }
}

$pageTitle = $paid ? 'Thank you' : 'Confirming your payment';
include __DIR__ . '/includes/headerNav.php';
?>

<section class="confirm">
  <?php if (!$paid) { ?>
    <span class="confirm__icon confirm__icon--waiting"><?php echo icon('clock'); ?></span>
    <p class="eyebrow">Payment</p>
    <h1 class="display">Confirming <em>your payment</em></h1>
    <p class="muted">Stripe hasn’t confirmed this payment to us yet. If your card was charged, your <?php echo $payment['type'] === 'repair' ? 'repair booking' : 'order'; ?> will appear in your account as soon as it does, usually within a minute.</p>
    <div class="confirm__actions">
      <a class="btn btn--lg" href="checkout-success.php?session_id=<?php echo e(urlencode($sessionId)); ?>">Check again <?php echo icon('arrow'); ?></a>
      <a class="btn btn--lg btn--ghost" href="contact.php">Contact us</a>
    </div>
  <?php } elseif ($payment['type'] === 'repair') {
      $quote = $items['repair']; ?>
    <span class="confirm__icon"><?php echo icon('tool'); ?></span>
    <p class="eyebrow">Repair booked</p>
    <h1 class="display">Thank <em>you!</em></h1>
    <p class="muted">We’ve received your advance of <?php echo e(base_money($payment['amount'])); ?>. Bring the device to the shop and we’ll take it from there.</p>

    <div class="panel receipt">
      <h2 class="panel__title">Your <em>repair</em></h2>
      <div class="summary-row"><span>Device</span><span><?php echo e($quote['product']); ?></span></div>
      <div class="summary-row"><span>Problem</span><span><?php echo e(ucfirst($quote['damage'])); ?></span></div>
      <?php if ($quote['serial'] !== '') { ?><div class="summary-row"><span>Serial used</span><span class="mono"><?php echo e($quote['serial']); ?></span></div><?php } ?>
      <div class="summary-row summary-row--total"><span>Advance paid</span><span><?php echo e(base_money($payment['amount'])); ?></span></div>
    </div>

    <div class="confirm__actions">
      <a class="btn btn--lg" href="profile.php#repairs">View my repairs <?php echo icon('arrow'); ?></a>
      <a class="btn btn--lg btn--ghost" href="index.php#shop">Back to the shop</a>
    </div>
  <?php } else { ?>
    <span class="confirm__icon"><?php echo icon('check'); ?></span>
    <p class="eyebrow">Order confirmed</p>
    <h1 class="display">Thank <em>you!</em></h1>
    <p class="muted">Your order is being prepared for delivery to <?php echo e($customer['customer_address']); ?>. Each item has its own repair serial, also kept in your order history.</p>

    <div class="panel receipt">
      <h2 class="panel__title">Your <em>order</em></h2>
      <?php foreach ($items['lines'] as $line) { ?>
        <div class="receipt__line">
          <div class="summary-row">
            <span><?php echo e($line['title']); ?> × <?php echo (int) $line['quantity']; ?></span>
            <span><?php echo e(base_money($line['price'] * $line['quantity'])); ?></span>
          </div>
          <?php if (!empty($serials[(int) $line['pid']])) { ?>
            <p class="receipt__serials">
              Repair serial<?php echo count($serials[(int) $line['pid']]) > 1 ? 's' : ''; ?>
              (<?php echo e(repair_perk_label($line['price'])); ?>):
              <?php foreach ($serials[(int) $line['pid']] as $serial) { ?><span class="mono serial"><?php echo e($serial); ?></span> <?php } ?>
            </p>
          <?php } ?>
        </div>
      <?php } ?>
      <div class="summary-row"><span>Delivery</span><span><?php echo e(base_money($items['delivery'])); ?></span></div>
      <div class="summary-row summary-row--total"><span>Total paid</span><span><?php echo e(base_money($payment['amount'])); ?></span></div>
    </div>

    <div class="confirm__actions">
      <a class="btn btn--lg" href="profile.php#orders">View my orders <?php echo icon('arrow'); ?></a>
      <a class="btn btn--lg btn--ghost" href="index.php#shop">Keep shopping</a>
    </div>
  <?php } ?>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
