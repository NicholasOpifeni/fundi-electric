<?php
/**
 * The customer's account page: details, order history with repair serials,
 * and repair history.
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
    } elseif ($section === 'password') {
        $current = isset($_POST['current_password']) && is_string($_POST['current_password']) ? $_POST['current_password'] : '';
        $new     = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
        $repeat  = isset($_POST['password_repeat']) && is_string($_POST['password_repeat']) ? $_POST['password_repeat'] : '';
        $stored  = db_one($conn, 'SELECT customer_pwd FROM customer WHERE customer_id = ?', 'i', array($uid));

        if (!password_verify($current, (string) $stored['customer_pwd'])) {
            $errors = array('current_password' => 'That isn’t your current password.');
        } else {
            $errors = new_password_errors($new, $repeat);
        }
        if (!$errors) {
            db_exec($conn, 'UPDATE customer SET customer_pwd = ? WHERE customer_id = ?', 'si', array(password_hash($new, PASSWORD_DEFAULT), $uid));
            // As at login, give this browser a fresh session ID once the password changes.
            session_regenerate_id(true);
        }
    } else {
        redirect('profile.php');
    }

    if (!$errors) {
        flash($section === 'password' ? 'Your password was changed.' : 'Your details were saved.');
        redirect('profile.php#details');
    }
    $open = $section;
}

$isAdmin = $customer['customer_role'] === 'admin';

// Each order with the latest repair serial issued for that product to this customer.
$orders = db_all(
    $conn,
    'SELECT s.sid, s.pid, s.quantity, s.price, s.date, s.status, p.product_title, p.product_img,
            (SELECT ss.uuid FROM servicestatus ss WHERE ss.uid = s.uid AND ss.pid = s.pid ORDER BY ss.sid DESC LIMIT 1) AS serial,
            (SELECT ss.status FROM servicestatus ss WHERE ss.uid = s.uid AND ss.pid = s.pid ORDER BY ss.sid DESC LIMIT 1) AS serial_status
       FROM soldproducts s LEFT JOIN products p ON p.product_id = s.pid
      WHERE s.uid = ?
      ORDER BY s.sid DESC',
    'i',
    array($uid)
);

$repairs = db_all($conn, 'SELECT * FROM repair WHERE user_id = ? ORDER BY id DESC', 'i', array($uid));

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

        <div class="detail">
          <h3>Password</h3>
          <p><span aria-hidden="true">••••••••</span><span class="sr-only">Hidden</span></p>
          <details<?php echo $open === 'password' ? ' open' : ''; ?>>
            <summary><?php echo icon('edit'); ?> Change</summary>
            <form class="form" action="profile.php#details" method="post" novalidate>
              <?php echo csrf_field(); ?>
              <input type="hidden" name="section" value="password">
              <div class="field">
                <label for="current_password">Current password</label>
                <input type="password" id="current_password" name="current_password" autocomplete="current-password" required<?php echo field_attrs($errors, 'current_password'); ?>>
                <?php echo field_error($errors, 'current_password'); ?>
              </div>
              <div class="field">
                <label for="password">New password</label>
                <input type="password" id="password" name="password" minlength="8" autocomplete="new-password" required<?php echo field_attrs($errors, 'password'); ?>>
                <?php echo isset($errors['password']) ? field_error($errors, 'password') : '<p class="hint">At least 8 characters.</p>'; ?>
              </div>
              <div class="field">
                <label for="password_repeat">Repeat new password</label>
                <input type="password" id="password_repeat" name="password_repeat" autocomplete="new-password" required<?php echo field_attrs($errors, 'password_repeat'); ?>>
                <?php echo field_error($errors, 'password_repeat'); ?>
              </div>
              <button class="btn btn--sm" type="submit">Change password</button>
            </form>
          </details>
        </div>
      </div>
    </section>

    <section class="account-section" id="orders">
      <div class="account-section__head">
        <h2>Order <em>history</em></h2>
      </div>

      <?php if ($orders) { ?>
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th><span class="sr-only">Photo</span></th><th>Product</th><th>Price</th><th>Qty</th>
                <th>Repair serial</th><th>Date</th><th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($orders as $order) {
                  $thumb = $order['product_img'] !== null ? product_image($order['product_img']) : ''; ?>
                <tr>
                  <td>
                    <div class="thumb <?php echo $order['product_img'] !== null ? media_class($order['product_img']) : 'media--empty'; ?>">
                      <?php if ($thumb !== '') { ?><img src="<?php echo e($thumb); ?>" alt=""><?php } ?>
                    </div>
                  </td>
                  <td>
                    <?php if ($order['product_title'] !== null) { ?>
                      <a href="product.php?id=<?php echo (int) $order['pid']; ?>"><strong><?php echo e(trim($order['product_title'])); ?></strong></a>
                    <?php } else { ?>
                      <strong>Discontinued product</strong>
                    <?php } ?>
                  </td>
                  <td><?php echo e(base_money($order['price'])); ?></td>
                  <td><?php echo (int) $order['quantity']; ?></td>
                  <td class="mono">
                    <?php if ($order['serial'] === null) { ?>
                      —
                    <?php } elseif ($order['serial_status'] !== 'pending') { ?>
                      Used
                    <?php } else { ?>
                      <?php echo e($order['serial']); ?>
                    <?php } ?>
                  </td>
                  <td><?php echo e($order['date']); ?></td>
                  <td><span class="pill pill--<?php echo e($order['status']); ?>"><?php echo e(ucfirst($order['status'])); ?></span></td>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      <?php } else { ?>
        <div class="empty">No orders yet. <a class="link-arrow" href="index.php#shop">Start shopping <?php echo icon('arrow'); ?></a></div>
      <?php } ?>
    </section>

    <section class="account-section" id="repairs">
      <div class="account-section__head">
        <h2>Repair <em>history</em></h2>
        <a class="link-arrow" href="repair.php">Book a repair <?php echo icon('arrow'); ?></a>
      </div>

      <?php if ($repairs) { ?>
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th>Device</th><th>Category</th><th>Issue</th><th>Advance</th><th>Serial</th>
                <th>Still due</th><th>Booked</th><th>Status</th><th>Ready</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($repairs as $repair) { ?>
                <tr>
                  <td><strong><?php echo e($repair['p_name']); ?></strong></td>
                  <td><?php echo e(ucfirst($repair['category'])); ?></td>
                  <td><?php echo e(ucfirst($repair['damage_type'])); ?></td>
                  <td><?php echo e(base_money($repair['advance_amt'])); ?></td>
                  <td class="mono"><?php echo $repair['uuid'] ? e($repair['uuid']) : '—'; ?></td>
                  <td><?php echo $repair['due'] !== null ? e(base_money($repair['due'])) : '—'; ?></td>
                  <td><?php echo e($repair['booked_date']); ?></td>
                  <td><span class="pill pill--<?php echo e($repair['status']); ?>"><?php echo e(ucfirst($repair['status'])); ?></span></td>
                  <td><?php echo $repair['return_date'] ? e($repair['return_date']) : '—'; ?></td>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      <?php } else { ?>
        <div class="empty">No repair requests yet.</div>
      <?php } ?>
    </section>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
