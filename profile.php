<?php
/**
 * The customer's account page.
 *
 * Each details card has its own small form ("section"), so saving the address
 * can't touch the name or email. A rejected change re-opens its card with the
 * error under the field and the typed value kept.
 */
require_once __DIR__ . '/includes/init.php';
require_login();

$uid    = current_user_id();
$errors = array();
$open   = '';

// What each edit form shows: the saved value, or what was just typed if it was rejected.
$form = array(
    'name'    => $customer['customer_fname'],
    'email'   => $customer['customer_email'],
    'address' => $customer['customer_address'],
    'phone'   => $customer['customer_phone'],
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $section = post_text('section');

    if ($section === 'details') {
        $form['name']  = post_text('name');
        $form['email'] = post_text('email');
        $errors = array_filter(array(
            'name'  => name_error($form['name']),
            'email' => email_error($conn, $form['email'], $uid),
        ));
        if (!$errors) {
            db_exec($conn, 'UPDATE customer SET customer_fname = ?, customer_email = ? WHERE customer_id = ?', 'ssi', array($form['name'], $form['email'], $uid));
        }
    } elseif ($section === 'address') {
        $form['address'] = post_text('address');
        $errors = array_filter(array('address' => address_error($form['address'])));
        if (!$errors) {
            db_exec($conn, 'UPDATE customer SET customer_address = ? WHERE customer_id = ?', 'si', array($form['address'], $uid));
        }
    } elseif ($section === 'phone') {
        $form['phone'] = post_text('phone');
        $errors = array_filter(array('phone' => phone_error($form['phone'])));
        if (!$errors) {
            db_exec($conn, 'UPDATE customer SET customer_phone = ? WHERE customer_id = ?', 'si', array(normalise_phone($form['phone']), $uid));
        }
    } else {
        redirect('profile.php');
    }

    if (!$errors) {
        flash('Your details were saved.');
        redirect('profile.php#details');
    }
    $open = $section;
}

$isAdmin = $customer['customer_role'] === 'admin';

$pageTitle = 'My account';
include __DIR__ . '/includes/headerNav.php';
?>

<header class="page-head">
  <p class="eyebrow">My account</p>
  <h1 class="display">Hello, <em><?php echo e(strtok($customer['customer_fname'], ' ')); ?></em></h1>
</header>

<div class="account">
  <aside class="panel account-card">
    <div class="avatar" aria-hidden="true"><?php echo e(name_initial($customer['customer_fname'])); ?></div>
    <h2><?php echo e($customer['customer_fname']); ?></h2>
    <p><?php echo e($customer['customer_email']); ?></p>
    <span class="pill<?php echo $isAdmin ? ' pill--admin' : ''; ?>"><?php echo $isAdmin ? 'Store admin' : 'Customer'; ?></span>

    <div class="summary-actions">
      <a class="btn btn--ghost" href="repair.php"><?php echo icon('tool'); ?> Book a repair</a>
      <form action="logout.php" method="post">
        <?php echo csrf_field(); ?>
        <button class="btn btn--danger btn--block" type="submit" data-confirm="Log out of your account?"><?php echo icon('logout'); ?> Log out</button>
      </form>
    </div>
  </aside>

  <div>
    <section class="account-section" id="details">
      <div class="account-section__head">
        <h2>Your <em>details</em></h2>
      </div>

      <div class="detail-grid">
        <div class="detail">
          <h3>Personal profile</h3>
          <p><?php echo e($customer['customer_fname']); ?></p>
          <p><?php echo e($customer['customer_email']); ?></p>
          <details<?php echo $open === 'details' ? ' open' : ''; ?>>
            <summary><?php echo icon('edit'); ?> Edit</summary>
            <form class="form" action="profile.php#details" method="post" novalidate>
              <?php echo csrf_field(); ?>
              <input type="hidden" name="section" value="details">
              <div class="field">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" value="<?php echo e($form['name']); ?>" maxlength="50" autocomplete="name" required<?php echo field_attrs($errors, 'name'); ?>>
                <?php echo field_error($errors, 'name'); ?>
              </div>
              <div class="field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo e($form['email']); ?>" maxlength="100" autocomplete="email" required<?php echo field_attrs($errors, 'email'); ?>>
                <?php echo field_error($errors, 'email'); ?>
              </div>
              <button class="btn btn--sm" type="submit">Save</button>
            </form>
          </details>
        </div>

        <div class="detail">
          <h3>Delivery address</h3>
          <p><?php echo e($customer['customer_address']); ?></p>
          <details<?php echo $open === 'address' ? ' open' : ''; ?>>
            <summary><?php echo icon('edit'); ?> Edit</summary>
            <form class="form" action="profile.php#details" method="post" novalidate>
              <?php echo csrf_field(); ?>
              <input type="hidden" name="section" value="address">
              <div class="field">
                <label for="address">Address</label>
                <input type="text" id="address" name="address" value="<?php echo e($form['address']); ?>" maxlength="255" autocomplete="street-address" required<?php echo field_attrs($errors, 'address'); ?>>
                <?php echo field_error($errors, 'address'); ?>
              </div>
              <button class="btn btn--sm" type="submit">Save</button>
            </form>
          </details>
        </div>

        <div class="detail">
          <h3>Phone</h3>
          <p><?php echo e($customer['customer_phone']); ?></p>
          <details<?php echo $open === 'phone' ? ' open' : ''; ?>>
            <summary><?php echo icon('edit'); ?> Edit</summary>
            <form class="form" action="profile.php#details" method="post" novalidate>
              <?php echo csrf_field(); ?>
              <input type="hidden" name="section" value="phone">
              <div class="field">
                <label for="phone">Phone</label>
                <input type="tel" id="phone" name="phone" value="<?php echo e($form['phone']); ?>" placeholder="0772 123 456" autocomplete="tel" required<?php echo field_attrs($errors, 'phone'); ?>>
                <?php echo field_error($errors, 'phone'); ?>
              </div>
              <button class="btn btn--sm" type="submit">Save</button>
            </form>
          </details>
        </div>
      </div>
    </section>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
