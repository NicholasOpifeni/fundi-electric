<?php
/**
 * Home page: hero, new arrivals, story banner, value pills, categories and
 * the full paginated catalogue.
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/product-card.php';

/* New arrivals: the eight latest products, for the carousel. */
$latest = db_all($conn, 'SELECT * FROM products ORDER BY product_id DESC LIMIT 8');

/* Categories: product count and a cover photo for each, from a single query. */
$categories = array();
foreach (db_all($conn, 'SELECT product_catag, product_img FROM products ORDER BY product_id DESC') as $row) {
    $name = product_category($row);
    if (!isset($categories[$name])) {
        $categories[$name] = array('name' => $name, 'total' => 0, 'img' => '');
    }
    $categories[$name]['total']++;

    // Use the newest photo, but prefer one on a plain background: it blends into the coloured tile.
    $cover = $categories[$name]['img'];
    if (product_image($row['product_img']) !== ''
        && ($cover === '' || (!image_is_cutout($cover) && image_is_cutout($row['product_img'])))) {
        $categories[$name]['img'] = $row['product_img'];
    }
}
uasort($categories, function ($a, $b) {
    return $b['total'] - $a['total'];
});

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

$pageTitle     = 'Everyday electronics';
$bodyClass     = 'home';
$overlayHeader = true;

include __DIR__ . '/includes/headerNav.php';
?>

<!-- Hero ================================================================== -->
<section class="hero">
  <div class="hero__content">
    <h1 class="hero__title">Everyday <em>electronics</em> for a smarter home</h1>
    <p class="hero__lead">Laptops, phones, keyboards and mice from brands you trust. Every order comes with a serial code that unlocks discounted repairs at our service desk.</p>
    <a class="btn btn--butter" href="#shop">Shop now <?php echo icon('arrow'); ?></a>
  </div>

  <aside class="hero__stat glass" aria-label="Repair perk">
    <p>Genuine.<br>Warrantied.<br>Repair-backed.</p>
    <?php echo icon('shield', 'hero__stat-icon'); ?>
    <strong>50%</strong>
    <span>off repairs on every purchase</span>
  </aside>
</section>

<!-- New arrivals ========================================================== -->
<section class="section" id="shop">
  <div class="section__head">
    <h2 class="sec-title">
      <span>Fresh from the workshop, just</span>
      <span>Arrived <i class="spark" aria-hidden="true">✧</i> <em>Products</em></span>
    </h2>
    <a class="link-arrow" href="#catalogue">More products <?php echo icon('arrow'); ?></a>
  </div>

  <?php // The arrow buttons stay hidden until app.js wires them up; the track scrolls either way. ?>
  <div class="carousel" data-carousel>
    <button class="carousel__btn carousel__btn--prev" type="button" aria-label="Previous products" data-carousel-prev hidden><?php echo icon('arrow-left'); ?></button>
    <div class="carousel__track" role="region" aria-label="New arrivals" tabindex="0" data-carousel-track>
      <?php foreach ($latest as $i => $product) { render_product_card($product, $i < 2); } ?>
    </div>
    <button class="carousel__btn carousel__btn--next" type="button" aria-label="Next products" data-carousel-next hidden><?php echo icon('arrow'); ?></button>
  </div>
</section>

<!-- Story banner ========================================================== -->
<section class="story">
  <p class="story__text">
    We stock <em>electronics</em> you can count on for years to come, through late-night work, weekend <em>gaming</em> and everything in between. Each device is <em>checked, serialised</em> and backed by our own repair desk.
  </p>
</section>

<!-- Value pills =========================================================== -->
<section class="values" aria-label="Why shop with us">
  <div class="value">
    <span class="value__icon"><?php echo icon('shield'); ?></span>
    <p><em>Genuine</em><br>Brands only</p>
  </div>
  <div class="value value--round">
    <span class="value__icon"><?php echo icon('tool'); ?></span>
    <p><em>Repair</em><br>Perks included</p>
  </div>
  <div class="value">
    <span class="value__icon"><?php echo icon('card'); ?></span>
    <p><em>Secure</em><br>Stripe checkout</p>
  </div>
</section>

<!-- Categories ============================================================ -->
<section class="section" id="categories">
  <div class="section__head">
    <h2 class="sec-title">
      <span>Explore our carefully picked and</span>
      <span>well-tested <i class="spark" aria-hidden="true">✧</i> <em>Categories</em></span>
    </h2>
  </div>

  <div class="categories">
    <?php $tile = 0; foreach ($categories as $category) { ?>
      <a class="category category--<?php echo $tile++ % 4; ?>" href="search.php?cat=<?php echo e(urlencode($category['name'])); ?>">
        <?php if ($category['img'] !== '') { ?>
          <img class="<?php echo media_class($category['img']); ?>" src="<?php echo e(product_image($category['img'])); ?>" alt="" loading="lazy">
        <?php } ?>
        <span class="category__label">
          <span>Explore</span>
          <em><?php echo e(category_plural($category['name'])); ?></em>
          <small><?php echo $category['total']; ?> product<?php echo $category['total'] === 1 ? '' : 's'; ?></small>
        </span>
        <span class="btn btn--light btn--sm">Shop <?php echo icon('arrow'); ?></span>
      </a>
    <?php } ?>
  </div>
</section>

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
