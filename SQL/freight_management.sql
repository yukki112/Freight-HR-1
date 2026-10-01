-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3307
-- Generation Time: Feb 26, 2026 at 10:33 PM
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
-- Database: `freight_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_log`
--

INSERT INTO `activity_log` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 5, 'register', 'New user registered as admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 06:39:20'),
(2, 5, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 06:39:37'),
(3, 5, 'import_job_from_api', 'Imported job posting: DSP-001 - Dispatcher from API', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 09:20:10'),
(4, 5, 'bulk_import_jobs', 'Imported 2 jobs from API, 0 skipped', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 09:44:35'),
(5, 5, 'bulk_import_jobs', 'Imported 0 jobs from API, 2 skipped', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 09:47:11'),
(6, 5, 'bulk_import_jobs', 'Imported 0 jobs from API, 2 skipped', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 09:47:25'),
(7, 5, 'bulk_import_jobs', 'Imported 0 jobs from API, 2 skipped', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 09:47:28'),
(8, 5, 'bulk_import_jobs', 'Imported 1 jobs from API, 0 skipped', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 09:54:38'),
(9, 5, 'bulk_import_jobs', 'Imported 1 jobs from API, 0 skipped', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 09:54:50'),
(10, 5, 'bulk_import_jobs', 'Imported 1 jobs from API, 0 skipped', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 09:55:13'),
(11, 5, 'create_job_posting', 'Created job posting: DSP-001 - Dispatcher', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 09:56:14'),
(12, 5, 'create_job_posting', 'Created job posting: OPS-001 - Operations Supervisor', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 11:48:14'),
(13, 5, 'verify_all_documents', 'Verified all documents for applicant #1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 14:16:29'),
(14, 5, 'update_applicant_status', 'Updated applicant #1 status to shortlisted via screening', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 14:54:11'),
(15, 5, 'screening_evaluation', 'Saved screening evaluation for applicant #1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 14:54:11'),
(16, 5, 'update_applicant_status', 'Updated applicant #1 status to shortlisted via screening', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 14:54:14'),
(17, 5, 'screening_evaluation', 'Saved screening evaluation for applicant #1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 14:54:14'),
(18, 5, 'update_applicant_status', 'Updated applicant #1 status to shortlisted via screening', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 14:54:43'),
(19, 5, 'screening_evaluation', 'Saved screening evaluation for applicant #1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 14:54:43'),
(20, 5, 'update_applicant_status', 'Updated applicant #1 status to shortlisted via screening', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 15:01:09'),
(21, 5, 'screening_evaluation', 'Saved screening evaluation for applicant #1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 15:01:09'),
(22, 5, 'update_applicant_status', 'Updated applicant #1 status to shortlisted via screening', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 15:01:22'),
(23, 5, 'screening_evaluation', 'Saved screening evaluation for applicant #1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 15:01:22'),
(24, 5, 'update_applicant_status', 'Updated applicant #1 status to shortlisted via screening', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 15:07:11'),
(25, 5, 'screening_evaluation', 'Saved screening evaluation for applicant #1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 15:07:11'),
(26, 5, 'update_applicant_status', 'Updated applicant #1 status to shortlisted via screening', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 15:09:04'),
(27, 5, 'screening_evaluation', 'Saved screening evaluation for applicant #1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 15:09:04'),
(28, 5, 'update_applicant_status', 'Updated applicant #1 status to shortlisted via screening', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 15:09:09'),
(29, 5, 'screening_evaluation', 'Saved screening evaluation for applicant #1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 15:09:09'),
(30, 5, 'update_applicant_status', 'Updated applicant #1 status to shortlisted via screening', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 15:09:14'),
(31, 5, 'screening_evaluation', 'Saved screening evaluation for applicant #1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 15:09:14'),
(32, 5, 'update_applicant_status', 'Updated applicant #1 status to shortlisted via screening', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 15:09:16'),
(33, 5, 'screening_evaluation', 'Saved screening evaluation for applicant #1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-23 15:09:16'),
(34, 5, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 16:05:26'),
(35, 5, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 16:08:53'),
(36, 5, 'schedule_interview', 'Scheduled interview for applicant #1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 17:28:16'),
(37, 5, 'schedule_interview', 'Scheduled interview for applicant #1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 17:37:42'),
(38, 5, 'schedule_interview', 'Scheduled interview for applicant #1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 17:41:38'),
(39, 5, 'schedule_interview', 'Scheduled interview for applicant #1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 18:00:03'),
(40, 5, 'schedule_interview', 'Scheduled interview for applicant #1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 18:00:25'),
(41, 5, 'schedule_interview', 'Scheduled interview for applicant #1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 18:25:48'),
(42, 5, 'schedule_interview', 'Scheduled interview for applicant #1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 18:26:48'),
(43, 5, 'schedule_interview', 'Scheduled interview for applicant #1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 18:45:28'),
(44, 5, 'start_evaluation', 'Started evaluation for interview #10', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 21:56:57'),
(45, 5, 'submit_evaluation', 'Submitted evaluation #1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 22:22:22'),
(46, 5, 'verify_all_documents', 'Verified all documents for applicant #3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 22:44:19'),
(47, 5, 'update_applicant_status', 'Updated applicant #3 status to shortlisted via screening', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 22:44:29'),
(48, 5, 'screening_evaluation', 'Saved screening evaluation for applicant #3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 22:44:29'),
(49, 5, 'schedule_interview', 'Scheduled interview for applicant #3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 22:45:01'),
(50, 5, 'start_evaluation', 'Started evaluation for interview #14', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 22:45:08'),
(51, 5, 'submit_evaluation', 'Submitted evaluation #2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-24 22:57:10'),
(52, 5, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 07:55:45'),
(53, 5, 'start_final_interview', 'Started final interview for applicant #1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 08:19:29'),
(54, 5, 'complete_final_interview', 'Completed final interview #1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 08:45:34'),
(55, 5, 'start_final_interview', 'Started final interview for applicant #3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 09:07:22'),
(56, 5, 'verify_all_documents', 'Verified all documents for applicant #4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 09:53:05'),
(57, 5, 'update_applicant_status', 'Updated applicant #4 status to shortlisted via screening', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 09:53:17'),
(58, 5, 'screening_evaluation', 'Saved screening evaluation for applicant #4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 09:53:17'),
(59, 5, 'schedule_interview', 'Scheduled interview for applicant #4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 09:53:37'),
(60, 5, 'start_evaluation', 'Started evaluation for interview #15', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 09:53:42'),
(61, 5, 'final_selection', 'Selected applicant #1 for hiring', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 10:10:12'),
(62, 5, 'complete_final_interview', 'Completed final interview #2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 13:00:03'),
(63, 5, 'upload_onboarding_document', 'Uploaded CONTRACT for new hire #2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 13:07:36'),
(64, 5, 'upload_onboarding_document', 'Uploaded CONTRACT for new hire #2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 13:07:44'),
(65, 5, 'verify_onboarding_document', 'Verified document #1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 13:08:04'),
(66, 5, 'request_documents', 'Sent document request to new hire #2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 13:30:13'),
(67, 5, 'request_documents', 'Sent document request to new hire #2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 13:59:43'),
(68, 5, 'request_documents', 'Sent document request to new hire #2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 14:00:45'),
(69, 5, 'request_documents', 'Sent document request to new hire #2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 14:25:36'),
(70, 5, 'request_documents', 'Sent document request to new hire #2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 14:26:29'),
(71, 5, 'verify_onboarding_document', 'Verified document #2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 14:50:38'),
(72, 5, 'request_documents', 'Sent document request to new hire #2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 14:50:54'),
(73, 5, 'reject_onboarding_document', 'Rejected document #3: too blurry', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 14:57:29'),
(74, 5, 'request_documents', 'Sent document request to new hire #2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 14:58:13'),
(75, 5, 'request_documents', 'Sent document request to new hire #2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 15:29:39'),
(76, 5, 'request_documents', 'Sent document request to new hire #2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 16:13:23'),
(77, 5, 'request_documents', 'Sent document request to new hire #2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 16:19:33'),
(78, 5, 'schedule_orientation', 'Scheduled orientation for new hire #2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 17:49:47'),
(79, 5, 'schedule_orientation', 'Scheduled orientation for new hire #2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-25 17:50:54'),
(80, 5, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 12:46:15'),
(81, 5, 'create_review', 'Created performance review for employee ID: 2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 14:40:11'),
(82, 5, 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 16:20:35'),
(83, 6, 'register', 'New user registered as admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 16:20:58'),
(84, 5, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 16:21:28'),
(85, 5, 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 16:30:48'),
(86, 6, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 16:30:54'),
(87, 6, 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 16:31:19'),
(88, 5, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 16:31:25'),
(89, 5, 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 16:37:36'),
(90, 6, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 16:37:40'),
(91, 6, 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 16:56:09'),
(92, 5, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 16:56:15'),
(93, 5, 'initiate_probation', 'Initiated probation for employee ID: 2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 16:58:18'),
(94, 6, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 16:58:47'),
(95, 6, 'probation_evaluation', 'Submitted final evaluation for probation ID: 0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 17:09:18'),
(96, 5, 'batch_decision', 'Batch confirm: 1 succeeded, 0 failed', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 18:08:08'),
(97, 5, 'submit_eom_nomination', 'Nominated employee #2 for EOM', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 18:56:41'),
(98, 5, 'create_recognition_post', 'Created recognition post #PE-202602-0001 for employee #2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 19:08:58'),
(99, 5, 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 19:20:11'),
(100, 5, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 19:20:34'),
(101, 5, 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 19:21:04'),
(102, 5, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 19:24:06'),
(103, 5, 'verify_all_documents', 'Verified all documents for applicant #6', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 19:32:14'),
(104, 5, 'update_applicant_status', 'Updated applicant #6 status to shortlisted via screening', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 19:32:26'),
(105, 5, 'screening_evaluation', 'Saved screening evaluation for applicant #6', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 19:32:26'),
(106, 5, 'schedule_interview', 'Scheduled interview for applicant #6', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 19:33:03'),
(107, 5, 'start_evaluation', 'Started evaluation for interview #21', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 19:33:10'),
(108, 5, 'submit_evaluation', 'Submitted evaluation #4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 19:49:53'),
(109, 5, 'submit_evaluation', 'Submitted evaluation #4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 19:50:45'),
(110, 5, 'submit_evaluation', 'Submitted evaluation #3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 19:51:28'),
(111, 5, 'submit_evaluation', 'Submitted evaluation #3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 19:51:57'),
(112, 5, 'final_selection', 'Selected applicant #3 for hiring', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 20:31:46'),
(113, 5, 'request_documents', 'Sent document request to new hire #5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 20:33:14'),
(114, 5, 'schedule_orientation', 'Scheduled orientation for new hire #5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 20:33:51'),
(115, 6, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 20:36:26'),
(116, 5, 'create_review', 'Created performance review for employee ID: 5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 20:36:42'),
(117, 5, 'generate_report', 'Generated summary probation report for 2026-02-01 to 2026-02-26', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 20:45:25'),
(118, 5, 'initiate_probation', 'Initiated probation for employee ID: 5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 20:46:46'),
(119, 6, 'probation_evaluation', 'Submitted final evaluation for probation ID: 2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 20:48:32'),
(120, 5, 'make_decision', 'Made confirm decision for employee ID: 5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 20:48:51'),
(121, 5, 'submit_eom_nomination', 'Nominated employee #5 for EOM', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 20:49:09'),
(122, 5, 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 21:20:56'),
(123, 5, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 21:32:35'),
(124, 5, 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 21:32:40'),
(125, 6, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 21:32:44'),
(126, 6, 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-26 21:32:47');

-- --------------------------------------------------------

--
-- Table structure for table `applicants`
--

CREATE TABLE `applicants` (
  `id` int(11) NOT NULL,
  `application_number` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT 'other',
  `address` text DEFAULT NULL,
  `position_applied` varchar(100) NOT NULL,
  `department` enum('driver','warehouse','logistics','admin','management') DEFAULT 'driver',
  `experience_years` int(11) DEFAULT 0,
  `education_level` varchar(100) DEFAULT NULL,
  `resume_path` varchar(255) DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `status` enum('new','in_review','shortlisted','interviewed','offered','hired','rejected','on_hold') DEFAULT 'new',
  `application_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `applicant_documents`
--

CREATE TABLE `applicant_documents` (
  `id` int(11) NOT NULL,
  `applicant_id` int(11) NOT NULL,
  `document_type` enum('resume','license','id','certificate','nbi','medical','other') NOT NULL,
  `document_name` varchar(255) DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `verified` tinyint(1) DEFAULT 0,
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `hours_worked` decimal(5,2) DEFAULT NULL,
  `status` enum('present','absent','late','half_day','holiday','leave') DEFAULT 'present',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `communication_log`
--

CREATE TABLE `communication_log` (
  `id` int(11) NOT NULL,
  `applicant_id` int(11) DEFAULT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `communication_type` enum('email','sms','call','notification') DEFAULT 'email',
  `subject` varchar(255) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `sent_by` int(11) DEFAULT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('sent','delivered','failed') DEFAULT 'sent'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `communication_log`
--

INSERT INTO `communication_log` (`id`, `applicant_id`, `employee_id`, `communication_type`, `subject`, `message`, `sent_by`, `sent_at`, `status`) VALUES
(4, 1, NULL, 'email', 'Interview Schedule: Dispatcher', 'Interview scheduled on February 25, 2026 at 10:00 AM', 5, '2026-02-24 17:28:16', 'sent'),
(5, 1, NULL, 'email', 'Interview Schedule: Dispatcher', 'Interview scheduled on February 25, 2026 at 10:00 AM', 5, '2026-02-24 17:37:42', 'sent'),
(6, 1, NULL, 'email', 'Interview Schedule: Dispatcher', 'Interview scheduled on February 25, 2026 at 10:00 AM', 5, '2026-02-24 17:41:38', 'sent'),
(7, 1, NULL, 'email', 'Interview Schedule: Dispatcher', 'Interview scheduled on February 25, 2026 at 10:00 AM', 5, '2026-02-24 18:00:03', 'sent'),
(8, 1, NULL, 'email', 'Interview Schedule: Dispatcher', 'Interview scheduled on February 25, 2026 at 10:00 AM', 5, '2026-02-24 18:00:25', 'sent'),
(9, 1, NULL, 'email', 'Interview Schedule: Dispatcher', 'Interview scheduled on February 26, 2026 at 10:00 AM', 5, '2026-02-24 18:25:48', 'sent'),
(10, 1, NULL, 'email', 'Interview Schedule: Dispatcher', 'Interview scheduled on February 25, 2026 at 10:00 AM', 5, '2026-02-24 18:26:48', 'sent'),
(11, 1, NULL, 'email', 'Interview Schedule: Dispatcher', 'Interview scheduled on February 25, 2026 at 10:00 AM', 5, '2026-02-24 18:45:28', 'sent'),
(12, 3, NULL, 'email', 'Interview Schedule: Operations Supervisor', 'Interview scheduled on February 25, 2026 at 10:00 AM', 5, '2026-02-24 22:45:01', 'sent'),
(13, 3, NULL, 'email', 'Application Update: Operations Supervisor', 'Final decision: Final interview', 5, '2026-02-24 22:57:10', 'sent'),
(14, 1, NULL, 'email', 'Final Interview Result: Dispatcher', 'Final decision: Hire (Score: 97.5%)', 5, '2026-02-25 08:45:34', 'sent'),
(15, 4, NULL, 'email', 'Interview Schedule: Operations Supervisor', 'Interview scheduled on February 26, 2026 at 10:00 AM', 5, '2026-02-25 09:53:37', 'sent'),
(16, 3, NULL, 'email', 'Final Interview Result: Operations Supervisor', 'Final decision: Hire (Score: 95%)', 5, '2026-02-25 13:00:03', 'sent'),
(17, 1, NULL, 'email', '📄 Document Submission Required - Freight Management Onboarding', 'Document request sent with link: http://localhost\\/public/onboarding-upload.php?tok...', 5, '2026-02-25 13:30:13', 'sent'),
(18, 1, NULL, 'email', '📄 Document Submission Required - Freight Management Onboarding', 'Document request sent with link', 5, '2026-02-25 13:59:43', 'sent'),
(19, 1, NULL, 'email', '📄 Document Submission Required - Freight Management Onboarding', 'Document request sent with link', 5, '2026-02-25 14:00:45', 'sent'),
(20, 1, NULL, 'email', '📄 Document Submission Required - Freight Management Onboarding', 'Document request sent with link', 5, '2026-02-25 14:25:36', 'sent'),
(21, 1, NULL, 'email', '📄 Document Submission Required - Freight Management Onboarding', 'Document request sent with link', 5, '2026-02-25 14:26:29', 'sent'),
(22, 1, NULL, 'email', '📄 Document Submission Required - Freight Management Onboarding', 'Document request sent with link', 5, '2026-02-25 14:50:54', 'sent'),
(23, 1, NULL, 'email', '⚠️ Document Update Required - Police Clearance', 'Document rejected: too blurry', 5, '2026-02-25 14:57:29', 'sent'),
(24, 1, NULL, 'email', '📄 Document Submission Required - Freight Management Onboarding', 'Document request sent with link', 5, '2026-02-25 14:58:13', 'sent'),
(25, 1, NULL, 'email', '📄 Document Submission Required - Freight Management Onboarding', 'Document request sent with link', 5, '2026-02-25 15:29:39', 'sent'),
(26, 1, NULL, 'email', '📄 Document Submission Required - Freight Management Onboarding', 'Document request sent with link', 5, '2026-02-25 16:13:23', 'sent'),
(27, 1, NULL, 'email', '📄 Document Submission Required - Freight Management Onboarding', 'Document request sent with link', 5, '2026-02-25 16:19:33', 'sent'),
(28, 1, NULL, 'email', 'Orientation Schedule: Dispatcher', 'Orientation scheduled on February 26, 2026 at 09:00 AM (3 hours)', 5, '2026-02-25 17:49:47', 'sent'),
(29, 1, NULL, 'email', 'Orientation Schedule: Dispatcher', 'Orientation scheduled on February 26, 2026 at 09:00 AM (4 hours)', 5, '2026-02-25 17:50:54', 'sent'),
(30, 6, NULL, 'email', 'Interview Schedule: Dispatcher', 'Interview scheduled on February 27, 2026 at 10:00 AM', 5, '2026-02-26 19:33:03', 'sent'),
(31, 6, NULL, 'email', 'Application Update: Dispatcher', 'Final decision: Final interview', 5, '2026-02-26 19:49:53', 'sent'),
(32, 6, NULL, 'email', 'Application Update: Dispatcher', 'Final decision: Final interview', 5, '2026-02-26 19:50:45', 'sent'),
(33, 4, NULL, 'email', 'Application Update: Operations Supervisor', 'Final decision: Final interview', 5, '2026-02-26 19:51:28', 'sent'),
(34, 4, NULL, 'email', 'Application Update: Operations Supervisor', 'Final decision: Final interview', 5, '2026-02-26 19:51:57', 'sent'),
(35, 3, NULL, 'email', '📄 Document Submission Required - Freight Management Onboarding', 'Document request sent with link', 5, '2026-02-26 20:33:14', 'sent'),
(36, 3, NULL, 'email', 'Orientation Schedule: Operations Supervisor', 'Orientation scheduled on February 27, 2026 at 09:00 AM (4 hours)', 5, '2026-02-26 20:33:51', 'sent');

-- --------------------------------------------------------

--
-- Table structure for table `document_requests`
--

CREATE TABLE `document_requests` (
  `id` int(11) NOT NULL,
  `new_hire_id` int(11) NOT NULL,
  `requested_by` int(11) NOT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','completed','expired') DEFAULT 'pending',
  `completed_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_requests`
--

INSERT INTO `document_requests` (`id`, `new_hire_id`, `requested_by`, `requested_at`, `status`, `completed_at`, `notes`) VALUES
(1, 2, 5, '2026-02-25 13:30:08', 'pending', NULL, NULL),
(2, 2, 5, '2026-02-25 13:59:38', 'pending', NULL, NULL),
(3, 2, 5, '2026-02-25 14:00:40', 'pending', NULL, NULL),
(4, 2, 5, '2026-02-25 14:25:32', 'pending', NULL, NULL),
(5, 2, 5, '2026-02-25 14:26:24', 'pending', NULL, NULL),
(6, 2, 5, '2026-02-25 14:50:49', 'pending', NULL, NULL),
(7, 2, 5, '2026-02-25 14:58:09', 'pending', NULL, NULL),
(8, 2, 5, '2026-02-25 15:29:34', 'pending', NULL, NULL),
(9, 2, 5, '2026-02-25 16:13:18', 'pending', NULL, NULL),
(10, 2, 5, '2026-02-25 16:19:27', 'pending', NULL, NULL),
(11, 5, 5, '2026-02-26 20:33:10', 'pending', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `employee_rewards`
--

CREATE TABLE `employee_rewards` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `reward_id` int(11) NOT NULL,
  `points_spent` int(11) NOT NULL,
  `status` enum('pending','approved','fulfilled','cancelled') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `fulfilled_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `employee_training`
--

CREATE TABLE `employee_training` (
  `id` int(11) NOT NULL,
  `new_hire_id` int(11) NOT NULL,
  `training_id` int(11) NOT NULL,
  `completed` tinyint(1) DEFAULT 0,
  `completion_date` date DEFAULT NULL,
  `score` int(11) DEFAULT NULL,
  `certificate_path` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `eom_criteria`
--

CREATE TABLE `eom_criteria` (
  `id` int(11) NOT NULL,
  `criteria_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `weight` int(11) DEFAULT 100,
  `category` enum('driver','warehouse','logistics','admin','management','all') DEFAULT 'all',
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `eom_criteria`
--

INSERT INTO `eom_criteria` (`id`, `criteria_name`, `description`, `weight`, `category`, `is_active`, `sort_order`, `created_by`, `created_at`) VALUES
(1, 'On-time Performance', 'Consistently meeting deadlines and schedules', 25, 'all', 1, 1, NULL, '2026-02-26 18:35:13'),
(2, 'Quality of Work', 'Accuracy and attention to detail', 20, 'all', 1, 2, NULL, '2026-02-26 18:35:13'),
(3, 'Attendance & Punctuality', 'No absences or tardiness', 20, 'all', 1, 3, NULL, '2026-02-26 18:35:13'),
(4, 'Teamwork', 'Collaboration with colleagues', 15, 'all', 1, 4, NULL, '2026-02-26 18:35:13'),
(5, 'Customer Feedback', 'Positive feedback from customers', 10, 'all', 1, 5, NULL, '2026-02-26 18:35:13'),
(6, 'Safety Compliance', 'Following safety protocols', 10, 'all', 1, 6, NULL, '2026-02-26 18:35:13');

-- --------------------------------------------------------

--
-- Table structure for table `eom_nominations`
--

CREATE TABLE `eom_nominations` (
  `id` int(11) NOT NULL,
  `month` date NOT NULL,
  `employee_id` int(11) NOT NULL,
  `nominated_by` int(11) NOT NULL,
  `category` enum('driver','warehouse','logistics','admin','management') NOT NULL,
  `reason` text NOT NULL,
  `performance_highlights` text DEFAULT NULL,
  `supporting_metrics` text DEFAULT NULL,
  `kpi_score` decimal(5,2) DEFAULT NULL,
  `supervisor_score` int(11) DEFAULT NULL,
  `vote_count` int(11) DEFAULT 0,
  `vote_percentage` decimal(5,2) DEFAULT NULL,
  `final_score` decimal(5,2) DEFAULT NULL,
  `status` enum('pending','approved','rejected','winner') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `eom_nominations`
--

INSERT INTO `eom_nominations` (`id`, `month`, `employee_id`, `nominated_by`, `category`, `reason`, `performance_highlights`, `supporting_metrics`, `kpi_score`, `supervisor_score`, `vote_count`, `vote_percentage`, `final_score`, `status`, `created_at`, `updated_at`) VALUES
(1, '2026-02-01', 2, 5, 'driver', 'letss gooo', 'testtttt', '', NULL, NULL, 0, NULL, NULL, 'pending', '2026-02-26 18:56:41', '2026-02-26 18:56:41'),
(2, '2026-02-01', 5, 5, 'driver', 'asdad', 'testtttt', 'asdasdasd', NULL, NULL, 0, NULL, NULL, 'pending', '2026-02-26 20:49:09', '2026-02-26 20:49:09');

-- --------------------------------------------------------

--
-- Table structure for table `eom_settings`
--

CREATE TABLE `eom_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `eom_settings`
--

INSERT INTO `eom_settings` (`id`, `setting_key`, `setting_value`, `description`, `updated_by`, `updated_at`) VALUES
(1, 'voting_enabled', '0', 'Enable employee voting (1=Yes, 0=No)', NULL, '2026-02-26 18:35:13'),
(2, 'supervisor_weight', '50', 'Supervisor score weight percentage', NULL, '2026-02-26 18:35:13'),
(3, 'kpi_weight', '30', 'KPI score weight percentage', NULL, '2026-02-26 18:35:13'),
(4, 'vote_weight', '20', 'Employee vote weight percentage', NULL, '2026-02-26 18:35:13'),
(5, 'nomination_start_day', '1', 'Day of month to start nominations', NULL, '2026-02-26 18:35:13'),
(6, 'nomination_end_day', '10', 'Day of month to end nominations', NULL, '2026-02-26 18:35:13'),
(7, 'voting_start_day', '11', 'Day of month to start voting', NULL, '2026-02-26 18:35:13'),
(8, 'voting_end_day', '15', 'Day of month to end voting', NULL, '2026-02-26 18:35:13'),
(9, 'announcement_day', '20', 'Day of month to announce winner', NULL, '2026-02-26 18:35:13'),
(10, 'prevent_consecutive_wins', '1', 'Prevent same person winning 2 months in a row', NULL, '2026-02-26 18:35:13'),
(11, 'auto_suggest_kpi', '1', 'Auto-suggest nominees based on KPI scores', NULL, '2026-02-26 18:35:13');

-- --------------------------------------------------------

--
-- Table structure for table `eom_votes`
--

CREATE TABLE `eom_votes` (
  `id` int(11) NOT NULL,
  `nomination_id` int(11) NOT NULL,
  `voter_id` int(11) NOT NULL,
  `score` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `eom_winners`
--

CREATE TABLE `eom_winners` (
  `id` int(11) NOT NULL,
  `month` date NOT NULL,
  `employee_id` int(11) NOT NULL,
  `nomination_id` int(11) DEFAULT NULL,
  `reward_type` enum('certificate','bonus','gift_card','extra_leave','public_recognition') DEFAULT 'public_recognition',
  `reward_details` varchar(255) DEFAULT NULL,
  `description` text NOT NULL,
  `badge_path` varchar(255) DEFAULT NULL,
  `announcement_banner` varchar(255) DEFAULT NULL,
  `approved_by` int(11) NOT NULL,
  `approved_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `evaluation_categories`
--

CREATE TABLE `evaluation_categories` (
  `id` int(11) NOT NULL,
  `template_id` int(11) NOT NULL,
  `category_name` varchar(255) NOT NULL,
  `weight` int(11) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `evaluation_categories`
--

INSERT INTO `evaluation_categories` (`id`, `template_id`, `category_name`, `weight`, `sort_order`) VALUES
(1, 1, 'Technical Competency', 40, 1),
(2, 1, 'Behavioral Competency', 30, 2),
(3, 1, 'Situational / Problem Solving', 30, 3),
(4, 2, 'Technical Competency', 40, 1),
(5, 2, 'Behavioral Competency', 30, 2),
(6, 2, 'Situational / Problem Solving', 30, 3),
(7, 3, 'Technical Competency', 40, 1),
(8, 3, 'Behavioral Competency', 30, 2),
(9, 3, 'Situational / Problem Solving', 30, 3);

-- --------------------------------------------------------

--
-- Table structure for table `evaluation_questions`
--

CREATE TABLE `evaluation_questions` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `question` text NOT NULL,
  `question_type` enum('technical','behavioral','situational') DEFAULT 'technical',
  `sort_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `evaluation_questions`
--

INSERT INTO `evaluation_questions` (`id`, `category_id`, `question`, `question_type`, `sort_order`) VALUES
(1, 1, 'Knowledge of dispatch software and systems', 'technical', 1),
(2, 1, 'Understanding of routing and scheduling principles', 'technical', 2),
(3, 1, 'Familiarity with communication protocols', 'technical', 3),
(4, 1, 'Experience with GPS and tracking systems', 'technical', 4),
(5, 1, 'Knowledge of safety regulations and compliance', 'technical', 5),
(6, 2, 'Communication skills with drivers and clients', 'behavioral', 1),
(7, 2, 'Ability to work under pressure', 'behavioral', 2),
(8, 2, 'Teamwork and collaboration', 'behavioral', 3),
(9, 2, 'Professional attitude and demeanor', 'behavioral', 4),
(10, 2, 'Adaptability to changing situations', 'behavioral', 5),
(11, 3, 'How would you handle a driver who is running late?', 'situational', 1),
(12, 3, 'A client calls complaining about a delayed delivery. What do you do?', 'situational', 2),
(13, 3, 'Two drivers have a conflict over route assignments. How do you resolve?', 'situational', 3),
(14, 3, 'System goes down during peak hours. Your next steps?', 'situational', 4),
(15, 3, 'How do you prioritize multiple urgent requests?', 'situational', 5),
(16, 4, 'Forklift operation experience', 'technical', 1),
(17, 4, 'Inventory management system knowledge', 'technical', 2),
(18, 4, 'Picking and packing accuracy', 'technical', 3),
(19, 4, 'Safety protocol compliance', 'technical', 4),
(20, 4, 'Physical stamina and endurance', 'technical', 5),
(21, 7, 'Experience in team leadership', 'technical', 1),
(22, 7, 'Knowledge of logistics KPIs', 'technical', 2),
(23, 7, 'Budget management experience', 'technical', 3),
(24, 7, 'Process improvement methodologies', 'technical', 4),
(25, 7, 'Regulatory compliance knowledge', 'technical', 5);

-- --------------------------------------------------------

--
-- Table structure for table `evaluation_responses`
--

CREATE TABLE `evaluation_responses` (
  `id` int(11) NOT NULL,
  `evaluation_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `rating` int(11) DEFAULT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `comments` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `evaluation_responses`
--

INSERT INTO `evaluation_responses` (`id`, `evaluation_id`, `question_id`, `rating`, `comments`) VALUES
(1, 1, 1, 5, ''),
(2, 1, 2, 5, ''),
(3, 1, 3, 5, ''),
(4, 1, 4, 5, ''),
(5, 1, 5, 5, ''),
(6, 1, 6, 5, ''),
(7, 1, 7, 5, ''),
(8, 1, 8, 5, ''),
(9, 1, 9, 5, ''),
(10, 1, 10, 5, ''),
(11, 1, 11, 4, ''),
(12, 1, 12, 5, ''),
(13, 1, 13, 5, ''),
(14, 1, 14, 4, ''),
(15, 1, 15, 4, ''),
(16, 2, 21, 4, ''),
(17, 2, 22, 3, ''),
(18, 2, 23, 5, ''),
(19, 2, 24, 5, ''),
(20, 2, 25, 5, ''),
(21, 4, 1, 5, ''),
(22, 4, 2, 5, ''),
(23, 4, 3, 5, ''),
(24, 4, 4, 5, ''),
(25, 4, 5, 5, ''),
(26, 4, 6, 4, ''),
(27, 4, 7, 5, ''),
(28, 4, 8, 4, ''),
(29, 4, 9, 5, ''),
(30, 4, 10, 5, ''),
(31, 4, 11, 4, ''),
(32, 4, 12, 5, ''),
(33, 4, 13, 4, ''),
(34, 4, 14, 5, ''),
(35, 4, 15, 5, ''),
(36, 3, 21, 5, ''),
(37, 3, 22, 5, ''),
(38, 3, 23, 5, ''),
(39, 3, 24, 5, ''),
(40, 3, 25, 4, '');

-- --------------------------------------------------------

--
-- Table structure for table `evaluation_templates`
--

CREATE TABLE `evaluation_templates` (
  `id` int(11) NOT NULL,
  `position_id` int(11) NOT NULL,
  `position_code` varchar(50) NOT NULL,
  `template_name` varchar(255) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `evaluation_templates`
--

INSERT INTO `evaluation_templates` (`id`, `position_id`, `position_code`, `template_name`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 11, 'DSP-001', 'Dispatcher Evaluation Template', 1, 5, '2026-02-24 21:50:43', '2026-02-24 21:50:43'),
(2, 10, 'WH-001', 'Warehouse Staff Evaluation Template', 1, 5, '2026-02-24 21:50:43', '2026-02-24 21:50:43'),
(3, 13, 'OPS-001', 'Operations Supervisor Evaluation Template', 1, 5, '2026-02-24 21:50:43', '2026-02-24 21:50:43');

-- --------------------------------------------------------

--
-- Table structure for table `feedback_notes`
--

CREATE TABLE `feedback_notes` (
  `id` int(11) NOT NULL,
  `module` enum('recruitment','onboarding','probation','performance','training','disciplinary','exit','general') NOT NULL,
  `type` enum('interview','screening','probation','performance','general','warning','commendation','training','disciplinary','exit') NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_for` int(11) DEFAULT NULL,
  `for_type` enum('applicant','employee','user','none') DEFAULT 'none',
  `is_private` tinyint(1) DEFAULT 0,
  `is_important` tinyint(1) DEFAULT 0,
  `is_resolved` tinyint(1) DEFAULT 0,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `attachments` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feedback_reactions`
--

CREATE TABLE `feedback_reactions` (
  `id` int(11) NOT NULL,
  `feedback_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reaction` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `final_evaluation_questions`
--

CREATE TABLE `final_evaluation_questions` (
  `id` int(11) NOT NULL,
  `question` text NOT NULL,
  `category` enum('leadership','technical','cultural','decision_making') DEFAULT 'leadership',
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `final_evaluation_questions`
--

INSERT INTO `final_evaluation_questions` (`id`, `question`, `category`, `sort_order`, `is_active`) VALUES
(1, 'Leadership potential and management capability', 'leadership', 1, 1),
(2, 'Strategic thinking and problem-solving', 'leadership', 2, 1),
(3, 'Cultural fit with organization', 'cultural', 3, 1),
(4, 'Career goals alignment with company', 'cultural', 4, 1),
(5, 'Technical expertise for the role', 'technical', 5, 1),
(6, 'Industry knowledge and experience', 'technical', 6, 1),
(7, 'Decision-making under pressure', 'decision_making', 7, 1),
(8, 'Handling conflict and difficult situations', 'decision_making', 8, 1);

-- --------------------------------------------------------

--
-- Table structure for table `final_evaluation_responses`
--

CREATE TABLE `final_evaluation_responses` (
  `id` int(11) NOT NULL,
  `final_interview_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `rating` int(11) DEFAULT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `comments` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `final_evaluation_responses`
--

INSERT INTO `final_evaluation_responses` (`id`, `final_interview_id`, `question_id`, `rating`, `comments`) VALUES
(1, 1, 1, 5, ''),
(2, 1, 2, 4, ''),
(3, 1, 3, 5, ''),
(4, 1, 4, 5, ''),
(5, 1, 5, 5, ''),
(6, 1, 6, 5, ''),
(7, 1, 7, 5, ''),
(8, 1, 8, 5, ''),
(9, 2, 1, 5, ''),
(10, 2, 2, 5, ''),
(11, 2, 3, 5, ''),
(12, 2, 4, 5, ''),
(13, 2, 5, 4, ''),
(14, 2, 6, 5, ''),
(15, 2, 7, 4, ''),
(16, 2, 8, 5, '');

-- --------------------------------------------------------

--
-- Table structure for table `final_interviews`
--

CREATE TABLE `final_interviews` (
  `id` int(11) NOT NULL,
  `applicant_id` int(11) NOT NULL,
  `job_posting_id` int(11) DEFAULT NULL,
  `interviewer_id` int(11) DEFAULT NULL,
  `interview_date` date NOT NULL,
  `interview_time` time DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `meeting_link` varchar(255) DEFAULT NULL,
  `status` enum('scheduled','completed','cancelled','rescheduled') DEFAULT 'scheduled',
  `final_score` decimal(5,2) DEFAULT NULL,
  `recommendation` enum('hire','reject') DEFAULT NULL,
  `interview_notes` text DEFAULT NULL,
  `strengths` text DEFAULT NULL,
  `weaknesses` text DEFAULT NULL,
  `overall_comments` text DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `final_interviews`
--

INSERT INTO `final_interviews` (`id`, `applicant_id`, `job_posting_id`, `interviewer_id`, `interview_date`, `interview_time`, `location`, `meeting_link`, `status`, `final_score`, `recommendation`, `interview_notes`, `strengths`, `weaknesses`, `overall_comments`, `submitted_at`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 11, 5, '2026-02-25', NULL, NULL, NULL, 'completed', 97.50, 'hire', NULL, 'look its a new me', 'switch it up whos this', 'new jeans so fresh so clean', '2026-02-25 08:45:28', 5, '2026-02-25 08:19:29', '2026-02-25 10:52:37'),
(2, 3, 13, 5, '2026-02-25', NULL, NULL, NULL, 'completed', 95.00, 'hire', NULL, '', '', '', NULL, 5, '2026-02-25 09:07:22', '2026-02-25 12:59:59');

-- --------------------------------------------------------

--
-- Table structure for table `incentive_budget_tracking`
--

CREATE TABLE `incentive_budget_tracking` (
  `id` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `quarter` int(11) DEFAULT NULL,
  `month` int(11) DEFAULT NULL,
  `department` enum('driver','warehouse','logistics','admin','management','all') DEFAULT 'all',
  `total_budget` decimal(10,2) NOT NULL,
  `allocated_budget` decimal(10,2) DEFAULT 0.00,
  `used_budget` decimal(10,2) DEFAULT 0.00,
  `remaining_budget` decimal(10,2) GENERATED ALWAYS AS (`total_budget` - `used_budget`) STORED,
  `notes` text DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `incentive_eligibility`
--

CREATE TABLE `incentive_eligibility` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `program_id` int(11) NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `calculated_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `eligibility_score` decimal(5,2) DEFAULT NULL,
  `criteria_met` text DEFAULT NULL,
  `criteria_missed` text DEFAULT NULL,
  `calculated_value` decimal(10,2) DEFAULT NULL,
  `status` enum('eligible','approved','paid','rejected','pending') DEFAULT 'pending',
  `supervisor_approved` tinyint(1) DEFAULT 0,
  `supervisor_id` int(11) DEFAULT NULL,
  `supervisor_approved_at` timestamp NULL DEFAULT NULL,
  `supervisor_comments` text DEFAULT NULL,
  `hr_approved` tinyint(1) DEFAULT 0,
  `hr_id` int(11) DEFAULT NULL,
  `hr_approved_at` timestamp NULL DEFAULT NULL,
  `hr_comments` text DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `incentive_payouts`
--

CREATE TABLE `incentive_payouts` (
  `id` int(11) NOT NULL,
  `payout_number` varchar(50) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `eligibility_id` int(11) DEFAULT NULL,
  `program_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `reward_type` enum('cash_bonus','gift_card','extra_leave','fuel_allowance','certificate','points') NOT NULL,
  `reward_details` varchar(255) DEFAULT NULL,
  `payout_date` date NOT NULL,
  `payout_method` enum('payroll','gcash','check','voucher','points') DEFAULT 'payroll',
  `reference_number` varchar(100) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `paid_by` int(11) DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `status` enum('pending','approved','processing','paid','cancelled') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `incentive_points`
--

CREATE TABLE `incentive_points` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `points_earned` int(11) NOT NULL DEFAULT 0,
  `points_redeemed` int(11) NOT NULL DEFAULT 0,
  `points_balance` int(11) NOT NULL DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `incentive_points_transactions`
--

CREATE TABLE `incentive_points_transactions` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `transaction_type` enum('earn','redeem','adjust') NOT NULL,
  `points` int(11) NOT NULL,
  `reference_type` enum('eom','safety','performance','attendance','milestone','redemption','adjustment') DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `balance_after` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `incentive_programs`
--

CREATE TABLE `incentive_programs` (
  `id` int(11) NOT NULL,
  `program_code` varchar(50) NOT NULL,
  `program_name` varchar(255) NOT NULL,
  `category` enum('performance','safety','productivity','attendance','milestone','other') NOT NULL,
  `description` text DEFAULT NULL,
  `eligibility_criteria` text NOT NULL,
  `calculation_method` text DEFAULT NULL,
  `reward_type` enum('cash_bonus','gift_card','extra_leave','fuel_allowance','certificate','points') NOT NULL,
  `reward_value` decimal(10,2) NOT NULL,
  `reward_unit` varchar(50) DEFAULT 'PHP',
  `budget_limit` decimal(10,2) DEFAULT NULL,
  `budget_used` decimal(10,2) DEFAULT 0.00,
  `max_awards_per_employee` int(11) DEFAULT 1,
  `department` enum('driver','warehouse','logistics','admin','management','all') DEFAULT 'all',
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `is_recurring` tinyint(1) DEFAULT 0,
  `recurring_frequency` enum('monthly','quarterly','annual','one_time') DEFAULT 'one_time',
  `requires_supervisor_approval` tinyint(1) DEFAULT 1,
  `requires_hr_approval` tinyint(1) DEFAULT 1,
  `auto_calculate` tinyint(1) DEFAULT 1,
  `status` enum('active','paused','expired','cancelled') DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `incentive_programs`
--

INSERT INTO `incentive_programs` (`id`, `program_code`, `program_name`, `category`, `description`, `eligibility_criteria`, `calculation_method`, `reward_type`, `reward_value`, `reward_unit`, `budget_limit`, `budget_used`, `max_awards_per_employee`, `department`, `start_date`, `end_date`, `is_recurring`, `recurring_frequency`, `requires_supervisor_approval`, `requires_hr_approval`, `auto_calculate`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'SAFE-001', 'Safety Excellence Bonus', 'safety', 'Reward for zero safety incidents in a quarter', 'No safety incidents for 90 consecutive days', NULL, 'cash_bonus', 150.00, 'PHP', 50000.00, 0.00, 1, 'all', '2026-02-27', NULL, 0, 'quarterly', 1, 1, 1, 'active', NULL, '2026-02-26 19:25:55', '2026-02-26 19:25:55'),
(2, 'PERF-001', 'Performance Bonus', 'performance', 'Performance review score ≥ 90%', 'Performance review score of 90% or higher', NULL, 'cash_bonus', 200.00, 'PHP', 100000.00, 0.00, 1, 'all', '2026-02-27', NULL, 0, 'quarterly', 1, 1, 1, 'active', NULL, '2026-02-26 19:25:55', '2026-02-26 19:25:55'),
(3, 'ONTM-001', 'On-Time Excellence', 'productivity', 'Drivers with 98%+ on-time delivery', 'On-time delivery rate ≥ 98% for the month', NULL, 'cash_bonus', 100.00, 'PHP', 30000.00, 0.00, 1, 'driver', '2026-02-27', NULL, 0, 'monthly', 1, 1, 1, 'active', NULL, '2026-02-26 19:25:55', '2026-02-26 19:25:55'),
(4, 'ACC-001', 'Warehouse Accuracy Bonus', 'productivity', 'Picking accuracy ≥ 99.5%', 'Inventory accuracy of 99.5% or higher', NULL, 'cash_bonus', 100.00, 'PHP', 25000.00, 0.00, 1, 'warehouse', '2026-02-27', NULL, 0, 'monthly', 1, 1, 1, 'active', NULL, '2026-02-26 19:25:55', '2026-02-26 19:25:55'),
(5, 'ATT-001', 'Perfect Attendance', 'attendance', 'No absences or tardiness for the month', '100% attendance record with no tardiness', NULL, 'gift_card', 500.00, 'PHP', 20000.00, 0.00, 1, 'all', '2026-02-27', NULL, 0, 'monthly', 1, 1, 1, 'active', NULL, '2026-02-26 19:25:55', '2026-02-26 19:25:55'),
(6, 'MIL-001', '6-Month Safety Milestone', 'milestone', '6 months with zero incidents', 'No safety incidents for 6 consecutive months', NULL, 'fuel_allowance', 1000.00, 'PHP', 40000.00, 0.00, 1, 'driver', '2026-02-27', NULL, 0, 'quarterly', 1, 1, 1, 'active', NULL, '2026-02-26 19:25:55', '2026-02-26 19:25:55');

-- --------------------------------------------------------

--
-- Table structure for table `incentive_redeemable_items`
--

CREATE TABLE `incentive_redeemable_items` (
  `id` int(11) NOT NULL,
  `item_code` varchar(50) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category` enum('gift_card','merchandise','leave','allowance','other') NOT NULL,
  `points_required` int(11) NOT NULL,
  `cash_value` decimal(10,2) DEFAULT NULL,
  `stock` int(11) DEFAULT 999,
  `image_path` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `incentive_redeemable_items`
--

INSERT INTO `incentive_redeemable_items` (`id`, `item_code`, `item_name`, `description`, `category`, `points_required`, `cash_value`, `stock`, `image_path`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'GC-500', '₱500 GCash Load', 'GCash load worth 500 pesos', 'gift_card', 500, 500.00, 999, NULL, 1, NULL, '2026-02-26 19:25:55', '2026-02-26 19:25:55'),
(2, 'GC-1000', '₱1,000 Shopping Voucher', 'SM or Robinsons gift certificate', 'gift_card', 1000, 1000.00, 999, NULL, 1, NULL, '2026-02-26 19:25:55', '2026-02-26 19:25:55'),
(3, 'LEAVE-1', 'One Day Paid Leave', 'Extra paid leave day', 'leave', 800, 800.00, 999, NULL, 1, NULL, '2026-02-26 19:25:55', '2026-02-26 19:25:55'),
(4, 'JACKET', 'Company Jacket', 'Official company jacket', 'merchandise', 600, 600.00, 50, NULL, 1, NULL, '2026-02-26 19:25:55', '2026-02-26 19:25:55'),
(5, 'FUEL-500', '₱500 Fuel Allowance', 'Fuel allowance voucher', 'allowance', 500, 500.00, 999, NULL, 1, NULL, '2026-02-26 19:25:55', '2026-02-26 19:25:55'),
(6, 'EARLY-2', '2 Hours Early Release', 'Go home 2 hours early on Friday', 'other', 300, 0.00, 999, NULL, 1, NULL, '2026-02-26 19:25:55', '2026-02-26 19:25:55');

-- --------------------------------------------------------

--
-- Table structure for table `incentive_redemptions`
--

CREATE TABLE `incentive_redemptions` (
  `id` int(11) NOT NULL,
  `redemption_number` varchar(50) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `points_used` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `total_points` int(11) NOT NULL,
  `status` enum('pending','approved','fulfilled','cancelled') DEFAULT 'pending',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `fulfilled_by` int(11) DEFAULT NULL,
  `fulfilled_at` timestamp NULL DEFAULT NULL,
  `delivery_method` varchar(100) DEFAULT NULL,
  `tracking_number` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `interviews`
--

CREATE TABLE `interviews` (
  `id` int(11) NOT NULL,
  `applicant_id` int(11) NOT NULL,
  `job_posting_id` int(11) DEFAULT NULL,
  `interviewer_id` int(11) DEFAULT NULL,
  `interview_date` date NOT NULL,
  `interview_time` time DEFAULT NULL,
  `interview_type` enum('initial','technical','hr','final') DEFAULT 'initial',
  `interview_round` enum('initial','technical','hr','final') DEFAULT 'initial',
  `location` varchar(255) DEFAULT NULL,
  `meeting_link` varchar(255) DEFAULT NULL,
  `status` enum('scheduled','completed','cancelled','rescheduled') DEFAULT 'scheduled',
  `feedback` text DEFAULT NULL,
  `rating` int(11) DEFAULT NULL CHECK (`rating` >= 1 and `rating` <= 10),
  `final_recommendation` enum('hire','final_interview','hold','reject') DEFAULT NULL,
  `auto_processed` tinyint(1) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `interviews`
--

INSERT INTO `interviews` (`id`, `applicant_id`, `job_posting_id`, `interviewer_id`, `interview_date`, `interview_time`, `interview_type`, `interview_round`, `location`, `meeting_link`, `status`, `feedback`, `rating`, `final_recommendation`, `auto_processed`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(10, 1, 11, 5, '2026-02-25', '10:00:00', 'initial', 'initial', NULL, 'https://meet.google.com/zjopc1vy', 'completed', NULL, NULL, 'final_interview', 1, 'asdasdas', 5, '2026-02-24 18:00:21', '2026-02-24 22:22:22'),
(14, 3, 13, 5, '2026-02-25', '10:00:00', 'initial', 'initial', NULL, 'https://meet.google.com/dor-rpqx-ben', 'completed', NULL, NULL, 'final_interview', 1, 'asdasdasd', 5, '2026-02-24 22:44:55', '2026-02-24 22:57:06'),
(15, 4, 13, 5, '2026-02-26', '10:00:00', 'initial', 'initial', NULL, 'https://meet.google.com/dor-rpqx-ben', 'completed', NULL, NULL, 'final_interview', 1, 'SDFSDFSD', 5, '2026-02-25 09:53:32', '2026-02-26 19:51:24'),
(21, 6, 11, 5, '2026-02-27', '10:00:00', 'initial', 'initial', NULL, 'https://meet.google.com/dor-rpqx-ben', 'completed', NULL, NULL, 'final_interview', 1, 'wasdadsadasd', 5, '2026-02-26 19:32:58', '2026-02-26 19:49:48'),
(22, 3, 13, 5, '2026-02-27', '09:00:00', '', 'initial', NULL, 'https://meet.google.com/yva-cckb-pqh', 'scheduled', NULL, NULL, NULL, 0, 'Please bring:\r\n- Valid ID\r\n- Signed contract\r\n- SSS, PhilHealth, Pag-IBIG, TIN numbers\r\n- Notebook and pen', 5, '2026-02-26 20:33:46', '2026-02-26 20:33:46');

-- --------------------------------------------------------

--
-- Table structure for table `job_applications`
--

CREATE TABLE `job_applications` (
  `id` int(11) NOT NULL,
  `application_number` varchar(50) NOT NULL,
  `job_posting_id` int(11) NOT NULL,
  `job_posting_link_id` int(11) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT 'other',
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `elementary_school` varchar(255) DEFAULT NULL,
  `elementary_year` varchar(50) DEFAULT NULL,
  `high_school` varchar(255) DEFAULT NULL,
  `high_school_year` varchar(50) DEFAULT NULL,
  `senior_high` varchar(255) DEFAULT NULL,
  `senior_high_strand` varchar(100) DEFAULT NULL,
  `senior_high_year` varchar(50) DEFAULT NULL,
  `college` varchar(255) DEFAULT NULL,
  `college_course` varchar(255) DEFAULT NULL,
  `college_year` varchar(50) DEFAULT NULL,
  `vocational` varchar(255) DEFAULT NULL,
  `vocational_course` varchar(255) DEFAULT NULL,
  `vocational_year` varchar(50) DEFAULT NULL,
  `work_experience` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`work_experience`)),
  `skills` text DEFAULT NULL,
  `certifications` text DEFAULT NULL,
  `references_info` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`references_info`)),
  `resume_path` varchar(255) DEFAULT NULL,
  `cover_letter_path` varchar(255) DEFAULT NULL,
  `documents_verified` tinyint(1) DEFAULT 0,
  `photo_path` varchar(255) DEFAULT NULL,
  `status` enum('new','in_review','shortlisted','interviewed','offered','hired','rejected') DEFAULT 'new',
  `final_status` enum('pending','hired','rejected','final_interview') DEFAULT 'pending',
  `final_interview_score` decimal(5,2) DEFAULT NULL,
  `overall_ranking` int(11) DEFAULT NULL,
  `selected_by` int(11) DEFAULT NULL,
  `selection_date` datetime DEFAULT NULL,
  `approval_remarks` text DEFAULT NULL,
  `approved_salary` decimal(10,2) DEFAULT NULL,
  `proposed_start_date` date DEFAULT NULL,
  `hired_date` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `source` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `job_applications`
--

INSERT INTO `job_applications` (`id`, `application_number`, `job_posting_id`, `job_posting_link_id`, `first_name`, `last_name`, `email`, `phone`, `birth_date`, `gender`, `address`, `city`, `province`, `postal_code`, `elementary_school`, `elementary_year`, `high_school`, `high_school_year`, `senior_high`, `senior_high_strand`, `senior_high_year`, `college`, `college_course`, `college_year`, `vocational`, `vocational_course`, `vocational_year`, `work_experience`, `skills`, `certifications`, `references_info`, `resume_path`, `cover_letter_path`, `documents_verified`, `photo_path`, `status`, `final_status`, `final_interview_score`, `overall_ranking`, `selected_by`, `selection_date`, `approval_remarks`, `approved_salary`, `proposed_start_date`, `hired_date`, `notes`, `ip_address`, `user_agent`, `source`, `created_at`, `applied_at`, `updated_at`) VALUES
(1, 'APP-202602-6AA60B', 11, NULL, 'Rei', 'Naoi', 'stephenviray12@gmail.com', '0998 431 9585', '2004-02-10', 'male', '54 gold', 'QuezonCity NCR', 'metro manila', '1121', 'asdasdasd', '2010', 'asd1222asdasdasd', '2014', '123asd', 'tvl', '2020', 'best link', 'IT', '2020', '', '', '', '[{\"company\":\"asdasd\",\"position\":\"asdasd\",\"from_year\":\"2021\",\"to_year\":\"2022\",\"responsibilities\":\"test\"}]', 'asda111', 'asds1111', '[{\"name\":\"test\",\"position\":\"test\",\"company\":\"test\",\"contact\":\"09984319585\",\"relationship\":\"colleiges\"}]', 'uploads/applications/APP-202602-6AA60B/resume.docx', 'uploads/applications/APP-202602-6AA60B/cover_letter.docx', 1, 'uploads/applications/APP-202602-6AA60B/photo.jpg', 'hired', 'hired', 97.50, NULL, 5, '2026-02-25 18:10:08', 'I LOVE THIS GIRL', 18000.00, '2026-03-11', '2026-02-25 18:10:08', '\n[2026-02-23 15:54] Status updated to shortlisted based on screening evaluation.\n[2026-02-23 15:54] Status updated to shortlisted based on screening evaluation.\n[2026-02-23 15:54] Status updated to shortlisted based on screening evaluation.\n[2026-02-23 16:01] Status updated to shortlisted based on screening evaluation.\n[2026-02-23 16:01] Status updated to shortlisted based on screening evaluation.\n[2026-02-23 16:07] Status updated to shortlisted based on screening evaluation.\n[2026-02-23 16:09] Status updated to shortlisted based on screening evaluation.\n[2026-02-23 16:09] Status updated to shortlisted based on screening evaluation.\n[2026-02-23 16:09] Status updated to shortlisted based on screening evaluation.\n[2026-02-23 16:09] Status updated to shortlisted based on screening evaluation.\n[2026-02-24 18:28] Interview scheduled: initial on February 25, 2026 at 10:00 AM - Link: https://meet.google.com/zjopc1vy\n[2026-02-24 18:37] Interview scheduled: initial on February 25, 2026 at 10:00 AM - Link: https://meet.google.com/zjopc1vy\n[2026-02-24 18:41] Interview scheduled: initial on February 25, 2026 at 10:00 AM - Link: https://meet.google.com/zjopc1vy\n[2026-02-24 19:00] Interview scheduled: initial on February 25, 2026 at 10:00 AM - Link: https://meet.google.com/zjopc1vy\n[2026-02-24 19:00] Interview scheduled: initial on February 25, 2026 at 10:00 AM - Link: https://meet.google.com/zjopc1vy\n[2026-02-24 19:25] Interview scheduled: technical on February 26, 2026 at 10:00 AM - Link: https://meet.google.com/atz-arcu-zjf\n[2026-02-24 19:26] Interview scheduled: initial on February 25, 2026 at 10:00 AM\n[2026-02-24 19:45] Interview scheduled: hr on February 25, 2026 at 10:00 AM - Location: Training Center A', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'LinkedIn', '2026-02-25 11:02:58', '2026-02-23 03:24:38', '2026-02-25 11:02:58'),
(2, 'APP-202602-820581', 13, NULL, 'Minji', 'Kim', 'Kimminji@gmail.com', '09984319585', '2004-02-10', 'male', '54 gold', 'QuezonCity NCR', 'metro manila', '1121', 'Manuel ', '2010', 'commonwealth Highschoo;', '2016', 'Electron', 'TVL', '2020', 'bestlink', 'IT', 'Current', '', '', '', '[{\"company\":\"1111\",\"position\":\"test\",\"from_year\":\"2021\",\"to_year\":\"2023\",\"responsibilities\":\"taga hugas\"}]', 'mechanic', 'Prefessional Driving licens', '[{\"name\":\"Viray Stephen kyle\",\"position\":\"test\",\"company\":\"asdasd\",\"contact\":\"09984319585\",\"relationship\":\"colleiges\"}]', 'uploads/applications/APP-202602-820581/resume.docx', 'uploads/applications/APP-202602-820581/cover_letter.docx', 0, 'uploads/applications/APP-202602-820581/photo.jpg', 'new', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'Referral', '2026-02-25 11:02:58', '2026-02-23 08:23:20', '2026-02-25 11:02:58'),
(3, 'APP-202602-647126', 13, NULL, 'Danielle', 'Marsh', 'stephenviray12@gmail.com', '09984319585', '2004-02-10', 'female', '54 gold', 'QuezonCity NCR', 'metro manila', '1121', 'Manuel L Quezon', '2012', 'Commonwealth Highschool', '2016', 'Electron College', 'TVL', '2020', 'bestlink', 'IT', '2022', '', '', '', '[{\"company\":\"asdasd\",\"position\":\"asdasd\",\"from_year\":\"2021\",\"to_year\":\"2023\",\"responsibilities\":\"asdasdasd\"}]', 'asdasdasdasd', 'asdasdasdasd', '[{\"name\":\"Viray Stephen kyle\",\"position\":\"Manager\",\"company\":\"asdasd\",\"contact\":\"09984319585\",\"relationship\":\"Supervisor\"}]', 'uploads/applications/APP-202602-647126/resume.docx', 'uploads/applications/APP-202602-647126/cover_letter.docx', 1, 'uploads/applications/APP-202602-647126/photo.jpg', 'hired', 'hired', 95.00, NULL, 5, '2026-02-27 04:31:42', 'ASDASDASD', 18000.00, '2026-03-12', '2026-02-27 04:31:42', '\n[2026-02-24 23:44] Status updated to shortlisted based on screening evaluation.\n[2026-02-24 23:45] Interview scheduled: initial on February 25, 2026 at 10:00 AM - Link: https://meet.google.com/dor-rpqx-ben', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'Website', '2026-02-25 11:02:58', '2026-02-24 15:44:06', '2026-02-26 20:31:42'),
(4, 'APP-202602-9B0750', 13, NULL, 'Hanni', 'Pham', 'stephenviray12@gmail.com', '09984319585', '2004-02-10', 'female', '54 gold', 'QuezonCity NCR', 'metro manila', '1121', 'Manuel L Quezon', '2012', 'Commonwealth Highschool', '2016', 'Electron College', 'TVL', '2020', 'bestlink', 'IT', '2020', '', '', '', '[{\"company\":\"asdasd\",\"position\":\"asdasd\",\"from_year\":\"2021\",\"to_year\":\"2023\",\"responsibilities\":\"ASasA\"}]', 'SADASD', 'ASDASDASDASD', '[{\"name\":\"Viray Stephen kyle\",\"position\":\"Manager\",\"company\":\"asdasd\",\"contact\":\"09984319585\",\"relationship\":\"Supervisor\"}]', 'uploads/applications/APP-202602-9B0750/resume.docx', 'uploads/applications/APP-202602-9B0750/cover_letter.docx', 1, 'uploads/applications/APP-202602-9B0750/photo.jpg', 'interviewed', 'final_interview', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '\n[2026-02-25 10:53] Status updated to shortlisted based on screening evaluation.\n[2026-02-25 10:53] Interview scheduled: initial on February 26, 2026 at 10:00 AM - Link: https://meet.google.com/dor-rpqx-ben', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'LinkedIn', '2026-02-25 11:02:58', '2026-02-25 02:52:57', '2026-02-26 19:51:24'),
(5, 'APP-202602-31C791', 13, NULL, 'sullyoon', 'Seol', 'stephenviray12@gmail.com', '09984319585', '2004-02-10', 'female', '54 gold', 'QuezonCity NCR', 'metro manila', '1121', 'Manuel L Quezon', '2012', 'Commonwealth Highschool', '2016', 'Electron College', 'TVL', '2020', 'bestlink', 'IT', '2022', '', '', '', '[{\"company\":\"asdasd\",\"position\":\"asdasd\",\"from_year\":\"2021\",\"to_year\":\"2023\",\"responsibilities\":\"ASDASDASDAS\"}]', 'sdfsdfsdfsddf', 'sdfsdfsdfsdfsdf', '[{\"name\":\"Viray Stephen kyle\",\"position\":\"Manager\",\"company\":\"asdasd\",\"contact\":\"09984319585\",\"relationship\":\"colleiges\"}]', 'uploads/applications/APP-202602-31C791/resume.docx', 'uploads/applications/APP-202602-31C791/cover_letter.docx', 0, 'uploads/applications/APP-202602-31C791/photo.jpg', 'new', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, '2026-02-26 19:29:07', '2026-02-26 12:29:07', '2026-02-26 19:31:55'),
(6, 'APP-202602-B9658F', 11, NULL, 'Eunchae', 'Hong', 'stephenviray12@gmail.com', '09984319585', '2004-02-10', 'female', '54 gold', 'QuezonCity NCR', 'metro manila', '1121', 'Manuel L Quezon', '2012', 'Commonwealth Highschool', '2016', 'Electron College', 'TVL', '2020', 'bestlink', 'IT', '2022', '', '', '', '[{\"company\":\"asdasd\",\"position\":\"asdasd\",\"from_year\":\"2021\",\"to_year\":\"2023\",\"responsibilities\":\"dsfsdfsd\"}]', 'sdffdgasd', 'asdasdasgdsfg', '[{\"name\":\"Viray Stephen kyle\",\"position\":\"Manager\",\"company\":\"asdasd\",\"contact\":\"09984319585\",\"relationship\":\"colleiges\"}]', 'uploads/applications/APP-202602-B9658F/resume.docx', 'uploads/applications/APP-202602-B9658F/cover_letter.docx', 1, 'uploads/applications/APP-202602-B9658F/photo.jpg', 'interviewed', 'final_interview', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '\n[2026-02-26 20:32] Status updated to shortlisted based on screening evaluation.\n[2026-02-26 20:33] Interview scheduled: initial on February 27, 2026 at 10:00 AM - Link: https://meet.google.com/dor-rpqx-ben', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, '2026-02-26 19:31:39', '2026-02-26 12:31:39', '2026-02-26 19:49:48');

-- --------------------------------------------------------

--
-- Table structure for table `job_postings`
--

CREATE TABLE `job_postings` (
  `id` int(11) NOT NULL,
  `job_code` varchar(50) NOT NULL,
  `api_position_id` varchar(50) DEFAULT NULL,
  `api_synced` tinyint(1) DEFAULT 0,
  `last_api_sync` timestamp NULL DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `department` enum('driver','warehouse','logistics','admin','management') NOT NULL,
  `employment_type` enum('full_time','part_time','contract','probationary') DEFAULT 'full_time',
  `experience_required` varchar(100) DEFAULT NULL,
  `education_required` varchar(100) DEFAULT NULL,
  `license_required` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `requirements` text DEFAULT NULL,
  `responsibilities` text DEFAULT NULL,
  `salary_min` decimal(10,2) DEFAULT NULL,
  `salary_max` decimal(10,2) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `application_link` varchar(255) DEFAULT NULL,
  `link_expiration` datetime DEFAULT NULL,
  `link_code` varchar(100) DEFAULT NULL,
  `slots_available` int(11) DEFAULT 1,
  `slots_filled` int(11) DEFAULT 0,
  `slots_filled_auto` int(11) DEFAULT 0,
  `status` enum('draft','published','closed','cancelled') DEFAULT 'draft',
  `published_date` date DEFAULT NULL,
  `closing_date` date DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `job_postings`
--

INSERT INTO `job_postings` (`id`, `job_code`, `api_position_id`, `api_synced`, `last_api_sync`, `title`, `department`, `employment_type`, `experience_required`, `education_required`, `license_required`, `description`, `requirements`, `responsibilities`, `salary_min`, `salary_max`, `location`, `application_link`, `link_expiration`, `link_code`, `slots_available`, `slots_filled`, `slots_filled_auto`, `status`, `published_date`, `closing_date`, `created_by`, `created_at`, `updated_at`) VALUES
(10, 'WH-001', 'WH-001', 1, '2026-02-23 09:55:13', 'Warehouse Staff', 'warehouse', 'full_time', '1 year experience', 'High School Graduate', 'Forklift Certification (preferred)', 'Handle loading/unloading of goods and warehouse inventory', 'Inventory Management, Forklift Operation, Packing\n\nEducation: High School Graduate\n\nCertifications: Forklift Certification (preferred)', NULL, NULL, NULL, NULL, 'http://localhost/freight/apply.php?code=fd35968d6ffa841136d3e27e6ecf04b3', '2026-03-25 10:55:13', 'fd35968d6ffa841136d3e27e6ecf04b3', 5, 0, 0, 'published', '2026-02-23', '2026-03-25', 5, '2026-02-23 09:55:13', '2026-02-23 09:55:13'),
(11, 'DSP-001', NULL, 0, NULL, 'Dispatcher', 'logistics', 'full_time', '1 year experience', 'College Graduate', 'None required', 'Coordinate delivery schedules and communicate with drivers', 'Communication, Scheduling, Problem Solving\r\n\r\nEducation: College Graduate\r\n\r\nCertifications: None required', 'test', 18000.00, 20000.00, 'Quezon City', 'http://localhost/freight/apply.php?code=37fdc62c86b7cab9d1de27fdbec774e7', '2026-03-25 10:56:14', '37fdc62c86b7cab9d1de27fdbec774e7', 2, 1, 1, 'published', '2026-02-23', '2026-03-25', 5, '2026-02-23 09:56:14', '2026-02-25 10:10:12'),
(13, 'OPS-001', NULL, 0, NULL, 'Operations Supervisor', 'management', 'full_time', '3 years experience', 'Bachelor\'s Degree', 'Six Sigma Certification (preferred)', 'Oversee daily logistics operations and coordinate with departments', 'Logistics Planning, Team Management, Problem Solving\r\n\r\nEducation: Bachelor\'s Degree\r\n\r\nCertifications: Six Sigma Certification (preferred)', 'test', 28000.00, 40000.00, 'Quezon City', 'http://localhost/freight/apply.php?code=da9f977ccccd3b45de00730f57be9163', '2026-03-25 12:48:14', 'da9f977ccccd3b45de00730f57be9163', 2, 1, 1, 'published', '2026-02-23', '2026-03-29', 5, '2026-02-23 11:48:14', '2026-02-26 20:31:42');

-- --------------------------------------------------------

--
-- Table structure for table `job_posting_links`
--

CREATE TABLE `job_posting_links` (
  `id` int(11) NOT NULL,
  `job_posting_id` int(11) NOT NULL,
  `unique_link` varchar(100) NOT NULL,
  `link_code` varchar(50) NOT NULL,
  `expiration_date` datetime NOT NULL,
  `max_applications` int(11) DEFAULT NULL,
  `current_applications` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `milestones`
--

CREATE TABLE `milestones` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `milestone_type` enum('work_anniversary','birthday','promotion','certification','other') DEFAULT 'work_anniversary',
  `milestone_date` date NOT NULL,
  `years` int(11) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `recognized` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `new_hires`
--

CREATE TABLE `new_hires` (
  `id` int(11) NOT NULL,
  `applicant_id` int(11) NOT NULL,
  `employee_id` varchar(50) NOT NULL,
  `job_posting_id` int(11) DEFAULT NULL,
  `hire_date` date NOT NULL,
  `start_date` date DEFAULT NULL,
  `probation_end_date` date DEFAULT NULL,
  `position` varchar(100) NOT NULL,
  `department` enum('driver','warehouse','logistics','admin','management') NOT NULL,
  `supervisor_id` int(11) DEFAULT NULL,
  `employment_status` enum('probationary','regular','contractual') DEFAULT 'probationary',
  `contract_signed` tinyint(1) DEFAULT 0,
  `contract_signed_date` date DEFAULT NULL,
  `id_submitted` tinyint(1) DEFAULT 0,
  `medical_clearance` tinyint(1) DEFAULT 0,
  `training_completed` tinyint(1) DEFAULT 0,
  `orientation_completed` tinyint(1) DEFAULT 0,
  `equipment_assigned` tinyint(1) DEFAULT 0,
  `system_access_granted` tinyint(1) DEFAULT 0,
  `uniform_size` varchar(20) DEFAULT NULL,
  `assigned_vehicle` varchar(100) DEFAULT NULL,
  `assigned_device` varchar(100) DEFAULT NULL,
  `locker_number` varchar(50) DEFAULT NULL,
  `system_username` varchar(100) DEFAULT NULL,
  `system_role` varchar(100) DEFAULT NULL,
  `status` enum('onboarding','active','terminated','resigned') DEFAULT 'onboarding',
  `onboarding_progress` int(11) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `eom_count` int(11) DEFAULT 0,
  `last_eom_month` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `new_hires`
--

INSERT INTO `new_hires` (`id`, `applicant_id`, `employee_id`, `job_posting_id`, `hire_date`, `start_date`, `probation_end_date`, `position`, `department`, `supervisor_id`, `employment_status`, `contract_signed`, `contract_signed_date`, `id_submitted`, `medical_clearance`, `training_completed`, `orientation_completed`, `equipment_assigned`, `system_access_granted`, `uniform_size`, `assigned_vehicle`, `assigned_device`, `locker_number`, `system_username`, `system_role`, `status`, `onboarding_progress`, `notes`, `created_by`, `created_at`, `updated_at`, `eom_count`, `last_eom_month`) VALUES
(2, 1, '', 11, '2026-02-25', '2026-03-11', '2026-06-26', 'Dispatcher', 'logistics', NULL, 'regular', 0, NULL, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'onboarding', 0, '\n[2026-02-25 18:49] Orientation scheduled on February 26, 2026 at 09:00 AM (Duration: 3 hours) - Link: https://meet.google.com/yva-cckb-pqh\n[2026-02-25 18:50] Orientation scheduled on February 26, 2026 at 09:00 AM (Duration: 4 hours) - Link: https://meet.google.com/yva-cckb-pqh', 5, '2026-02-25 10:10:12', '2026-02-26 17:56:01', 0, NULL),
(5, 3, 'EMP-2026-1515', 13, '2026-02-27', '2026-03-12', '2026-05-27', 'Operations Supervisor', 'management', NULL, 'regular', 0, NULL, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'active', 0, '\n[2026-02-26 21:33] Orientation scheduled on February 27, 2026 at 09:00 AM (Duration: 4 hours) - Link: https://meet.google.com/yva-cckb-pqh', 5, '2026-02-26 20:31:42', '2026-02-26 20:48:51', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text DEFAULT NULL,
  `type` enum('info','success','warning','danger') DEFAULT 'info',
  `module` varchar(50) DEFAULT 'system',
  `is_read` tinyint(1) DEFAULT 0,
  `link` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `module`, `is_read`, `link`, `created_at`) VALUES
(376, 1, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 19:21:01'),
(377, 2, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 19:21:01'),
(378, 5, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 19:21:01'),
(379, 1, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 19:25:28'),
(380, 2, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 19:25:28'),
(381, 5, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 19:25:28'),
(382, 1, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 19:25:57'),
(383, 2, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 19:25:57'),
(384, 5, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 19:25:57'),
(385, 1, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 19:32:16'),
(386, 2, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 19:32:16'),
(387, 5, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 19:32:16'),
(388, 1, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 19:32:26'),
(389, 2, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 19:32:26'),
(390, 5, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 19:32:26'),
(391, 1, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 19:42:37'),
(392, 2, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 19:42:37'),
(393, 5, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 19:42:37'),
(394, 1, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 19:43:37'),
(395, 2, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 19:43:37'),
(396, 5, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 19:43:37'),
(397, 1, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 19:49:22'),
(398, 2, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 19:49:22'),
(399, 5, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 19:49:22'),
(400, 1, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 19:49:25'),
(401, 2, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 19:49:25'),
(402, 5, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 19:49:25'),
(403, 1, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 19:51:07'),
(404, 2, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 19:51:07'),
(405, 5, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 19:51:07'),
(406, 1, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 19:51:13'),
(407, 2, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 19:51:13'),
(408, 5, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 19:51:13'),
(409, 1, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 19:52:51'),
(410, 2, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 19:52:51'),
(411, 5, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 19:52:51'),
(412, 1, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 20:19:26'),
(413, 2, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 20:19:26'),
(414, 5, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 20:19:26'),
(415, 1, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 20:34:06'),
(416, 2, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 20:34:06'),
(417, 5, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 20:34:06'),
(418, 1, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 20:37:20'),
(419, 2, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 20:37:20'),
(420, 5, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 20:37:20'),
(421, 1, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 20:47:58'),
(422, 2, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 20:47:58'),
(423, 5, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 20:47:58'),
(424, 1, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 20:49:31'),
(425, 2, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 20:49:31'),
(426, 5, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 20:49:31'),
(427, 1, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 20:59:48'),
(428, 2, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 20:59:48'),
(429, 5, '3 New Job Positions Available', '3 new positions from API: Cost Analyst, Administrative Assistant, Truck Driver', 'info', 'recruitment', 0, '?page=recruitment&subpage=job-posting', '2026-02-26 20:59:48');

-- --------------------------------------------------------

--
-- Table structure for table `onboarding_access_tokens`
--

CREATE TABLE `onboarding_access_tokens` (
  `id` int(11) NOT NULL,
  `new_hire_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `email` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `onboarding_access_tokens`
--

INSERT INTO `onboarding_access_tokens` (`id`, `new_hire_id`, `token`, `email`, `expires_at`, `used_at`, `created_by`, `created_at`) VALUES
(1, 2, '579a4d4a123e1ffe9fdd5d71b651efb22c915ba9e711641284b72951065b7cd7', 'stephenviray12@gmail.com', '2026-03-04 14:30:08', NULL, 5, '2026-02-25 13:30:08'),
(2, 2, '9109b178bb463b0e38f2ad8319f643805a7ffd4871328794ee2c116df192fbf0', 'stephenviray12@gmail.com', '2026-03-04 14:59:38', NULL, 5, '2026-02-25 13:59:38'),
(3, 2, '52353aa0aa1936978beca410be7f7863208eb7373292a03dc6cc9ccc206990c0', 'stephenviray12@gmail.com', '2026-03-04 15:00:40', NULL, 5, '2026-02-25 14:00:40'),
(4, 2, 'd440a3908b2c41c540c72905dcd084d4ece8c28a5807a1e17d671e316b5f5f3d', 'stephenviray12@gmail.com', '2026-03-04 15:25:32', NULL, 5, '2026-02-25 14:25:32'),
(5, 2, '562710e15c636534c4ba5da9a71d5b64478b2d62fd9d5e8668d4807df72956bd', 'stephenviray12@gmail.com', '2026-03-04 15:26:24', NULL, 5, '2026-02-25 14:26:24'),
(6, 2, 'e9450355f0889022f5fca4f9887c4f899bb4e245ee12e380e2b3adc63ed57c5e', 'stephenviray12@gmail.com', '2026-03-04 15:50:49', '2026-02-25 22:50:57', 5, '2026-02-25 14:50:49'),
(7, 2, '46f06baa5140bb9cccd6708540ca2492bb9998c02647c53d7167885b3030c8ce', 'stephenviray12@gmail.com', '2026-03-04 15:57:25', '2026-02-26 00:12:39', 5, '2026-02-25 14:57:25'),
(8, 2, '80fbf3aec51b7376e25c75dd7a81ae81adfa296085f5addff881a63916331932', 'stephenviray12@gmail.com', '2026-03-04 15:58:09', '2026-02-25 22:58:21', 5, '2026-02-25 14:58:09'),
(9, 2, 'ec6a56d3eba73bbe022d48e7193522399caf35ae9b0be1cf0d1dbfb473d40fd8', 'stephenviray12@gmail.com', '2026-03-04 16:29:34', NULL, 5, '2026-02-25 15:29:34'),
(10, 2, 'f49c134e452bcf5ccc2801badd1a892d5fc6a79beff0c859e8ddb559e33ae3d8', 'stephenviray12@gmail.com', '2026-03-04 17:13:18', '2026-02-26 00:13:37', 5, '2026-02-25 16:13:18'),
(11, 2, 'a9200afa5d1773299ac7752f8ced375f4025774bdaf897ffbd6bed96f54c843f', 'stephenviray12@gmail.com', '2026-03-04 17:19:27', NULL, 5, '2026-02-25 16:19:27'),
(12, 5, '1b6c237090d30e8046fa2680942aa64d429f3d985d8bc5304efe1e48261a9042', 'stephenviray12@gmail.com', '2026-03-05 21:33:10', NULL, 5, '2026-02-26 20:33:10');

-- --------------------------------------------------------

--
-- Table structure for table `onboarding_documents`
--

CREATE TABLE `onboarding_documents` (
  `id` int(11) NOT NULL,
  `new_hire_id` int(11) NOT NULL,
  `document_type` varchar(50) NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `document_number` varchar(100) DEFAULT NULL,
  `issue_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `status` enum('pending','verified','rejected','expired') DEFAULT 'pending',
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `version` int(11) DEFAULT 1,
  `previous_version_id` int(11) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `onboarding_documents`
--

INSERT INTO `onboarding_documents` (`id`, `new_hire_id`, `document_type`, `document_name`, `file_path`, `file_size`, `file_type`, `document_number`, `issue_date`, `expiry_date`, `remarks`, `status`, `verified_by`, `verified_at`, `rejection_reason`, `version`, `previous_version_id`, `uploaded_at`, `updated_at`) VALUES
(1, 2, 'CONTRACT', '274bc028-0d7d-4055-9612-a39b882544e0.jpg', 'uploads/onboarding/documents/newhire_2_CONTRACT_1772024856.jpg', 91455, 'image/jpeg', NULL, '2026-02-25', NULL, '', 'verified', 5, '2026-02-25 13:08:04', NULL, 1, NULL, '2026-02-25 13:07:36', '2026-02-25 13:08:04'),
(2, 2, 'CONTRACT', '274bc028-0d7d-4055-9612-a39b882544e0.jpg', 'uploads/onboarding/documents/newhire_2_CONTRACT_1772024864.jpg', 91455, 'image/jpeg', NULL, '2026-02-25', NULL, '', 'verified', 5, '2026-02-25 14:50:38', NULL, 2, 1, '2026-02-25 13:07:44', '2026-02-25 14:50:38'),
(3, 2, 'POLICE_CLEARANCE', 'Pastil_Report.pdf', '../uploads/onboarding/documents/newhire_2_POLICE_CLEARANCE_1772029698.pdf', 135616, 'application/pdf', '32231446524', '2026-02-25', '2026-03-14', '', 'rejected', 5, '2026-02-25 14:57:25', 'too blurry', 1, NULL, '2026-02-25 14:28:18', '2026-02-25 14:57:25'),
(4, 2, 'SSS_ID', 'Pastil_Report.pdf', '../uploads/onboarding/documents/newhire_2_SSS_ID_1772029722.pdf', 135616, 'application/pdf', '123123123', NULL, NULL, '', 'pending', NULL, NULL, NULL, 1, NULL, '2026-02-25 14:28:42', '2026-02-25 14:28:42'),
(5, 2, 'POLICE_CLEARANCE', '632592057_1429120955626271_7922396535313233536_n.jpg', '../uploads/onboarding/documents/newhire_2_POLICE_CLEARANCE_1772036394.jpg', 68255, 'image/jpeg', '32231446524', '2026-02-25', '2026-03-14', 'this will work?', 'pending', NULL, NULL, NULL, 2, 3, '2026-02-25 16:19:54', '2026-02-25 16:19:54');

-- --------------------------------------------------------

--
-- Table structure for table `onboarding_document_audit`
--

CREATE TABLE `onboarding_document_audit` (
  `id` int(11) NOT NULL,
  `document_id` int(11) NOT NULL,
  `action` enum('upload','update','verify','reject','expire') NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `performed_by` int(11) DEFAULT NULL,
  `performed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `onboarding_document_audit`
--

INSERT INTO `onboarding_document_audit` (`id`, `document_id`, `action`, `old_status`, `new_status`, `remarks`, `performed_by`, `performed_at`) VALUES
(1, 3, 'upload', NULL, 'pending', NULL, NULL, '2026-02-25 14:28:18'),
(2, 4, 'upload', NULL, 'pending', NULL, NULL, '2026-02-25 14:28:42'),
(3, 2, 'verify', 'pending', 'verified', NULL, 5, '2026-02-25 14:50:38'),
(4, 3, 'reject', 'pending', 'rejected', 'too blurry', 5, '2026-02-25 14:57:25'),
(5, 5, 'upload', NULL, 'pending', NULL, NULL, '2026-02-25 16:19:54');

-- --------------------------------------------------------

--
-- Table structure for table `panel_evaluations`
--

CREATE TABLE `panel_evaluations` (
  `id` int(11) NOT NULL,
  `interview_id` int(11) NOT NULL,
  `applicant_id` int(11) NOT NULL,
  `panel_id` int(11) NOT NULL,
  `status` enum('ongoing','submitted','locked') DEFAULT 'ongoing',
  `total_score` decimal(10,2) DEFAULT 0.00,
  `max_score` decimal(10,2) DEFAULT 0.00,
  `final_percentage` decimal(5,2) DEFAULT 0.00,
  `recommendation` enum('hire','final_interview','hold','reject') DEFAULT NULL,
  `strengths` text DEFAULT NULL,
  `weaknesses` text DEFAULT NULL,
  `overall_comments` text DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `panel_evaluations`
--

INSERT INTO `panel_evaluations` (`id`, `interview_id`, `applicant_id`, `panel_id`, `status`, `total_score`, `max_score`, `final_percentage`, `recommendation`, `strengths`, `weaknesses`, `overall_comments`, `submitted_at`, `created_at`, `updated_at`) VALUES
(1, 10, 1, 5, 'submitted', 72.00, 75.00, 96.00, 'final_interview', 'ZXasd', 'asdasd', 'asdasd', '2026-02-24 22:22:22', '2026-02-24 21:56:57', '2026-02-24 22:22:22'),
(2, 14, 3, 5, 'submitted', 22.00, 25.00, 88.00, 'final_interview', 'test', 'testt', 'testtt', '2026-02-24 22:57:06', '2026-02-24 22:45:08', '2026-02-24 22:57:06'),
(3, 15, 4, 5, 'submitted', 24.00, 25.00, 96.00, 'final_interview', '', '', '', '2026-02-26 19:51:53', '2026-02-25 09:53:42', '2026-02-26 19:51:53'),
(4, 21, 6, 5, 'submitted', 71.00, 75.00, 94.67, 'final_interview', '', '', '', '2026-02-26 19:50:40', '2026-02-26 19:33:10', '2026-02-26 19:50:40');

-- --------------------------------------------------------

--
-- Table structure for table `performance_reviews`
--

CREATE TABLE `performance_reviews` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `reviewer_id` int(11) DEFAULT NULL,
  `period_id` int(11) DEFAULT NULL,
  `review_type` enum('probation','monthly','quarterly','annual') DEFAULT 'probation',
  `review_date` date DEFAULT NULL,
  `metrics` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metrics`)),
  `kpi_rating` decimal(3,2) DEFAULT NULL,
  `attendance_rating` decimal(3,2) DEFAULT NULL,
  `quality_rating` decimal(3,2) DEFAULT NULL,
  `teamwork_rating` decimal(3,2) DEFAULT NULL,
  `overall_rating` decimal(3,2) DEFAULT NULL,
  `strengths` text DEFAULT NULL,
  `improvements` text DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `status` enum('draft','submitted','acknowledged') DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `performance_reviews`
--

INSERT INTO `performance_reviews` (`id`, `employee_id`, `reviewer_id`, `period_id`, `review_type`, `review_date`, `metrics`, `kpi_rating`, `attendance_rating`, `quality_rating`, `teamwork_rating`, `overall_rating`, `strengths`, `improvements`, `comments`, `status`, `created_at`, `updated_at`) VALUES
(1, 2, 5, NULL, 'quarterly', '2026-02-26', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'draft', '2026-02-26 14:40:11', '2026-02-26 14:40:11'),
(2, 5, 5, NULL, '', '2026-02-26', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'draft', '2026-02-26 20:36:42', '2026-02-26 20:36:42');

-- --------------------------------------------------------

--
-- Table structure for table `performance_review_kpis`
--

CREATE TABLE `performance_review_kpis` (
  `id` int(11) NOT NULL,
  `template_id` int(11) NOT NULL,
  `category` varchar(100) NOT NULL,
  `kpi_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `target_value` varchar(100) DEFAULT NULL,
  `target_percentage` int(11) DEFAULT NULL,
  `weight` int(11) NOT NULL DEFAULT 0,
  `measurement_unit` varchar(50) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `performance_review_periods`
--

CREATE TABLE `performance_review_periods` (
  `id` int(11) NOT NULL,
  `period_name` varchar(255) NOT NULL,
  `period_type` enum('monthly','quarterly','semi_annual','annual') NOT NULL,
  `year` int(11) NOT NULL,
  `quarter` int(11) DEFAULT NULL,
  `month` int(11) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `review_deadline` date NOT NULL,
  `status` enum('draft','active','completed','archived') DEFAULT 'draft',
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `performance_review_templates`
--

CREATE TABLE `performance_review_templates` (
  `id` int(11) NOT NULL,
  `template_name` varchar(255) NOT NULL,
  `position_id` int(11) DEFAULT NULL,
  `department` enum('driver','warehouse','logistics','admin','management') DEFAULT NULL,
  `description` text DEFAULT NULL,
  `categories` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`categories`)),
  `is_default` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `probation_incidents`
--

CREATE TABLE `probation_incidents` (
  `id` int(11) NOT NULL,
  `probation_record_id` int(11) NOT NULL,
  `incident_date` date NOT NULL,
  `incident_type` enum('attendance','safety','performance','conduct','warning','other') NOT NULL,
  `severity` enum('minor','moderate','major','critical') DEFAULT 'minor',
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `action_taken` text DEFAULT NULL,
  `reported_by` int(11) DEFAULT NULL,
  `warning_issued` tinyint(1) DEFAULT 0,
  `warning_level` int(11) DEFAULT 1,
  `document_path` varchar(255) DEFAULT NULL,
  `status` enum('open','resolved','closed') DEFAULT 'open',
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `probation_kpis`
--

CREATE TABLE `probation_kpis` (
  `id` int(11) NOT NULL,
  `department` enum('driver','warehouse','logistics','admin','management') NOT NULL,
  `position_id` int(11) DEFAULT NULL,
  `kpi_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `target_value` varchar(100) DEFAULT NULL,
  `target_percentage` int(11) DEFAULT NULL,
  `weight` int(11) DEFAULT 100,
  `measurement_unit` varchar(50) DEFAULT NULL,
  `is_required` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `probation_kpis`
--

INSERT INTO `probation_kpis` (`id`, `department`, `position_id`, `kpi_name`, `description`, `target_value`, `target_percentage`, `weight`, `measurement_unit`, `is_required`, `sort_order`, `is_active`, `created_at`) VALUES
(1, 'driver', NULL, 'On-time Delivery Rate', 'Percentage of deliveries completed on or before scheduled time', '95%', 95, 25, 'percentage', 1, 1, 1, '2026-02-26 13:29:34'),
(2, 'driver', NULL, 'Safety Compliance', 'Adherence to safety protocols and regulations', '100%', 100, 20, 'percentage', 1, 2, 1, '2026-02-26 13:29:34'),
(3, 'driver', NULL, 'Accident Record', 'Number of accidents/incidents during probation', '0', 100, 20, 'count', 1, 3, 1, '2026-02-26 13:29:34'),
(4, 'driver', NULL, 'Customer Complaints', 'Number of complaints received', '0', 100, 15, 'count', 1, 4, 1, '2026-02-26 13:29:34'),
(5, 'driver', NULL, 'Attendance & Punctuality', 'Attendance record and punctuality', '95%', 95, 20, 'percentage', 1, 5, 1, '2026-02-26 13:29:34'),
(6, 'warehouse', NULL, 'Picking Accuracy', 'Accuracy in picking orders', '98%', 98, 25, 'percentage', 1, 1, 1, '2026-02-26 13:29:34'),
(7, 'warehouse', NULL, 'Processing Speed', 'Time to process incoming/outgoing shipments', '90%', 90, 20, 'percentage', 1, 2, 1, '2026-02-26 13:29:34'),
(8, 'warehouse', NULL, 'Inventory Error Rate', 'Errors in inventory counts', '<2%', 98, 20, 'percentage', 1, 3, 1, '2026-02-26 13:29:34'),
(9, 'warehouse', NULL, 'Attendance & Punctuality', 'Attendance record and punctuality', '95%', 95, 20, 'percentage', 1, 4, 1, '2026-02-26 13:29:34'),
(10, 'warehouse', NULL, 'Team Cooperation', 'Ability to work with team members', 'Good', 85, 15, 'rating', 1, 5, 1, '2026-02-26 13:29:34'),
(11, 'logistics', NULL, 'Route Optimization', 'Efficiency in route planning', '90%', 90, 20, 'percentage', 1, 1, 1, '2026-02-26 13:29:34'),
(12, 'logistics', NULL, 'Dispatch Accuracy', 'Accuracy in dispatching', '98%', 98, 25, 'percentage', 1, 2, 1, '2026-02-26 13:29:34'),
(13, 'logistics', NULL, 'Communication', 'Communication with drivers and clients', 'Good', 90, 20, 'rating', 1, 3, 1, '2026-02-26 13:29:34'),
(14, 'logistics', NULL, 'Problem Resolution', 'Speed in resolving issues', '85%', 85, 20, 'percentage', 1, 4, 1, '2026-02-26 13:29:34'),
(15, 'logistics', NULL, 'Documentation', 'Accuracy of documentation', '98%', 98, 15, 'percentage', 1, 5, 1, '2026-02-26 13:29:34'),
(16, 'admin', NULL, 'Task Completion Rate', 'Percentage of tasks completed on time', '95%', 95, 25, 'percentage', 1, 1, 1, '2026-02-26 13:29:34'),
(17, 'admin', NULL, 'Accuracy of Work', 'Error rate in administrative tasks', '98%', 98, 25, 'percentage', 1, 2, 1, '2026-02-26 13:29:34'),
(18, 'admin', NULL, 'Responsiveness', 'Response time to requests', '90%', 90, 20, 'percentage', 1, 3, 1, '2026-02-26 13:29:34'),
(19, 'admin', NULL, 'Attendance & Punctuality', 'Attendance record and punctuality', '95%', 95, 20, 'percentage', 1, 4, 1, '2026-02-26 13:29:34'),
(20, 'admin', NULL, 'Initiative', 'Shows initiative and proactive behavior', 'Good', 85, 10, 'rating', 1, 5, 1, '2026-02-26 13:29:34'),
(21, 'management', NULL, 'Team Performance', 'Performance of supervised team', '85%', 85, 25, 'percentage', 1, 1, 1, '2026-02-26 13:29:34'),
(22, 'management', NULL, 'Decision Making', 'Quality of decisions made', 'Good', 90, 20, 'rating', 1, 2, 1, '2026-02-26 13:29:34'),
(23, 'management', NULL, 'Process Improvement', 'Implemented improvements', '2', 80, 15, 'count', 1, 3, 1, '2026-02-26 13:29:34'),
(24, 'management', NULL, 'Communication', 'Communication with stakeholders', 'Good', 90, 20, 'rating', 1, 4, 1, '2026-02-26 13:29:34'),
(25, 'management', NULL, 'Leadership', 'Demonstrated leadership skills', 'Good', 85, 20, 'rating', 1, 5, 1, '2026-02-26 13:29:34');

-- --------------------------------------------------------

--
-- Table structure for table `probation_kpi_results`
--

CREATE TABLE `probation_kpi_results` (
  `id` int(11) NOT NULL,
  `probation_record_id` int(11) NOT NULL,
  `kpi_id` int(11) NOT NULL,
  `review_phase` enum('30_day','60_day','90_day','final') DEFAULT 'final',
  `actual_value` varchar(100) DEFAULT NULL,
  `actual_percentage` decimal(5,2) DEFAULT NULL,
  `rating` int(11) DEFAULT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `score` decimal(5,2) DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `supervisor_comments` text DEFAULT NULL,
  `evaluated_by` int(11) DEFAULT NULL,
  `evaluated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `probation_kpi_results`
--

INSERT INTO `probation_kpi_results` (`id`, `probation_record_id`, `kpi_id`, `review_phase`, `actual_value`, `actual_percentage`, `rating`, `score`, `comments`, `supervisor_comments`, `evaluated_by`, `evaluated_at`, `created_at`, `updated_at`) VALUES
(6, 2, 21, 'final', NULL, NULL, 5, NULL, '', NULL, 6, '2026-02-26 20:48:32', '2026-02-26 20:48:32', '2026-02-26 20:48:32'),
(7, 2, 22, 'final', NULL, NULL, 5, NULL, '', NULL, 6, '2026-02-26 20:48:32', '2026-02-26 20:48:32', '2026-02-26 20:48:32'),
(8, 2, 23, 'final', NULL, NULL, 5, NULL, '', NULL, 6, '2026-02-26 20:48:32', '2026-02-26 20:48:32', '2026-02-26 20:48:32'),
(9, 2, 24, 'final', NULL, NULL, 5, NULL, '', NULL, 6, '2026-02-26 20:48:32', '2026-02-26 20:48:32', '2026-02-26 20:48:32'),
(10, 2, 25, 'final', NULL, NULL, 5, NULL, '', NULL, 6, '2026-02-26 20:48:32', '2026-02-26 20:48:32', '2026-02-26 20:48:32');

-- --------------------------------------------------------

--
-- Table structure for table `probation_records`
--

CREATE TABLE `probation_records` (
  `id` int(11) NOT NULL,
  `new_hire_id` int(11) NOT NULL,
  `applicant_id` int(11) NOT NULL,
  `probation_start_date` date NOT NULL,
  `probation_end_date` date NOT NULL,
  `probation_duration_days` int(11) DEFAULT 90,
  `status` enum('ongoing','completed','extended','failed','terminated') DEFAULT 'ongoing',
  `extended_days` int(11) DEFAULT 0,
  `extension_reason` text DEFAULT NULL,
  `final_decision` enum('confirm','extend','terminate','pending') DEFAULT 'pending',
  `decision_date` date DEFAULT NULL,
  `decision_made_by` int(11) DEFAULT NULL,
  `decision_notes` text DEFAULT NULL,
  `hr_notes` text DEFAULT NULL,
  `supervisor_notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `probation_records`
--

INSERT INTO `probation_records` (`id`, `new_hire_id`, `applicant_id`, `probation_start_date`, `probation_end_date`, `probation_duration_days`, `status`, `extended_days`, `extension_reason`, `final_decision`, `decision_date`, `decision_made_by`, `decision_notes`, `hr_notes`, `supervisor_notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 2, 1, '2026-02-26', '2026-06-26', 120, 'completed', 0, NULL, 'confirm', '2026-02-26', 5, 'asdasdasdasdasd', NULL, NULL, 5, '2026-02-26 16:58:18', '2026-02-26 18:08:08'),
(2, 5, 3, '2026-02-26', '2026-05-27', 90, 'completed', 0, NULL, 'confirm', '2026-02-26', 5, 'asdasd', 'asdasdads', NULL, 5, '2026-02-26 20:46:46', '2026-02-26 20:48:51');

-- --------------------------------------------------------

--
-- Table structure for table `probation_reminders`
--

CREATE TABLE `probation_reminders` (
  `id` int(11) NOT NULL,
  `probation_record_id` int(11) NOT NULL,
  `reminder_type` enum('30_day','60_day','90_day','7_day_before','extension','decision') NOT NULL,
  `scheduled_date` date NOT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `sent_to` int(11) DEFAULT NULL,
  `status` enum('pending','sent','acknowledged') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `probation_reviews`
--

CREATE TABLE `probation_reviews` (
  `id` int(11) NOT NULL,
  `probation_record_id` int(11) NOT NULL,
  `review_phase` enum('30_day','60_day','90_day','final','extension') NOT NULL,
  `review_date` date NOT NULL,
  `reviewer_id` int(11) DEFAULT NULL,
  `overall_score` decimal(5,2) DEFAULT NULL,
  `max_score` decimal(5,2) DEFAULT NULL,
  `percentage_score` decimal(5,2) DEFAULT NULL,
  `strengths` text DEFAULT NULL,
  `weaknesses` text DEFAULT NULL,
  `improvement_areas` text DEFAULT NULL,
  `supervisor_feedback` text DEFAULT NULL,
  `hr_feedback` text DEFAULT NULL,
  `recommendation` enum('continue','extend','terminate','confirm') DEFAULT 'continue',
  `status` enum('draft','submitted','acknowledged') DEFAULT 'draft',
  `submitted_at` timestamp NULL DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `probation_reviews`
--

INSERT INTO `probation_reviews` (`id`, `probation_record_id`, `review_phase`, `review_date`, `reviewer_id`, `overall_score`, `max_score`, `percentage_score`, `strengths`, `weaknesses`, `improvement_areas`, `supervisor_feedback`, `hr_feedback`, `recommendation`, `status`, `submitted_at`, `created_by`, `created_at`, `updated_at`) VALUES
(2, 2, 'final', '2026-02-26', 6, 5.00, 5.00, 100.00, 'asdasdasdasd', 'asdasdasd', '', NULL, NULL, 'confirm', 'submitted', NULL, NULL, '2026-02-26 20:48:32', '2026-02-26 20:48:32');

-- --------------------------------------------------------

--
-- Table structure for table `recognitions`
--

CREATE TABLE `recognitions` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `recognizer_id` int(11) DEFAULT NULL,
  `recognition_type` enum('employee_month','spotlight','kudos','milestone','achievement') DEFAULT 'kudos',
  `title` varchar(255) NOT NULL,
  `message` text DEFAULT NULL,
  `points` int(11) DEFAULT 0,
  `badge` varchar(100) DEFAULT NULL,
  `published` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `recognition_categories`
--

CREATE TABLE `recognition_categories` (
  `id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `badge_color` varchar(20) DEFAULT 'primary',
  `description` text DEFAULT NULL,
  `allowed_roles` varchar(255) DEFAULT 'hr,supervisor,system',
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recognition_categories`
--

INSERT INTO `recognition_categories` (`id`, `category_name`, `icon`, `badge_color`, `description`, `allowed_roles`, `is_active`, `sort_order`, `created_at`) VALUES
(1, 'Safety Excellence', 'fa-shield-alt', 'success', 'Outstanding safety compliance and incident prevention', 'hr,supervisor,system', 1, 1, '2026-02-26 19:07:43'),
(2, 'Performance Award', 'fa-chart-line', 'primary', 'Exceptional performance and KPI achievement', 'hr,supervisor,system', 1, 2, '2026-02-26 19:07:43'),
(3, 'Customer Service', 'fa-headset', 'info', 'Excellent customer feedback and service', 'hr,supervisor,system', 1, 3, '2026-02-26 19:07:43'),
(4, 'Team Player', 'fa-users', 'warning', 'Outstanding collaboration and teamwork', 'hr,supervisor,peer', 1, 4, '2026-02-26 19:07:43'),
(5, 'Innovation', 'fa-lightbulb', 'purple', 'Process improvement and innovative ideas', 'hr,supervisor', 1, 5, '2026-02-26 19:07:43'),
(6, 'Attendance Star', 'fa-calendar-check', 'success', 'Perfect attendance and punctuality', 'system', 1, 6, '2026-02-26 19:07:43'),
(7, 'Safety Milestone', 'fa-trophy', 'gold', 'Reached significant safety milestone', 'system', 1, 7, '2026-02-26 19:07:43'),
(8, 'Peer Recognition', 'fa-heart', 'danger', 'Recognized by fellow employees', 'peer', 1, 8, '2026-02-26 19:07:43');

-- --------------------------------------------------------

--
-- Table structure for table `recognition_comments`
--

CREATE TABLE `recognition_comments` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `is_approved` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `recognition_likes`
--

CREATE TABLE `recognition_likes` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `recognition_mentions`
--

CREATE TABLE `recognition_mentions` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `recognition_posts`
--

CREATE TABLE `recognition_posts` (
  `id` int(11) NOT NULL,
  `post_number` varchar(50) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `recognition_type` enum('employee_month','supervisor','peer','system','safety','milestone') NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `achievement_details` text DEFAULT NULL,
  `badge_color` varchar(20) DEFAULT 'primary',
  `icon` varchar(50) DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `attachment_type` varchar(50) DEFAULT NULL,
  `posted_by` int(11) NOT NULL,
  `poster_role` enum('hr','supervisor','system','peer') DEFAULT 'system',
  `visibility` enum('company','department','managers') DEFAULT 'company',
  `is_pinned` tinyint(1) DEFAULT 0,
  `is_approved` tinyint(1) DEFAULT 1,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `view_count` int(11) DEFAULT 0,
  `like_count` int(11) DEFAULT 0,
  `comment_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recognition_posts`
--

INSERT INTO `recognition_posts` (`id`, `post_number`, `employee_id`, `recognition_type`, `category`, `title`, `description`, `achievement_details`, `badge_color`, `icon`, `attachment_path`, `attachment_type`, `posted_by`, `poster_role`, `visibility`, `is_pinned`, `is_approved`, `approved_by`, `approved_at`, `view_count`, `like_count`, `comment_count`, `created_at`, `updated_at`) VALUES
(1, 'PE-202602-0001', 2, 'peer', 'Team Player', 'yeheyyy', 'asdasdasdasdasd', 'adadasdasd', 'primary', NULL, 'uploads/recognition/recog_69a09a4ab61b8_1772132938.jpg', NULL, 5, 'peer', 'department', 0, 1, NULL, NULL, 0, 0, 0, '2026-02-26 19:08:58', '2026-02-26 19:08:58');

-- --------------------------------------------------------

--
-- Table structure for table `recognition_settings`
--

CREATE TABLE `recognition_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recognition_settings`
--

INSERT INTO `recognition_settings` (`id`, `setting_key`, `setting_value`, `description`, `updated_by`, `updated_at`) VALUES
(1, 'require_approval', '0', 'Require approval before posts go live (1=Yes, 0=No)', NULL, '2026-02-26 19:07:43'),
(2, 'allow_peer_recognition', '1', 'Allow employees to recognize peers (1=Yes, 0=No)', NULL, '2026-02-26 19:07:43'),
(3, 'peer_recognition_limit', '3', 'Maximum peer recognitions per employee per month', NULL, '2026-02-26 19:07:43'),
(4, 'allow_comments', '1', 'Allow comments on recognition posts (1=Yes, 0=No)', NULL, '2026-02-26 19:07:43'),
(5, 'moderate_comments', '1', 'Moderate comments before publishing (1=Yes, 0=No)', NULL, '2026-02-26 19:07:43'),
(6, 'auto_post_kpi_threshold', '95', 'Auto-post when KPI reaches this percentage', NULL, '2026-02-26 19:07:43'),
(7, 'auto_post_safety_months', '6', 'Auto-post after X months with zero incidents', NULL, '2026-02-26 19:07:43'),
(8, 'allow_image_attachments', '1', 'Allow image uploads with posts (1=Yes, 0=No)', NULL, '2026-02-26 19:07:43'),
(9, 'max_attachments_size', '5', 'Maximum attachment size in MB', NULL, '2026-02-26 19:07:43');

-- --------------------------------------------------------

--
-- Table structure for table `required_onboarding_documents`
--

CREATE TABLE `required_onboarding_documents` (
  `id` int(11) NOT NULL,
  `document_code` varchar(50) NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category` enum('legal','employment','medical','role_specific','other') DEFAULT 'employment',
  `is_required` tinyint(1) DEFAULT 1,
  `requires_expiry` tinyint(1) DEFAULT 0,
  `requires_document_number` tinyint(1) DEFAULT 1,
  `requires_issue_date` tinyint(1) DEFAULT 0,
  `applicable_departments` text DEFAULT NULL,
  `applicable_positions` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `required_onboarding_documents`
--

INSERT INTO `required_onboarding_documents` (`id`, `document_code`, `document_name`, `description`, `category`, `is_required`, `requires_expiry`, `requires_document_number`, `requires_issue_date`, `applicable_departments`, `applicable_positions`, `sort_order`, `is_active`, `created_at`) VALUES
(1, 'CONTRACT', 'Signed Employment Contract', 'Signed copy of employment contract', 'legal', 1, 0, 0, 1, NULL, NULL, 1, 1, '2026-02-25 12:58:39'),
(2, 'SSS_ID', 'SSS ID or Number', 'Social Security System ID or number', 'legal', 1, 0, 1, 0, NULL, NULL, 2, 1, '2026-02-25 12:58:39'),
(3, 'PHILHEALTH_ID', 'PhilHealth ID or Number', 'PhilHealth ID or MDR number', 'legal', 1, 0, 1, 0, NULL, NULL, 3, 1, '2026-02-25 12:58:39'),
(4, 'PAGIBIG_ID', 'Pag-IBIG ID or Number', 'Pag-IBIG Loyalty Card or number', 'legal', 1, 0, 1, 0, NULL, NULL, 4, 1, '2026-02-25 12:58:39'),
(5, 'TIN_ID', 'TIN ID or Number', 'Tax Identification Number', 'legal', 1, 0, 1, 0, NULL, NULL, 5, 1, '2026-02-25 12:58:39'),
(6, 'BANK_INFO', 'Bank Account Details', 'Bank account information for payroll', 'employment', 1, 0, 0, 0, NULL, NULL, 6, 1, '2026-02-25 12:58:39'),
(7, 'MEDICAL', 'Medical Certificate', 'Medical clearance certificate', 'medical', 1, 1, 0, 1, NULL, NULL, 7, 1, '2026-02-25 12:58:39'),
(8, 'DRUG_TEST', 'Drug Test Result', 'Negative drug test result', 'medical', 1, 1, 0, 1, NULL, NULL, 8, 1, '2026-02-25 12:58:39'),
(9, 'NBI_CLEARANCE', 'NBI Clearance', 'National Bureau of Investigation clearance', 'legal', 1, 1, 1, 1, NULL, NULL, 9, 1, '2026-02-25 12:58:39'),
(10, 'POLICE_CLEARANCE', 'Police Clearance', 'Barangay/Police clearance certificate', 'legal', 0, 1, 1, 1, NULL, NULL, 10, 1, '2026-02-25 12:58:39'),
(11, 'PROF_DRIVERS_LICENSE', 'Professional Driver\'s License', 'Valid professional driver\'s license', 'role_specific', 0, 1, 1, 1, 'driver', NULL, 11, 1, '2026-02-25 12:58:39'),
(12, 'DEFENSIVE_DRIVING_CERT', 'Defensive Driving Certificate', 'Certificate of defensive driving training', 'role_specific', 0, 1, 0, 1, 'driver', NULL, 12, 1, '2026-02-25 12:58:39'),
(13, 'SAFETY_TRAINING_CERT', 'Safety Training Certificate', 'Safety orientation certificate', 'role_specific', 0, 1, 0, 1, 'warehouse,driver', NULL, 13, 1, '2026-02-25 12:58:39'),
(14, 'FORKLIFT_CERT', 'Forklift Operation Certificate', 'Forklift operator certification', 'role_specific', 0, 1, 0, 1, 'warehouse', NULL, 14, 1, '2026-02-25 12:58:39'),
(15, 'HAZMAT_CERT', 'Hazmat Certification', 'Hazardous materials handling certification', 'role_specific', 0, 1, 0, 1, 'driver,warehouse', NULL, 15, 1, '2026-02-25 12:58:39');

-- --------------------------------------------------------

--
-- Table structure for table `rewards_catalog`
--

CREATE TABLE `rewards_catalog` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `points_required` int(11) NOT NULL,
  `category` enum('gift_card','merchandise','bonus','privilege','other') DEFAULT 'gift_card',
  `stock` int(11) DEFAULT 999,
  `image_path` varchar(255) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rewards_catalog`
--

INSERT INTO `rewards_catalog` (`id`, `name`, `description`, `points_required`, `category`, `stock`, `image_path`, `active`, `created_at`) VALUES
(1, '₱500 GCash Load', 'GCash load worth 500 pesos', 500, 'gift_card', 999, NULL, 1, '2026-02-23 07:10:28'),
(2, '₱1,000 Shopping Voucher', 'SM or Robinsons gift certificate', 1000, 'gift_card', 999, NULL, 1, '2026-02-23 07:10:28'),
(3, 'Free Day Off', 'One day paid leave', 800, 'privilege', 999, NULL, 1, '2026-02-23 07:10:28'),
(4, 'Company Jacket', 'Official company jacket', 600, 'merchandise', 999, NULL, 1, '2026-02-23 07:10:28'),
(5, 'Fuel Subsidy', '₱1,000 fuel allowance', 1200, 'bonus', 999, NULL, 1, '2026-02-23 07:10:28'),
(6, 'Early Release', 'Go home 2 hours early on Friday', 300, 'privilege', 999, NULL, 1, '2026-02-23 07:10:28');

-- --------------------------------------------------------

--
-- Table structure for table `screening_evaluations`
--

CREATE TABLE `screening_evaluations` (
  `id` int(11) NOT NULL,
  `applicant_id` int(11) NOT NULL,
  `screening_score` int(11) DEFAULT NULL,
  `qualification_match` int(11) DEFAULT NULL,
  `screening_notes` text DEFAULT NULL,
  `evaluated_by` int(11) DEFAULT NULL,
  `evaluation_date` datetime DEFAULT NULL,
  `screening_result` enum('pass','fail','pending') DEFAULT 'pending',
  `status_updated_to` varchar(50) DEFAULT NULL,
  `ai_analysis` text DEFAULT NULL,
  `ai_recommendation` enum('strong_reject','reject','maybe','consider','strong_hire') DEFAULT NULL,
  `ai_confidence` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `screening_evaluations`
--

INSERT INTO `screening_evaluations` (`id`, `applicant_id`, `screening_score`, `qualification_match`, `screening_notes`, `evaluated_by`, `evaluation_date`, `screening_result`, `status_updated_to`, `ai_analysis`, `ai_recommendation`, `ai_confidence`, `created_at`, `updated_at`) VALUES
(1, 1, 90, 87, 'solid', 5, '2026-02-23 23:09:16', 'pass', NULL, NULL, NULL, NULL, '2026-02-23 14:54:11', '2026-02-23 15:09:16'),
(2, 3, 100, 100, 'asdasd', 5, '2026-02-25 06:44:29', 'pass', NULL, NULL, NULL, NULL, '2026-02-24 22:44:29', '2026-02-24 22:44:29'),
(3, 4, 90, 96, 'ASDASDASD', 5, '2026-02-25 17:53:17', 'pass', NULL, NULL, NULL, NULL, '2026-02-25 09:53:17', '2026-02-25 09:53:17'),
(4, 6, 100, 92, 'asdasdasd', 5, '2026-02-27 03:32:26', 'pass', NULL, NULL, NULL, NULL, '2026-02-26 19:32:26', '2026-02-26 19:32:26');

-- --------------------------------------------------------

--
-- Table structure for table `training_modules`
--

CREATE TABLE `training_modules` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `department` enum('driver','warehouse','logistics','admin','management','all') DEFAULT 'all',
  `content` text DEFAULT NULL,
  `duration_hours` int(11) DEFAULT NULL,
  `required` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `training_modules`
--

INSERT INTO `training_modules` (`id`, `title`, `description`, `department`, `content`, `duration_hours`, `required`, `created_by`, `created_at`) VALUES
(1, 'Driver Safety Orientation', 'Basic safety protocols for drivers', 'driver', NULL, 4, 1, 5, '2026-02-23 07:10:28'),
(2, 'Defensive Driving Techniques', 'Advanced driving safety course', 'driver', NULL, 8, 1, 5, '2026-02-23 07:10:28'),
(3, 'Warehouse Safety Guidelines', 'Safety procedures in warehouse', 'warehouse', NULL, 3, 1, 5, '2026-02-23 07:10:28'),
(4, 'Forklift Operation Basics', 'Basic forklift operation training', 'warehouse', NULL, 6, 1, 5, '2026-02-23 07:10:28'),
(5, 'Company Policies and Code of Conduct', 'HR orientation for all employees', 'all', NULL, 2, 1, 5, '2026-02-23 07:10:28'),
(6, 'Logistics Software Training', 'How to use our tracking system', 'logistics', NULL, 4, 1, 5, '2026-02-23 07:10:28');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `company_name` varchar(100) DEFAULT NULL,
  `role` enum('admin','dispatcher','driver','customer','manager') DEFAULT 'customer',
  `phone` varchar(20) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `full_name`, `company_name`, `role`, `phone`, `profile_picture`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@freight.com', '$2y$10$YourHashedPasswordHere', 'System Administrator', 'Freight Management Inc', 'admin', '123-456-7890', NULL, '2026-02-23 06:25:21', '2026-02-23 06:25:21'),
(2, 'dispatcher1', 'dispatch@freight.com', '$2y$10$YourHashedPasswordHere', 'Juan Dela Cruz', 'Freight Management Inc', 'dispatcher', '123-456-7891', NULL, '2026-02-23 06:25:21', '2026-02-23 06:25:21'),
(3, 'driver1', 'driver@freight.com', '$2y$10$YourHashedPasswordHere', 'Pedro Santos', NULL, 'driver', '123-456-7892', NULL, '2026-02-23 06:25:21', '2026-02-23 06:25:21'),
(4, 'customer1', 'customer@company.com', '$2y$10$YourHashedPasswordHere', 'Maria Garcia', 'ABC Trading', 'customer', '123-456-7893', NULL, '2026-02-23 06:25:21', '2026-02-23 06:25:21'),
(5, 'yukki', 'stephenviray12@gmail.com', '$2y$10$OznauUHMJNSG4G8ifNBbF.GyckV6WrT0AllBvidyIXY01Id1jWoQC', 'Stephen Kyle Viray', 'Freight Management HR 1', 'admin', '0998 431 9585', NULL, '2026-02-23 06:39:20', '2026-02-23 08:16:26'),
(6, 'yokai', 'yenajigumina12@gmail.com', '$2y$10$maKNttFLznxomnPiqKDsm.mzTzrhyIIt.Ikht2bTKGccM1AzXWrPi', 'Viray Stephen kyle', 'Freight Management HR 1', 'manager', '09984319585', NULL, '2026-02-26 16:20:58', '2026-02-26 16:21:17');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `applicants`
--
ALTER TABLE `applicants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `application_number` (`application_number`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_position` (`position_applied`);

--
-- Indexes for table `applicant_documents`
--
ALTER TABLE `applicant_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `applicant_id` (`applicant_id`),
  ADD KEY `verified_by` (`verified_by`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_attendance` (`employee_id`,`date`);

--
-- Indexes for table `communication_log`
--
ALTER TABLE `communication_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `sent_by` (`sent_by`),
  ADD KEY `communication_log_ibfk_1` (`applicant_id`);

--
-- Indexes for table `document_requests`
--
ALTER TABLE `document_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `new_hire_id` (`new_hire_id`),
  ADD KEY `requested_by` (`requested_by`);

--
-- Indexes for table `employee_rewards`
--
ALTER TABLE `employee_rewards`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `reward_id` (`reward_id`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `employee_training`
--
ALTER TABLE `employee_training`
  ADD PRIMARY KEY (`id`),
  ADD KEY `new_hire_id` (`new_hire_id`),
  ADD KEY `training_id` (`training_id`);

--
-- Indexes for table `eom_criteria`
--
ALTER TABLE `eom_criteria`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `eom_nominations`
--
ALTER TABLE `eom_nominations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `nominated_by` (`nominated_by`),
  ADD KEY `month` (`month`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `eom_settings`
--
ALTER TABLE `eom_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `eom_votes`
--
ALTER TABLE `eom_votes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_vote` (`nomination_id`,`voter_id`),
  ADD KEY `voter_id` (`voter_id`);

--
-- Indexes for table `eom_winners`
--
ALTER TABLE `eom_winners`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_month_winner` (`month`,`employee_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `nomination_id` (`nomination_id`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `evaluation_categories`
--
ALTER TABLE `evaluation_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `template_id` (`template_id`);

--
-- Indexes for table `evaluation_questions`
--
ALTER TABLE `evaluation_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `evaluation_responses`
--
ALTER TABLE `evaluation_responses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_response` (`evaluation_id`,`question_id`),
  ADD KEY `question_id` (`question_id`);

--
-- Indexes for table `evaluation_templates`
--
ALTER TABLE `evaluation_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `position_id` (`position_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `feedback_notes`
--
ALTER TABLE `feedback_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `module` (`module`),
  ADD KEY `type` (`type`),
  ADD KEY `reference_id` (`reference_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `created_for` (`created_for`),
  ADD KEY `for_type` (`for_type`),
  ADD KEY `is_important` (`is_important`),
  ADD KEY `is_resolved` (`is_resolved`);

--
-- Indexes for table `feedback_reactions`
--
ALTER TABLE `feedback_reactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_reaction` (`feedback_id`,`user_id`,`reaction`),
  ADD KEY `feedback_id` (`feedback_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `final_evaluation_questions`
--
ALTER TABLE `final_evaluation_questions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `final_evaluation_responses`
--
ALTER TABLE `final_evaluation_responses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_response` (`final_interview_id`,`question_id`),
  ADD KEY `question_id` (`question_id`);

--
-- Indexes for table `final_interviews`
--
ALTER TABLE `final_interviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `applicant_id` (`applicant_id`),
  ADD KEY `job_posting_id` (`job_posting_id`),
  ADD KEY `interviewer_id` (`interviewer_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `incentive_budget_tracking`
--
ALTER TABLE `incentive_budget_tracking`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_period` (`year`,`quarter`,`month`,`department`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `incentive_eligibility`
--
ALTER TABLE `incentive_eligibility`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_employee_period` (`employee_id`,`program_id`,`period_start`),
  ADD KEY `program_id` (`program_id`),
  ADD KEY `status` (`status`),
  ADD KEY `supervisor_id` (`supervisor_id`),
  ADD KEY `hr_id` (`hr_id`);

--
-- Indexes for table `incentive_payouts`
--
ALTER TABLE `incentive_payouts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payout_number` (`payout_number`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `eligibility_id` (`eligibility_id`),
  ADD KEY `program_id` (`program_id`),
  ADD KEY `status` (`status`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `processed_by` (`processed_by`),
  ADD KEY `paid_by` (`paid_by`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `incentive_points`
--
ALTER TABLE `incentive_points`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`);

--
-- Indexes for table `incentive_points_transactions`
--
ALTER TABLE `incentive_points_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `reference_type` (`reference_type`),
  ADD KEY `reference_id` (`reference_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `incentive_programs`
--
ALTER TABLE `incentive_programs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `program_code` (`program_code`),
  ADD KEY `category` (`category`),
  ADD KEY `status` (`status`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `incentive_redeemable_items`
--
ALTER TABLE `incentive_redeemable_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `item_code` (`item_code`),
  ADD KEY `category` (`category`),
  ADD KEY `is_active` (`is_active`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `incentive_redemptions`
--
ALTER TABLE `incentive_redemptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `redemption_number` (`redemption_number`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `status` (`status`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `fulfilled_by` (`fulfilled_by`);

--
-- Indexes for table `interviews`
--
ALTER TABLE `interviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `job_posting_id` (`job_posting_id`),
  ADD KEY `interviewer_id` (`interviewer_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `interviews_ibfk_1` (`applicant_id`);

--
-- Indexes for table `job_applications`
--
ALTER TABLE `job_applications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `application_number` (`application_number`),
  ADD KEY `job_posting_id` (`job_posting_id`),
  ADD KEY `job_posting_link_id` (`job_posting_link_id`),
  ADD KEY `email` (`email`),
  ADD KEY `status` (`status`),
  ADD KEY `fk_selected_by` (`selected_by`),
  ADD KEY `idx_applied_at` (`applied_at`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_hired_date` (`hired_date`);

--
-- Indexes for table `job_postings`
--
ALTER TABLE `job_postings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `job_code` (`job_code`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_department` (`department`);

--
-- Indexes for table `job_posting_links`
--
ALTER TABLE `job_posting_links`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_link` (`unique_link`),
  ADD UNIQUE KEY `link_code` (`link_code`),
  ADD KEY `job_posting_id` (`job_posting_id`),
  ADD KEY `is_active` (`is_active`),
  ADD KEY `expiration_date` (`expiration_date`);

--
-- Indexes for table `milestones`
--
ALTER TABLE `milestones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `new_hires`
--
ALTER TABLE `new_hires`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `applicant_id` (`applicant_id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`),
  ADD KEY `job_posting_id` (`job_posting_id`),
  ADD KEY `supervisor_id` (`supervisor_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_read` (`user_id`,`is_read`);

--
-- Indexes for table `onboarding_access_tokens`
--
ALTER TABLE `onboarding_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `new_hire_id` (`new_hire_id`),
  ADD KEY `expires_at` (`expires_at`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `onboarding_documents`
--
ALTER TABLE `onboarding_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `new_hire_id` (`new_hire_id`),
  ADD KEY `verified_by` (`verified_by`),
  ADD KEY `status` (`status`),
  ADD KEY `expiry_date` (`expiry_date`),
  ADD KEY `previous_version_id` (`previous_version_id`);

--
-- Indexes for table `onboarding_document_audit`
--
ALTER TABLE `onboarding_document_audit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `document_id` (`document_id`),
  ADD KEY `performed_by` (`performed_by`);

--
-- Indexes for table `panel_evaluations`
--
ALTER TABLE `panel_evaluations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_evaluation` (`interview_id`,`panel_id`),
  ADD KEY `applicant_id` (`applicant_id`),
  ADD KEY `panel_id` (`panel_id`);

--
-- Indexes for table `performance_reviews`
--
ALTER TABLE `performance_reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `reviewer_id` (`reviewer_id`),
  ADD KEY `period_id` (`period_id`);

--
-- Indexes for table `probation_incidents`
--
ALTER TABLE `probation_incidents`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `probation_kpis`
--
ALTER TABLE `probation_kpis`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `probation_kpi_results`
--
ALTER TABLE `probation_kpi_results`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `probation_records`
--
ALTER TABLE `probation_records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_new_hire` (`new_hire_id`),
  ADD KEY `applicant_id` (`applicant_id`),
  ADD KEY `decision_made_by` (`decision_made_by`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_end_date` (`probation_end_date`),
  ADD KEY `idx_decision` (`final_decision`);

--
-- Indexes for table `probation_reviews`
--
ALTER TABLE `probation_reviews`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `recognitions`
--
ALTER TABLE `recognitions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `recognizer_id` (`recognizer_id`);

--
-- Indexes for table `recognition_categories`
--
ALTER TABLE `recognition_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `category_name` (`category_name`);

--
-- Indexes for table `recognition_comments`
--
ALTER TABLE `recognition_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_id` (`post_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `recognition_likes`
--
ALTER TABLE `recognition_likes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_like` (`post_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `recognition_mentions`
--
ALTER TABLE `recognition_mentions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_mention` (`post_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `recognition_posts`
--
ALTER TABLE `recognition_posts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `post_number` (`post_number`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `posted_by` (`posted_by`),
  ADD KEY `recognition_type` (`recognition_type`),
  ADD KEY `is_pinned` (`is_pinned`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `recognition_settings`
--
ALTER TABLE `recognition_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `required_onboarding_documents`
--
ALTER TABLE `required_onboarding_documents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `document_code` (`document_code`),
  ADD KEY `is_active` (`is_active`),
  ADD KEY `category` (`category`);

--
-- Indexes for table `rewards_catalog`
--
ALTER TABLE `rewards_catalog`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `screening_evaluations`
--
ALTER TABLE `screening_evaluations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `evaluated_by` (`evaluated_by`),
  ADD KEY `idx_applicant` (`applicant_id`),
  ADD KEY `idx_result` (`screening_result`);

--
-- Indexes for table `training_modules`
--
ALTER TABLE `training_modules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=127;

--
-- AUTO_INCREMENT for table `applicants`
--
ALTER TABLE `applicants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `applicant_documents`
--
ALTER TABLE `applicant_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `communication_log`
--
ALTER TABLE `communication_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `document_requests`
--
ALTER TABLE `document_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `employee_rewards`
--
ALTER TABLE `employee_rewards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_training`
--
ALTER TABLE `employee_training`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `eom_criteria`
--
ALTER TABLE `eom_criteria`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `eom_nominations`
--
ALTER TABLE `eom_nominations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `eom_settings`
--
ALTER TABLE `eom_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `eom_votes`
--
ALTER TABLE `eom_votes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `eom_winners`
--
ALTER TABLE `eom_winners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `evaluation_categories`
--
ALTER TABLE `evaluation_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `evaluation_questions`
--
ALTER TABLE `evaluation_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `evaluation_responses`
--
ALTER TABLE `evaluation_responses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `evaluation_templates`
--
ALTER TABLE `evaluation_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `feedback_notes`
--
ALTER TABLE `feedback_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feedback_reactions`
--
ALTER TABLE `feedback_reactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `final_evaluation_questions`
--
ALTER TABLE `final_evaluation_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `final_evaluation_responses`
--
ALTER TABLE `final_evaluation_responses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `final_interviews`
--
ALTER TABLE `final_interviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `incentive_budget_tracking`
--
ALTER TABLE `incentive_budget_tracking`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `incentive_eligibility`
--
ALTER TABLE `incentive_eligibility`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `incentive_payouts`
--
ALTER TABLE `incentive_payouts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `incentive_points`
--
ALTER TABLE `incentive_points`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `incentive_points_transactions`
--
ALTER TABLE `incentive_points_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `incentive_programs`
--
ALTER TABLE `incentive_programs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `incentive_redeemable_items`
--
ALTER TABLE `incentive_redeemable_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `incentive_redemptions`
--
ALTER TABLE `incentive_redemptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `interviews`
--
ALTER TABLE `interviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `job_applications`
--
ALTER TABLE `job_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `job_postings`
--
ALTER TABLE `job_postings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `job_posting_links`
--
ALTER TABLE `job_posting_links`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `milestones`
--
ALTER TABLE `milestones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `new_hires`
--
ALTER TABLE `new_hires`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=430;

--
-- AUTO_INCREMENT for table `onboarding_access_tokens`
--
ALTER TABLE `onboarding_access_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `onboarding_documents`
--
ALTER TABLE `onboarding_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `onboarding_document_audit`
--
ALTER TABLE `onboarding_document_audit`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `panel_evaluations`
--
ALTER TABLE `panel_evaluations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `performance_reviews`
--
ALTER TABLE `performance_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `probation_incidents`
--
ALTER TABLE `probation_incidents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `probation_kpis`
--
ALTER TABLE `probation_kpis`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `probation_kpi_results`
--
ALTER TABLE `probation_kpi_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `probation_records`
--
ALTER TABLE `probation_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `probation_reviews`
--
ALTER TABLE `probation_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `recognitions`
--
ALTER TABLE `recognitions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `recognition_categories`
--
ALTER TABLE `recognition_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `recognition_comments`
--
ALTER TABLE `recognition_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `recognition_likes`
--
ALTER TABLE `recognition_likes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `recognition_mentions`
--
ALTER TABLE `recognition_mentions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `recognition_posts`
--
ALTER TABLE `recognition_posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `recognition_settings`
--
ALTER TABLE `recognition_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `required_onboarding_documents`
--
ALTER TABLE `required_onboarding_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `rewards_catalog`
--
ALTER TABLE `rewards_catalog`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `screening_evaluations`
--
ALTER TABLE `screening_evaluations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `training_modules`
--
ALTER TABLE `training_modules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `applicant_documents`
--
ALTER TABLE `applicant_documents`
  ADD CONSTRAINT `applicant_documents_ibfk_1` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `applicant_documents_ibfk_2` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `new_hires` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `communication_log`
--
ALTER TABLE `communication_log`
  ADD CONSTRAINT `communication_log_ibfk_1` FOREIGN KEY (`applicant_id`) REFERENCES `job_applications` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `communication_log_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `new_hires` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `communication_log_ibfk_3` FOREIGN KEY (`sent_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `document_requests`
--
ALTER TABLE `document_requests`
  ADD CONSTRAINT `document_requests_ibfk_1` FOREIGN KEY (`new_hire_id`) REFERENCES `new_hires` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_requests_ibfk_2` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employee_rewards`
--
ALTER TABLE `employee_rewards`
  ADD CONSTRAINT `employee_rewards_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `new_hires` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employee_rewards_ibfk_2` FOREIGN KEY (`reward_id`) REFERENCES `rewards_catalog` (`id`),
  ADD CONSTRAINT `employee_rewards_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `employee_training`
--
ALTER TABLE `employee_training`
  ADD CONSTRAINT `employee_training_ibfk_1` FOREIGN KEY (`new_hire_id`) REFERENCES `new_hires` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employee_training_ibfk_2` FOREIGN KEY (`training_id`) REFERENCES `training_modules` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `eom_criteria`
--
ALTER TABLE `eom_criteria`
  ADD CONSTRAINT `eom_criteria_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `eom_nominations`
--
ALTER TABLE `eom_nominations`
  ADD CONSTRAINT `eom_nominations_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `new_hires` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `eom_nominations_ibfk_2` FOREIGN KEY (`nominated_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `eom_votes`
--
ALTER TABLE `eom_votes`
  ADD CONSTRAINT `eom_votes_ibfk_1` FOREIGN KEY (`nomination_id`) REFERENCES `eom_nominations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `eom_votes_ibfk_2` FOREIGN KEY (`voter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `eom_winners`
--
ALTER TABLE `eom_winners`
  ADD CONSTRAINT `eom_winners_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `new_hires` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `eom_winners_ibfk_2` FOREIGN KEY (`nomination_id`) REFERENCES `eom_nominations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `eom_winners_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `evaluation_categories`
--
ALTER TABLE `evaluation_categories`
  ADD CONSTRAINT `evaluation_categories_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `evaluation_templates` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `evaluation_questions`
--
ALTER TABLE `evaluation_questions`
  ADD CONSTRAINT `evaluation_questions_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `evaluation_categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `evaluation_responses`
--
ALTER TABLE `evaluation_responses`
  ADD CONSTRAINT `evaluation_responses_ibfk_1` FOREIGN KEY (`evaluation_id`) REFERENCES `panel_evaluations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `evaluation_responses_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `evaluation_questions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `evaluation_templates`
--
ALTER TABLE `evaluation_templates`
  ADD CONSTRAINT `evaluation_templates_ibfk_1` FOREIGN KEY (`position_id`) REFERENCES `job_postings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `evaluation_templates_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `final_evaluation_responses`
--
ALTER TABLE `final_evaluation_responses`
  ADD CONSTRAINT `final_evaluation_responses_ibfk_1` FOREIGN KEY (`final_interview_id`) REFERENCES `final_interviews` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `final_evaluation_responses_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `final_evaluation_questions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `final_interviews`
--
ALTER TABLE `final_interviews`
  ADD CONSTRAINT `final_interviews_ibfk_1` FOREIGN KEY (`applicant_id`) REFERENCES `job_applications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `final_interviews_ibfk_2` FOREIGN KEY (`job_posting_id`) REFERENCES `job_postings` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `final_interviews_ibfk_3` FOREIGN KEY (`interviewer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `final_interviews_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `incentive_budget_tracking`
--
ALTER TABLE `incentive_budget_tracking`
  ADD CONSTRAINT `incentive_budget_tracking_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `incentive_eligibility`
--
ALTER TABLE `incentive_eligibility`
  ADD CONSTRAINT `incentive_eligibility_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `new_hires` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `incentive_eligibility_ibfk_2` FOREIGN KEY (`program_id`) REFERENCES `incentive_programs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `incentive_eligibility_ibfk_3` FOREIGN KEY (`supervisor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `incentive_eligibility_ibfk_4` FOREIGN KEY (`hr_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `incentive_payouts`
--
ALTER TABLE `incentive_payouts`
  ADD CONSTRAINT `incentive_payouts_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `new_hires` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `incentive_payouts_ibfk_2` FOREIGN KEY (`eligibility_id`) REFERENCES `incentive_eligibility` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `incentive_payouts_ibfk_3` FOREIGN KEY (`program_id`) REFERENCES `incentive_programs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `incentive_payouts_ibfk_4` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `incentive_payouts_ibfk_5` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `incentive_payouts_ibfk_6` FOREIGN KEY (`paid_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `incentive_payouts_ibfk_7` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `incentive_points`
--
ALTER TABLE `incentive_points`
  ADD CONSTRAINT `incentive_points_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `new_hires` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `incentive_points_transactions`
--
ALTER TABLE `incentive_points_transactions`
  ADD CONSTRAINT `incentive_points_transactions_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `new_hires` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `incentive_points_transactions_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `incentive_programs`
--
ALTER TABLE `incentive_programs`
  ADD CONSTRAINT `incentive_programs_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `incentive_redeemable_items`
--
ALTER TABLE `incentive_redeemable_items`
  ADD CONSTRAINT `incentive_redeemable_items_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `incentive_redemptions`
--
ALTER TABLE `incentive_redemptions`
  ADD CONSTRAINT `incentive_redemptions_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `new_hires` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `incentive_redemptions_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `incentive_redeemable_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `incentive_redemptions_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `incentive_redemptions_ibfk_4` FOREIGN KEY (`fulfilled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `interviews`
--
ALTER TABLE `interviews`
  ADD CONSTRAINT `interviews_ibfk_1` FOREIGN KEY (`applicant_id`) REFERENCES `job_applications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `interviews_ibfk_2` FOREIGN KEY (`job_posting_id`) REFERENCES `job_postings` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `interviews_ibfk_3` FOREIGN KEY (`interviewer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `interviews_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `job_applications`
--
ALTER TABLE `job_applications`
  ADD CONSTRAINT `fk_selected_by` FOREIGN KEY (`selected_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `job_applications_ibfk_1` FOREIGN KEY (`job_posting_id`) REFERENCES `job_postings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `job_applications_ibfk_2` FOREIGN KEY (`job_posting_link_id`) REFERENCES `job_posting_links` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `job_postings`
--
ALTER TABLE `job_postings`
  ADD CONSTRAINT `job_postings_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `job_posting_links`
--
ALTER TABLE `job_posting_links`
  ADD CONSTRAINT `job_posting_links_ibfk_1` FOREIGN KEY (`job_posting_id`) REFERENCES `job_postings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `milestones`
--
ALTER TABLE `milestones`
  ADD CONSTRAINT `milestones_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `new_hires` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `new_hires`
--
ALTER TABLE `new_hires`
  ADD CONSTRAINT `new_hires_ibfk_1` FOREIGN KEY (`applicant_id`) REFERENCES `job_applications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `new_hires_ibfk_2` FOREIGN KEY (`job_posting_id`) REFERENCES `job_postings` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `new_hires_ibfk_3` FOREIGN KEY (`supervisor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `new_hires_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `onboarding_access_tokens`
--
ALTER TABLE `onboarding_access_tokens`
  ADD CONSTRAINT `onboarding_access_tokens_ibfk_1` FOREIGN KEY (`new_hire_id`) REFERENCES `new_hires` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `onboarding_access_tokens_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `onboarding_documents`
--
ALTER TABLE `onboarding_documents`
  ADD CONSTRAINT `onboarding_documents_ibfk_1` FOREIGN KEY (`new_hire_id`) REFERENCES `new_hires` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `onboarding_documents_ibfk_2` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `onboarding_documents_ibfk_3` FOREIGN KEY (`previous_version_id`) REFERENCES `onboarding_documents` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `onboarding_document_audit`
--
ALTER TABLE `onboarding_document_audit`
  ADD CONSTRAINT `onboarding_document_audit_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `onboarding_documents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `onboarding_document_audit_ibfk_2` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `panel_evaluations`
--
ALTER TABLE `panel_evaluations`
  ADD CONSTRAINT `panel_evaluations_ibfk_1` FOREIGN KEY (`interview_id`) REFERENCES `interviews` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `panel_evaluations_ibfk_2` FOREIGN KEY (`applicant_id`) REFERENCES `job_applications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `panel_evaluations_ibfk_3` FOREIGN KEY (`panel_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `performance_reviews`
--
ALTER TABLE `performance_reviews`
  ADD CONSTRAINT `performance_reviews_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `new_hires` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `performance_reviews_ibfk_2` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `recognitions`
--
ALTER TABLE `recognitions`
  ADD CONSTRAINT `recognitions_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `new_hires` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `recognitions_ibfk_2` FOREIGN KEY (`recognizer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `recognition_comments`
--
ALTER TABLE `recognition_comments`
  ADD CONSTRAINT `recognition_comments_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `recognition_posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `recognition_comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `recognition_likes`
--
ALTER TABLE `recognition_likes`
  ADD CONSTRAINT `recognition_likes_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `recognition_posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `recognition_likes_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `recognition_mentions`
--
ALTER TABLE `recognition_mentions`
  ADD CONSTRAINT `recognition_mentions_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `recognition_posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `recognition_mentions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `recognition_posts`
--
ALTER TABLE `recognition_posts`
  ADD CONSTRAINT `recognition_posts_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `new_hires` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `recognition_posts_ibfk_2` FOREIGN KEY (`posted_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `screening_evaluations`
--
ALTER TABLE `screening_evaluations`
  ADD CONSTRAINT `screening_evaluations_ibfk_1` FOREIGN KEY (`applicant_id`) REFERENCES `job_applications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `screening_evaluations_ibfk_2` FOREIGN KEY (`evaluated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `training_modules`
--
ALTER TABLE `training_modules`
  ADD CONSTRAINT `training_modules_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
