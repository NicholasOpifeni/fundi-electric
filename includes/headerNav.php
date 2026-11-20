<?php
/**
 * Shared page header: <head>, icon sprite, site header with search, cart,
 * account menu and mobile menu, then any flash message. It opens the page
 * wrappers and <main>; includes/footer.php closes them.
 *
 * Include it after includes/init.php, once the page has handled its form
 * posts (they redirect, which can't happen after output). A page can set:
 *   $pageTitle      text for the <title>, shown before the store name
 *   $bodyClass      extra class on <body>, e.g. "home"
 *   $overlayHeader  true to float a transparent header over the page's hero
 */

/**
 * Set by includes/init.php before the page includes this file.
 * @var mysqli     $conn
 * @var array      $site
 * @var array|null $customer
 */
$documentTitle = isset($pageTitle) ? $pageTitle . ' · ' . $site['name'] : $site['name'];
$bodyClass     = isset($bodyClass) ? $bodyClass : '';
$overlay       = !empty($overlayHeader);
$cartItems     = cart_count($conn);
$searchTerm    = search_term();
$flash         = take_flash();

$navLinks = array(
    'Shop'        => 'index.php#shop',
    'Bestsellers' => 'index.php#bestsellers',
    'Categories'  => 'index.php#categories',
    'Repairs'     => 'repair.php',
    'Contact'     => 'contact.php',
);

// Mark the first link that points at the page being viewed.
$currentPage = basename($_SERVER['SCRIPT_NAME']);
$currentLink = null;
foreach ($navLinks as $href) {
    if (strtok($href, '#') === $currentPage) {
        $currentLink = $href;
        break;
    }
}

$flashType = ($flash && in_array($flash['type'], array('success', 'error', 'info'), true)) ? $flash['type'] : 'info';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo e($documentTitle); ?></title>
  <meta name="description" content="Laptops, phones, keyboards and mice in Uganda, with repair perks on every order.">
  <link rel="icon" href="images/site/favicon.svg" type="image/svg+xml">
  <link rel="apple-touch-icon" href="images/site/logo-mark.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&amp;family=Manrope:wght@400;500;600;700;800&amp;display=swap">
  <link rel="stylesheet" href="<?php echo e(asset_version('css/app.css')); ?>">
</head>
<body<?php echo $bodyClass !== '' ? ' class="' . e($bodyClass) . '"' : ''; ?>>
<a class="skip-link" href="#main">Skip to content</a>

<!-- Icon sprite: each symbol is drawn with icon('name') -->
<svg class="icon-sprite" aria-hidden="true" focusable="false">
  <symbol id="i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="m20 20-3.5-3.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></symbol>
  <symbol id="i-mic" viewBox="0 0 24 24"><rect x="9" y="3" width="6" height="11" rx="3" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></symbol>
  <symbol id="i-bag" viewBox="0 0 24 24"><path d="M5 8h14l-1 12H6L5 8Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M9 8V6a3 3 0 0 1 6 0v2" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M10 13h4M12 11v4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></symbol>
  <symbol id="i-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M4 20c1.5-3.5 4.5-5 8-5s6.5 1.5 8 5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></symbol>
  <symbol id="i-arrow" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></symbol>
  <symbol id="i-arrow-left" viewBox="0 0 24 24"><path d="M19 12H5M11 6l-6 6 6 6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></symbol>
  <symbol id="i-plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></symbol>
  <symbol id="i-minus" viewBox="0 0 24 24"><path d="M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></symbol>
  <symbol id="i-menu" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></symbol>
  <symbol id="i-close" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></symbol>
  <symbol id="i-alert" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M12 7.5v5.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="12" cy="16.3" r="1.1" fill="currentColor"/></symbol>
  <symbol id="i-shield" viewBox="0 0 24 24"><path d="M12 3 5 6v5c0 4.5 3 8.3 7 10 4-1.7 7-5.5 7-10V6l-7-3Z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="m9 12 2 2 4-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></symbol>
  <symbol id="i-tool" viewBox="0 0 24 24"><path d="M14.5 5.5a4 4 0 0 0 4.9 4.9L20 11l-9 9a2.1 2.1 0 0 1-3-3l9-9-.6-.6a4 4 0 0 0-4.9-4.9l2.6 2.6-.6 2.4-2.4.6L8.5 5.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" stroke-linecap="round"/></symbol>
  <symbol id="i-card" viewBox="0 0 24 24"><rect x="3" y="5.5" width="18" height="13" rx="2.5" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M3 10h18M7 15h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></symbol>
  <symbol id="i-truck" viewBox="0 0 24 24"><path d="M3 6h11v10H3zM14 10h4l3 3v3h-7" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><circle cx="7" cy="18" r="2" fill="#fff" stroke="currentColor" stroke-width="1.6"/><circle cx="17" cy="18" r="2" fill="#fff" stroke="currentColor" stroke-width="1.6"/></symbol>
  <symbol id="i-check" viewBox="0 0 24 24"><path d="m5 12.5 4.5 4.5L19 7.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></symbol>
  <symbol id="i-bolt" viewBox="0 0 24 24"><path d="M13 3 5 13.5h6L10 21l8-10.5h-6L13 3Z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></symbol>
  <symbol id="i-trash" viewBox="0 0 24 24"><path d="M5 7h14M10 7V5h4v2M7 7l1 13h8l1-13" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></symbol>
  <symbol id="i-pin" viewBox="0 0 24 24"><path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21Z" fill="none" stroke="currentColor" stroke-width="1.6"/><circle cx="12" cy="9.5" r="2.5" fill="none" stroke="currentColor" stroke-width="1.6"/></symbol>
  <symbol id="i-mail" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2.5" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="m4 7 8 6 8-6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></symbol>
  <symbol id="i-phone" viewBox="0 0 24 24"><path d="M6.5 3.5h3l1.5 4-2 1.3a11 11 0 0 0 6.2 6.2l1.3-2 4 1.5v3a2 2 0 0 1-2.2 2A16.5 16.5 0 0 1 4.5 5.7a2 2 0 0 1 2-2.2Z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></symbol>
  <symbol id="i-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M12 7.5V12l3 2" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></symbol>
  <symbol id="i-logout" viewBox="0 0 24 24"><path d="M14 4h4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-4M10 8l-4 4 4 4M6 12h10" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></symbol>
  <symbol id="i-edit" viewBox="0 0 24 24"><path d="M4 20h4L19 9l-4-4L4 16v4Z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></symbol>
  <symbol id="i-facebook" viewBox="0 0 24 24"><path d="M13.5 21v-7.5H16l.5-3h-3V8.6c0-.9.3-1.6 1.6-1.6h1.6V4.3A21 21 0 0 0 14.3 4C12 4 10.5 5.4 10.5 8v2.5H8v3h2.5V21h3Z" fill="currentColor"/></symbol>
  <symbol id="i-x" viewBox="0 0 24 24"><path d="M17.5 4h3l-6.6 7.5L21.7 20h-6.1l-4.8-6.2L5.3 20h-3l7.1-8.1L2 4h6.2l4.3 5.7L17.5 4Zm-1 14.3h1.7L7.6 5.6H5.8l10.7 12.7Z" fill="currentColor"/></symbol>
  <symbol id="i-youtube" viewBox="0 0 24 24"><path d="M21.6 7.2a2.5 2.5 0 0 0-1.8-1.8C18.2 5 12 5 12 5s-6.2 0-7.8.4A2.5 2.5 0 0 0 2.4 7.2C2 8.8 2 12 2 12s0 3.2.4 4.8a2.5 2.5 0 0 0 1.8 1.8C5.8 19 12 19 12 19s6.2 0 7.8-.4a2.5 2.5 0 0 0 1.8-1.8c.4-1.6.4-4.8.4-4.8s0-3.2-.4-4.8ZM10 15V9l5.2 3L10 15Z" fill="currentColor"/></symbol>
</svg>

<div class="page">
<div class="frame">

<header class="site-header<?php echo $overlay ? ' site-header--overlay' : ''; ?>">
  <nav class="site-nav" aria-label="Main">
    <?php foreach ($navLinks as $label => $href) { ?>
      <a href="<?php echo e($href); ?>"<?php echo $href === $currentLink ? ' aria-current="page"' : ''; ?>><?php echo e($label); ?></a>
    <?php } ?>
  </nav>

  <a class="brand" href="index.php" aria-label="<?php echo e($site['name']); ?> home"><?php echo brand_markup($site['name']); ?></a>

  <div class="header-actions">
    <form class="search-pill" action="search.php" method="get" role="search">
      <?php echo icon('search'); ?>
      <input type="search" name="q" placeholder="Search products…" value="<?php echo e($searchTerm); ?>" aria-label="Search products">
      <?php // Shown by app.js only in browsers that support speech recognition. ?>
      <button type="button" class="mic-btn" aria-label="Search by voice" title="Search by voice" data-voice-search hidden><?php echo icon('mic'); ?></button>
    </form>

    <a class="icon-btn" href="cart.php" aria-label="Cart, <?php echo $cartItems; ?> item<?php echo $cartItems === 1 ? '' : 's'; ?>">
      <?php echo icon('bag'); ?>
      <?php if ($cartItems > 0) { ?><span class="icon-btn__count" aria-hidden="true"><?php echo $cartItems; ?></span><?php } ?>
    </a>

    <details class="account-menu" data-account-menu>
      <summary class="icon-btn" aria-label="Account menu">
        <?php if ($customer) { ?>
          <span class="avatar-initial" aria-hidden="true"><?php echo e(name_initial($customer['customer_fname'])); ?></span>
        <?php } else { ?>
          <?php echo icon('user'); ?>
        <?php } ?>
      </summary>
      <div class="account-menu__panel">
        <?php if ($customer) { ?>
          <p class="account-menu__hello">Hi, <?php echo e(strtok($customer['customer_fname'], ' ')); ?></p>
          <a href="profile.php">My account</a>
          <a href="profile.php#orders">Order history</a>
          <a href="repair.php">Book a repair</a>
          <form action="logout.php" method="post">
            <?php echo csrf_field(); ?>
            <button type="submit" class="account-menu__logout" data-confirm="Log out of your account?"><?php echo icon('logout'); ?> Log out</button>
          </form>
        <?php } else { ?>
          <p class="account-menu__hello">Welcome</p>
          <a href="login.php">Log in</a>
          <a href="signup.php">Create an account</a>
        <?php } ?>
      </div>
    </details>

    <button class="icon-btn menu-toggle" type="button" aria-expanded="false" aria-controls="mobile-menu" aria-label="Open menu" data-menu-toggle>
      <?php echo icon('menu'); ?>
    </button>
  </div>

  <div class="mobile-menu" id="mobile-menu" hidden>
    <form class="search-pill search-pill--block" action="search.php" method="get" role="search">
      <?php echo icon('search'); ?>
      <input type="search" name="q" placeholder="Search products…" value="<?php echo e($searchTerm); ?>" aria-label="Search products">
    </form>
    <?php foreach ($navLinks as $label => $href) { ?>
      <a href="<?php echo e($href); ?>"<?php echo $href === $currentLink ? ' aria-current="page"' : ''; ?>><?php echo e($label); ?></a>
    <?php } ?>
    <?php if ($customer) { ?>
      <a href="profile.php">My account</a>
      <form action="logout.php" method="post">
        <?php echo csrf_field(); ?>
        <button type="submit" class="mobile-menu__button" data-confirm="Log out of your account?">Log out</button>
      </form>
    <?php } else { ?>
      <a href="login.php">Log in</a>
      <a href="signup.php">Create an account</a>
    <?php } ?>
  </div>
</header>

<?php if ($flash) { ?>
  <div class="flash flash--<?php echo $flashType; ?>" role="<?php echo $flashType === 'error' ? 'alert' : 'status'; ?>">
    <?php echo icon($flashType === 'success' ? 'check' : 'alert'); ?>
    <span><?php echo e($flash['message']); ?></span>
    <button type="button" class="flash__close" aria-label="Dismiss message" data-dismiss><?php echo icon('close'); ?></button>
  </div>
<?php } ?>

<main class="main" id="main">
