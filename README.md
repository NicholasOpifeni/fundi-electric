# Fundi Electric

*Fundi* is Swahili for a skilled craftsperson or technician. Fundi Electric is
an electronics shop for Uganda, built with plain PHP and MySQL. Customers browse
laptops, phones, keyboards and mice and pay by card through Stripe. Every item
they buy comes with a serial code that makes its repairs cheaper, or free, at
the shop's repair desk.

## Features

- A catalogue with search (including voice search in Chrome and Edge), category
  filters, new arrivals, best sellers and stock badges
- A cart and a Buy now option, both limited to what is in stock
- Card payments through Stripe Checkout; orders are only recorded once Stripe
  confirms the full amount was paid
- A repair serial for every item bought: 50% off a repair, or one free repair
  for products over UGX 1,500,000. Each serial works once, for that kind of device
- Online repair booking with published rates: paid advances by card, free
  repairs confirmed straight away
- Accounts with order and repair history, editable details and password change
- Prices shown in the visitor's own currency (detected from their country, or
  chosen in the footer), always charged in Ugandan shillings
- A contact form, and a layout that works on phones

## Requirements

- PHP 7.4 or newer, with the mysqli (mysqlnd) and cURL extensions
- MySQL 8 or MariaDB
- Apache with `.htaccess` support, which keeps `.git`, `database/`, `includes/`
  and `cache/` out of reach of browsers. MAMP and XAMPP both work locally.
- Optional: the intl extension (knows every country's currency; a shorter
  built-in list is used without it), gd (spots product photos on white
  backgrounds) and mbstring (counts accented characters correctly)

## Setting up

1. Put the project in your web server's document root. On MAMP that is
   **MAMP → Settings → Server → Document root**. Keep it out of folders that
   iCloud syncs (Desktop and Documents, if that's switched on): when the disk
   runs low, macOS moves their files to the cloud and git stops working.
2. In phpMyAdmin, create a database called `fundi_electric`, open it, go to
   **Import** and import `database/fundi_electric.sql`.
3. Check the connection details in `includes/config.php`. They are set for
   MAMP (host `127.0.0.1`, port `8889`, user and password `root`). XAMPP uses
   port `3306` and an empty password.
4. For card payments, copy `includes/secrets.example.php` to
   `includes/secrets.php` and paste in your Stripe test secret key (see
   [Card payments](#card-payments-with-stripe)). Without a key the shop still
   works, but checkout says card payments aren't available.
5. Make sure PHP can write to the project folder, so it can create `cache/`
   for exchange rates.
6. Open the shop (http://localhost:8888/ on MAMP) and log in as
   `owner@fundielectric.test` with the password `changeme`. Change that
   password straight away from **My account**.

Already have the database from an earlier version? Run the new files in
`database/migrations/` in order instead of importing the dump again; that
folder's README explains how.

On a live server, use a database user of its own with a strong password, and
keep those details out of the repository.

## Card payments with Stripe

1. In the [Stripe Dashboard](https://dashboard.stripe.com), switch on **Test
   mode** and copy the secret key (`sk_test_…`) from **Developers → API keys**
   into `includes/secrets.php`.
2. Pay with the test card `4242 4242 4242 4242`, any future expiry date and any
   CVC.
3. **Recommended:** add a webhook, so a payment is recorded even if the
   customer closes the tab before coming back from Stripe. In the Dashboard,
   send `checkout.session.completed` and
   `checkout.session.async_payment_succeeded` to
   `https://<your shop>/stripe-webhook.php`, then put its signing secret
   (`whsec_…`) in `secrets.php`. Locally, the
   [Stripe CLI](https://docs.stripe.com/stripe-cli) can forward events instead:

   ```sh
   stripe listen --forward-to localhost:8888/stripe-webhook.php
   ```

To go live, swap in your live key (`sk_live_…`), set up the webhook for the
live site and serve the shop over HTTPS.

Prices are stored and charged in UGX. Visitors from elsewhere see an estimate
in their own currency, and the cart and checkout state the exact UGX amount
they'll pay. Note that Stripe's API expects UGX amounts multiplied by 100
([Stripe's special cases](https://docs.stripe.com/currencies#special-cases));
`includes/stripe.php` takes care of that.

## Products and photos

There is no admin panel yet. Add and edit products in phpMyAdmin, in the
`products` table. Prices are whole Ugandan shillings. Repair pricing is set up
for the categories `laptop`, `mobile`, `keyboard` and `mouse`.

- **Product photos** go in `images/products/`, named as in each product's
  `product_img` column. Until a photo exists, the shop shows a tile with the
  category name. See `images/products/README.md`.
- **Site photos** (the home page banner, the login and sign-up pages, the
  repair page) go in `images/site/`. Until they exist, those spots show a green
  gradient. See `images/site/README.md` for names, sizes and what to shoot.

## Configuration

Shop-wide settings live in `includes/shop-config.php`:

| Setting | What it controls |
| --- | --- |
| `BASE_CURRENCY`, `SHIPPING_FEE` | The currency prices are stored and charged in, and the flat delivery fee |
| `FREE_REPAIR_THRESHOLD` | The price above which a purchase earns a free repair rather than 50% off |
| `$REPAIR_CATEGORY_RATES`, `$REPAIR_DAMAGE_RATES`, `MIN_REPAIR_ADVANCE` | Repair advance = rate × percentage ÷ 100, never less than the minimum card payment |
| `PRODUCTS_PER_PAGE`, `LOW_STOCK_THRESHOLD`, `MAX_CART_QUANTITY` | Catalogue pages, the "Only N left" badge, and the most of one product per order |
| `$SHOP_CONTACT` | Address, phone, email, opening hours and map on the contact page. **These are placeholders: replace them with the shop's real details.** |
| `$SHOP_SOCIAL` | Social links in the footer; each is hidden while empty |
| `$SITE_IMAGES` | Where the site photos are |
| `$SWITCHER_CURRENCIES`, `RATES_API_URL`, `GEO_API_URL` | The footer's currency list, and the free exchange-rate and IP-to-country services |
| `SHOP_TIMEZONE` | The time zone for order and repair dates |

The store name and footer text come from the `settings` table.

## How it fits together

- Every page starts with `require 'includes/init.php'`, which starts the
  session, connects to the database, loads the logged-in customer and turns
  away any POST without the session's CSRF token. Forms print `csrf_field()`.
- Queries go through the prepared-statement helpers in `includes/helpers.php`
  (`db_all`, `db_one`, `db_exec`). Everything printed is escaped with `e()`.
- Passwords are stored with `password_hash()`. Accounts from before that still
  work and are upgraded to a hash the next time they log in.
- **Checkout:** `checkout.php` works out the items and total from the database,
  saves a pending row in `payments` and sends the customer to Stripe. When the
  customer comes back, `checkout-success.php` asks Stripe whether the session
  was paid in full; `stripe-webhook.php` gets the same news from Stripe
  directly, checking Stripe's signature. Either one then calls
  `record_payment()` (`includes/orders.php`), which records the orders and
  repair serials, reduces stock and clears the cart in one transaction. Only
  the first call does anything, so a payment is never recorded twice.
- **Repairs:** `includes/repairs.php` prices quotes and checks serials. A
  serial is marked used in the same transaction that saves the booking, so it
  can't be used twice.
- **Currency:** `includes/currency.php` detects the visitor's currency and
  caches exchange rates in `cache/rates.json` for 12 hours.

| Path | What it is |
| --- | --- |
| `index.php`, `search.php`, `product.php` | Home page, search and product pages |
| `cart.php`, `cart-add.php`, `payment.php` | Cart, adding to it, and the Buy now page |
| `checkout.php`, `checkout-success.php`, `checkout-cancel.php`, `stripe-webhook.php` | Stripe payments |
| `repair.php` | Repair quotes and bookings |
| `signup.php`, `login.php`, `logout.php`, `profile.php` | Accounts |
| `contact.php`, `set-currency.php` | Contact page and the currency switcher |
| `includes/` | Settings, helpers, shared header and footer, and the payment, repair and currency code |
| `css/app.css`, `js/app.js` | All styles (design tokens at the top) and scripts |
| `database/` | The full database dump, and migrations for existing databases |

## Credits

Fundi Electric builds on [Electric Shop](https://github.com/Bhabishya-123/E-commerce)
by [Bhabishya-123](https://github.com/Bhabishya-123), a PHP and MySQL store with
Stripe checkout and a repair service; the database schema comes from that
project. The storefront design takes inspiration from the
[Homedine concept on Dribbble](https://dribbble.com/shots/26163743-Ecommerce-Website-Design-for-Crockery-Kitchenware-Brands).
