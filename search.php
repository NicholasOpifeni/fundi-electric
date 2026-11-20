<?php
/**
 * Search by keyword (?q=) and/or category (?cat=), newest first, paginated.
 *
 * Every word typed must appear in the title, category or description. The
 * form uses GET so a results page can be bookmarked, shared or reloaded, and
 * the page links keep the search term and category.
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/product-card.php';

$term = search_term();
$cat  = isset($_GET['cat']) && is_string($_GET['cat']) ? strtolower(trim($_GET['cat'])) : '';

// The WHERE clause is assembled from fixed SQL fragments and ? placeholders;
// the visitor's words only ever travel as bound parameters. % and _ are
// escaped so they match literally instead of acting as LIKE wildcards.
$where  = array();
$types  = '';
$params = array();
$words  = array_slice(array_filter(explode(' ', $term), 'strlen'), 0, 8);
foreach ($words as $word) {
    $like     = '%' . addcslashes($word, '%_\\') . '%';
    $where[]  = '(product_title LIKE ? OR product_catag LIKE ? OR product_desc LIKE ?)';
    $types   .= 'sss';
    array_push($params, $like, $like, $like);
}
if ($cat !== '') {
    $where[]  = 'LOWER(TRIM(product_catag)) = ?';
    $types   .= 's';
    $params[] = $cat;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countRow   = db_one($conn, "SELECT COUNT(*) AS total FROM products $whereSql", $types, $params);
$total      = (int) $countRow['total'];
$totalPages = max(1, (int) ceil($total / PRODUCTS_PER_PAGE));
$page       = min($totalPages, max(1, query_int('page', 1)));
$results    = db_all(
    $conn,
    "SELECT * FROM products $whereSql ORDER BY product_id DESC LIMIT ? OFFSET ?",
    $types . 'ii',
    array_merge($params, array(PRODUCTS_PER_PAGE, ($page - 1) * PRODUCTS_PER_PAGE))
);

$categories = db_all($conn, 'SELECT DISTINCT LOWER(TRIM(product_catag)) AS name FROM products ORDER BY name');

/** Link to a results page, keeping only the filters that are set. */
$searchUrl = function ($q, $category, $pageNumber = 1) {
    $query = array();
    if ($q !== '') {
        $query['q'] = $q;
    }
    if ($category !== '') {
        $query['cat'] = $category;
    }
    if ($pageNumber > 1) {
        $query['page'] = $pageNumber;
    }
    return 'search.php' . ($query ? '?' . http_build_query($query) : '');
};

if ($term !== '') {
    $pageTitle = 'Search: ' . $term;
} elseif ($cat !== '') {
    $pageTitle = category_plural($cat);
} else {
    $pageTitle = 'All products';
}
include __DIR__ . '/includes/headerNav.php';
?>

<header class="page-head">
  <p class="eyebrow"><?php echo $total; ?> result<?php echo $total === 1 ? '' : 's'; ?></p>
  <h1 class="display">
    <?php if ($term !== '') { ?>
      Results for <em>“<?php echo e($term); ?>”</em>
    <?php } elseif ($cat !== '') { ?>
      Shop <em><?php echo e(strtolower(category_plural($cat))); ?></em>
    <?php } else { ?>
      All <em>products</em>
    <?php } ?>
  </h1>
</header>

<section class="section section--flush">
  <nav class="chips" aria-label="Filter by category">
    <a class="chip" href="<?php echo e($searchUrl($term, '')); ?>"<?php echo $cat === '' ? ' aria-current="page"' : ''; ?>>All</a>
    <?php foreach ($categories as $category) { ?>
      <a class="chip" href="<?php echo e($searchUrl($term, $category['name'])); ?>"<?php echo $cat === $category['name'] ? ' aria-current="page"' : ''; ?>><?php echo e(category_plural($category['name'])); ?></a>
    <?php } ?>
  </nav>

  <?php if ($results) { ?>
    <div class="grid">
      <?php foreach ($results as $product) { render_product_card($product); } ?>
    </div>
  <?php } else { ?>
    <div class="empty empty--cta">
      <p>No products matched<?php echo $term !== '' ? ' “' . e($term) . '”' : ''; ?>. Try a different word or browse a category.</p>
      <a class="btn" href="search.php">Browse everything <?php echo icon('arrow'); ?></a>
    </div>
  <?php } ?>

  <?php echo pagination_links($page, $totalPages, function ($n) use ($searchUrl, $term, $cat) {
      return $searchUrl($term, $cat, $n);
  }, 'Result pages'); ?>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
