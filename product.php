<?php
/**
 * Product page: photo with hover zoom, description, price, the repair perk
 * the purchase earns, quantity and add to cart, then more from the category.
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/product-card.php';

$productId = query_int('id');
$product   = $productId > 0
    ? db_one($conn, 'SELECT * FROM products WHERE product_id = ?', 'i', array($productId))
    : null;

if (!$product) {
    http_response_code(404);
    $pageTitle = 'Product not found';
    include __DIR__ . '/includes/headerNav.php';
    ?>
    <header class="page-head">
      <p class="eyebrow">Not found</p>
      <h1 class="display">Nothing <em>here</em></h1>
    </header>
    <div class="empty empty--cta">
      <p>We couldn't find that product. It may have been removed from the shop.</p>
      <a class="btn" href="index.php#catalogue">Browse the catalogue <?php echo icon('arrow'); ?></a>
    </div>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

$title    = trim($product['product_title']);
$category = product_category($product);
$price    = (int) $product['product_price'];
$stock    = (int) $product['product_left'];
$image    = product_image($product['product_img']);

// The quantity picker never offers more than a cart line may hold.
$maxQuantity = max(1, cart_line_limit($stock));

$related = db_all(
    $conn,
    'SELECT * FROM products WHERE LOWER(TRIM(product_catag)) = ? AND product_id <> ? ORDER BY product_id DESC LIMIT 4',
    'si',
    array($category, $productId)
);

$pageTitle = $title;
include __DIR__ . '/includes/headerNav.php';
?>

<nav class="breadcrumbs" aria-label="Breadcrumb">
  <a href="index.php">Home</a> <span aria-hidden="true">/</span>
  <a href="search.php?cat=<?php echo e(urlencode($category)); ?>"><?php echo e(category_plural($category)); ?></a> <span aria-hidden="true">/</span>
  <span aria-current="page"><?php echo e($title); ?></span>
</nav>

<section class="pdp">
  <div class="pdp__media <?php echo media_class($product['product_img']); ?>"<?php echo $image !== '' ? ' data-zoom' : ''; ?>>
    <?php if ($image !== '') { ?>
      <img src="<?php echo e($image); ?>" alt="<?php echo e($title); ?>">
      <span class="pdp__hint" aria-hidden="true">Hover to zoom</span>
    <?php } else { ?>
      <span class="media-placeholder"><em><?php echo e(ucfirst($category)); ?></em></span>
    <?php } ?>
    <span class="badge"><?php echo e(product_badge($product)); ?></span>
  </div>

  <div class="pdp__info">
    <p class="eyebrow"><?php echo e(ucfirst($category)); ?></p>
    <h1 class="pdp__title"><?php echo e($title); ?></h1>
    <div class="pdp__meta">
      <span>Listed <?php echo e(trim($product['product_date'])); ?></span>
      <span><?php echo $stock > 0 ? $stock . ' in stock' : 'Currently sold out'; ?></span>
    </div>

    <p class="pdp__desc"><?php echo nl2br(e(trim($product['product_desc']))); ?></p>

    <p class="pdp__price"><?php echo e(money($price)); ?><small>+ <?php echo e(money(SHIPPING_FEE)); ?> delivery</small></p>
    <?php if (showing_converted_prices()) { ?>
      <p class="charge-note">Estimated at today’s rate. You pay <?php echo e(base_money($price)); ?> plus <?php echo e(base_money(SHIPPING_FEE)); ?> delivery.</p>
    <?php } ?>

    <div class="perk">
      <?php echo icon('tool'); ?>
      <div>
        <strong>Includes <?php echo e(repair_perk_label($price)); ?></strong>
        <span>You get a serial code after checkout. Enter it when you book a repair.</span>
      </div>
    </div>

    <?php if ($stock > 0) { ?>
      <div class="buy-row">
        <form class="buy-row__form" action="cart-add.php" method="post">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="pid" value="<?php echo $productId; ?>">
          <?php // The − and + buttons appear once app.js runs; the number field works on its own. ?>
          <div class="qty" data-qty>
            <button type="button" aria-label="Decrease quantity" data-qty-dec hidden><?php echo icon('minus'); ?></button>
            <input type="number" name="quantity" value="1" min="1" max="<?php echo $maxQuantity; ?>" aria-label="Quantity">
            <button type="button" aria-label="Increase quantity" data-qty-inc hidden><?php echo icon('plus'); ?></button>
          </div>
          <button class="btn btn--lg" type="submit"><?php echo icon('bag'); ?> Add to cart</button>
        </form>
        <a class="btn btn--lg btn--ghost" href="payment.php?id=<?php echo $productId; ?>">Buy now <?php echo icon('arrow'); ?></a>
      </div>
    <?php } else { ?>
      <div class="buy-row">
        <button class="btn btn--lg" type="button" disabled>Sold out</button>
      </div>
    <?php } ?>

    <ul class="assurances">
      <li><?php echo icon('truck'); ?> Flat <?php echo e(money(SHIPPING_FEE)); ?> delivery to your saved address</li>
      <li><?php echo icon('card'); ?> Secure card payment through Stripe</li>
      <li><?php echo icon('shield'); ?> Genuine product with its own repair serial</li>
    </ul>
  </div>
</section>

<?php if ($related) { ?>
<section class="section">
  <div class="section__head">
    <h2 class="sec-title">
      <span>More from this category</span>
      <span>You may also <i class="spark" aria-hidden="true">✧</i> <em>like</em></span>
    </h2>
    <a class="link-arrow" href="search.php?cat=<?php echo e(urlencode($category)); ?>">See all <?php echo e(strtolower(category_plural($category))); ?> <?php echo icon('arrow'); ?></a>
  </div>
  <div class="grid">
    <?php foreach ($related as $item) { render_product_card($item); } ?>
  </div>
</section>
<?php } ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
