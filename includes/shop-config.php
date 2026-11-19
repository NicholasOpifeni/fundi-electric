<?php
/**
 * Store-wide settings: currency, delivery fee, repair pricing and time zone.
 * Change prices and fees here rather than in the pages that use them.
 */

/** Time zone used for order and repair dates. */
define('SHOP_TIMEZONE', 'Africa/Kampala');

/* Money ------------------------------------------------------------------- */

/**
 * Every price in the database is stored in this currency and every payment is
 * taken in it. Shillings have no smaller unit, so prices are whole numbers.
 */
define('BASE_CURRENCY', 'UGX');

/** Flat delivery fee added once to every order, in BASE_CURRENCY. */
define('SHIPPING_FEE', 10000);

/** Products priced above this earn one free repair; cheaper ones earn 50% off. */
define('FREE_REPAIR_THRESHOLD', 1500000);

/* Social links ------------------------------------------------------------ */

/**
 * Full URLs of the shop's social pages, shown as icons in the footer.
 * Leave one empty to hide it; the "Follow us" column disappears when all are.
 */
$SHOP_SOCIAL = array(
    'facebook' => '',
    'x'        => '',
    'youtube'  => '',
);

/* Repair pricing ---------------------------------------------------------- */

/**
 * The advance a customer pays when booking a repair is
 *   category rate × damage percentage / 100
 * so a laptop with water damage is 100,000 × 40 / 100 = UGX 40,000.
 */
$REPAIR_CATEGORY_RATES = array(
    'mouse'    => 20000,
    'keyboard' => 30000,
    'mobile'   => 60000,
    'laptop'   => 100000,
);

$REPAIR_DAMAGE_RATES = array(
    'water damage'        => 40,
    'power surges'        => 60,
    'physical damage'     => 80,
    'software corruption' => 20,
    'virus & malware'     => 30,
);
