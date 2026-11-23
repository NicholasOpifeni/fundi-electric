<?php
/**
 * A small Stripe API client: just what checkout needs, over cURL, without
 * the Stripe PHP library.
 *
 * The secret key lives in includes/secrets.php, which is git-ignored (copy
 * includes/secrets.example.php to create it). Without a key, checkout tells
 * customers card payments aren't available instead of failing.
 */
if (is_file(__DIR__ . '/secrets.php')) {
    require_once __DIR__ . '/secrets.php';
}

// secrets.php may point this at a local mock of the API for testing.
if (!defined('STRIPE_API_BASE')) {
    define('STRIPE_API_BASE', 'https://api.stripe.com/v1/');
}

/** True once a secret key (not the example placeholder) is set in secrets.php. */
function stripe_configured()
{
    return defined('STRIPE_SECRET_KEY')
        && preg_match('/^(sk|rk)_(test|live)_[A-Za-z0-9]{16,}$/', STRIPE_SECRET_KEY)
        && STRIPE_SECRET_KEY !== 'sk_test_replace_with_your_key';
}

/** True once a webhook signing secret (not the example placeholder) is set in secrets.php. */
function stripe_webhook_configured()
{
    return defined('STRIPE_WEBHOOK_SECRET')
        && strpos(STRIPE_WEBHOOK_SECRET, 'whsec_') === 0
        && STRIPE_WEBHOOK_SECRET !== 'whsec_replace_with_your_secret';
}

/**
 * Check a webhook's Stripe-Signature header and return the event, or null.
 *
 * Stripe signs "timestamp.payload" with the endpoint's signing secret
 * (HMAC-SHA256) and sends t=<timestamp>,v1=<signature>. Events more than five
 * minutes old are refused, so a captured one can't be replayed later.
 * https://docs.stripe.com/webhooks#verify-manually
 */
function stripe_webhook_event($payload, $header, $secret, $tolerance = 300)
{
    $timestamp  = 0;
    $signatures = array();
    foreach (explode(',', (string) $header) as $part) {
        $pair = explode('=', trim($part), 2);
        if (count($pair) !== 2) {
            continue;
        }
        if ($pair[0] === 't') {
            $timestamp = (int) $pair[1];
        } elseif ($pair[0] === 'v1') {
            $signatures[] = $pair[1];
        }
    }
    if ($timestamp === 0 || !$signatures || abs(time() - $timestamp) > $tolerance) {
        return null;
    }

    $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    foreach ($signatures as $signature) {
        if (hash_equals($expected, $signature)) {
            $event = json_decode($payload, true);
            return is_array($event) && isset($event['type'], $event['data']['object']) ? $event : null;
        }
    }
    return null;
}

/**
 * An amount in BASE_CURRENCY in the units Stripe's API expects.
 *
 * Most currencies go in cents (× 100) and true zero-decimal ones such as
 * RWF or JPY as they are. UGX is a special case: Stripe treats it as having
 * no minor unit, yet its API still takes UGX as two decimals that are always
 * 00, so UGX 4,200,000 is sent as 420000000.
 * https://docs.stripe.com/currencies#special-cases
 */
function stripe_amount($amount)
{
    $zeroDecimal = array('BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'VND', 'VUV', 'XAF', 'XOF', 'XPF');
    return (int) round($amount) * (in_array(BASE_CURRENCY, $zeroDecimal, true) ? 1 : 100);
}

/**
 * Call the Stripe API, e.g. stripe_request('POST', 'checkout/sessions', $params).
 *
 * Returns array($data, $error): $data is the decoded reply and $error is ''
 * on success, otherwise a message for the PHP error log. An idempotency key
 * makes a retried request return the original result rather than creating
 * a second object. The cURL handle is freed when it goes out of scope
 * (curl_close() is deprecated as of PHP 8.5).
 */
function stripe_request($method, $path, $params = array(), $idempotencyKey = '')
{
    if (!stripe_configured()) {
        return array(null, 'No Stripe key is set in includes/secrets.php.');
    }

    $url     = STRIPE_API_BASE . $path;
    $headers = array('Authorization: Bearer ' . STRIPE_SECRET_KEY);
    if ($idempotencyKey !== '') {
        $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
    }
    if ($method !== 'POST' && $params) {
        $url .= '?' . http_build_query($params);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_USERAGENT      => 'FundiElectric/1.0',
    ));
    if ($method === 'POST') {
        // Nested arrays become Stripe's bracket form: line_items[0][quantity]=1
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    }

    $body   = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($body === false) {
        return array(null, 'Could not reach Stripe: ' . curl_error($ch));
    }

    $data = json_decode($body, true);
    if (!is_array($data)) {
        return array(null, 'Stripe sent a reply that isn’t JSON (HTTP ' . $status . ').');
    }
    if ($status >= 400) {
        $message = isset($data['error']['message']) ? $data['error']['message'] : 'no message';
        return array($data, 'Stripe returned HTTP ' . $status . ': ' . $message);
    }
    return array($data, '');
}
