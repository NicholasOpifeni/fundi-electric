<?php
/**
 * The customer's cart: change quantities, remove items, see the order total
 * and go to checkout.
 *
 * Prices come from the products table rather than carts.price (the price
 * when the item was added), so the cart always shows what checkout charges.
 * Quantities never exceed what is in stock (see cart_line_limit()).
 */
require_once __DIR__ . '/includes/init.php';
require_login();

$uid = current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sid    = (int) post_text('sid');
    $action = post_text('action');

    if ($action === 'remove') {
        db_exec($conn, "DELETE FROM carts WHERE sid = ? AND uid = ? AND status = 'active'", 'ii', array($sid, $uid));
        flash('Item removed from your cart.');
    } elseif ($action === 'update') {
        $line = db_one(
            $conn,
            "SELECT p.product_title, p.product_left
               FROM carts c JOIN products p ON p.product_id = c.pid
              WHERE c.sid = ? AND c.uid = ? AND c.status = 'active'",
            'ii',
            array($sid, $uid)
        );
        $limit = $line ? cart_line_limit($line['product_left']) : 0;
        if ($limit > 0) {
            $wanted   = max(1, (int) post_text('quantity'));
            $quantity = min($limit, $wanted);
            db_exec($conn, 'UPDATE carts SET quantity = ? WHERE sid = ? AND uid = ?', 'iii', array($quantity, $sid, $uid));
            if ($quantity < $wanted) {
                flash(cart_limit_message(trim($line['product_title']), $limit), 'info');
            }
        }
    }
    redirect('cart.php');
}

$lines = db_all(
    $conn,
    "SELECT c.sid, c.pid, c.product, c.quantity,
            p.product_title, p.product_price, p.product_img, p.product_catag, p.product_left
       FROM carts c LEFT JOIN products p ON p.product_id = c.pid
      WHERE c.uid = ? AND c.status = 'active'
      ORDER BY c.sid DESC",
    'i',
    array($uid)
);

// Stock may have dropped since something was added. Lower those lines to what
// is left, tell the customer, and reload so the page shows the new amounts.
$lowered = array();
foreach ($lines as $line) {
    $limit = $line['product_price'] !== null ? cart_line_limit($line['product_left']) : 0;
    if ($limit > 0 && (int) $line['quantity'] > $limit) {
        db_exec($conn, 'UPDATE carts SET quantity = ? WHERE sid = ?', 'ii', array($limit, (int) $line['sid']));
        $lowered[] = cart_limit_message(trim($line['product_title']), $limit);
    }
}
if ($lowered) {
    flash(implode(' ', $lowered), 'info');
    redirect('cart.php');
}

// A line whose product was deleted or has sold out can't be bought; it stays
// visible so the customer can remove it, but it blocks checkout until then.
$subtotal    = 0;
$itemCount   = 0;
$blocked     = false;
foreach ($lines as $line) {
    if ($line['product_price'] === null || cart_line_limit($line['product_left']) === 0) {
        $blocked = true;
        continue;
    }
    $subtotal  += (int) $line['product_price'] * (int) $line['quantity'];
    $itemCount += (int) $line['quantity'];
}
$shipping    = $itemCount > 0 ? SHIPPING_FEE : 0;
$total       = $subtotal + $shipping;
$canCheckout = $itemCount > 0 && !$blocked;

$pageTitle = 'Your cart';
include __DIR__ . '/includes/headerNav.php';
?>

<header class="page-head">
  <p class="eyebrow">Checkout</p>
  <h1 class="display">Your <em>cart</em></h1>
  <p class="muted"><?php echo $itemCount > 0 ? $itemCount . ' item' . ($itemCount === 1 ? '' : 's') . ' ready to go.' : 'Nothing here yet.'; ?></p>
</header>

<?php if (!$lines) { ?>
  <div class="empty empty--cta">
    <p>Your cart is empty. Find something you like and it will show up here.</p>
    <a class="btn" href="index.php#shop">Start shopping <?php echo icon('arrow'); ?></a>
  </div>
<?php } else { ?>
<div class="layout-2">
  <div class="line-items">
    <?php foreach ($lines as $line) {
        $sid       = (int) $line['sid'];
        $available = $line['product_price'] !== null;
        $limit     = $available ? cart_line_limit($line['product_left']) : 0;
        $buyable   = $limit > 0;
        $name      = trim($available ? $line['product_title'] : $line['product']);
        $link      = 'product.php?id=' . (int) $line['pid'];
        $thumb     = $available ? product_image($line['product_img']) : '';
        ?>
      <div class="line-item">
        <a class="line-item__thumb <?php echo $available ? media_class($line['product_img']) : 'media--empty'; ?>" href="<?php echo e($link); ?>" tabindex="-1" aria-hidden="true">
          <?php if ($thumb !== '') { ?><img src="<?php echo e($thumb); ?>" alt=""><?php } ?>
        </a>

        <div>
          <p class="line-item__name"><a href="<?php echo e($link); ?>"><?php echo e($name); ?></a></p>
          <?php if ($buyable) { ?>
            <p class="line-item__sub"><?php echo e(ucfirst(product_category($line))); ?> · <?php echo e(money($line['product_price'])); ?> each</p>
            <form class="qty-form" method="post" action="cart.php">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="sid" value="<?php echo $sid; ?>">
              <input type="hidden" name="action" value="update">
              <label class="line-item__sub" for="qty-<?php echo $sid; ?>">Qty</label>
              <select class="qty-select" id="qty-<?php echo $sid; ?>" name="quantity" data-autosubmit>
                <?php for ($q = 1; $q <= $limit; $q++) { ?>
                  <option value="<?php echo $q; ?>"<?php echo $q === (int) $line['quantity'] ? ' selected' : ''; ?>><?php echo $q; ?></option>
                <?php } ?>
              </select>
              <noscript><button class="btn btn--sm btn--ghost" type="submit">Update</button></noscript>
            </form>
          <?php } elseif ($available) { ?>
            <p class="line-item__warn">Sold out. Remove it to check out.</p>
          <?php } else { ?>
            <p class="line-item__warn">No longer sold. Remove it to check out.</p>
          <?php } ?>
        </div>

        <p class="line-item__total"><?php echo $buyable ? e(money((int) $line['product_price'] * (int) $line['quantity'])) : '—'; ?></p>

        <form method="post" action="cart.php">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="sid" value="<?php echo $sid; ?>">
          <button class="icon-action" type="submit" name="action" value="remove" aria-label="Remove <?php echo e($name); ?> from your cart"><?php echo icon('trash'); ?></button>
        </form>
      </div>
    <?php } ?>
  </div>

  <aside class="panel sticky">
    <h2 class="panel__title">Order <em>summary</em></h2>
    <div class="summary-row"><span>Subtotal (<?php echo $itemCount; ?> item<?php echo $itemCount === 1 ? '' : 's'; ?>)</span><span><?php echo e(money($subtotal)); ?></span></div>
    <div class="summary-row"><span>Delivery</span><span><?php echo e(money($shipping)); ?></span></div>
    <div class="summary-row summary-row--total"><span>Total</span><span><?php echo e(money($total)); ?></span></div>
    <?php if (showing_converted_prices() && $itemCount > 0) { ?>
      <p class="charge-note">Prices in <?php echo e(display_currency()); ?> are estimates at today’s rate. You’ll be charged exactly <strong><?php echo e(base_money($total)); ?></strong>.</p>
    <?php } ?>

    <div class="summary-actions">
      <form action="checkout.php" method="post">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="type" value="cart">
        <button class="btn btn--lg btn--block" type="submit"<?php echo $canCheckout ? '' : ' disabled'; ?>><?php echo icon('card'); ?> Go to checkout</button>
      </form>
      <a class="btn btn--ghost btn--lg" href="index.php#shop">Continue shopping</a>
    </div>

    <p class="summary-note"><?php echo icon('truck'); ?> <span>Delivering to <?php echo e($customer['customer_address']); ?> · <a href="profile.php#details">Change</a></span></p>
    <p class="summary-note"><?php echo icon('tool'); ?> Each item comes with a serial code for discounted repairs.</p>
  </aside>
</div>
<?php } ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
