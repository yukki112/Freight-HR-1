-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3307
-- Generation Time: Sep 30, 2026 at 08:19 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `freight_managements`
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
(129, 5, 'create_job_posting', 'Created job posting: JOB-2026-747 - Heavy Truck Driver', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-30 17:39:26'),
(130, 5, 'create_job_posting', 'Created job posting: JOB-2026-179 - Driver', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-30 17:45:13'),
(131, 5, 'verify_all_documents', 'Verified all documents for applicant #7', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-30 17:48:00'),
(132, 5, 'update_applicant_status', 'Updated applicant #7 status to shortlisted via screening', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-30 17:49:19'),
(133, 5, 'screening_evaluation', 'Saved screening evaluation for applicant #7', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-30 17:49:19'),
(134, 5, 'update_applicant_status', 'Updated applicant #7 status to shortlisted via screening', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-30 17:49:22'),
(135, 5, 'screening_evaluation', 'Saved screening evaluation for applicant #7', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-30 17:49:22'),
(136, 5, 'schedule_interview', 'Scheduled interview for applicant #7', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-30 17:51:23'),
(137, 5, 'start_evaluation', 'Started evaluation for interview #23', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-30 18:01:27'),
(138, 5, 'submit_evaluation', 'Submitted evaluation #5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-30 18:02:17'),
(139, 5, 'submit_evaluation', 'Submitted evaluation #5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-30 18:03:07'),
(140, 5, 'start_final_interview', 'Started final interview for applicant #7', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-30 18:03:26'),
(141, 5, 'complete_final_interview', 'Completed final interview #3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-30 18:03:42'),
(142, 5, 'final_selection', 'Selected applicant #7 for hiring', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-30 18:08:27'),
(143, 5, 'request_documents', 'Sent document request to new hire #6', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-30 18:10:47'),
(144, 5, 'request_documents', 'Sent document request to new hire #6', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-30 18:13:26'),
(145, 5, 'request_documents', 'Sent document request to new hire #6', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-30 18:14:27');

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
  `department` varchar(100) DEFAULT 'driver',
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
(37, 7, NULL, 'email', 'Interview Schedule: Driver', 'Interview scheduled on October 01, 2026 at 10:00 AM', 5, '2026-09-30 17:51:23', 'failed'),
(38, 7, NULL, 'email', 'Application Update: Driver', 'Final decision: Final interview', 5, '2026-09-30 18:02:17', 'failed'),
(39, 7, NULL, 'email', 'Application Update: Driver', 'Final decision: Final interview', 5, '2026-09-30 18:03:07', 'failed'),
(40, 7, NULL, 'email', 'Final Interview Result: Driver', 'Final decision: Hire (Score: 100%)', 5, '2026-09-30 18:03:42', 'failed'),
(41, 7, NULL, 'email', '📄 Document Submission Required - Freight Management Onboarding', 'Document request sent with link', 5, '2026-09-30 18:10:47', 'sent'),
(42, 7, NULL, 'email', '📄 Document Submission Required - Freight Management Onboarding', 'Document request sent with link', 5, '2026-09-30 18:13:26', 'sent'),
(43, 7, NULL, 'email', '📄 Document Submission Required - Freight Management Onboarding', 'Document request sent with link', 5, '2026-09-30 18:14:27', 'sent');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `department_code` varchar(50) NOT NULL,
  `department_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `department_code`, `department_name`, `description`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'operations', 'Operations Department', 'Overall operations management', 1, 1, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(2, 'transportation', 'Transportation / Fleet Department', 'Fleet and transportation management', 1, 2, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(3, 'warehouse', 'Warehouse Department', 'Warehouse operations and inventory', 1, 3, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(4, 'customer_service', 'Customer Service Department', 'Customer support and service', 1, 4, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(5, 'sales', 'Sales & Business Development Department', 'Sales and business growth', 1, 5, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(6, 'finance', 'Finance & Accounting Department', 'Financial management and accounting', 1, 6, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(7, 'hr', 'Human Resources Department', 'HR management and personnel', 1, 7, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(8, 'procurement', 'Procurement Department', 'Purchasing and procurement', 1, 8, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(9, 'compliance', 'Compliance / Legal & Risk Department', 'Legal, compliance and risk management', 1, 9, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(10, 'it', 'IT Department', 'Information technology', 1, 10, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(11, 'safety_security', 'Safety & Security Department', 'Safety and security management', 1, 11, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(12, 'administration', 'Administration Department', 'Administrative support', 1, 12, '2026-09-30 17:42:34', '2026-09-30 17:42:34');

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
(12, 6, 5, '2026-09-30 18:10:44', 'pending', NULL, NULL),
(13, 6, 5, '2026-09-30 18:13:22', 'pending', NULL, NULL),
(14, 6, 5, '2026-09-30 18:14:23', 'pending', NULL, NULL);

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
  `category` varchar(100) DEFAULT 'all',
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
  `category` varchar(100) DEFAULT NULL,
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
(10, 6, 'Technical Skills', 30, 1),
(11, 6, 'Communication Skills', 25, 2),
(12, 6, 'Problem Solving', 20, 3),
(13, 6, 'Cultural Fit', 15, 4),
(14, 6, 'Overall Impression', 10, 5);

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
(26, 10, 'Does the candidate demonstrate the required technical knowledge for this position?', 'technical', 1),
(27, 10, 'Can the candidate apply relevant skills and experience to real-world scenarios?', 'technical', 2),
(28, 10, 'Does the candidate show familiarity with industry tools and best practices?', 'technical', 3),
(29, 11, 'Does the candidate communicate clearly and effectively?', 'behavioral', 1),
(30, 11, 'Does the candidate listen actively and respond appropriately?', 'behavioral', 2),
(31, 11, 'Can the candidate explain complex ideas in simple terms?', 'behavioral', 3),
(32, 12, 'Does the candidate demonstrate critical thinking skills?', 'situational', 1),
(33, 12, 'Can the candidate provide practical solutions to problems?', 'situational', 2),
(34, 12, 'How does the candidate handle unexpected challenges?', 'situational', 3),
(35, 13, 'Does the candidate align with company values and culture?', 'behavioral', 1),
(36, 13, 'Would the candidate work well with the existing team?', 'behavioral', 2),
(37, 13, 'Does the candidate show commitment to long-term growth?', 'behavioral', 3),
(38, 14, 'What is your overall impression of the candidate?', 'behavioral', 1),
(39, 14, 'Would you recommend this candidate for the position?', 'behavioral', 2);

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
(41, 5, 26, 1, ''),
(42, 5, 27, 4, ''),
(43, 5, 28, 3, ''),
(44, 5, 29, 2, ''),
(45, 5, 30, 5, ''),
(46, 5, 31, 5, ''),
(47, 5, 32, 5, ''),
(48, 5, 33, 5, ''),
(49, 5, 34, 5, ''),
(50, 5, 35, 5, ''),
(51, 5, 36, 5, ''),
(52, 5, 37, 5, ''),
(53, 5, 38, 5, ''),
(54, 5, 39, 5, '');

-- --------------------------------------------------------

--
-- Table structure for table `evaluation_templates`
--

CREATE TABLE `evaluation_templates` (
  `id` int(11) NOT NULL,
  `position_id` int(11) DEFAULT NULL,
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
(6, NULL, 'DEFAULT', 'Default Interview Evaluation Template', 1, 5, '2026-09-30 17:57:40', '2026-09-30 17:57:40');

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
(17, 3, 1, 5, ''),
(18, 3, 2, 5, ''),
(19, 3, 3, 5, ''),
(20, 3, 4, 5, ''),
(21, 3, 5, 5, ''),
(22, 3, 6, 5, ''),
(23, 3, 7, 5, ''),
(24, 3, 8, 5, '');

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
(3, 7, 16, 5, '2026-10-01', NULL, NULL, NULL, 'completed', 100.00, 'hire', NULL, '', '', '', '2026-09-30 18:03:40', 5, '2026-09-30 18:03:26', '2026-09-30 18:03:40');

-- --------------------------------------------------------

--
-- Table structure for table `incentive_budget_tracking`
--

CREATE TABLE `incentive_budget_tracking` (
  `id` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `quarter` int(11) DEFAULT NULL,
  `month` int(11) DEFAULT NULL,
  `department` varchar(100) DEFAULT 'all',
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
  `department` varchar(100) DEFAULT 'all',
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
(23, 7, 16, NULL, '2026-10-01', '10:00:00', 'initial', 'initial', NULL, 'https://meet.google.com/dor-rpqx-ben', 'completed', NULL, NULL, 'final_interview', 1, 'test', 5, '2026-09-30 17:51:21', '2026-09-30 18:02:15');

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
(7, 'APP-202609-C0121E', 16, NULL, 'Stephen Kyle ', 'Viray', 'Stephenviray12@gmail.com', '09984319585', '2004-02-10', 'male', '54 gold Ext', 'Quezon City', 'Metro Manila', '1121', '', '', '', '', '', '', '', '', '', '', '', '', '', NULL, 'test', 'test', '[{\"name\":\"yrdy\",\"position\":\"yrdy\",\"company\":\"yrdy\",\"contact\":\"09999999999\",\"relationship\":\"test\"}]', 'uploads/applications/APP-202609-C0121E/resume.docx', 'uploads/applications/APP-202609-C0121E/cover_letter.docx', 1, 'uploads/applications/APP-202609-C0121E/photo.jpeg', 'hired', 'hired', 100.00, NULL, 5, '2026-10-01 02:08:24', 'arppoved', 20000.00, '2026-10-14', '2026-10-01 02:08:24', '\n[2026-09-30 19:49] Status updated to shortlisted based on screening evaluation.\n[2026-09-30 19:49] Status updated to shortlisted based on screening evaluation.\n[2026-09-30 19:51] Interview scheduled: initial on October 01, 2026 at 10:00 AM - Link: https://meet.google.com/dor-rpqx-ben', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', NULL, '2026-09-30 17:46:52', '2026-09-30 11:46:52', '2026-09-30 18:08:24');

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
  `department` varchar(100) DEFAULT NULL,
  `position_id` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
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

INSERT INTO `job_postings` (`id`, `job_code`, `api_position_id`, `api_synced`, `last_api_sync`, `title`, `department`, `position_id`, `department_id`, `employment_type`, `experience_required`, `education_required`, `license_required`, `description`, `requirements`, `responsibilities`, `salary_min`, `salary_max`, `location`, `application_link`, `link_expiration`, `link_code`, `slots_available`, `slots_filled`, `slots_filled_auto`, `status`, `published_date`, `closing_date`, `created_by`, `created_at`, `updated_at`) VALUES
(16, 'JOB-2026-179', NULL, 0, NULL, 'Driver', 'transportation', 14, NULL, 'full_time', '2 Years of Exp', 'High School Graduate', 'Professional Driver\'s License', 'test', 'test', 'test', 17000.00, 22000.00, 'Quezon City', 'http://localhost/hr1/apply.php?code=ebc9facb54f44ff6406ef3498a3389ce', '2026-10-30 19:45:13', 'ebc9facb54f44ff6406ef3498a3389ce', 3, 1, 1, 'published', '2026-09-30', '2026-10-30', 5, '2026-09-30 17:45:13', '2026-09-30 18:08:24');

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
  `department` varchar(100) DEFAULT NULL,
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
(6, 7, 'EMP-2026-5950', 16, '2026-10-01', '2026-10-14', NULL, 'Driver', 'transportation', NULL, 'probationary', 0, NULL, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, NULL, NULL, NULL, 'onboarding', 0, NULL, 5, '2026-09-30 18:08:24', '2026-09-30 18:08:24', 0, NULL);

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
(13, 6, '544e8e27f5ffde63e587d8149b94c8c994033abb8f01d74aef37ba35a571143c', 'Stephenviray12@gmail.com', '2026-10-07 20:10:44', NULL, 5, '2026-09-30 18:10:44'),
(14, 6, '94a756ed62718e5f1b48254c0435d393d16d5b6cc61a2c5e5cfaf5755964da66', 'Stephenviray12@gmail.com', '2026-10-07 20:13:22', NULL, 5, '2026-09-30 18:13:22'),
(15, 6, 'c65810b26fbc1f324921753d5589e894de6e5251ff90fd695a3e678a94b6202c', 'Stephenviray12@gmail.com', '2026-10-07 20:14:23', NULL, 5, '2026-09-30 18:14:23');

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
(6, 6, 'MEDICAL', '28d84ec6-bf37-48b7-abd8-b0b152fdd1cf.jpg', '../uploads/onboarding/documents/newhire_6_MEDICAL_1790792053.jpg', 211910, 'image/jpeg', NULL, NULL, NULL, '', 'pending', NULL, NULL, NULL, 1, NULL, '2026-09-30 18:14:13', '2026-09-30 18:14:13');

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
(6, 6, 'upload', NULL, 'pending', NULL, NULL, '2026-09-30 18:14:13');

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
(5, 23, 7, 5, 'submitted', 60.00, 70.00, 85.71, 'final_interview', '', '', '', '2026-09-30 18:03:04', '2026-09-30 18:01:27', '2026-09-30 18:03:04');

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
  `department` varchar(100) DEFAULT NULL,
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
-- Table structure for table `positions`
--

CREATE TABLE `positions` (
  `id` int(11) NOT NULL,
  `position_code` varchar(50) NOT NULL,
  `position_title` varchar(255) NOT NULL,
  `department_id` int(11) NOT NULL,
  `level` int(11) DEFAULT 1 COMMENT '1=Executive, 2=Director, 3=Manager, 4=Supervisor, 5=Staff',
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `positions`
--

INSERT INTO `positions` (`id`, `position_code`, `position_title`, `department_id`, `level`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'OPS-COO', 'Chief Operating Officer (COO)', 1, 1, 1, 1, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(2, 'OPS-DIR', 'Operations Director', 1, 2, 1, 2, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(3, 'OPS-MGR', 'Operations Manager', 1, 3, 1, 3, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(4, 'OPS-SUP', 'Operations Supervisor', 1, 4, 1, 4, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(5, 'OPS-COORD', 'Operations Coordinator', 1, 5, 1, 5, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(6, 'OPS-OFF', 'Operations Officer', 1, 5, 1, 6, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(7, 'OPS-STAFF', 'Operations Staff / Assistant', 1, 5, 1, 7, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(8, 'TRN-DIR', 'Fleet Director', 2, 2, 1, 1, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(9, 'TRN-MGR', 'Fleet Manager', 2, 3, 1, 2, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(10, 'TRN-SUP', 'Fleet Supervisor', 2, 4, 1, 3, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(11, 'TRN-COORD', 'Fleet Coordinator', 2, 5, 1, 4, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(12, 'TRN-OFF', 'Fleet Officer', 2, 5, 1, 5, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(13, 'TRN-DRV-SR', 'Senior Driver', 2, 5, 1, 6, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(14, 'TRN-DRV', 'Driver', 2, 5, 1, 7, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(15, 'TRN-DRV-AST', 'Driver Assistant / Helper', 2, 5, 1, 8, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(16, 'WHS-DIR', 'Warehouse Director', 3, 2, 1, 1, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(17, 'WHS-MGR', 'Warehouse Manager', 3, 3, 1, 2, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(18, 'WHS-SUP', 'Warehouse Supervisor', 3, 4, 1, 3, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(19, 'WHS-COORD', 'Warehouse Coordinator', 3, 5, 1, 4, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(20, 'WHS-OFF', 'Warehouse Officer', 3, 5, 1, 5, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(21, 'WHS-STAFF-SR', 'Senior Warehouse Staff', 3, 5, 1, 6, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(22, 'WHS-STAFF', 'Warehouse Staff', 3, 5, 1, 7, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(23, 'WHS-HELPER', 'Warehouse Helper', 3, 5, 1, 8, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(24, 'CS-DIR', 'Customer Service Director', 4, 2, 1, 1, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(25, 'CS-MGR', 'Customer Service Manager', 4, 3, 1, 2, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(26, 'CS-SUP', 'Customer Service Supervisor', 4, 4, 1, 3, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(27, 'CS-TL', 'Customer Service Team Leader', 4, 4, 1, 4, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(28, 'CS-REP-SR', 'Senior Customer Service Representative', 4, 5, 1, 5, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(29, 'CS-REP', 'Customer Service Representative', 4, 5, 1, 6, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(30, 'CS-AST', 'Customer Service Assistant', 4, 5, 1, 7, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(31, 'SLS-CSO', 'Chief Sales Officer (CSO)', 5, 1, 1, 1, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(32, 'SLS-DIR', 'Sales Director', 5, 2, 1, 2, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(33, 'SLS-MGR', 'Sales Manager', 5, 3, 1, 3, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(34, 'SLS-SUP', 'Sales Supervisor', 5, 4, 1, 4, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(35, 'SLS-BDM', 'Business Development Manager', 5, 3, 1, 5, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(36, 'SLS-AM', 'Account Manager', 5, 4, 1, 6, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(37, 'SLS-EXEC', 'Sales Executive', 5, 5, 1, 7, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(38, 'SLS-REP', 'Sales Representative', 5, 5, 1, 8, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(39, 'SLS-AST', 'Sales Assistant', 5, 5, 1, 9, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(40, 'FIN-CFO', 'Chief Financial Officer (CFO)', 6, 1, 1, 1, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(41, 'FIN-DIR', 'Finance Director', 6, 2, 1, 2, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(42, 'FIN-MGR', 'Finance Manager', 6, 3, 1, 3, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(43, 'ACC-MGR', 'Accounting Manager', 6, 3, 1, 4, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(44, 'FIN-SUP', 'Finance Supervisor', 6, 4, 1, 5, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(45, 'ACC-SUP', 'Accounting Supervisor', 6, 4, 1, 6, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(46, 'ACC-SR', 'Senior Accountant', 6, 5, 1, 7, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(47, 'ACC', 'Accountant', 6, 5, 1, 8, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(48, 'FIN-OFF', 'Finance/Accounting Officer', 6, 5, 1, 9, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(49, 'ACC-AST', 'Accounting Assistant', 6, 5, 1, 10, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(50, 'HR-CHRO', 'Chief Human Resources Officer (CHRO)', 7, 1, 1, 1, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(51, 'HR-DIR', 'HR Director', 7, 2, 1, 2, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(52, 'HR-MGR', 'HR Manager', 7, 3, 1, 3, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(53, 'HR-SUP', 'HR Supervisor', 7, 4, 1, 4, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(54, 'HR-BP', 'HR Business Partner', 7, 4, 1, 5, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(55, 'HR-OFF-SR', 'Senior HR Officer', 7, 5, 1, 6, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(56, 'HR-OFF', 'HR Officer', 7, 5, 1, 7, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(57, 'HR-AST', 'HR Assistant', 7, 5, 1, 8, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(58, 'HR-STAFF', 'HR Staff', 7, 5, 1, 9, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(59, 'PRC-DIR', 'Procurement Director', 8, 2, 1, 1, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(60, 'PRC-MGR', 'Procurement Manager', 8, 3, 1, 2, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(61, 'PRC-SUP', 'Procurement Supervisor', 8, 4, 1, 3, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(62, 'PRC-OFF', 'Procurement Officer', 8, 5, 1, 4, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(63, 'PRC-SPEC-SR', 'Senior Procurement Specialist', 8, 5, 1, 5, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(64, 'PRC-SPEC', 'Procurement Specialist', 8, 5, 1, 6, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(65, 'PUR-OFF', 'Purchasing Officer', 8, 5, 1, 7, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(66, 'PRC-AST', 'Procurement Assistant', 8, 5, 1, 8, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(67, 'CMP-CCO', 'Chief Compliance Officer / General Counsel', 9, 1, 1, 1, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(68, 'CMP-DIR', 'Legal & Compliance Director', 9, 2, 1, 2, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(69, 'CMP-MGR', 'Compliance Manager', 9, 3, 1, 3, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(70, 'RSK-MGR', 'Risk Manager', 9, 3, 1, 4, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(71, 'CMP-SUP', 'Compliance Supervisor', 9, 4, 1, 5, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(72, 'CMP-OFF', 'Legal/Compliance Officer', 9, 5, 1, 6, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(73, 'RSK-OFF', 'Risk Officer', 9, 5, 1, 7, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(74, 'CMP-AST', 'Compliance Assistant', 9, 5, 1, 8, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(75, 'IT-CIO', 'Chief Information Officer (CIO)', 10, 1, 1, 1, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(76, 'IT-DIR', 'IT Director', 10, 2, 1, 2, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(77, 'IT-MGR', 'IT Manager', 10, 3, 1, 3, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(78, 'IT-SUP', 'IT Supervisor', 10, 4, 1, 4, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(79, 'IT-TL', 'IT Team Leader', 10, 4, 1, 5, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(80, 'IT-SR', 'Senior Systems Administrator / Senior Developer', 10, 5, 1, 6, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(81, 'IT-ADM', 'Systems Administrator / Software Developer', 10, 5, 1, 7, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(82, 'IT-OFF', 'IT Support Officer', 10, 5, 1, 8, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(83, 'IT-STAFF', 'IT Support Staff', 10, 5, 1, 9, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(84, 'SAF-DIR', 'Safety & Security Director', 11, 2, 1, 1, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(85, 'SAF-MGR', 'Safety & Security Manager', 11, 3, 1, 2, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(86, 'SAF-SUP', 'Safety Supervisor', 11, 4, 1, 3, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(87, 'SEC-SUP', 'Security Supervisor', 11, 4, 1, 4, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(88, 'SAF-OFF', 'Safety Officer', 11, 5, 1, 5, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(89, 'SEC-OFF', 'Security Officer', 11, 5, 1, 6, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(90, 'SAF-STAFF', 'Safety/Security Staff', 11, 5, 1, 7, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(91, 'SEC-GRD', 'Security Guard', 11, 5, 1, 8, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(92, 'ADM-DIR', 'Administration Director', 12, 2, 1, 1, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(93, 'ADM-MGR', 'Administration Manager', 12, 3, 1, 2, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(94, 'ADM-SUP', 'Administration Supervisor', 12, 4, 1, 3, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(95, 'ADM-OFF', 'Administrative Officer', 12, 5, 1, 4, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(96, 'ADM-AST-SR', 'Senior Administrative Assistant', 12, 5, 1, 5, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(97, 'ADM-AST', 'Administrative Assistant', 12, 5, 1, 6, '2026-09-30 17:42:34', '2026-09-30 17:42:34'),
(98, 'ADM-STAFF', 'Administrative Staff', 12, 5, 1, 7, '2026-09-30 17:42:34', '2026-09-30 17:42:34');

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
  `department` varchar(100) DEFAULT NULL,
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
(5, 7, 80, 96, '2 years of exp with professional driver\'s license', 5, '2026-10-01 01:49:22', 'pass', NULL, NULL, NULL, NULL, '2026-09-30 17:49:19', '2026-09-30 17:49:22');

-- --------------------------------------------------------

--
-- Table structure for table `training_modules`
--

CREATE TABLE `training_modules` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `department` varchar(100) DEFAULT 'all',
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
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `department_code` (`department_code`);

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
  ADD KEY `idx_department` (`department`),
  ADD KEY `job_postings_position_fk` (`position_id`),
  ADD KEY `job_postings_department_fk` (`department_id`);

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
-- Indexes for table `positions`
--
ALTER TABLE `positions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `position_code` (`position_code`),
  ADD KEY `department_id` (`department_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=146;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `document_requests`
--
ALTER TABLE `document_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `evaluation_questions`
--
ALTER TABLE `evaluation_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `evaluation_responses`
--
ALTER TABLE `evaluation_responses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `evaluation_templates`
--
ALTER TABLE `evaluation_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `final_interviews`
--
ALTER TABLE `final_interviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `job_applications`
--
ALTER TABLE `job_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `job_postings`
--
ALTER TABLE `job_postings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=430;

--
-- AUTO_INCREMENT for table `onboarding_access_tokens`
--
ALTER TABLE `onboarding_access_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `onboarding_documents`
--
ALTER TABLE `onboarding_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `onboarding_document_audit`
--
ALTER TABLE `onboarding_document_audit`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `panel_evaluations`
--
ALTER TABLE `panel_evaluations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `performance_reviews`
--
ALTER TABLE `performance_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `positions`
--
ALTER TABLE `positions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=99;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

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
  ADD CONSTRAINT `job_postings_department_fk` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `job_postings_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `job_postings_position_fk` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`) ON DELETE SET NULL;

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
-- Constraints for table `positions`
--
ALTER TABLE `positions`
  ADD CONSTRAINT `positions_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE;

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
