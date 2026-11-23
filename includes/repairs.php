<?php
/**
 * Repair bookings: quotes, purchase serials and booking.
 *
 * The advance a customer pays is the category rate × the damage percentage
 * / 100 (both set in shop-config.php). A serial from one of their own orders
 * halves it, or makes the repair free for products priced over
 * FREE_REPAIR_THRESHOLD. The serial must be for the same kind of device.
 *
 * Each serial works once. book_repair() marks it used in the same database
 * transaction that saves the booking, and only if it is still unused, so two
 * bookings can never share a serial, even when both are sent at once.
 *
 * Loaded by repair.php, and by checkout once paid repairs are booked there.
 */

/** servicestatus.discount values, in the format the original shop stored them. */
const PERK_FREE_REPAIR = '(1)free repairing';
const PERK_HALF_PRICE  = '50% discount';

/**
 * Find a purchase serial the customer can use for this kind of repair.
 * Returns array('serial' => row) when it can be used, otherwise
 * array('error' => message for the form).
 */
function find_repair_serial($conn, $code, $customerId, $category)
{
    $serial = db_one(
        $conn,
        'SELECT ss.sid, ss.discount, ss.status, p.product_catag
           FROM servicestatus ss LEFT JOIN products p ON p.product_id = ss.pid
          WHERE ss.uuid = ? AND ss.uid = ?
          ORDER BY ss.sid DESC LIMIT 1',
        'si',
        array($code, (int) $customerId)
    );

    // Another customer's serial gets the same answer as a made-up one, so
    // serials can't be tested for by guessing.
    if (!$serial) {
        return array('error' => 'We couldn’t find that serial on your account. Copy it from Order history on your account page.');
    }
    if ($serial['status'] !== 'pending') {
        return array('error' => 'That serial has already been used for a repair.');
    }
    // A product deleted from the shop has no category; its serial still works.
    if ($serial['product_catag'] !== null && product_category($serial) !== $category) {
        return array('error' => 'That serial came with a ' . product_category($serial) . ', so it only works for '
            . product_category($serial) . ' repairs.');
    }
    return array('serial' => $serial);
}

/**
 * Check the repair form and work out the advance.
 *
 * $input holds product, category, damage and serial (optional). Returns
 * array($quote, $errors): the quote is null when there are errors.
 */
function build_repair_quote($conn, $input, $customerId)
{
    global $REPAIR_CATEGORY_RATES, $REPAIR_DAMAGE_RATES;

    $errors = array();
    if ($input['product'] === '') {
        $errors['product'] = 'Tell us which device needs fixing, e.g. Dell Inspiron 15.';
    } elseif (text_length($input['product']) > 50) {
        $errors['product'] = 'Keep the device name under 50 characters.';
    }
    if (!isset($REPAIR_CATEGORY_RATES[$input['category']])) {
        $errors['category'] = 'Choose the kind of device.';
    }
    if (!isset($REPAIR_DAMAGE_RATES[$input['damage']])) {
        $errors['damage'] = 'Choose what’s wrong with it.';
    }

    $perk = '';
    if ($input['serial'] !== '' && !isset($errors['category'])) {
        $found = text_length($input['serial']) > 100
            ? array('error' => 'That doesn’t look like one of our serials.')
            : find_repair_serial($conn, $input['serial'], $customerId, $input['category']);
        if (isset($found['error'])) {
            $errors['serial'] = $found['error'];
        } else {
            $perk = $found['serial']['discount'] === PERK_FREE_REPAIR ? 'free' : 'half';
        }
    }

    if ($errors) {
        return array(null, $errors);
    }

    $full = (int) round($REPAIR_CATEGORY_RATES[$input['category']] * $REPAIR_DAMAGE_RATES[$input['damage']] / 100);
    if ($perk === 'free') {
        $advance = 0;
    } elseif ($perk === 'half') {
        $advance = (int) round($full / 2);
    } else {
        $advance = $full;
    }

    // Card payments have a minimum (about USD 0.50 at Stripe), so a paid
    // advance is never less than MIN_REPAIR_ADVANCE. Free repairs stay free.
    $raised = $advance > 0 && $advance < MIN_REPAIR_ADVANCE;
    if ($raised) {
        $advance = MIN_REPAIR_ADVANCE;
    }

    return array(array(
        'product'  => $input['product'],
        'category' => $input['category'],
        'damage'   => $input['damage'],
        'serial'   => $input['serial'],
        'perk'     => $perk,
        'full'     => $full,
        'advance'  => $advance,
        'minimum'  => $raised,
    ), array());
}

/**
 * Save a repair booking from a quote, using up its serial if it has one.
 * Returns '' on success, or a message when the serial was used in the
 * meantime (then nothing is saved).
 */
function book_repair($conn, $customerId, $quote)
{
    $conn->begin_transaction();

    if ($quote['serial'] !== '') {
        // Only an unused serial is updated; InnoDB locks the row, so a second
        // booking racing this one finds it already used and changes nothing.
        $claimed = db_exec(
            $conn,
            "UPDATE servicestatus SET status = 'used'
              WHERE uuid = ? AND uid = ? AND status = 'pending'
              ORDER BY sid LIMIT 1",
            'si',
            array($quote['serial'], (int) $customerId)
        );
        if ($claimed !== 1) {
            $conn->rollback();
            return 'That serial has already been used for a repair, so this booking wasn’t made. Please get a new quote.';
        }
    }

    db_exec(
        $conn,
        'INSERT INTO repair (user_id, p_name, category, damage_type, uuid, advance_amt, booked_date)
         VALUES (?, ?, ?, ?, ?, ?, ?)',
        'issssis',
        array(
            (int) $customerId,
            $quote['product'],
            $quote['category'],
            $quote['damage'],
            $quote['serial'] !== '' ? $quote['serial'] : null,
            (int) $quote['advance'],
            date('j M Y'),
        )
    );

    $conn->commit();
    return '';
}
