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

/**
 * Optional but recommended: the signing secret (whsec_...) of the webhook
 * that sends checkout.session.completed and
 * checkout.session.async_payment_succeeded to /stripe-webhook.php. It lets
 * the shop record a payment even if the customer closes the tab before
 * coming back from Stripe. On MAMP, `stripe listen --forward-to
 * localhost:8888/stripe-webhook.php` (Stripe CLI) prints a secret to use.
 */
define('STRIPE_WEBHOOK_SECRET', 'whsec_replace_with_your_secret');
