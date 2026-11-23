<?php
/**
 * Private keys. Copy this file to includes/secrets.php and fill it in.
 * secrets.php is git-ignored, so keys never end up in the repository.
 *
 * Use your Stripe test key (sk_test_...) until the shop goes live:
 * Stripe Dashboard → Developers → API keys, with "Test mode" switched on.
 * Test payments use card 4242 4242 4242 4242 with any future date and CVC.
 */
define('STRIPE_SECRET_KEY', 'sk_test_replace_with_your_key');
