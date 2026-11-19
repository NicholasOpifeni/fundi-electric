<?php
/**
 * Shared site footer. Closes <main> and the page wrappers opened in
 * includes/headerNav.php, then loads the storefront script.
 */

/**
 * Set by includes/init.php before the page includes this file.
 * @var array $site
 * @var array $SHOP_SOCIAL
 */

// The settings table stores the footer note as HTML with <br> line breaks.
// Show it as one line of plain text, escaped like any other value.
$footerNote = preg_replace('/<br\s*\/?>/i', ' · ', (string) $site['footer']);
$footerNote = html_entity_decode(strip_tags($footerNote), ENT_QUOTES, 'UTF-8');
$footerNote = trim(preg_replace('/\s+/u', ' ', $footerNote));

$socialLinks  = array_filter($SHOP_SOCIAL);
$socialLabels = array('facebook' => 'Facebook', 'x' => 'X (Twitter)', 'youtube' => 'YouTube');
?>
</main>

<footer class="site-footer">
  <div class="site-footer__top">
    <div class="site-footer__brand">
      <a class="brand" href="index.php"><?php echo brand_markup($site['name']); ?></a>
      <p>Everyday electronics you can rely on, and a repair desk that has your back long after checkout.</p>
      <a class="btn btn--butter" href="repair.php">Book a repair <?php echo icon('arrow'); ?></a>
    </div>

    <nav class="site-footer__col" aria-label="Account">
      <h4>Account</h4>
      <a href="profile.php">Your account</a>
      <a href="profile.php#details">Personal details</a>
      <a href="profile.php#orders">Order history</a>
      <a href="profile.php#repairs">Repair history</a>
    </nav>

    <nav class="site-footer__col" aria-label="Shop">
      <h4>Shop</h4>
      <a href="index.php#shop">New arrivals</a>
      <a href="index.php#bestsellers">Best sellers</a>
      <a href="index.php#categories">Categories</a>
      <a href="index.php#catalogue">Full catalogue</a>
    </nav>

    <nav class="site-footer__col" aria-label="Help">
      <h4>Help</h4>
      <a href="contact.php">Contact us</a>
      <a href="repair.php">Repairs and discounts</a>
      <a href="signup.php">Create an account</a>
    </nav>

    <?php if ($socialLinks) { ?>
      <div class="site-footer__col">
        <h4>Follow us</h4>
        <div class="socials">
          <?php foreach ($socialLinks as $network => $url) { ?>
            <a href="<?php echo e($url); ?>" target="_blank" rel="noopener" aria-label="<?php echo e(isset($socialLabels[$network]) ? $socialLabels[$network] : ucfirst($network)); ?>"><?php echo icon($network); ?></a>
          <?php } ?>
        </div>
      </div>
    <?php } ?>
  </div>

  <div class="site-footer__bottom">
    <p><?php echo e($footerNote); ?></p>
    <p class="site-footer__secure"><?php echo icon('card'); ?> Payments secured by Stripe</p>
  </div>
</footer>

</div><!-- /.frame -->
</div><!-- /.page -->

<script src="<?php echo e(asset_version('js/app.js')); ?>"></script>
</body>
</html>
