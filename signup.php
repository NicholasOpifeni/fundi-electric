<?php
/**
 * Create an account.
 *
 * The form posts back to this page. Problems are shown under the field they
 * belong to and everything typed is kept except the passwords. Passwords are
 * stored with password_hash(); the new customer is logged in straight away.
 */
require_once __DIR__ . '/includes/init.php';

$next = requested_next();
if (current_user_id()) {
    redirect($next !== '' ? $next : 'profile.php');
}

$values = array('name' => '', 'email' => '', 'phone' => '', 'address' => '');
$errors = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($values) as $field) {
        $values[$field] = post_text($field);
    }
    // Passwords are taken exactly as typed: no trimming.
    $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
    $repeat   = isset($_POST['password_repeat']) && is_string($_POST['password_repeat']) ? $_POST['password_repeat'] : '';
    $phone    = normalise_phone($values['phone']);

    if ($values['name'] === '') {
        $errors['name'] = 'Enter your name.';
    } elseif (text_length($values['name']) > 50) {
        $errors['name'] = 'Keep your name under 50 characters.';
    }

    if ($values['email'] === '') {
        $errors['email'] = 'Enter your email address.';
    } elseif (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || strlen($values['email']) > 100) {
        $errors['email'] = 'Enter a valid email address, like name@example.com.';
    } elseif (db_one($conn, 'SELECT customer_id FROM customer WHERE customer_email = ?', 's', array($values['email']))) {
        $errors['email'] = 'An account with this email already exists. Log in instead.';
    }

    if ($values['phone'] === '') {
        $errors['phone'] = 'Enter your phone number.';
    } elseif ($phone === '') {
        $errors['phone'] = 'Enter a Ugandan number, like 0772 123 456 or +256 772 123 456.';
    }

    if ($values['address'] === '') {
        $errors['address'] = 'Enter the address we should deliver to.';
    } elseif (text_length($values['address']) > 255) {
        $errors['address'] = 'Keep the address under 255 characters.';
    }

    // bcrypt only reads the first 72 bytes, so longer passwords are refused
    // rather than silently shortened.
    if (strlen($password) < 8) {
        $errors['password'] = 'Use at least 8 characters.';
    } elseif (strlen($password) > 72) {
        $errors['password'] = 'Use 72 characters or fewer.';
    } elseif ($repeat !== $password) {
        $errors['password_repeat'] = 'The two passwords don’t match.';
    }

    if (!$errors) {
        db_exec(
            $conn,
            'INSERT INTO customer (customer_fname, customer_email, customer_pwd, customer_phone, customer_address)
             VALUES (?, ?, ?, ?, ?)',
            'sssss',
            array($values['name'], $values['email'], password_hash($password, PASSWORD_DEFAULT), $phone, $values['address'])
        );
        log_in_customer($conn->insert_id);
        flash('Welcome, ' . strtok($values['name'], ' ') . '! Your account is ready.');
        redirect($next !== '' ? $next : 'index.php');
    }
}

$pageTitle = 'Create an account';
include __DIR__ . '/includes/headerNav.php';
?>

<section class="auth">
  <div class="auth__visual">
    <h2>Join the <em>shop</em></h2>
    <ul>
      <li><?php echo icon('check'); ?> Your cart is saved between visits</li>
      <li><?php echo icon('check'); ?> 50% off repairs, or a free repair on premium devices</li>
      <li><?php echo icon('check'); ?> Secure card checkout with Stripe</li>
    </ul>
  </div>

  <div class="auth__form">
    <div class="auth__inner">
      <p class="eyebrow">New account</p>
      <h1 class="display">Sign <em>up</em></h1>
      <p class="muted">It takes less than a minute.</p>

      <form class="form" action="signup.php" method="post" novalidate>
        <?php echo csrf_field(); ?>
        <?php if ($next !== '') { ?><input type="hidden" name="next" value="<?php echo e($next); ?>"><?php } ?>
        <?php if ($errors) { ?>
          <div class="alert" role="alert">Please check the highlighted fields.</div>
        <?php } ?>

        <div class="field">
          <label for="name">Full name</label>
          <input type="text" id="name" name="name" value="<?php echo e($values['name']); ?>" maxlength="50" autocomplete="name" required<?php echo field_attrs($errors, 'name'); ?>>
          <?php echo field_error($errors, 'name'); ?>
        </div>
        <div class="form-row">
          <div class="field">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?php echo e($values['email']); ?>" maxlength="100" autocomplete="email" required<?php echo field_attrs($errors, 'email'); ?>>
            <?php echo field_error($errors, 'email'); ?>
          </div>
          <div class="field">
            <label for="phone">Phone</label>
            <input type="tel" id="phone" name="phone" value="<?php echo e($values['phone']); ?>" placeholder="0772 123 456" autocomplete="tel" required<?php echo field_attrs($errors, 'phone'); ?>>
            <?php echo field_error($errors, 'phone'); ?>
          </div>
        </div>
        <div class="field">
          <label for="address">Delivery address</label>
          <input type="text" id="address" name="address" value="<?php echo e($values['address']); ?>" maxlength="255" placeholder="Plot, street, area and town" autocomplete="street-address" required<?php echo field_attrs($errors, 'address'); ?>>
          <?php echo field_error($errors, 'address'); ?>
        </div>
        <div class="form-row">
          <div class="field">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" minlength="8" autocomplete="new-password" required<?php echo field_attrs($errors, 'password'); ?>>
            <?php echo isset($errors['password']) ? field_error($errors, 'password') : '<p class="hint">At least 8 characters.</p>'; ?>
          </div>
          <div class="field">
            <label for="password_repeat">Repeat password</label>
            <input type="password" id="password_repeat" name="password_repeat" autocomplete="new-password" required<?php echo field_attrs($errors, 'password_repeat'); ?>>
            <?php echo field_error($errors, 'password_repeat'); ?>
          </div>
        </div>
        <button class="btn btn--lg btn--block" type="submit">Create account <?php echo icon('arrow'); ?></button>
      </form>

      <p class="auth__switch">Already have an account? <a href="login.php<?php echo $next !== '' ? '?next=' . e(urlencode($next)) : ''; ?>">Log in</a></p>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
