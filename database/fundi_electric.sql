SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- Products shown in the shop. Prices are in UGX.
CREATE TABLE `products` (
  `product_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_catag` varchar(100) NOT NULL,
  `product_title` varchar(255) NOT NULL,
  `product_price` int(11) NOT NULL,
  `product_desc` text NOT NULL,
  `product_date` varchar(50) NOT NULL,
  `product_img` text NOT NULL,
  `product_left` int(11) NOT NULL DEFAULT 0,
  `product_author` varchar(100) NOT NULL DEFAULT 'admin',
  PRIMARY KEY (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `products` (`product_id`, `product_catag`, `product_title`, `product_price`, `product_desc`, `product_date`, `product_img`, `product_left`, `product_author`) VALUES
(19, 'laptop', 'HP ProBook 450 G9', 4200000, '14-inch business laptop with a 12th-gen Intel Core i5, 16GB RAM and a 512GB SSD. Backlit keyboard and a fingerprint reader.', '20, Sep 2026', 'hp-probook-450.jpg', 12, 'nathan'),
(20, 'laptop', 'Lenovo IdeaPad Slim 3', 2150000, 'Everyday 15-inch laptop with a Ryzen 5 processor, 8GB RAM and a 256GB SSD. Light enough to carry all day.', '20, Sep 2026', 'lenovo-ideapad-slim-3.jpg', 20, 'nathan'),
(21, 'laptop', 'Dell Latitude 5420 (refurbished)', 1850000, 'Refurbished business laptop, tested and cleaned in our workshop. Core i5, 16GB RAM, 256GB SSD, 90-day warranty.', '20, Sep 2026', 'dell-latitude-5420.jpg', 7, 'nathan'),
(22, 'laptop', 'Apple MacBook Air M2', 5900000, '13.6-inch Liquid Retina display, Apple M2 chip, 8GB unified memory and 256GB storage. Silent, fanless and about 18 hours of battery.', '20, Sep 2026', 'macbook-air-m2.jpg', 5, 'nathan'),
(23, 'laptop', 'Acer Aspire 5 Gaming', 3100000, '15.6-inch laptop with a Core i7, 16GB RAM and RTX 3050 graphics. Handles editing and gaming.', '20, Sep 2026', 'acer-aspire-5.jpg', 9, 'nathan'),
(24, 'mobile', 'Samsung Galaxy A15', 950000, '6.5-inch Super AMOLED screen, 128GB storage, 50MP main camera and a 5000mAh battery that lasts the day.', '20, Sep 2026', 'samsung-galaxy-a15.jpg', 25, 'nathan'),
(25, 'mobile', 'Tecno Spark 20', 620000, 'Big 6.6-inch display, 128GB storage and 32MP selfie camera. Popular, affordable and easy to service.', '20, Sep 2026', 'tecno-spark-20.jpg', 40, 'nathan'),
(26, 'mobile', 'Apple iPhone 13', 3450000, '6.1-inch Super Retina XDR display, A15 Bionic chip and 128GB storage. Dual 12MP cameras with Night mode.', '20, Sep 2026', 'iphone-13.jpg', 6, 'nathan'),
(27, 'mobile', 'Infinix Hot 40i', 560000, '90Hz display, 128GB storage and a 5000mAh battery with 18W fast charging. Good value for a first smartphone.', '20, Sep 2026', 'infinix-hot-40i.jpg', 30, 'nathan'),
(28, 'mobile', 'Xiaomi Redmi Note 13', 1150000, '120Hz AMOLED display, 108MP camera and 256GB storage. Fast charging at 33W.', '20, Sep 2026', 'redmi-note-13.jpg', 18, 'nathan'),
(29, 'keyboard', 'Logitech K380 Bluetooth', 185000, 'Compact wireless keyboard that pairs with three devices at once. Runs two years on a pair of AA batteries.', '20, Sep 2026', 'logitech-k380.jpg', 22, 'nathan'),
(30, 'keyboard', 'Redragon K552 Mechanical', 240000, 'Tenkeyless mechanical keyboard with blue switches, red backlight and a steel frame. Built for typing and gaming.', '20, Sep 2026', 'redragon-k552.jpg', 15, 'nathan'),
(31, 'keyboard', 'HP 125 Wired Keyboard', 55000, 'Plain, sturdy USB keyboard with spill channels. The one we fit in most office setups.', '20, Sep 2026', 'hp-125-keyboard.jpg', 50, 'nathan'),
(32, 'mouse', 'Logitech M170 Wireless', 65000, 'Quiet wireless mouse with a tiny USB receiver and 12 months of battery life.', '20, Sep 2026', 'logitech-m170.jpg', 45, 'nathan'),
(33, 'mouse', 'Redragon M602 Gaming Mouse', 120000, '7200 DPI sensor, seven programmable buttons and RGB lighting. Comfortable for long sessions.', '20, Sep 2026', 'redragon-m602.jpg', 19, 'nathan'),
(34, 'mouse', 'HP 150 Wireless Mouse', 75000, 'Ambidextrous wireless mouse with a 1600 DPI sensor and a low-profile shape.', '20, Sep 2026', 'hp-150-mouse.jpg', 35, 'nathan');

-- Registered customers. customer_role is 'normal' or 'admin'.
CREATE TABLE `customer` (
  `customer_id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_fname` varchar(50) NOT NULL,
  `customer_email` varchar(100) NOT NULL,
  `customer_pwd` varchar(255) NOT NULL,
  `customer_phone` varchar(15) NOT NULL,
  `customer_address` text NOT NULL,
  `customer_role` varchar(50) NOT NULL DEFAULT 'normal',
  PRIMARY KEY (`customer_id`),
  UNIQUE KEY `customer_email` (`customer_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One starter account for development: owner@fundielectric.test with the
-- password "changeme", stored as a password_hash() bcrypt hash. Change the
-- password after your first login.
INSERT INTO `customer` (`customer_id`, `customer_fname`, `customer_email`, `customer_pwd`, `customer_phone`, `customer_address`, `customer_role`) VALUES
(1, 'Store owner', 'owner@fundielectric.test', '$2y$10$4ZB4ICFSQcwXxaXuQ4jsiOMUvR/qZKQPn3kVQdv2nUUrM7dmxqkRG', '0700000000', 'Kampala', 'admin');

-- Items a customer has put in their cart but not paid for yet.
CREATE TABLE `carts` (
  `sid` int(11) NOT NULL AUTO_INCREMENT,
  `pid` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  `product` varchar(255) NOT NULL,
  `price` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `status` enum('active','purchased') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`sid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per product in a paid order. payment_id links it to the payments table.
CREATE TABLE `soldproducts` (
  `sid` int(11) NOT NULL AUTO_INCREMENT,
  `uid` int(11) NOT NULL,
  `pid` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `price` int(11) NOT NULL,
  `date` varchar(60) DEFAULT NULL,
  `status` enum('pending','delivered') NOT NULL DEFAULT 'pending',
  `payment_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`sid`),
  KEY `payment_id` (`payment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Repair serials: one for each item bought, with the discount it unlocks.
-- status is 'pending' until the serial is used for a repair, then 'used'.
CREATE TABLE `servicestatus` (
  `sid` int(11) NOT NULL AUTO_INCREMENT,
  `uid` int(11) DEFAULT NULL,
  `discount` varchar(20) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `pid` int(11) DEFAULT NULL,
  `uuid` varchar(100) DEFAULT NULL,
  `payment_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`sid`),
  KEY `payment_id` (`payment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Repair bookings.
CREATE TABLE `repair` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `p_name` varchar(50) NOT NULL,
  `category` varchar(20) NOT NULL,
  `damage_type` varchar(40) NOT NULL,
  `uuid` varchar(100) DEFAULT NULL,
  `advance_amt` int(11) NOT NULL,
  `due` int(11) DEFAULT NULL,
  `status` enum('repaired','pending') NOT NULL DEFAULT 'pending',
  `booked_date` varchar(25) NOT NULL,
  `return_date` varchar(25) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Messages sent from the contact page.
CREATE TABLE `email` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `email` varchar(120) NOT NULL,
  `subject` varchar(50) NOT NULL,
  `message` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Store name and footer text, shown in the header and footer.
CREATE TABLE `settings` (
  `website_name` varchar(60) NOT NULL,
  `website_logo` varchar(50) NOT NULL,
  `website_footer` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `settings` (`website_name`, `website_logo`, `website_footer`) VALUES
('Fundi Electric', '', '© Fundi Electric 2026 <br> All rights reserved.');

-- One row per card payment started with Stripe Checkout. `items` is a JSON
-- snapshot of what is being paid for; `amount` is the total in UGX. Orders
-- are only recorded once Stripe confirms the payment and the row turns 'paid'.
CREATE TABLE `payments` (
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

COMMIT;
