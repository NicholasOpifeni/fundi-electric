<?php
/**
 * Log in with email and password, then return to ?next= (if it is a page on
 * this site) or the account page.
 *
 * Passwords are checked with password_verify(). Accounts created before the
 * shop hashed passwords still hold plain text: that is accepted one last time,
 * compared in constant time, and replaced with a hash on the spot.
 */
require_once __DIR__ . '/includes/init.php';

/**
 * A hash of a random string nobody knows. Checking against it when the email
 * isn't registered makes a failed login take as long either way, so response
 * times don't reveal which emails have accounts.
 */
const UNKNOWN_EMAIL_HASH = '$2y$10$Dn/sdAI8U5X9zIJ5a8ssDeT0m6urZx88N0yFZJZLFQy9VctKyQOyi';

$next = requested_next();
if (current_user_id()) {
    redirect($next !== '' ? $next : 'profile.php');
}

$email = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = post_text('email');
    $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';

    if ($email === '' || $password === '') {
        $error = 'Enter your email and password.';
    } else {
        $user = db_one(
            $conn,
            'SELECT customer_id, customer_fname, customer_pwd FROM customer WHERE customer_email = ?',
            's',
            array($email)
        );

        $valid  = false;
        $rehash = false;
        if (!$user) {
            password_verify($password, UNKNOWN_EMAIL_HASH);
        } elseif (password_get_info($user['customer_pwd'])['algoName'] !== 'unknown') {
            $valid  = password_verify($password, $user['customer_pwd']);
            $rehash = $valid && password_needs_rehash($user['customer_pwd'], PASSWORD_DEFAULT);
        } else {
            // Plain text from before hashing: accept it once, then store a hash.
            $valid  = hash_equals((string) $user['customer_pwd'], $password);
            $rehash = $valid;
        }

        if ($valid) {
            if ($rehash) {
                db_exec(
                    $conn,
                    'UPDATE customer SET customer_pwd = ? WHERE customer_id = ?',
                    'si',
                    array(password_hash($password, PASSWORD_DEFAULT), (int) $user['customer_id'])
                );
            }
            log_in_customer($user['customer_id']);
            flash('Welcome back, ' . strtok($user['customer_fname'], ' ') . '!');
            redirect($next !== '' ? $next : 'profile.php');
        }

        // One message for both cases, so the form doesn't confirm which emails are registered.
        $error = 'That email and password don’t match an account. Please try again.';
    }
}

$pageTitle = 'Log in';
include __DIR__ . '/includes/headerNav.php';
?>

<section class="auth">
  <?php $photo = site_image('login'); ?>
  <div class="auth__visual<?php echo $photo !== '' ? ' auth__visual--photo' : ''; ?>">
    <?php if ($photo !== '') { ?><img class="auth__photo" src="<?php echo e($photo); ?>" alt=""><?php } ?>
    <h2>Good to see you <em>again</em></h2>
    <ul>
      <li><?php echo icon('check'); ?> Track your orders and deliveries</li>
      <li><?php echo icon('check'); ?> Keep your repair serial codes in one place</li>
      <li><?php echo icon('check'); ?> Check out faster with saved details</li>
    </ul>
  </div>

  <div class="auth__form">
    <div class="auth__inner">
      <p class="eyebrow">Account</p>
      <h1 class="display">Log <em>in</em></h1>
      <p class="muted">Use the email and password you registered with.</p>

      <form class="form" action="login.php" method="post" novalidate>
        <?php echo csrf_field(); ?>
        <?php if ($next !== '') { ?><input type="hidden" name="next" value="<?php echo e($next); ?>"><?php } ?>
        <?php if ($error !== '') { ?><div class="alert" role="alert"><?php echo e($error); ?></div><?php } ?>

        <div class="field">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" value="<?php echo e($email); ?>" autocomplete="email" required>
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" autocomplete="current-password" required>
        </div>
        <button class="btn btn--lg btn--block" type="submit">Log in <?php echo icon('arrow'); ?></button>
      </form>

      <p class="auth__switch">New here? <a href="signup.php<?php echo $next !== '' ? '?next=' . e(urlencode($next)) : ''; ?>">Create an account</a></p>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
