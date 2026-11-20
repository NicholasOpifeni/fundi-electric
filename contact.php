<?php
/**
 * Contact page: the shop's address, phone, email and opening hours (set in
 * shop-config.php), a message form that saves to the email table, and a map.
 */
require_once __DIR__ . '/includes/init.php';

$values = array('name' => '', 'email' => '', 'subject' => '', 'message' => '');
$errors = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($values) as $field) {
        $values[$field] = post_text($field);
    }

    // "website" is a field people never see; only bots fill it in. They get the
    // normal thank-you so they don't adapt, but nothing is saved.
    if (post_text('website') !== '') {
        flash('Message sent. We usually reply within one working day.');
        redirect('contact.php');
    }

    $email = $values['email'];
    if ($email === '') {
        $emailError = 'Enter your email address so we can reply.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) {
        $emailError = 'Enter a valid email address, like name@example.com.';
    } else {
        $emailError = '';
    }

    $errors = array_filter(array(
        'name'    => name_error($values['name']),
        'email'   => $emailError,
        'subject' => text_length($values['subject']) > 50 ? 'Keep the subject under 50 characters.' : '',
        'message' => $values['message'] === '' ? 'Write your message.'
            : (text_length($values['message']) > 5000 ? 'Keep your message under 5,000 characters.' : ''),
    ));

    if (!$errors) {
        db_exec(
            $conn,
            'INSERT INTO email (name, email, subject, message) VALUES (?, ?, ?, ?)',
            'ssss',
            array($values['name'], $values['email'], $values['subject'], $values['message'])
        );
        flash('Message sent. We usually reply within one working day.');
        redirect('contact.php');
    }
} elseif ($customer) {
    $values['name']  = $customer['customer_fname'];
    $values['email'] = $customer['customer_email'];
}

$phoneLink = preg_replace('/[^\d+]/', '', $SHOP_CONTACT['phone']);
$mapUrl    = 'https://maps.google.com/maps?q=' . rawurlencode($SHOP_CONTACT['map']) . '&z=14&output=embed';

$pageTitle = 'Contact';
include __DIR__ . '/includes/headerNav.php';
?>

<header class="page-head">
  <p class="eyebrow">Contact</p>
  <h1 class="display">Visit <em>us</em></h1>
  <p class="muted">Questions about an order, a product or a repair? Drop by the shop or send us a message.</p>
</header>

<div class="contact-grid">
  <div class="contact-cards">
    <div class="contact-card">
      <span class="value__icon"><?php echo icon('pin'); ?></span>
      <div>
        <h3>Shop address</h3>
        <p><?php echo implode('<br>', array_map('e', $SHOP_CONTACT['address'])); ?></p>
      </div>
    </div>
    <div class="contact-card">
      <span class="value__icon"><?php echo icon('phone'); ?></span>
      <div>
        <h3>Call or WhatsApp</h3>
        <p><a href="tel:<?php echo e($phoneLink); ?>"><?php echo e($SHOP_CONTACT['phone']); ?></a></p>
      </div>
    </div>
    <div class="contact-card">
      <span class="value__icon"><?php echo icon('mail'); ?></span>
      <div>
        <h3>Email</h3>
        <p><a href="mailto:<?php echo e($SHOP_CONTACT['email']); ?>"><?php echo e($SHOP_CONTACT['email']); ?></a></p>
      </div>
    </div>
    <div class="contact-card">
      <span class="value__icon"><?php echo icon('clock'); ?></span>
      <div>
        <h3>Opening hours</h3>
        <p><?php echo implode('<br>', array_map('e', $SHOP_CONTACT['hours'])); ?></p>
      </div>
    </div>
  </div>

  <section class="panel">
    <h2 class="panel__title">Send us a <em>message</em></h2>
    <form class="form" action="contact.php" method="post" novalidate>
      <?php echo csrf_field(); ?>
      <?php if ($errors) { ?><div class="alert" role="alert">Please check the highlighted fields.</div><?php } ?>

      <div class="form-row">
        <div class="field">
          <label for="name">Name</label>
          <input type="text" id="name" name="name" value="<?php echo e($values['name']); ?>" maxlength="50" autocomplete="name" required<?php echo field_attrs($errors, 'name'); ?>>
          <?php echo field_error($errors, 'name'); ?>
        </div>
        <div class="field">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" value="<?php echo e($values['email']); ?>" maxlength="120" autocomplete="email" required<?php echo field_attrs($errors, 'email'); ?>>
          <?php echo field_error($errors, 'email'); ?>
        </div>
      </div>
      <div class="field">
        <label for="subject">Subject <span class="muted">(optional)</span></label>
        <input type="text" id="subject" name="subject" value="<?php echo e($values['subject']); ?>" maxlength="50"<?php echo field_attrs($errors, 'subject'); ?>>
        <?php echo field_error($errors, 'subject'); ?>
      </div>
      <div class="field">
        <label for="message">Message</label>
        <textarea id="message" name="message" rows="6" maxlength="5000" required<?php echo field_attrs($errors, 'message'); ?>><?php echo e($values['message']); ?></textarea>
        <?php echo field_error($errors, 'message'); ?>
      </div>
      <?php // Honeypot: hidden from people and screen readers, so only bots fill it in. ?>
      <div class="field field--trap" aria-hidden="true">
        <label for="website">Leave this empty</label>
        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
      </div>
      <button class="btn btn--lg" type="submit">Send message <?php echo icon('arrow'); ?></button>
    </form>
  </section>
</div>

<iframe class="map" title="Map showing <?php echo e($SHOP_CONTACT['map']); ?>" src="<?php echo e($mapUrl); ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>

<?php include __DIR__ . '/includes/footer.php'; ?>
