# Database migrations

`database/fundi_electric.sql` always creates the latest schema, so a fresh
install only needs that file.

An existing database needs each numbered file here that it hasn't had yet,
run once, in order. With MAMP's MySQL client:

```sh
/Applications/MAMP/Library/bin/mysql80/bin/mysql -u root -p \
  -S /Applications/MAMP/tmp/mysql/mysql.sock fundi_electric \
  < database/migrations/001_hash_starter_admin_password.sql
```

Or open the database in phpMyAdmin, go to **Import** and choose the file.

| File | What it changes |
| --- | --- |
| `001_hash_starter_admin_password.sql` | Stores the starter admin's password as a bcrypt hash |
| `002_add_payments_table.sql` | Adds the `payments` table for Stripe Checkout |
| `003_link_orders_to_payments.sql` | Links orders and repair serials to their payment (run once) |
