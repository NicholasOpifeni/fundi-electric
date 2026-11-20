<?php
/**
 * The customer's cart: change quantities, remove items, see the order total
 * and go to checkout.
 *
 * Prices come from the products table rather than carts.price (the price
 * when the item was added), so the cart always shows what checkout charges.
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
        $quantity = max(1, min(MAX_CART_QUANTITY, (int) post_text('quantity')));
        db_exec(
            $conn,
            "UPDATE carts SET quantity = ? WHERE sid = ? AND uid = ? AND status = 'active'",
            'iii',
            array($quantity, $sid, $uid)
        );
    }
    redirect('cart.php');
}

$lines = db_all(
    $conn,
    "SELECT c.sid, c.pid, c.product, c.quantity,
            p.product_title, p.product_price, p.product_img, p.product_catag
       FROM carts c LEFT JOIN products p ON p.product_id = c.pid
      WHERE c.uid = ? AND c.status = 'active'
      ORDER BY c.sid DESC",
    'i',
    array($uid)
);

// A line whose product was deleted from the shop can't be bought; it stays
// visible so the customer can remove it, but it blocks checkout until then.
$subtotal    = 0;
$itemCount   = 0;
$blocked     = false;
foreach ($lines as $line) {
    if ($line['product_price'] === null) {
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
          <?php if ($available) { ?>
            <p class="line-item__sub"><?php echo e(ucfirst(product_category($line))); ?> · <?php echo e(base_money($line['product_price'])); ?> each</p>
            <form class="qty-form" method="post" action="cart.php">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="sid" value="<?php echo $sid; ?>">
              <input type="hidden" name="action" value="update">
              <label class="line-item__sub" for="qty-<?php echo $sid; ?>">Qty</label>
              <select class="qty-select" id="qty-<?php echo $sid; ?>" name="quantity" data-autosubmit>
                <?php for ($q = 1; $q <= MAX_CART_QUANTITY; $q++) { ?>
                  <option value="<?php echo $q; ?>"<?php echo $q === (int) $line['quantity'] ? ' selected' : ''; ?>><?php echo $q; ?></option>
                <?php } ?>
              </select>
              <noscript><button class="btn btn--sm btn--ghost" type="submit">Update</button></noscript>
            </form>
          <?php } else { ?>
            <p class="line-item__warn">No longer sold. Remove it to check out.</p>
          <?php } ?>
        </div>

        <p class="line-item__total"><?php echo $available ? e(base_money((int) $line['product_price'] * (int) $line['quantity'])) : '—'; ?></p>

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
    <div class="summary-row"><span>Subtotal (<?php echo $itemCount; ?> item<?php echo $itemCount === 1 ? '' : 's'; ?>)</span><span><?php echo e(base_money($subtotal)); ?></span></div>
    <div class="summary-row"><span>Delivery</span><span><?php echo e(base_money($shipping)); ?></span></div>
    <div class="summary-row summary-row--total"><span>Total</span><span><?php echo e(base_money($total)); ?></span></div>

    <div class="summary-actions">
      <form action="checkout.php" method="post">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="type" value="cart">
        <button class="btn btn--lg btn--block" type="submit"<?php echo $canCheckout ? '' : ' disabled'; ?>><?php echo icon('card'); ?> Go to checkout</button>
      </form>
      <a class="btn btn--ghost btn--lg" href="index.php#shop">Continue shopping</a>
    </div>

    <p class="summary-note"><?php echo icon('tool'); ?> Each item comes with a serial code for discounted repairs.</p>
  </aside>
</div>
<?php } ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
