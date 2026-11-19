<?php
/**
 * Home page.
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/product-card.php';

/* Full catalogue, newest first. Out-of-range page numbers show the nearest real page. */
$totalRow   = db_one($conn, 'SELECT COUNT(*) AS total FROM products');
$totalPages = max(1, (int) ceil((int) $totalRow['total'] / PRODUCTS_PER_PAGE));
$page       = min($totalPages, max(1, query_int('page', 1)));
$catalogue  = db_all(
    $conn,
    'SELECT * FROM products ORDER BY product_id DESC LIMIT ? OFFSET ?',
    'ii',
    array(PRODUCTS_PER_PAGE, ($page - 1) * PRODUCTS_PER_PAGE)
);

$pageTitle = 'Everyday electronics';

include __DIR__ . '/includes/headerNav.php';
?>

<header class="page-head">
  <p class="eyebrow">Laptops · Phones · Accessories</p>
  <h1 class="display">Everyday <em>electronics</em></h1>
  <p class="muted">Genuine devices from brands you trust, with repair perks on every order.</p>
</header>

<!-- Full catalogue ======================================================== -->
<section class="section" id="catalogue">
  <div class="section__head">
    <h2 class="sec-title">
      <span>Everything in store, newest first</span>
      <span>The full <i class="spark" aria-hidden="true">✧</i> <em>Catalogue</em></span>
    </h2>
    <p class="muted">Page <?php echo $page; ?> of <?php echo $totalPages; ?></p>
  </div>

  <?php if ($catalogue) { ?>
    <div class="grid">
      <?php foreach ($catalogue as $product) { render_product_card($product); } ?>
    </div>
  <?php } else { ?>
    <p class="empty">No products yet. New stock is on its way.</p>
  <?php } ?>

  <?php echo pagination_links($page, $totalPages, function ($n) {
      return 'index.php?page=' . $n . '#catalogue';
  }, 'Catalogue pages'); ?>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
