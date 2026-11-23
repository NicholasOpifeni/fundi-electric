<?php
/**
 * Prices in the visitor's own currency.
 *
 * Every price is stored and charged in BASE_CURRENCY (UGX). Visitors from
 * elsewhere see an estimate in their own currency so they can judge a price,
 * but payment is always taken in shillings: Stripe charges the UGX amount,
 * and exchange rates can move between browsing and paying. That's why the
 * cart and checkout also state the exact UGX amount that will be charged.
 *
 * The display currency is, in order:
 *   1. one the visitor chose in the footer switcher (a cookie)
 *   2. their country's, from Cloudflare's CF-IPCountry header if present
 *   3. their country's, from an IP lookup (public addresses only)
 *   4. BASE_CURRENCY
 * The country is looked up once per session.
 */

/**
 * Fetch a URL with a short timeout and return the body, or null on failure.
 * The cURL handle is freed when $ch goes out of scope; curl_close() is a
 * no-op since PHP 8.0 and deprecated in 8.5, so it isn't called.
 */
function http_get($url, $timeout = 3)
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_USERAGENT      => 'FundiElectric/1.0',
        ));
        $body = curl_exec($ch);
        $ok   = $body !== false && curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
        return $ok ? $body : null;
    }
    $context = stream_context_create(array('http' => array('timeout' => $timeout)));
    $body    = @file_get_contents($url, false, $context);
    return $body === false ? null : $body;
}

/**
 * The exchange-rate cache: rates from BASE_CURRENCY to every other currency,
 * when the provider last updated them, and when to ask again. Kept in
 * cache/rates.json so the API is called at most every RATES_CACHE_SECONDS.
 * If the API is down, the last good rates are kept and it is tried again in
 * 15 minutes, so a failing API never slows down every page view.
 */
function rates_cache()
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $file  = __DIR__ . '/../cache/rates.json';
    $saved = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
    if (is_array($saved) && isset($saved['rates'], $saved['updated'], $saved['next_check']) && time() < $saved['next_check']) {
        return $cache = $saved;
    }

    $data  = json_decode((string) http_get(RATES_API_URL), true);
    $rates = array();
    if (is_array($data) && isset($data['result'], $data['rates']) && $data['result'] === 'success') {
        foreach ($data['rates'] as $code => $rate) {
            if (preg_match('/^[A-Z]{3}$/', $code) && is_numeric($rate) && $rate > 0) {
                $rates[$code] = (float) $rate;
            }
        }
    }

    if ($rates) {
        $updated = isset($data['time_last_update_unix']) ? (int) $data['time_last_update_unix'] : time();
        $cache   = array('rates' => $rates, 'updated' => $updated, 'next_check' => time() + RATES_CACHE_SECONDS);
    } else {
        $hasSaved = is_array($saved) && !empty($saved['rates']);
        $cache    = array(
            'rates'      => $hasSaved ? $saved['rates'] : array(BASE_CURRENCY => 1.0),
            'updated'    => $hasSaved ? (int) $saved['updated'] : 0,
            'next_check' => time() + 15 * 60,
        );
    }

    // Write to a temporary file and rename it, so a reader never sees half a file.
    if (is_dir(dirname($file)) || @mkdir(dirname($file), 0775, true)) {
        $tmp = $file . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, json_encode($cache)) !== false) {
            @rename($tmp, $file);
        }
    }
    return $cache;
}

/** Rates from BASE_CURRENCY, e.g. array('UGX' => 1, 'KES' => 0.031, ...). */
function exchange_rates()
{
    $cache = rates_cache();
    return $cache['rates'];
}

/** When the provider last updated the rates (Unix time), or 0 if no rates are available. */
function exchange_rates_updated()
{
    $cache = rates_cache();
    return (int) $cache['updated'];
}

/** Is there a rate for this currency code? */
function currency_supported($code)
{
    $rates = exchange_rates();
    return is_string($code) && isset($rates[$code]);
}

/** The currency of a country (ISO 3166 code such as "KE"), or null if unknown. */
function currency_for_country($country)
{
    $country = strtoupper(trim((string) $country));
    if (!preg_match('/^[A-Z]{2}$/', $country)) {
        return null;
    }

    // The intl extension knows every country's currency. For a code it doesn't
    // recognise it returns a placeholder or metal code (XXX, XAG...) instead.
    if (class_exists('NumberFormatter')) {
        $formatter = new NumberFormatter('en_' . $country, NumberFormatter::CURRENCY);
        $code      = $formatter->getTextAttribute(NumberFormatter::CURRENCY_CODE);
        if ($code && !in_array($code, array('XXX', 'XAG', 'XAU', 'XPD', 'XPT', 'XDR', 'XTS'), true)) {
            return $code;
        }
    }

    // Without intl: East Africa and the countries visitors most often come from.
    $map = array(
        'UG' => 'UGX', 'KE' => 'KES', 'TZ' => 'TZS', 'RW' => 'RWF', 'BI' => 'BIF', 'SS' => 'SSP',
        'CD' => 'CDF', 'ET' => 'ETB', 'SO' => 'SOS', 'NG' => 'NGN', 'GH' => 'GHS', 'ZA' => 'ZAR',
        'ZM' => 'ZMW', 'MW' => 'MWK', 'US' => 'USD', 'CA' => 'CAD', 'GB' => 'GBP', 'IN' => 'INR',
        'CN' => 'CNY', 'AE' => 'AED', 'AU' => 'AUD',
    );
    $euro = array('AT', 'BE', 'DE', 'ES', 'FI', 'FR', 'GR', 'IE', 'IT', 'NL', 'PT');
    if (in_array($country, $euro, true)) {
        return 'EUR';
    }
    return isset($map[$country]) ? $map[$country] : null;
}

/** The visitor's country, or null when it can't be told (e.g. on localhost). */
function visitor_country()
{
    $cloudflare = isset($_SERVER['HTTP_CF_IPCOUNTRY']) ? strtoupper((string) $_SERVER['HTTP_CF_IPCOUNTRY']) : '';
    if (preg_match('/^[A-Z]{2}$/', $cloudflare) && !in_array($cloudflare, array('XX', 'T1'), true)) {
        return $cloudflare;
    }

    // Private and local addresses (MAMP on localhost) can't be located.
    $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return null;
    }
    $data = json_decode((string) http_get(str_replace('{ip}', rawurlencode($ip), GEO_API_URL), 2), true);
    return is_array($data) && !empty($data['success']) && !empty($data['country_code']) ? $data['country_code'] : null;
}

/** The currency this visitor would get without choosing one (labels "Automatic" in the switcher). */
function detected_currency()
{
    // '' records "couldn't tell", so a failed lookup isn't retried on every page.
    if (!isset($_SESSION['geo-currency'])) {
        $_SESSION['geo-currency'] = (string) currency_for_country(visitor_country());
    }
    return currency_supported($_SESSION['geo-currency']) ? $_SESSION['geo-currency'] : BASE_CURRENCY;
}

/** The currency the visitor chose in the switcher, or null for automatic. */
function chosen_currency()
{
    $code = isset($_COOKIE['currency']) ? (string) $_COOKIE['currency'] : '';
    return currency_supported($code) ? $code : null;
}

/** The currency prices are shown in for this visitor. */
function display_currency()
{
    $chosen = chosen_currency();
    return $chosen !== null ? $chosen : detected_currency();
}

/** True when prices on screen are estimates in another currency. */
function showing_converted_prices()
{
    return display_currency() !== BASE_CURRENCY;
}

/**
 * Format an amount already in $currency, e.g. "KES 130,083" or "USD 2.42".
 * Currencies without a minor unit never show decimals; others show cents only
 * on small amounts, where they matter.
 */
function format_money($amount, $currency)
{
    $noDecimals = array('UGX', 'RWF', 'BIF', 'JPY', 'KRW', 'VND', 'XAF', 'XOF', 'CLP', 'PYG');
    $decimals   = (in_array($currency, $noDecimals, true) || abs($amount) >= 100) ? 0 : 2;
    return $currency . ' ' . number_format((float) $amount, $decimals);
}

/** A BASE_CURRENCY amount shown in the visitor's currency. Display only: never charge this figure. */
function money($amount)
{
    $currency = display_currency();
    $rates    = exchange_rates();
    return format_money((float) $amount * $rates[$currency], $currency);
}
