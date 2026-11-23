-- 003: Link orders and repair serials to the payment they came from.
--
-- Run once. Rows from before card payments have no payment, so they keep
-- payment_id NULL.

ALTER TABLE `soldproducts`
  ADD COLUMN `payment_id` int(11) DEFAULT NULL,
  ADD KEY `payment_id` (`payment_id`);

ALTER TABLE `servicestatus`
  ADD COLUMN `payment_id` int(11) DEFAULT NULL,
  ADD KEY `payment_id` (`payment_id`);
