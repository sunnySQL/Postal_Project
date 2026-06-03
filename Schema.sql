-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Apr 09, 2025 at 12:07 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `postal`
--

-- --------------------------------------------------------

--
-- Table structure for table `Customer`
--

CREATE TABLE `Customer` (
  `user_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `street_address` varchar(100) DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `state` varchar(50) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `is_guest` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Employee`
--

CREATE TABLE `Employee` (
  `user_id` int(11) NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `role` enum('Clerk','Driver','Sorting Staff','Pilot','Customer Support','Admin','Manager') NOT NULL,
  `facility_id` int(11) NOT NULL,
  `phone` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Facility`
--

CREATE TABLE `Facility` (
  `facility_id` int(11) NOT NULL,
  `street_address` varchar(100) NOT NULL,
  `city` varchar(50) NOT NULL,
  `state` varchar(50) NOT NULL,
  `postal_code` varchar(20) NOT NULL,
  `type` enum('Hub','Post Office','Airport') NOT NULL,
  `airport_code` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Inventory`
--

CREATE TABLE `Inventory` (
  `inventory_id` int(11) NOT NULL,
  `shop_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp(),
  `min_stock_level` int(11) DEFAULT 0,
  `max_stock_level` int(11) DEFAULT 9999
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Items`
--

CREATE TABLE `Items` (
  `item_id` int(11) NOT NULL,
  `image` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `price_wholesale` decimal(10,2) NOT NULL,
  `sale_price` decimal(10,2) NOT NULL,
  `description` varchar(255) NOT NULL,
  `category` varchar(128) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Package`
--

CREATE TABLE `Package` (
  `tracking_number` varchar(50) NOT NULL,
  `weight` decimal(10,2) NOT NULL,
  `size` varchar(50) NOT NULL,
  `postage` decimal(10,2) NOT NULL,
  `signature_required` enum('Y','N') NOT NULL DEFAULT 'N',
  `shipping_speed` enum('Economy','Standard','Express') NOT NULL DEFAULT 'Standard',
  `status` varchar(30) DEFAULT NULL,
  `timestamp_created` timestamp NOT NULL DEFAULT current_timestamp(),
  `sender_id` int(11) DEFAULT NULL,
  `receiver_id` int(11) DEFAULT NULL,
  `facility_id` int(11) DEFAULT NULL,
  `last_tracking_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Package_Payment`
--

CREATE TABLE `Package_Payment` (
  `payment_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `package_id` varchar(50) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('Credit Card','PayPal','Cash','Bank Transfer') NOT NULL,
  `transaction_status` enum('Pending','Completed','Failed') DEFAULT 'Pending',
  `invoice_number` varchar(50) NOT NULL,
  `payment_date` datetime DEFAULT current_timestamp(),
  `facility_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Shop`
--

CREATE TABLE `Shop` (
  `shop_id` int(11) NOT NULL,
  `shop_name` varchar(100) NOT NULL,
  `sales` decimal(10,2) DEFAULT 0.00,
  `facility_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Shop_Sale`
--

CREATE TABLE `Shop_Sale` (
  `sale_id` int(11) NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `shop_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `sale_amount` decimal(10,2) NOT NULL,
  `sale_date` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Shop_Transaction`
--

CREATE TABLE `Shop_Transaction` (
  `transaction_id` int(11) NOT NULL,
  `shop_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_method` enum('Credit Card','PayPal','Cash','Bank Transfer') NOT NULL,
  `transaction_status` enum('Pending','Completed','Failed') DEFAULT 'Pending',
  `transaction_date` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Support_Ticket`
--

CREATE TABLE `Support_Ticket` (
  `ticket_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `package_id` varchar(50) DEFAULT NULL,
  `issue_type` enum('Delayed','Lost','Damaged','Payment Issue') NOT NULL,
  `status` enum('Open','In Progress','Resolved') DEFAULT 'Open',
  `assigned_employee_id` int(11) DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Tracking_History`
--

CREATE TABLE `Tracking_History` (
  `history_id` int(11) NOT NULL,
  `tracking_number` varchar(50) NOT NULL,
  `location` text NOT NULL,
  `status` varchar(30) DEFAULT NULL,
  `timestamp` datetime DEFAULT current_timestamp(),
  `employee_id` int(11) DEFAULT NULL,
  `action` enum('Scanning','Sorting','Loading','Delivery','None') DEFAULT 'None',
  `trip_id` int(11) DEFAULT NULL,
  `facility_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `Tracking_History`
--
DELIMITER $$
CREATE TRIGGER `update_package_last_tracking` AFTER INSERT ON `Tracking_History` FOR EACH ROW BEGIN
    UPDATE Package 
    SET last_tracking_id = NEW.history_id 
    WHERE tracking_number = NEW.tracking_number;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `Trip`
--

CREATE TABLE `Trip` (
  `trip_id` int(11) NOT NULL,
  `depart_time` datetime NOT NULL,
  `arrival_time` datetime DEFAULT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `depart_facility_id` int(11) NOT NULL,
  `arrive_facility_id` int(11) DEFAULT NULL,
  `vehicle_id` int(11) DEFAULT NULL,
  `trip_type` enum('Road','Air','Delivery') NOT NULL,
  `flight_number` varchar(20) DEFAULT NULL,
  `aircraft_registration` varchar(20) DEFAULT NULL,
  `airline` varchar(50) DEFAULT NULL,
  `is_delivery_route` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Trip_Package`
--

CREATE TABLE `Trip_Package` (
  `trip_id` int(11) NOT NULL,
  `tracking_number` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Users`
--

CREATE TABLE `Users` (
  `user_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Customer','Employee','Admin') NOT NULL,
  `account_status` enum('Active','Suspended','Banned') DEFAULT 'Active',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `password_change_required` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Vehicle`
--

CREATE TABLE `Vehicle` (
  `vehicle_id` int(11) NOT NULL,
  `license_plate` varchar(20) DEFAULT NULL,
  `vehicle_type` enum('Van','Truck','Motorcycle','Drone','Airplane') NOT NULL,
  `capacity` decimal(10,2) NOT NULL,
  `aircraft_registration` varchar(20) DEFAULT NULL,
  `airline` varchar(50) DEFAULT NULL,
  `current_facility_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `Admin_Messages`
--

CREATE TABLE `Admin_Messages` (
  `message_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('Support Escalation','Employee Action','General') NOT NULL,
  `related_id` int(11) DEFAULT NULL,
  `status` enum('New','In Progress','Resolved','Rejected') NOT NULL DEFAULT 'New',
  `admin_notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `Admin_Notification`
--

CREATE TABLE `Admin_Notification` (
  `id` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------


--
-- Indexes for dumped tables
--

--
-- Indexes for table `Customer`
--
ALTER TABLE `Customer`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `Employee`
--
ALTER TABLE `Employee`
  ADD PRIMARY KEY (`user_id`),
  ADD KEY `employee_ibfk_1` (`facility_id`);

--
-- Indexes for table `Facility`
--
ALTER TABLE `Facility`
  ADD PRIMARY KEY (`facility_id`);

--
-- Indexes for table `Inventory`
--
ALTER TABLE `Inventory`
  ADD PRIMARY KEY (`inventory_id`),
  ADD KEY `inventory_ibfk_1` (`shop_id`),
  ADD KEY `inventory_ibfk_2` (`item_id`);

--
-- Indexes for table `Items`
--
ALTER TABLE `Items`
  ADD PRIMARY KEY (`item_id`);

--
-- Indexes for table `Package`
--
ALTER TABLE `Package`
  ADD PRIMARY KEY (`tracking_number`),
  ADD KEY `package_ibfk_1` (`sender_id`),
  ADD KEY `package_ibfk_2` (`receiver_id`),
  ADD KEY `package_ibfk_3` (`facility_id`),
  ADD KEY `fk_last_tracking` (`last_tracking_id`);

--
-- Indexes for table `Package_Payment`
--
ALTER TABLE `Package_Payment`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `package_payment_ibfk_1` (`user_id`),
  ADD KEY `package_payment_ibfk_2` (`package_id`),
  ADD KEY `fk_facility_payment` (`facility_id`);

--
-- Indexes for table `Shop`
--
ALTER TABLE `Shop`
  ADD PRIMARY KEY (`shop_id`),
  ADD KEY `shop_ibfk_1` (`facility_id`);

--
-- Indexes for table `Shop_Sale`
--
ALTER TABLE `Shop_Sale`
  ADD PRIMARY KEY (`sale_id`),
  ADD KEY `shop_id` (`shop_id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `transaction_id` (`transaction_id`);

--
-- Indexes for table `Shop_Transaction`
--
ALTER TABLE `Shop_Transaction`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `shop_id` (`shop_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `Support_Ticket`
--
ALTER TABLE `Support_Ticket`
  ADD PRIMARY KEY (`ticket_id`),
  ADD KEY `support_ticket_ibfk_1` (`user_id`),
  ADD KEY `support_ticket_ibfk_2` (`package_id`),
  ADD KEY `support_ticket_ibfk_3` (`assigned_employee_id`);

--
-- Indexes for table `Tracking_History`
--
ALTER TABLE `Tracking_History`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `tracking_history_ibfk_1` (`tracking_number`),
  ADD KEY `tracking_history_ibfk_2` (`employee_id`),
  ADD KEY `tracking_history_ibfk_3` (`trip_id`),
  ADD KEY `fk_facility_tracking` (`facility_id`);

--
-- Indexes for table `Trip`
--
ALTER TABLE `Trip`
  ADD PRIMARY KEY (`trip_id`),
  ADD KEY `trip_ibfk_1` (`employee_id`),
  ADD KEY `trip_ibfk_2` (`depart_facility_id`),
  ADD KEY `trip_ibfk_3` (`arrive_facility_id`),
  ADD KEY `trip_ibfk_4` (`vehicle_id`);

--
-- Indexes for table `Trip_Package`
--
ALTER TABLE `Trip_Package`
  ADD PRIMARY KEY (`trip_id`,`tracking_number`),
  ADD KEY `trip_package_ibfk_2` (`tracking_number`);

--
-- Indexes for table `Users`
--
ALTER TABLE `Users`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `Vehicle`
--
ALTER TABLE `Vehicle`
  ADD PRIMARY KEY (`vehicle_id`),
  ADD KEY `vehicle_facility_fk` (`current_facility_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `Facility`
--
ALTER TABLE `Facility`
  MODIFY `facility_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Package_Payment`
--
ALTER TABLE `Package_Payment`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Shop`
--
ALTER TABLE `Shop`
  MODIFY `shop_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Shop_Sale`
--
ALTER TABLE `Shop_Sale`
  MODIFY `sale_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Shop_Transaction`
--
ALTER TABLE `Shop_Transaction`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Tracking_History`
--
ALTER TABLE `Tracking_History`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Trip`
--
ALTER TABLE `Trip`
  MODIFY `trip_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Users`
--
ALTER TABLE `Users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `Vehicle`
--
ALTER TABLE `Vehicle`
  MODIFY `vehicle_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `Customer`
--
ALTER TABLE `Customer`
  ADD CONSTRAINT `customer_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `Users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `Employee`
--
ALTER TABLE `Employee`
  ADD CONSTRAINT `employee_ibfk_1` FOREIGN KEY (`facility_id`) REFERENCES `Facility` (`facility_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employee_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `Users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `Inventory`
--
ALTER TABLE `Inventory`
  ADD CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`shop_id`) REFERENCES `Shop` (`shop_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `Items` (`item_id`) ON DELETE CASCADE;

--
-- Constraints for table `Package`
--
ALTER TABLE `Package`
  ADD CONSTRAINT `fk_last_tracking` FOREIGN KEY (`last_tracking_id`) REFERENCES `Tracking_History` (`history_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `package_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `Customer` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `package_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `Customer` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `package_ibfk_3` FOREIGN KEY (`facility_id`) REFERENCES `Facility` (`facility_id`) ON DELETE SET NULL;

--
-- Constraints for table `Package_Payment`
--
ALTER TABLE `Package_Payment`
  ADD CONSTRAINT `fk_facility_payment` FOREIGN KEY (`facility_id`) REFERENCES `Facility` (`facility_id`),
  ADD CONSTRAINT `package_payment_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `Customer` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `package_payment_ibfk_2` FOREIGN KEY (`package_id`) REFERENCES `Package` (`tracking_number`) ON DELETE SET NULL;

--
-- Constraints for table `Shop`
--
ALTER TABLE `Shop`
  ADD CONSTRAINT `shop_ibfk_1` FOREIGN KEY (`facility_id`) REFERENCES `Facility` (`facility_id`) ON DELETE CASCADE;

--
-- Constraints for table `Shop_Sale`
--
ALTER TABLE `Shop_Sale`
  ADD CONSTRAINT `Shop_Sale_ibfk_1` FOREIGN KEY (`shop_id`) REFERENCES `Shop` (`shop_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `Shop_Sale_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `Items` (`item_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `Shop_Sale_ibfk_3` FOREIGN KEY (`transaction_id`) REFERENCES `Shop_Transaction` (`transaction_id`) ON DELETE CASCADE;

--
-- Constraints for table `Shop_Transaction`
--
ALTER TABLE `Shop_Transaction`
  ADD CONSTRAINT `Shop_Transaction_ibfk_1` FOREIGN KEY (`shop_id`) REFERENCES `Shop` (`shop_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `Shop_Transaction_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `Customer` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `Support_Ticket`
--
ALTER TABLE `Support_Ticket`
  ADD CONSTRAINT `support_ticket_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `Customer` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `support_ticket_ibfk_2` FOREIGN KEY (`package_id`) REFERENCES `Package` (`tracking_number`) ON DELETE SET NULL,
  ADD CONSTRAINT `support_ticket_ibfk_3` FOREIGN KEY (`assigned_employee_id`) REFERENCES `Employee` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `Tracking_History`
--
ALTER TABLE `Tracking_History`
  ADD CONSTRAINT `fk_facility_tracking` FOREIGN KEY (`facility_id`) REFERENCES `Facility` (`facility_id`),
  ADD CONSTRAINT `tracking_history_ibfk_1` FOREIGN KEY (`tracking_number`) REFERENCES `Package` (`tracking_number`) ON DELETE CASCADE,
  ADD CONSTRAINT `tracking_history_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `Employee` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tracking_history_ibfk_3` FOREIGN KEY (`trip_id`) REFERENCES `Trip` (`trip_id`) ON DELETE SET NULL;

--
-- Constraints for table `Trip`
--
ALTER TABLE `Trip`
  ADD CONSTRAINT `trip_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `Employee` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `trip_ibfk_2` FOREIGN KEY (`depart_facility_id`) REFERENCES `Facility` (`facility_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `trip_ibfk_3` FOREIGN KEY (`arrive_facility_id`) REFERENCES `Facility` (`facility_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `trip_ibfk_4` FOREIGN KEY (`vehicle_id`) REFERENCES `Vehicle` (`vehicle_id`) ON DELETE SET NULL;

--
-- Constraints for table `Trip_Package`
--
ALTER TABLE `Trip_Package`
  ADD CONSTRAINT `trip_package_ibfk_1` FOREIGN KEY (`trip_id`) REFERENCES `Trip` (`trip_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `trip_package_ibfk_2` FOREIGN KEY (`tracking_number`) REFERENCES `Package` (`tracking_number`) ON DELETE CASCADE;

--
-- Constraints for table `Vehicle`
--
ALTER TABLE `Vehicle`
  ADD CONSTRAINT `vehicle_facility_fk` FOREIGN KEY (`current_facility_id`) REFERENCES `Facility` (`facility_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;