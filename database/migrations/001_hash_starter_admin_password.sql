-- 001: Store the starter admin's password as a hash instead of plain text.
--
-- The password is still "changeme". Only the row that still holds the plain
-- text is touched, so running this twice, or after the owner has already
-- logged in (login.php upgrades plain-text passwords itself), changes nothing.

UPDATE `customer`
   SET `customer_pwd` = '$2y$10$4ZB4ICFSQcwXxaXuQ4jsiOMUvR/qZKQPn3kVQdv2nUUrM7dmxqkRG'
 WHERE `customer_email` = 'owner@fundielectric.test'
   AND `customer_pwd` = 'changeme';
