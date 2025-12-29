-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 29, 2025 at 03:27 PM
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
-- Database: `tmm`
--

-- --------------------------------------------------------

--
-- Table structure for table `compliance_cases`
--

CREATE TABLE `compliance_cases` (
  `case_id` int(11) NOT NULL,
  `franchise_ref_number` varchar(50) NOT NULL,
  `violation_type` varchar(100) DEFAULT NULL,
  `status` enum('Open','Resolved','Escalated') DEFAULT 'Open',
  `reported_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `compliance_summary`
--

CREATE TABLE `compliance_summary` (
  `vehicle_plate` varchar(32) NOT NULL,
  `franchise_id` varchar(64) DEFAULT NULL,
  `violation_count` int(11) DEFAULT 0,
  `last_violation_date` date DEFAULT NULL,
  `compliance_status` varchar(32) DEFAULT 'Normal',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `coops`
--

CREATE TABLE `coops` (
  `id` int(11) NOT NULL,
  `coop_name` varchar(128) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `chairperson_name` varchar(128) DEFAULT NULL,
  `lgu_approval_number` varchar(64) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int(11) NOT NULL,
  `plate_number` varchar(32) DEFAULT NULL,
  `type` varchar(16) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `uploaded_by` varchar(64) DEFAULT 'admin',
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `drivers`
--

CREATE TABLE `drivers` (
  `id` int(11) NOT NULL,
  `operator_id` int(11) DEFAULT NULL,
  `driver_name` varchar(255) DEFAULT NULL,
  `license_no` varchar(50) DEFAULT NULL,
  `contact_no` varchar(50) DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `endorsement_records`
--

CREATE TABLE `endorsement_records` (
  `endorsement_id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `issued_date` date DEFAULT NULL,
  `permit_number` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `evidence`
--

CREATE TABLE `evidence` (
  `evidence_id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `file_type` varchar(32) DEFAULT NULL,
  `uploaded_by` varchar(64) DEFAULT 'officer',
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fee_reconciliations`
--

CREATE TABLE `fee_reconciliations` (
  `id` int(11) NOT NULL,
  `terminal_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `period_from` date DEFAULT NULL,
  `period_to` date DEFAULT NULL,
  `treasury_ref` varchar(50) DEFAULT NULL,
  `status` enum('Pending','Verified') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `franchise_applications`
--

CREATE TABLE `franchise_applications` (
  `application_id` int(11) NOT NULL,
  `franchise_ref_number` varchar(50) NOT NULL,
  `operator_id` int(11) NOT NULL,
  `coop_id` int(11) DEFAULT NULL,
  `vehicle_count` int(11) DEFAULT 1,
  `status` enum('Pending','Under Review','Endorsed','Rejected') DEFAULT 'Pending',
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `officers`
--

CREATE TABLE `officers` (
  `officer_id` int(11) NOT NULL,
  `name` varchar(128) DEFAULT NULL,
  `role` varchar(64) DEFAULT NULL,
  `badge_no` varchar(64) DEFAULT NULL,
  `station_id` varchar(64) DEFAULT NULL,
  `active_status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `officers`
--

INSERT INTO `officers` (`officer_id`, `name`, `role`, `badge_no`, `station_id`, `active_status`, `created_at`) VALUES
(1, 'Officer Dela Cruz', 'Enforcer', 'MMDA-001', 'Station A', 1, '2025-12-29 10:34:46'),
(2, 'Officer Santos', 'Enforcer', 'MMDA-002', 'Station B', 1, '2025-12-29 10:34:46'),
(3, 'Officer Reyes', 'Supervisor', 'MMDA-010', 'HQ', 1, '2025-12-29 10:34:46'),
(4, 'Officer Garcia', 'Enforcer', 'MMDA-003', 'Station C', 1, '2025-12-29 10:34:46');

-- --------------------------------------------------------

--
-- Table structure for table `operators`
--

CREATE TABLE `operators` (
  `id` int(11) NOT NULL,
  `full_name` varchar(128) DEFAULT NULL,
  `contact_info` varchar(128) DEFAULT NULL,
  `coop_name` varchar(128) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ownership_transfers`
--

CREATE TABLE `ownership_transfers` (
  `id` int(11) NOT NULL,
  `plate_number` varchar(32) DEFAULT NULL,
  `new_operator_name` varchar(128) DEFAULT NULL,
  `deed_ref` varchar(128) DEFAULT NULL,
  `transferred_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `parking_areas`
--

CREATE TABLE `parking_areas` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `city` varchar(100) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL COMMENT 'Terminal Parking or Standalone',
  `terminal_id` int(11) DEFAULT NULL,
  `total_slots` int(11) DEFAULT 0,
  `allowed_puv_types` varchar(255) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `parking_areas`
--

INSERT INTO `parking_areas` (`id`, `name`, `city`, `location`, `type`, `terminal_id`, `total_slots`, `allowed_puv_types`, `status`) VALUES
(1, 'City Hall Parking', 'Caloocan City', 'City Hall Complex', 'Standalone', NULL, 50, 'Private, Official', 'Available'),
(2, 'Terminal A Annex', 'Caloocan City', '10th Ave', 'Terminal Parking', NULL, 30, 'Jeepney, UV Express', 'Available');

-- --------------------------------------------------------

--
-- Table structure for table `payment_records`
--

CREATE TABLE `payment_records` (
  `payment_id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `date_paid` datetime DEFAULT current_timestamp(),
  `receipt_ref` varchar(64) DEFAULT NULL,
  `verified_by_treasury` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `routes`
--

CREATE TABLE `routes` (
  `id` int(11) NOT NULL,
  `route_id` varchar(64) DEFAULT NULL,
  `route_name` varchar(128) DEFAULT NULL,
  `max_vehicle_limit` int(11) DEFAULT 50,
  `status` varchar(32) DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `routes`
--

INSERT INTO `routes` (`id`, `route_id`, `route_name`, `max_vehicle_limit`, `status`, `created_at`, `updated_at`) VALUES
(1, 'R-12', 'Central Loop', 50, 'Active', '2025-12-29 04:16:02', '2025-12-29 04:16:02'),
(2, 'R-08', 'East Corridor', 30, 'Active', '2025-12-29 04:16:02', '2025-12-29 04:16:02'),
(3, 'R-05', 'North Spur', 40, 'Active', '2025-12-29 04:16:02', '2025-12-29 04:16:02'),
(4, 'R-99', 'Test Route', 25, 'Active', '2025-12-29 04:20:12', '2025-12-29 04:20:12');

-- --------------------------------------------------------

--
-- Table structure for table `terminals`
--

CREATE TABLE `terminals` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  `type` enum('Terminal','Parking','LoadingBay') DEFAULT 'Terminal',
  `capacity` int(11) DEFAULT 0,
  `status` enum('Active','Inactive','Suspended') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `city` varchar(100) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `terminals`
--

INSERT INTO `terminals` (`id`, `name`, `address`, `type`, `capacity`, `status`, `created_at`, `city`, `location`) VALUES
(1, 'Central Terminal A', NULL, '', 0, 'Active', '2025-12-29 13:31:59', 'Caloocan City', '10th Ave'),
(2, 'North Hub B', NULL, '', 0, 'Active', '2025-12-29 13:31:59', 'Caloocan City', 'Monumento');

-- --------------------------------------------------------

--
-- Table structure for table `terminal_areas`
--

CREATE TABLE `terminal_areas` (
  `id` int(11) NOT NULL,
  `terminal_id` int(11) DEFAULT NULL,
  `area_name` varchar(100) DEFAULT NULL,
  `route_name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `slot_capacity` int(11) DEFAULT 0,
  `puv_type` varchar(50) DEFAULT NULL,
  `fare_range` varchar(50) DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `terminal_area_assignments`
--

CREATE TABLE `terminal_area_assignments` (
  `id` int(11) NOT NULL,
  `area_id` int(11) NOT NULL,
  `operator_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `terminal_area_operators`
--

CREATE TABLE `terminal_area_operators` (
  `id` int(11) NOT NULL,
  `area_id` int(11) NOT NULL,
  `operator_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `terminal_assignments`
--

CREATE TABLE `terminal_assignments` (
  `id` int(11) NOT NULL,
  `plate_number` varchar(32) DEFAULT NULL,
  `route_id` varchar(64) DEFAULT NULL,
  `terminal_name` varchar(128) DEFAULT NULL,
  `status` varchar(32) DEFAULT 'Authorized',
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `terminal_assignments`
--

INSERT INTO `terminal_assignments` (`id`, `plate_number`, `route_id`, `terminal_name`, `status`, `assigned_at`) VALUES
(1, 'ABC-1234', 'R-99', 'West Yard', 'Authorized', '2025-12-29 04:20:12');

-- --------------------------------------------------------

--
-- Table structure for table `terminal_charges`
--

CREATE TABLE `terminal_charges` (
  `id` int(11) NOT NULL,
  `terminal_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `charge_type` enum('Permit Fee','Usage Fee','Stall Rent','Penalty') DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `status` enum('Unpaid','Paid','Overdue') DEFAULT 'Unpaid',
  `receipt_no` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `terminal_designated_areas`
--

CREATE TABLE `terminal_designated_areas` (
  `id` int(11) NOT NULL,
  `terminal_id` int(11) NOT NULL,
  `area_name` varchar(100) NOT NULL,
  `route_name` varchar(255) DEFAULT NULL,
  `fare_range` varchar(50) DEFAULT NULL,
  `max_slots` int(11) DEFAULT 0,
  `current_usage` int(11) DEFAULT 0,
  `puv_type` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `terminal_designated_areas`
--

INSERT INTO `terminal_designated_areas` (`id`, `terminal_id`, `area_name`, `route_name`, `fare_range`, `max_slots`, `current_usage`, `puv_type`) VALUES
(1, 1, 'Line 1', 'Downtown Route', '15-25 PHP', 20, 0, 'Tricycle'),
(2, 1, 'Line 2', 'Barangay Route', '10-15 PHP', 15, 0, 'Tricycle');

-- --------------------------------------------------------

--
-- Table structure for table `terminal_drivers`
--

CREATE TABLE `terminal_drivers` (
  `id` int(11) NOT NULL,
  `operator_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `license_no` varchar(50) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `terminal_incidents`
--

CREATE TABLE `terminal_incidents` (
  `id` int(11) NOT NULL,
  `terminal_id` int(11) DEFAULT NULL,
  `vehicle_plate` varchar(20) DEFAULT NULL,
  `incident_type` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `evidence_path` varchar(255) DEFAULT NULL,
  `status` enum('Open','Resolved','Escalated') DEFAULT 'Open',
  `reported_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `terminal_inspections`
--

CREATE TABLE `terminal_inspections` (
  `id` int(11) NOT NULL,
  `terminal_id` int(11) DEFAULT NULL,
  `inspector_name` varchar(255) DEFAULT NULL,
  `inspection_date` datetime DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `findings` text DEFAULT NULL,
  `status` enum('Passed','Failed','Conditional') DEFAULT 'Passed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `terminal_logs`
--

CREATE TABLE `terminal_logs` (
  `id` int(11) NOT NULL,
  `terminal_id` int(11) DEFAULT NULL,
  `vehicle_plate` varchar(20) DEFAULT NULL,
  `activity_type` enum('Entry','Exit','Unload','Load') NOT NULL,
  `log_time` datetime DEFAULT current_timestamp(),
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `terminal_operators`
--

CREATE TABLE `terminal_operators` (
  `id` int(11) NOT NULL,
  `terminal_id` int(11) DEFAULT NULL,
  `operator_name` varchar(255) DEFAULT NULL,
  `vehicle_plate` varchar(20) DEFAULT NULL,
  `enrolled_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Active','Inactive') DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `terminal_permits`
--

CREATE TABLE `terminal_permits` (
  `id` int(11) NOT NULL,
  `terminal_id` int(11) DEFAULT NULL,
  `application_no` varchar(50) DEFAULT NULL,
  `applicant_name` varchar(255) DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected','Revoked') DEFAULT 'Pending',
  `issue_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `conditions` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tickets`
--

CREATE TABLE `tickets` (
  `ticket_id` int(11) NOT NULL,
  `ticket_number` varchar(32) DEFAULT NULL,
  `date_issued` datetime DEFAULT current_timestamp(),
  `violation_code` varchar(32) DEFAULT NULL,
  `vehicle_plate` varchar(32) DEFAULT NULL,
  `franchise_id` varchar(64) DEFAULT NULL,
  `coop_id` int(11) DEFAULT NULL,
  `driver_name` varchar(128) DEFAULT NULL,
  `issued_by` varchar(128) DEFAULT NULL,
  `status` enum('Pending','Validated','Settled','Escalated') DEFAULT 'Pending',
  `fine_amount` decimal(10,2) DEFAULT 0.00,
  `due_date` date DEFAULT NULL,
  `payment_ref` varchar(64) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

CREATE TABLE `vehicles` (
  `id` int(11) NOT NULL,
  `plate_number` varchar(32) DEFAULT NULL,
  `vehicle_type` varchar(64) DEFAULT NULL,
  `operator_name` varchar(128) DEFAULT NULL,
  `coop_name` varchar(128) DEFAULT NULL,
  `franchise_id` varchar(64) DEFAULT NULL,
  `route_id` varchar(64) DEFAULT NULL,
  `status` varchar(32) DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vehicles`
--

INSERT INTO `vehicles` (`id`, `plate_number`, `vehicle_type`, `operator_name`, `coop_name`, `franchise_id`, `route_id`, `status`, `created_at`, `updated_at`) VALUES
(1, 'ABC-1234', 'Jeepney', 'Juan Dela Cruz', NULL, 'FR-000987', 'R-99', 'Active', '2025-12-29 04:09:02', '2025-12-29 04:20:12');

-- --------------------------------------------------------

--
-- Table structure for table `violation_types`
--

CREATE TABLE `violation_types` (
  `violation_code` varchar(32) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `fine_amount` decimal(10,2) DEFAULT 0.00,
  `category` varchar(64) DEFAULT NULL,
  `sts_equivalent_code` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `violation_types`
--

INSERT INTO `violation_types` (`violation_code`, `description`, `fine_amount`, `category`, `sts_equivalent_code`) VALUES
('DTS', 'Disregarding Traffic Signs', 500.00, 'General', 'STS-DTS'),
('IP', 'Illegal Parking', 1000.00, 'Parking', 'STS-IP'),
('NLZ', 'No Loading/Unloading Zone', 1000.00, 'Loading', 'STS-NLZ');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `compliance_cases`
--
ALTER TABLE `compliance_cases`
  ADD PRIMARY KEY (`case_id`);

--
-- Indexes for table `compliance_summary`
--
ALTER TABLE `compliance_summary`
  ADD PRIMARY KEY (`vehicle_plate`);

--
-- Indexes for table `coops`
--
ALTER TABLE `coops`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `coop_name` (`coop_name`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `plate_number` (`plate_number`);

--
-- Indexes for table `drivers`
--
ALTER TABLE `drivers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `endorsement_records`
--
ALTER TABLE `endorsement_records`
  ADD PRIMARY KEY (`endorsement_id`);

--
-- Indexes for table `evidence`
--
ALTER TABLE `evidence`
  ADD PRIMARY KEY (`evidence_id`),
  ADD KEY `ticket_id` (`ticket_id`);

--
-- Indexes for table `fee_reconciliations`
--
ALTER TABLE `fee_reconciliations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `franchise_applications`
--
ALTER TABLE `franchise_applications`
  ADD PRIMARY KEY (`application_id`),
  ADD UNIQUE KEY `franchise_ref_number` (`franchise_ref_number`);

--
-- Indexes for table `officers`
--
ALTER TABLE `officers`
  ADD PRIMARY KEY (`officer_id`),
  ADD UNIQUE KEY `badge_no` (`badge_no`);

--
-- Indexes for table `operators`
--
ALTER TABLE `operators`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `full_name` (`full_name`);

--
-- Indexes for table `ownership_transfers`
--
ALTER TABLE `ownership_transfers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `plate_number` (`plate_number`);

--
-- Indexes for table `parking_areas`
--
ALTER TABLE `parking_areas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `terminal_id` (`terminal_id`);

--
-- Indexes for table `payment_records`
--
ALTER TABLE `payment_records`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `ticket_id` (`ticket_id`);

--
-- Indexes for table `routes`
--
ALTER TABLE `routes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `route_id` (`route_id`);

--
-- Indexes for table `terminals`
--
ALTER TABLE `terminals`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `terminal_areas`
--
ALTER TABLE `terminal_areas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `terminal_id` (`terminal_id`);

--
-- Indexes for table `terminal_area_assignments`
--
ALTER TABLE `terminal_area_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `area_id` (`area_id`),
  ADD KEY `operator_id` (`operator_id`);

--
-- Indexes for table `terminal_area_operators`
--
ALTER TABLE `terminal_area_operators`
  ADD PRIMARY KEY (`id`),
  ADD KEY `area_id` (`area_id`),
  ADD KEY `operator_id` (`operator_id`);

--
-- Indexes for table `terminal_assignments`
--
ALTER TABLE `terminal_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_plate` (`plate_number`),
  ADD KEY `route_id` (`route_id`);

--
-- Indexes for table `terminal_charges`
--
ALTER TABLE `terminal_charges`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `terminal_designated_areas`
--
ALTER TABLE `terminal_designated_areas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `terminal_id` (`terminal_id`);

--
-- Indexes for table `terminal_drivers`
--
ALTER TABLE `terminal_drivers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `operator_id` (`operator_id`);

--
-- Indexes for table `terminal_incidents`
--
ALTER TABLE `terminal_incidents`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `terminal_inspections`
--
ALTER TABLE `terminal_inspections`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `terminal_logs`
--
ALTER TABLE `terminal_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `terminal_operators`
--
ALTER TABLE `terminal_operators`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `terminal_permits`
--
ALTER TABLE `terminal_permits`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tickets`
--
ALTER TABLE `tickets`
  ADD PRIMARY KEY (`ticket_id`),
  ADD UNIQUE KEY `ticket_number` (`ticket_number`),
  ADD KEY `vehicle_plate` (`vehicle_plate`),
  ADD KEY `violation_code` (`violation_code`);

--
-- Indexes for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `plate_number` (`plate_number`);

--
-- Indexes for table `violation_types`
--
ALTER TABLE `violation_types`
  ADD PRIMARY KEY (`violation_code`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `compliance_cases`
--
ALTER TABLE `compliance_cases`
  MODIFY `case_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `coops`
--
ALTER TABLE `coops`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `drivers`
--
ALTER TABLE `drivers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `endorsement_records`
--
ALTER TABLE `endorsement_records`
  MODIFY `endorsement_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `evidence`
--
ALTER TABLE `evidence`
  MODIFY `evidence_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fee_reconciliations`
--
ALTER TABLE `fee_reconciliations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `franchise_applications`
--
ALTER TABLE `franchise_applications`
  MODIFY `application_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `officers`
--
ALTER TABLE `officers`
  MODIFY `officer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `operators`
--
ALTER TABLE `operators`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ownership_transfers`
--
ALTER TABLE `ownership_transfers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `parking_areas`
--
ALTER TABLE `parking_areas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `payment_records`
--
ALTER TABLE `payment_records`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `routes`
--
ALTER TABLE `routes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `terminals`
--
ALTER TABLE `terminals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `terminal_areas`
--
ALTER TABLE `terminal_areas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `terminal_area_assignments`
--
ALTER TABLE `terminal_area_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `terminal_area_operators`
--
ALTER TABLE `terminal_area_operators`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `terminal_assignments`
--
ALTER TABLE `terminal_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `terminal_charges`
--
ALTER TABLE `terminal_charges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `terminal_designated_areas`
--
ALTER TABLE `terminal_designated_areas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `terminal_drivers`
--
ALTER TABLE `terminal_drivers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `terminal_incidents`
--
ALTER TABLE `terminal_incidents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `terminal_inspections`
--
ALTER TABLE `terminal_inspections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `terminal_logs`
--
ALTER TABLE `terminal_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `terminal_operators`
--
ALTER TABLE `terminal_operators`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `terminal_permits`
--
ALTER TABLE `terminal_permits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tickets`
--
ALTER TABLE `tickets`
  MODIFY `ticket_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`plate_number`) REFERENCES `vehicles` (`plate_number`) ON DELETE CASCADE;

--
-- Constraints for table `evidence`
--
ALTER TABLE `evidence`
  ADD CONSTRAINT `evidence_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`ticket_id`) ON DELETE CASCADE;

--
-- Constraints for table `ownership_transfers`
--
ALTER TABLE `ownership_transfers`
  ADD CONSTRAINT `ownership_transfers_ibfk_1` FOREIGN KEY (`plate_number`) REFERENCES `vehicles` (`plate_number`) ON DELETE CASCADE;

--
-- Constraints for table `parking_areas`
--
ALTER TABLE `parking_areas`
  ADD CONSTRAINT `parking_areas_ibfk_1` FOREIGN KEY (`terminal_id`) REFERENCES `terminals` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `payment_records`
--
ALTER TABLE `payment_records`
  ADD CONSTRAINT `payment_records_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`ticket_id`) ON DELETE CASCADE;

--
-- Constraints for table `terminal_areas`
--
ALTER TABLE `terminal_areas`
  ADD CONSTRAINT `terminal_areas_ibfk_1` FOREIGN KEY (`terminal_id`) REFERENCES `terminals` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `terminal_area_assignments`
--
ALTER TABLE `terminal_area_assignments`
  ADD CONSTRAINT `terminal_area_assignments_ibfk_1` FOREIGN KEY (`area_id`) REFERENCES `terminal_designated_areas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `terminal_area_assignments_ibfk_2` FOREIGN KEY (`operator_id`) REFERENCES `terminal_operators` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `terminal_area_operators`
--
ALTER TABLE `terminal_area_operators`
  ADD CONSTRAINT `terminal_area_operators_ibfk_1` FOREIGN KEY (`area_id`) REFERENCES `terminal_areas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `terminal_area_operators_ibfk_2` FOREIGN KEY (`operator_id`) REFERENCES `operators` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `terminal_assignments`
--
ALTER TABLE `terminal_assignments`
  ADD CONSTRAINT `terminal_assignments_ibfk_1` FOREIGN KEY (`plate_number`) REFERENCES `vehicles` (`plate_number`) ON DELETE CASCADE;

--
-- Constraints for table `terminal_designated_areas`
--
ALTER TABLE `terminal_designated_areas`
  ADD CONSTRAINT `terminal_designated_areas_ibfk_1` FOREIGN KEY (`terminal_id`) REFERENCES `terminals` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `terminal_drivers`
--
ALTER TABLE `terminal_drivers`
  ADD CONSTRAINT `terminal_drivers_ibfk_1` FOREIGN KEY (`operator_id`) REFERENCES `terminal_operators` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tickets`
--
ALTER TABLE `tickets`
  ADD CONSTRAINT `tickets_ibfk_1` FOREIGN KEY (`violation_code`) REFERENCES `violation_types` (`violation_code`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
