-- 002: Add the payments table used by Stripe Checkout.
--
-- One row per card payment started with Stripe Checkout. `items` is a JSON
-- snapshot of what is being paid for; `amount` is the total in UGX. Orders
-- are only recorded once Stripe confirms the payment and the row turns 'paid'.
-- Safe to run twice: the table is only created if it doesn't exist.

CREATE TABLE IF NOT EXISTS `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` int(11) NOT NULL,
  `type` enum('cart','product','repair') NOT NULL,
  `items` text NOT NULL,
  `amount` int(11) NOT NULL,
  `status` enum('pending','paid','cancelled') NOT NULL DEFAULT 'pending',
  `stripe_session` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `paid_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `stripe_session` (`stripe_session`),
  KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
