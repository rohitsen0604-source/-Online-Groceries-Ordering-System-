-- Online Grocery Management System
-- MySQL Schema for Aiven / Cloud MySQL

CREATE TABLE IF NOT EXISTS `admin_list` (
  `admin_id` INT NOT NULL AUTO_INCREMENT,
  `fullname` VARCHAR(255) NOT NULL,
  `username` VARCHAR(255) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `status` TINYINT NOT NULL DEFAULT 1,
  `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `customer_list` (
  `customer_id` INT NOT NULL AUTO_INCREMENT,
  `fullname` VARCHAR(255) NOT NULL,
  `username` VARCHAR(255) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `contact` VARCHAR(50) NOT NULL,
  `address` TEXT NOT NULL,
  `status` TINYINT NOT NULL DEFAULT 1,
  `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `date_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `category_list` (
  `category_id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `status` TINYINT NOT NULL DEFAULT 1,
  `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `fees_list` (
  `fee_id` INT NOT NULL AUTO_INCREMENT,
  `location` VARCHAR(255) NOT NULL,
  `amount` DOUBLE NOT NULL DEFAULT 0,
  `status` TINYINT NOT NULL DEFAULT 1,
  `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`fee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `product_list` (
  `product_id` INT NOT NULL AUTO_INCREMENT,
  `category_id` INT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `price` DOUBLE NOT NULL DEFAULT 0,
  `status` TINYINT NOT NULL DEFAULT 1,
  `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `date_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `inventory_list` (
  `inventory_id` INT NOT NULL AUTO_INCREMENT,
  `product_id` INT NOT NULL,
  `quantity` DOUBLE NOT NULL,
  `type` TINYINT NOT NULL DEFAULT 1,
  `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`inventory_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cart_list` (
  `cart_id` INT NOT NULL AUTO_INCREMENT,
  `customer_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `quantity` DOUBLE NOT NULL,
  `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`cart_id`),
  KEY `idx_cust` (`customer_id`),
  KEY `idx_prod` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `order_list` (
  `order_id` INT NOT NULL AUTO_INCREMENT,
  `customer_id` INT NOT NULL,
  `transaction_code` VARCHAR(100) NOT NULL,
  `fee_id` INT NOT NULL,
  `delivery_address` TEXT NOT NULL,
  `total_amount` DOUBLE NOT NULL,
  `status` TINYINT NOT NULL DEFAULT 0,
  `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `order_items` (
  `order_item_id` INT NOT NULL AUTO_INCREMENT,
  `order_id` INT NOT NULL,
  `inventory_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `quantity` DOUBLE NOT NULL,
  `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`order_item_id`),
  KEY `idx_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed Default Admin User: admin / admin123
INSERT IGNORE INTO `admin_list` (`admin_id`, `fullname`, `username`, `password`, `status`, `date_created`)
VALUES (1, 'Administrator', 'admin', MD5('admin123'), 1, CURRENT_TIMESTAMP);

-- Seed Default Categories
INSERT IGNORE INTO `category_list` (`category_id`, `name`, `status`) VALUES
(1, 'Snacks', 1),
(2, 'Beverages', 1),
(3, 'Canned Goods', 1);

-- Seed Default Delivery Fees
INSERT IGNORE INTO `fees_list` (`fee_id`, `location`, `amount`, `status`) VALUES
(1, 'Location 101', 50, 1),
(2, 'Location 102', 75, 1),
(3, 'Location 103', 100, 1);


-- Seed Sample Products matching existing uploads
INSERT IGNORE INTO `product_list` (`product_id`, `category_id`, `name`, `description`, `price`, `status`) VALUES
(1, 3, 'Spam Luncheon Meat', 'Delicious canned luncheon meat 340g', 150.00, 1),
(2, 3, 'Mega Sardines in Tomato Sauce', 'Canned sardines in rich tomato sauce 155g', 25.00, 1),
(3, 3, 'Mega Sardines with Chili', 'Canned sardines in spicy tomato sauce with chili 155g', 27.00, 1);

-- Seed Initial Inventory Stock
INSERT IGNORE INTO `inventory_list` (`inventory_id`, `product_id`, `quantity`, `type`) VALUES
(1, 1, 100, 1),
(2, 2, 150, 1),
(3, 3, 120, 1);
