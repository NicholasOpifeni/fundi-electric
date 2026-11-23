<?php
/**
 * Buy now: review one product, the quantity and the delivery address, then
 * pay by card. Changing the quantity reloads the page (?quantity=) so the
 * totals are always worked out here; checkout.php checks them again.
 */
require_once __DIR__ . '/includes/init.php';
require_login();

$pid     = query_int('id');
$product = $pid > 0 ? db_one($conn, 'SELECT * FROM products WHERE product_id = ?', 'i', array($pid)) : null;
if (!$product) {
    flash('That product is no longer available.', 'error');
    redirect('index.php');
}

$title = trim($product['product_title']);
$limit = cart_line_limit($product['product_left']);
if ($limit === 0) {
    flash('Sorry, ' . $title . ' is sold out.', 'error');
    redirect('product.php?id=' . $pid);
}

$quantity = min($limit, max(1, query_int('quantity', 1)));
$price    = (int) $product['product_price'];
$subtotal = $price * $quantity;
$total    = $subtotal + SHIPPING_FEE;
$image    = product_image($product['product_img']);

$pageTitle = 'Checkout';
include __DIR__ . '/includes/headerNav.php';
?>

<header class="page-head">
  <p class="eyebrow">Secure checkout</p>
  <h1 class="display">Almost <em>yours</em></h1>
  <p class="muted">Check your delivery details, then pay by card on Stripe’s secure page.</p>
</header>

<div class="layout-2">
  <div>
    <section class="panel address-block">
      <div class="account-section__head">
        <h2 class="panel__title panel__title--flush">Delivery <em>address</em></h2>
        <a class="link-arrow" href="profile.php#details">Change <?php echo icon('edit'); ?></a>
      </div>
      <p><strong><?php echo e($customer['customer_fname']); ?></strong></p>
      <p><?php echo e($customer['customer_address']); ?></p>
      <p><?php echo e($customer['customer_phone']); ?> · <?php echo e($customer['customer_email']); ?></p>
    </section>

    <div class="line-items line-items--spaced">
      <div class="line-item">
        <a class="line-item__thumb <?php echo media_class($product['product_img']); ?>" href="product.php?id=<?php echo $pid; ?>" tabindex="-1" aria-hidden="true">
          <?php if ($image !== '') { ?><img src="<?php echo e($image); ?>" alt=""><?php } ?>
        </a>
        <div>
          <p class="line-item__name"><a href="product.php?id=<?php echo $pid; ?>"><?php echo e($title); ?></a></p>
          <p class="line-item__sub"><?php echo e(ucfirst(product_category($product))); ?> · <?php echo e(money($price)); ?> each · Includes <?php echo e(repair_perk_label($price)); ?></p>
          <form class="qty-form" action="payment.php" method="get">
            <input type="hidden" name="id" value="<?php echo $pid; ?>">
            <label class="line-item__sub" for="quantity">Qty</label>
            <select class="qty-select" id="quantity" name="quantity" data-autosubmit>
              <?php for ($q = 1; $q <= $limit; $q++) { ?>
                <option value="<?php echo $q; ?>"<?php echo $q === $quantity ? ' selected' : ''; ?>><?php echo $q; ?></option>
              <?php } ?>
            </select>
            <noscript><button class="btn btn--sm btn--ghost" type="submit">Update</button></noscript>
          </form>
        </div>
        <p class="line-item__total"><?php echo e(money($subtotal)); ?></p>
      </div>
    </div>
  </div>

  <aside class="panel sticky">
    <h2 class="panel__title">Order <em>summary</em></h2>
    <div class="summary-row"><span>Subtotal (<?php echo $quantity; ?> item<?php echo $quantity === 1 ? '' : 's'; ?>)</span><span><?php echo e(money($subtotal)); ?></span></div>
    <div class="summary-row"><span>Delivery</span><span><?php echo e(money(SHIPPING_FEE)); ?></span></div>
    <div class="summary-row summary-row--total"><span>Total</span><span><?php echo e(money($total)); ?></span></div>
    <?php if (showing_converted_prices()) { ?>
      <p class="charge-note">Prices in <?php echo e(display_currency()); ?> are estimates at today’s rate. You’ll be charged exactly <strong><?php echo e(base_money($total)); ?></strong>.</p>
    <?php } ?>

    <div class="summary-actions">
      <form action="checkout.php" method="post">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="type" value="product">
        <input type="hidden" name="pid" value="<?php echo $pid; ?>">
        <input type="hidden" name="quantity" value="<?php echo $quantity; ?>">
        <button class="btn btn--lg btn--block" type="submit"><?php echo icon('card'); ?> Pay <?php echo e(base_money($total)); ?></button>
      </form>
      <a class="btn btn--ghost btn--lg" href="product.php?id=<?php echo $pid; ?>">Back to product</a>
    </div>

    <p class="summary-note"><?php echo icon('shield'); ?> You’ll pay on Stripe’s secure page. We never see your card details.</p>
  </aside>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
