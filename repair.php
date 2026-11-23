<?php
/**
 * Repair service: describe the problem, get a quote, then book.
 *
 * The quote is kept in the session between steps. A free repair (from a
 * free-repair serial) is booked here straight away. A paid one goes to
 * checkout to pay the advance and is booked once the payment succeeds.
 * Pricing and serial rules live in includes/repairs.php.
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/repairs.php';
require_login();

/**
 * Set by includes/shop-config.php, which init.php loads.
 * @var array $REPAIR_CATEGORY_RATES
 * @var array $REPAIR_DAMAGE_RATES
 */

$uid    = current_user_id();
$values = array('product' => '', 'category' => '', 'damage' => '', 'serial' => '');
$errors = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post_text('action');

    if ($action === 'quote') {
        foreach (array_keys($values) as $field) {
            $values[$field] = post_text($field);
        }
        list($quote, $errors) = build_repair_quote($conn, $values, $uid);
        if ($quote) {
            $_SESSION['repair_quote'] = $quote;
            redirect('repair.php#quote');
        }
        // Changed details that don't check out replace the old quote, so it can't be booked by mistake.
        unset($_SESSION['repair_quote']);
    } elseif ($action === 'book-free') {
        $quote = isset($_SESSION['repair_quote']) ? $_SESSION['repair_quote'] : null;
        if (!$quote || $quote['advance'] !== 0) {
            flash('Your quote has expired. Please fill in the form again.', 'error');
            redirect('repair.php');
        }
        $problem = book_repair($conn, $uid, $quote);
        unset($_SESSION['repair_quote']);
        if ($problem !== '') {
            flash($problem, 'error');
            redirect('repair.php');
        }
        flash('Your free repair is booked. Bring the device to the shop and we’ll take it from there.');
        redirect('profile.php#repairs');
    } elseif ($action === 'clear') {
        unset($_SESSION['repair_quote']);
        redirect('repair.php');
    } else {
        redirect('repair.php');
    }
}

$quote = !$errors && isset($_SESSION['repair_quote']) ? $_SESSION['repair_quote'] : null;
if ($quote) {
    foreach (array_keys($values) as $field) {
        $values[$field] = $quote[$field];
    }
}

$pageTitle = 'Repair service';
include __DIR__ . '/includes/headerNav.php';
?>

<header class="page-head">
  <p class="eyebrow">Repair service</p>
  <h1 class="display">Fixed by <em>people who care</em></h1>
  <p class="muted">Our technicians repair laptops, phones, keyboards and mice. Book online, pay a small advance and bring your device in. Bought it here? Your purchase serial makes the repair cheaper, or free.</p>
</header>

<div class="steps">
  <div class="step">
    <span class="step__num">01</span>
    <h3>Describe the problem</h3>
    <p>Tell us what the device is and what went wrong.</p>
  </div>
  <div class="step">
    <span class="step__num">02</span>
    <h3>Add your serial</h3>
    <p>Bought it here? The serial in your order history gives 50% off, or a free repair on premium devices.</p>
  </div>
  <div class="step">
    <span class="step__num">03</span>
    <h3>Book and bring it in</h3>
    <p>Pay the advance securely, or confirm a free repair, and follow progress in your account.</p>
  </div>
</div>

<div class="layout-2">
  <section class="panel">
    <h2 class="panel__title">Get a repair <em>quote</em></h2>
    <form class="form" action="repair.php" method="post" novalidate>
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="quote">
      <?php if ($errors) { ?><div class="alert" role="alert">Please check the highlighted fields.</div><?php } ?>

      <div class="field">
        <label for="product">Device</label>
        <input type="text" id="product" name="product" value="<?php echo e($values['product']); ?>" maxlength="50" placeholder="e.g. Dell Inspiron 15" required<?php echo field_attrs($errors, 'product'); ?>>
        <?php echo field_error($errors, 'product'); ?>
      </div>
      <div class="form-row">
        <div class="field">
          <label for="category">Kind of device</label>
          <select id="category" name="category" required<?php echo field_attrs($errors, 'category'); ?>>
            <option value="">Choose…</option>
            <?php foreach ($REPAIR_CATEGORY_RATES as $name => $rate) { ?>
              <option value="<?php echo e($name); ?>"<?php echo $values['category'] === $name ? ' selected' : ''; ?>><?php echo e(ucfirst($name)); ?></option>
            <?php } ?>
          </select>
          <?php echo field_error($errors, 'category'); ?>
        </div>
        <div class="field">
          <label for="damage">What’s wrong</label>
          <select id="damage" name="damage" required<?php echo field_attrs($errors, 'damage'); ?>>
            <option value="">Choose…</option>
            <?php foreach ($REPAIR_DAMAGE_RATES as $name => $percent) { ?>
              <option value="<?php echo e($name); ?>"<?php echo $values['damage'] === $name ? ' selected' : ''; ?>><?php echo e(ucfirst($name)); ?></option>
            <?php } ?>
          </select>
          <?php echo field_error($errors, 'damage'); ?>
        </div>
      </div>
      <div class="field">
        <label for="serial">Purchase serial <span class="muted">(optional)</span></label>
        <input type="text" id="serial" name="serial" class="mono" value="<?php echo e($values['serial']); ?>" maxlength="100" autocomplete="off"<?php echo field_attrs($errors, 'serial'); ?>>
        <?php echo isset($errors['serial']) ? field_error($errors, 'serial') : '<p class="hint">Find it under <a href="profile.php#orders">Order history</a> on your account page.</p>'; ?>
      </div>
      <button class="btn btn--lg" type="submit"><?php echo $quote ? 'Update quote' : 'Get a quote'; ?> <?php echo icon('arrow'); ?></button>
    </form>
  </section>

  <aside class="panel panel--white sticky quote" id="quote">
    <?php if ($quote) { ?>
      <p class="eyebrow">Your quote</p>
      <p class="quote__amount"><?php echo $quote['advance'] === 0 ? 'Free' : e(money($quote['advance'])); ?></p>
      <p class="muted"><?php echo $quote['advance'] === 0 ? 'No advance to pay.' : 'Advance payment to start the repair.'; ?></p>

      <?php if ($quote['perk'] !== '') { ?>
        <div class="alert alert--success quote__perk">
          Serial accepted: <?php echo $quote['perk'] === 'free' ? 'this repair is free' : '50% off'; ?>.
          Usual advance <?php echo e(money($quote['full'])); ?>.
        </div>
      <?php } ?>
      <?php if (!empty($quote['minimum'])) { ?>
        <p class="charge-note">The smallest advance we can take by card is <?php echo e(base_money(MIN_REPAIR_ADVANCE)); ?>.</p>
      <?php } ?>

      <dl>
        <dt>Device</dt><dd><?php echo e($quote['product']); ?></dd>
        <dt>Kind</dt><dd><?php echo e(ucfirst($quote['category'])); ?></dd>
        <dt>Problem</dt><dd><?php echo e(ucfirst($quote['damage'])); ?></dd>
        <?php if ($quote['serial'] !== '') { ?><dt>Serial</dt><dd class="mono"><?php echo e($quote['serial']); ?></dd><?php } ?>
      </dl>

      <?php if ($quote['advance'] === 0) { ?>
        <form action="repair.php" method="post">
          <?php echo csrf_field(); ?>
          <button class="btn btn--lg btn--block" type="submit" name="action" value="book-free"><?php echo icon('check'); ?> Book free repair</button>
        </form>
      <?php } else { ?>
        <?php if (showing_converted_prices()) { ?>
          <p class="charge-note">Estimated at today’s rate. You’ll be charged exactly <strong><?php echo e(base_money($quote['advance'])); ?></strong>.</p>
        <?php } ?>
        <form action="checkout.php" method="post">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="type" value="repair">
          <button class="btn btn--lg btn--block" type="submit"><?php echo icon('card'); ?> Pay advance <?php echo e(base_money($quote['advance'])); ?></button>
        </form>
      <?php } ?>

      <form class="quote__clear" action="repair.php" method="post">
        <?php echo csrf_field(); ?>
        <button class="link-button" type="submit" name="action" value="clear">Start again</button>
      </form>
    <?php } else { ?>
      <p class="eyebrow">How pricing works</p>
      <h2 class="panel__title">Transparent <em>rates</em></h2>
      <p class="muted">Your advance is the rate for the kind of device multiplied by the percentage for the problem.</p>
      <table class="rate-table">
        <caption class="sr-only">Rate for each kind of device</caption>
        <?php foreach ($REPAIR_CATEGORY_RATES as $name => $rate) { ?>
          <tr><td><?php echo e(ucfirst($name)); ?></td><td><?php echo e(money($rate)); ?></td></tr>
        <?php } ?>
      </table>
      <table class="rate-table rate-table--spaced">
        <caption class="sr-only">Percentage for each problem</caption>
        <?php foreach ($REPAIR_DAMAGE_RATES as $name => $percent) { ?>
          <tr><td><?php echo e(ucfirst($name)); ?></td><td><?php echo (int) $percent; ?>%</td></tr>
        <?php } ?>
      </table>
    <?php } ?>
  </aside>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
