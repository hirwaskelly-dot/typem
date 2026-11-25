-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 24, 2026 at 08:44 AM
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
-- Database: `typem_db`
--
CREATE DATABASE IF NOT EXISTS `typem_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `typem_db`;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `reply_to` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `attachment_path` varchar(255) DEFAULT NULL,
  `attachment_name` varchar(255) DEFAULT NULL,
  `attachment_type` varchar(100) DEFAULT NULL,
  `attachment_size` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `sender_id`, `receiver_id`, `message`, `reply_to`, `is_read`, `created_at`, `attachment_path`, `attachment_name`, `attachment_type`, `attachment_size`) VALUES
(1, 2, 1, 'hy', NULL, 1, '2026-11-03 14:45:58', NULL, NULL, NULL, NULL),
(2, 1, 2, 'hy too', NULL, 1, '2026-11-03 14:56:01', NULL, NULL, NULL, NULL),
(3, 1, 2, 'how are you doing', NULL, 1, '2026-11-09 07:36:12', NULL, NULL, NULL, NULL),
(4, 2, 1, 'am good how about you', NULL, 1, '2026-11-09 07:36:26', NULL, NULL, NULL, NULL),
(5, 2, 1, 'hy', NULL, 1, '2026-11-09 08:10:38', NULL, NULL, NULL, NULL),
(6, 1, 2, 'hello', NULL, 1, '2026-11-09 08:11:26', NULL, NULL, NULL, NULL),
(7, 2, 1, 'hy', NULL, 1, '2026-11-09 08:13:18', NULL, NULL, NULL, NULL),
(8, 2, 1, 'bit', NULL, 1, '2026-11-09 08:16:13', NULL, NULL, NULL, NULL),
(9, 2, 1, 'we', NULL, 1, '2026-11-09 08:16:36', NULL, NULL, NULL, NULL),
(10, 1, 2, 'hy', NULL, 1, '2026-11-09 09:07:57', NULL, NULL, NULL, NULL),
(11, 2, 1, 'wow 1', NULL, 1, '2026-11-09 09:20:06', NULL, NULL, NULL, NULL),
(13, 2, 1, 'gg', NULL, 1, '2026-11-09 11:44:37', NULL, NULL, NULL, NULL),
(14, 1, 2, 'hh', 13, 1, '2026-11-09 12:01:28', NULL, NULL, NULL, NULL),
(15, 2, 1, 'hy', NULL, 1, '2026-11-09 12:28:23', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `typing_status`
--

CREATE TABLE `typing_status` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `display_name` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `is_online` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `display_name`, `password_hash`, `profile_picture`, `is_online`, `created_at`) VALUES
(1, 'kelly', 'kk', '$2y$10$vqBkjIlZ1Bc7KuNfw2VJ0enJoLRx1FvhP0ZR8iFxlyHSj44ptWDyy', NULL, 1, '2026-11-03 14:37:52'),
(2, 'hhhh', 'keza', '$2y$10$YnEzclE5XP.jOaVh0mIqN.rZlkOWRk6OWXsk14v5XBYq/4LZZ8PqS', 'assets/uploads/917cdf943d6c2e80a7545f90b857f362.png', 1, '2026-11-03 14:44:58'),
(3, 'hoza', 'hoza', '$2y$10$e6DuaV2Luug9e94oDXl82ut0kp/jEm5S3PnKe2SkZe4I6R.GowrCS', NULL, 0, '2026-11-09 08:52:35');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sender_receiver` (`sender_id`,`receiver_id`),
  ADD KEY `idx_receiver_sender` (`receiver_id`,`sender_id`);

--
-- Indexes for table `typing_status`
--
ALTER TABLE `typing_status`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_typing` (`sender_id`,`receiver_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `typing_status`
--
ALTER TABLE `typing_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
