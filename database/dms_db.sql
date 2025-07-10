-- phpMyAdmin SQL Dump
-- version 4.9.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 10, 2025 at 03:30 PM
-- Server version: 10.4.8-MariaDB
-- PHP Version: 7.2.24

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dms_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) COLLATE utf16_bin NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `parent_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf16 COLLATE=utf16_bin;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `created_at`, `parent_id`) VALUES
(1, 'HR Policies', '2025-07-01 03:00:31', NULL),
(2, 'Story book', '2025-07-01 03:00:31', NULL),
(4, 'Mouse', '2025-07-01 03:00:31', NULL),
(6, 'HR Policies 2025', '2025-07-01 03:00:31', NULL),
(7, 'IT Document', '2025-07-01 03:29:50', NULL),
(19, 'SOPs', '2025-07-01 14:21:58', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int(11) NOT NULL,
  `name` varchar(255) COLLATE utf16_bin NOT NULL,
  `description` text COLLATE utf16_bin DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `file_path` varchar(255) COLLATE utf16_bin NOT NULL,
  `meta_tags` text COLLATE utf16_bin DEFAULT NULL,
  `created_by_user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `expired_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf16 COLLATE=utf16_bin;

--
-- Dumping data for table `documents`
--

INSERT INTO `documents` (`id`, `name`, `description`, `category_id`, `file_path`, `meta_tags`, `created_by_user_id`, `created_at`, `updated_at`, `expired_date`) VALUES
(1, 'testing', 'testing', 1, 'uploads/6863516d5b1fa.png', '', 1, '2025-07-01 03:09:33', '2025-07-01 03:09:33', NULL),
(2, 'IT_Officer', 'IT', 7, 'uploads/6863570e2c9d2.png', '', 1, '2025-07-01 03:33:34', '2025-07-01 03:48:13', NULL),
(3, 'SOP', 'SOP', 6, 'uploads/686360cf30bce.pdf', '', 1, '2025-07-01 04:15:11', '2025-07-01 04:15:11', NULL),
(4, 'IT SOPs', 'IT SOPs', 7, 'uploads/68674c9a4f0d1.xlsx', '', 1, '2025-07-04 03:38:02', '2025-07-04 03:38:02', NULL),
(5, 'TEST', 'TESTING', 2, 'uploads/68693e5391b0d.pdf', '', 1, '2025-07-05 15:01:39', '2025-07-05 15:01:39', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `document_audit_trail`
--

CREATE TABLE `document_audit_trail` (
  `id` int(11) NOT NULL,
  `action_date` datetime NOT NULL DEFAULT current_timestamp(),
  `document_id` int(11) DEFAULT NULL,
  `document_name` varchar(255) COLLATE utf16_bin NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `category_name` varchar(255) COLLATE utf16_bin DEFAULT NULL,
  `operation` varchar(50) COLLATE utf16_bin NOT NULL,
  `performed_by_user_id` int(11) NOT NULL,
  `performed_by_user_email` varchar(255) COLLATE utf16_bin NOT NULL,
  `to_whom_user_id` int(11) DEFAULT NULL,
  `to_whom_user_email` varchar(255) COLLATE utf16_bin DEFAULT NULL,
  `to_whom_role_id` int(11) DEFAULT NULL,
  `to_whom_role_name` varchar(255) COLLATE utf16_bin DEFAULT NULL,
  `details` text COLLATE utf16_bin DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf16 COLLATE=utf16_bin;

-- --------------------------------------------------------

--
-- Table structure for table `document_categories`
--

CREATE TABLE `document_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) COLLATE utf16_bin NOT NULL,
  `description` text COLLATE utf16_bin DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf16 COLLATE=utf16_bin;

-- --------------------------------------------------------

--
-- Table structure for table `document_shares`
--

CREATE TABLE `document_shares` (
  `id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `role_id` int(11) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `allow_download` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf16 COLLATE=utf16_bin;

--
-- Dumping data for table `document_shares`
--

INSERT INTO `document_shares` (`id`, `document_id`, `user_id`, `role_id`, `start_date`, `end_date`, `allow_download`, `created_at`, `updated_at`) VALUES
(1, 3, NULL, 2, '2025-07-01', '2025-07-01', 0, '2025-07-01 06:53:33', '2025-07-01 16:40:47'),
(2, 3, 1, NULL, '2025-07-01', '2025-07-01', 1, '2025-07-01 06:53:44', '2025-07-02 01:44:41'),
(3, 3, 2, NULL, '2025-07-02', '2025-07-31', 1, '2025-07-02 01:44:59', '2025-07-02 01:44:59');

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL,
  `name` varchar(100) COLLATE utf16_bin NOT NULL,
  `description` text COLLATE utf16_bin DEFAULT NULL,
  `category` varchar(50) COLLATE utf16_bin NOT NULL DEFAULT 'General',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf16 COLLATE=utf16_bin;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `name`, `description`, `category`, `created_at`) VALUES
(1, 'view_dashboard', 'Allows viewing of the dashboard.', 'Dashboard', '2025-07-01 14:50:45'),
(2, 'view_all_documents', 'Allows viewing of all documents in the system.', 'All Documents', '2025-07-01 14:50:45'),
(3, 'create_document', 'Allows creating new documents.', 'All Documents', '2025-07-01 14:50:45'),
(4, 'edit_document', 'Allows editing of documents.', 'All Documents', '2025-07-01 14:50:45'),
(5, 'delete_document', 'Allows deleting documents.', 'All Documents', '2025-07-01 14:50:45'),
(6, 'add_reminder', 'Allows adding reminders for documents.', 'All Documents', '2025-07-01 14:50:45'),
(7, 'share_document', 'Allows sharing documents with users/roles.', 'All Documents', '2025-07-01 14:50:45'),
(8, 'download_document', 'Allows downloading documents.', 'All Documents', '2025-07-01 14:50:45'),
(9, 'send_email', 'Allows sending emails related to documents.', 'All Documents', '2025-07-01 14:50:45'),
(10, 'view_assigned_documents', 'Allows viewing documents specifically assigned to the user.', 'Assigned Documents', '2025-07-01 14:50:45'),
(11, 'manage_document_category', 'Allows creating, editing, and deleting document categories.', 'Document Category', '2025-07-01 14:50:45'),
(12, 'view_document_audit_trail', 'Allows viewing the audit trail for documents.', 'Document Audit Trail', '2025-07-01 14:50:45'),
(13, 'view_users', 'Allows viewing the list of users.', 'User', '2025-07-01 14:50:45'),
(14, 'create_user', 'Allows creating new user accounts.', 'User', '2025-07-01 14:50:45'),
(15, 'edit_user', 'Allows editing existing user details.', 'User', '2025-07-01 14:50:45'),
(16, 'delete_user', 'Allows deleting user accounts.', 'User', '2025-07-01 14:50:45'),
(17, 'reset_password', 'Allows resetting user passwords.', 'User', '2025-07-01 14:50:45'),
(18, 'assign_user_role', 'Allows assigning roles to users.', 'User', '2025-07-01 14:50:45'),
(19, 'assign_permission', 'Allows assigning specific permissions to users.', 'User', '2025-07-01 14:50:45'),
(20, 'view_roles', 'Allows viewing the list of roles.', 'Role', '2025-07-01 14:50:45'),
(21, 'create_role', 'Allows creating new roles.', 'Role', '2025-07-01 14:50:45'),
(22, 'edit_role', 'Allows editing existing roles.', 'Role', '2025-07-01 14:50:45'),
(23, 'delete_role', 'Allows deleting roles.', 'Role', '2025-07-01 14:50:45');

-- --------------------------------------------------------

--
-- Table structure for table `reminders`
--

CREATE TABLE `reminders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `document_id` int(11) DEFAULT NULL,
  `reminder_date` date NOT NULL,
  `title` varchar(255) COLLATE utf16_bin NOT NULL,
  `description` text COLLATE utf16_bin DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf16 COLLATE=utf16_bin;

--
-- Dumping data for table `reminders`
--

INSERT INTO `reminders` (`id`, `user_id`, `document_id`, `reminder_date`, `title`, `description`, `created_at`) VALUES
(29, 1, 1, '2025-07-05', 'Happy Birthday', '0', '2025-07-02 03:40:45'),
(30, 1, 1, '2025-07-05', 'TESTING', '0', '2025-07-02 14:06:57'),
(31, 1, 4, '2025-07-06', 'Check SOPs', '0', '2025-07-05 03:36:50'),
(32, 1, 2, '2025-07-05', 'Dating', '0', '2025-07-05 03:57:13'),
(33, 1, NULL, '2025-07-06', 'Hello world', '0', '2025-07-05 15:18:32');

-- --------------------------------------------------------

--
-- Table structure for table `reminder_recipients`
--

CREATE TABLE `reminder_recipients` (
  `reminder_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf16 COLLATE=utf16_bin;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(255) COLLATE utf16_bin NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `description` varchar(100) COLLATE utf16_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf16 COLLATE=utf16_bin;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `created_at`, `description`) VALUES
(1, 'Super Admin', '2025-07-01 03:35:06', 'Full access to all documents and system settings.'),
(2, 'Employee', '2025-07-01 03:35:06', 'Can view assigned documents, limited other actions.'),
(3, 'Manager', '2025-07-01 07:01:50', 'Can view all documents and manage some settings.'),
(4, 'user', '2025-07-01 15:38:42', 'user role');

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf16 COLLATE=utf16_bin;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4),
(1, 5),
(1, 6),
(1, 7),
(1, 8),
(1, 9),
(1, 10),
(1, 11),
(1, 12),
(1, 13),
(1, 14),
(1, 15),
(1, 16),
(1, 17),
(1, 18),
(1, 19),
(1, 20),
(1, 21),
(1, 22),
(1, 23),
(3, 1),
(3, 2),
(3, 3),
(3, 4),
(3, 5),
(3, 6),
(3, 7),
(3, 8),
(3, 9),
(3, 10),
(3, 11),
(3, 12),
(3, 13),
(3, 14),
(3, 15),
(3, 16),
(3, 17),
(3, 18),
(3, 19),
(3, 20),
(3, 21),
(3, 22),
(3, 23),
(4, 1),
(4, 2),
(4, 3),
(4, 4),
(4, 5),
(4, 6),
(4, 7),
(4, 8),
(4, 9),
(4, 10),
(4, 11),
(4, 12),
(4, 13),
(4, 14),
(4, 15),
(4, 16),
(4, 17),
(4, 18),
(4, 19),
(4, 20),
(4, 21),
(4, 22),
(4, 23);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(255) COLLATE utf16_bin NOT NULL,
  `first_name` varchar(100) COLLATE utf16_bin DEFAULT NULL,
  `last_name` varchar(100) COLLATE utf16_bin DEFAULT NULL,
  `mobile_number` varchar(20) COLLATE utf16_bin DEFAULT NULL,
  `password` varchar(255) COLLATE utf16_bin NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `role_id` int(11) DEFAULT 2
) ENGINE=InnoDB DEFAULT CHARSET=utf16 COLLATE=utf16_bin;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `first_name`, `last_name`, `mobile_number`, `password`, `created_at`, `role_id`) VALUES
(1, 'admin@dcms.com', NULL, NULL, NULL, '$2a$12$rtJ/wrlZDGx8cWqbP/DN.ezKN2G89p3sNSzR/2igJtmMNYTdaPooW', '2025-07-01 02:45:48', 1),
(2, 'mange@dcms.com', '', '', '0987654321', '$2y$10$jdexU8SWzNRl66NZYEIfeujVFJkwzdIZd9.khJcYT52PdWGsvQnOG', '2025-07-01 08:07:11', 3),
(3, 'user@dcms.com', NULL, '', '0987654321', '$2y$10$yTkx2.w2tJd1zSsM.X6DxuL.wORtTH/pg1sq3kkQj9yYnRdH0JyqC', '2025-07-01 08:14:37', 2);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `fk_parent_category` (`parent_id`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `created_by_user_id` (`created_by_user_id`);

--
-- Indexes for table `document_audit_trail`
--
ALTER TABLE `document_audit_trail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `document_id` (`document_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `performed_by_user_id` (`performed_by_user_id`),
  ADD KEY `to_whom_user_id` (`to_whom_user_id`),
  ADD KEY `to_whom_role_id` (`to_whom_role_id`);

--
-- Indexes for table `document_categories`
--
ALTER TABLE `document_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `document_shares`
--
ALTER TABLE `document_shares`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uc_document_user_role` (`document_id`,`user_id`,`role_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `role_id` (`role_id`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `reminders`
--
ALTER TABLE `reminders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `document_id` (`document_id`);

--
-- Indexes for table `reminder_recipients`
--
ALTER TABLE `reminder_recipients`
  ADD PRIMARY KEY (`reminder_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_id`,`permission_id`),
  ADD KEY `permission_id` (`permission_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_user_role` (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `document_audit_trail`
--
ALTER TABLE `document_audit_trail`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_categories`
--
ALTER TABLE `document_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_shares`
--
ALTER TABLE `document_shares`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `reminders`
--
ALTER TABLE `reminders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `fk_parent_category` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `documents_ibfk_2` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `document_audit_trail`
--
ALTER TABLE `document_audit_trail`
  ADD CONSTRAINT `document_audit_trail_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `document_audit_trail_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `document_categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `document_audit_trail_ibfk_3` FOREIGN KEY (`performed_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_audit_trail_ibfk_4` FOREIGN KEY (`to_whom_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `document_audit_trail_ibfk_5` FOREIGN KEY (`to_whom_role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `document_shares`
--
ALTER TABLE `document_shares`
  ADD CONSTRAINT `document_shares_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_shares_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_shares_ibfk_3` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reminders`
--
ALTER TABLE `reminders`
  ADD CONSTRAINT `reminders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reminders_ibfk_2` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `reminder_recipients`
--
ALTER TABLE `reminder_recipients`
  ADD CONSTRAINT `reminder_recipients_ibfk_1` FOREIGN KEY (`reminder_id`) REFERENCES `reminders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reminder_recipients_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_user_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
