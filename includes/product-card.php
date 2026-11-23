<?php
/**
 * Product card, used in the catalogue, the new-arrivals carousel, search
 * results and "You may also like".
 */

/**
 * Print one product card with its add-to-cart button.
 *
 * @param array $product  a row from the products table
 * @param bool  $isNewest true to label it "New" (latest arrivals)
 */
function render_product_card($product, $isNewest = false)
{
    $id       = (int) $product['product_id'];
    $link     = 'product.php?id=' . $id;
    $title    = trim($product['product_title']);
    $category = product_category($product);
    $stock    = (int) $product['product_left'];
    $image    = product_image($product['product_img']);
    ?>
    <article class="card">
      <?php // The photo repeats the title link, so it is skipped by keyboard and screen readers. ?>
      <a class="card__media <?php echo media_class($product['product_img']); ?>" href="<?php echo e($link); ?>" tabindex="-1" aria-hidden="true">
        <?php if ($image !== '') { ?>
          <img src="<?php echo e($image); ?>" alt="" loading="lazy">
        <?php } else { ?>
          <span class="media-placeholder"><em><?php echo e(ucfirst($category)); ?></em></span>
        <?php } ?>
        <span class="badge"><?php echo e(product_badge($product, $isNewest)); ?></span>
      </a>
      <div class="card__body">
        <p class="card__meta">
          <span class="dot dot--<?php echo e($category); ?>"></span>
          <?php echo e(ucfirst($category)); ?>
          <span class="card__stock"><?php echo $stock > 0 ? $stock . ' in stock' : 'Sold out'; ?></span>
        </p>
        <h3 class="card__title"><a href="<?php echo e($link); ?>"><?php echo e($title); ?></a></h3>
        <p class="card__desc"><?php echo e(short_text($product['product_desc'], 70)); ?></p>
        <div class="card__foot">
          <span class="price"><?php echo e(money($product['product_price'])); ?></span>
          <form action="cart-add.php" method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="pid" value="<?php echo $id; ?>">
            <input type="hidden" name="quantity" value="1">
            <button class="btn btn--sm" type="submit" aria-label="Add <?php echo e($title); ?> to cart"<?php echo $stock > 0 ? '' : ' disabled'; ?>>
              <?php echo icon('plus'); ?> Cart
            </button>
          </form>
        </div>
      </div>
    </article>
    <?php
}
