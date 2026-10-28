-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 28-10-2026 a las 13:35:34
-- Versión del servidor: 10.11.16-MariaDB
-- Versión de PHP: 8.3.36

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `bdd_veterinaria`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `admins`
--

CREATE TABLE `admins` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `admins`
--

INSERT INTO `admins` (`id`, `email`, `password_hash`, `created_at`) VALUES
(1, 'vetcare987@gmail.com', '$2y$10$DaSz3XZhevJlljEVuWyY/uunn14RVBtVIhV/8atNH0JvbdJXXdKGm', '2026-10-21 03:00:17');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `appointments`
--

CREATE TABLE `appointments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `clinic_id` bigint(20) UNSIGNED NOT NULL,
  `client_id` bigint(20) UNSIGNED NOT NULL,
  `pet_id` bigint(20) UNSIGNED NOT NULL,
  `requested_by` enum('cliente','veterinaria') NOT NULL,
  `appointment_at` datetime NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `status` enum('Solicitado','Confirmado','Cancelado','Atendido') NOT NULL DEFAULT 'Solicitado',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `appointments`
--

INSERT INTO `appointments` (`id`, `clinic_id`, `client_id`, `pet_id`, `requested_by`, `appointment_at`, `reason`, `status`, `created_at`) VALUES
(1, 3, 2, 2, 'veterinaria', '2026-10-22 09:29:00', 'vacunacion', 'Atendido', '2026-10-21 12:29:18');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `audit_log`
--

CREATE TABLE `audit_log` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `clinic_id` bigint(20) UNSIGNED DEFAULT NULL,
  `vet_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `entity_type` varchar(60) NOT NULL,
  `entity_id` bigint(20) UNSIGNED NOT NULL,
  `action` varchar(60) NOT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `audit_log`
--

INSERT INTO `audit_log` (`id`, `clinic_id`, `vet_user_id`, `entity_type`, `entity_id`, `action`, `details`, `created_at`) VALUES
(1, 1, 1, 'pet', 1, 'created', '{\"name\":\"tobi\",\"client_id\":1}', '2026-10-16 16:24:51'),
(2, 2, 2, 'pet', 2, 'created', '{\"name\":\"Apolo\",\"client_id\":2}', '2026-10-21 03:17:32'),
(3, 3, 3, 'consultation', 2, 'updated', '{\"before\":{\"reason\":\"Vacuna\",\"notes\":\"dolor fisico\",\"diagnosis\":\"dolor estomacal\",\"treatment\":\"Penisilina cada 3 dias\",\"status\":\"En proceso\"},\"after\":{\"reason\":\"Vacuna\",\"notes\":\"dolor fisico\",\"diagnosis\":\"dolor estomacal\",\"treatment\":\"Penisilina cada 3 dias\",\"status\":\"En proceso\"}}', '2026-10-21 12:33:42'),
(4, 3, 3, 'consultation', 2, 'updated', '{\"before\":{\"reason\":\"Vacuna\",\"notes\":\"dolor fisico\",\"diagnosis\":\"dolor estomacal\",\"treatment\":\"Penisilina cada 3 dias\",\"status\":\"En proceso\"},\"after\":{\"reason\":\"Vacuna\",\"notes\":\"dolor fisico\",\"diagnosis\":\"dolor estomacal\",\"treatment\":\"Penisilina cada 3 dias\",\"status\":\"Dado de Alta\"}}', '2026-10-21 13:01:13'),
(5, NULL, NULL, 'client', 4, 'admin_update', '{\"full_name\":\"Ricardo Rickenbacher\",\"dni\":\"474747\",\"email\":\"ricardorickenbacher@gmail.com\"}', '2026-10-28 05:21:25');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clients`
--

CREATE TABLE `clients` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `dni` varchar(30) NOT NULL,
  `full_name` varchar(180) NOT NULL,
  `email` varchar(190) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `avatar_path` varchar(255) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `clients`
--

INSERT INTO `clients` (`id`, `dni`, `full_name`, `email`, `phone`, `avatar_path`, `password_hash`, `created_at`, `updated_at`) VALUES
(1, '44649955', 'Ivan I. Bruno', 'ivan@gmail.com', '11 4455-6677', NULL, '$2y$10$1Y03rzBQIPTOHHYFBwzcQ.YmPMzvpYzp4OH9cAxbDcI8S68aB0T3y', '2026-10-16 16:24:10', '2026-10-27 23:18:07'),
(2, '464646', 'Ivan Bruno', 'ivanignaciobruno@gmail.com', '111111', 'avatars/client_2_4846352e9bdb7cd9.jpg', '$2y$10$1Y03rzBQIPTOHHYFBwzcQ.YmPMzvpYzp4OH9cAxbDcI8S68aB0T3y', '2026-10-21 03:12:47', '2026-10-28 00:00:13'),
(4, '474747', 'Ricardo Rickenbacher', 'ricardorickenbacher@gmail.com', '222222', 'avatars/client_4_ce0db24f9ed2cad7.webp', '$2y$10$1Y03rzBQIPTOHHYFBwzcQ.YmPMzvpYzp4OH9cAxbDcI8S68aB0T3y', '2026-10-21 03:16:58', '2026-10-28 05:22:17'),
(5, '123', 'Pedro', 'aaaa@gmail.com', '1111', NULL, '$2y$10$1Y03rzBQIPTOHHYFBwzcQ.YmPMzvpYzp4OH9cAxbDcI8S68aB0T3y', '2026-10-21 14:59:44', '2026-10-27 23:18:07'),
(6, '123456', 'Thiago Mantoani', 'thiagomantoani@gmail.com', '11223344', NULL, '$2y$10$1Y03rzBQIPTOHHYFBwzcQ.YmPMzvpYzp4OH9cAxbDcI8S68aB0T3y', '2026-10-21 23:59:45', '2026-10-27 23:18:07');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clinics`
--

CREATE TABLE `clinics` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(160) NOT NULL,
  `email` varchar(190) NOT NULL,
  `street` varchar(180) DEFAULT NULL,
  `city` varchar(120) DEFAULT NULL,
  `province` varchar(80) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `cuit` varchar(20) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `opening_hours` varchar(255) DEFAULT NULL,
  `available_days` varchar(100) DEFAULT NULL,
  `opening_time` time DEFAULT NULL,
  `closing_time` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `clinics`
--

INSERT INTO `clinics` (`id`, `name`, `email`, `street`, `city`, `province`, `active`, `created_at`, `cuit`, `phone`, `opening_hours`, `available_days`, `opening_time`, `closing_time`) VALUES
(1, 'Mau', 'mau@gmail.com', 'Belgrano 3747', 'San Martín', 'Buenos Aires', 1, '2026-10-16 16:23:03', NULL, NULL, NULL, NULL, NULL, NULL),
(2, 'Veterinaria Santa Teresita', 'vetsanter@gmail.com', 'Plaza Belgrano', 'La Plata', 'Buenos Aires', 1, '2026-10-21 03:06:45', '404045', '444444', NULL, 'Lunes a Viernes', '08:00:00', '22:00:00'),
(3, 'Veterinaria La Matanza', 'vetmatanza@gmail.com', 'Dr. Rodolfo Monte 848', 'Morón', 'Buenos Aires', 1, '2026-10-21 03:08:40', '555', '11111', NULL, 'lun mar', '21:16:00', '17:18:00');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clinic_clients`
--

CREATE TABLE `clinic_clients` (
  `clinic_id` bigint(20) UNSIGNED NOT NULL,
  `client_id` bigint(20) UNSIGNED NOT NULL,
  `first_seen_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_seen_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `clinic_clients`
--

INSERT INTO `clinic_clients` (`clinic_id`, `client_id`, `first_seen_at`, `last_seen_at`) VALUES
(1, 1, '2026-10-16 16:24:10', '2026-10-16 16:39:06'),
(1, 4, '2026-10-21 23:38:33', '2026-10-21 23:39:49'),
(2, 2, '2026-10-21 03:12:47', '2026-10-28 01:44:27'),
(2, 4, '2026-10-21 03:16:58', '2026-10-21 03:16:58'),
(3, 2, '2026-10-21 03:31:48', '2026-10-21 14:53:25'),
(3, 4, '2026-10-21 12:26:23', '2026-10-21 12:26:23'),
(3, 5, '2026-10-21 14:59:44', '2026-10-21 14:59:44');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `consultations`
--

CREATE TABLE `consultations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `pet_id` bigint(20) UNSIGNED NOT NULL,
  `clinic_id` bigint(20) UNSIGNED NOT NULL,
  `vet_user_id` bigint(20) UNSIGNED NOT NULL,
  `reason` varchar(100) NOT NULL,
  `notes` text NOT NULL,
  `diagnosis` text DEFAULT NULL,
  `treatment` text DEFAULT NULL,
  `status` enum('En proceso','Dado de Alta') NOT NULL DEFAULT 'En proceso',
  `vaccine_expiry` date DEFAULT NULL,
  `next_dose` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `consultations`
--

INSERT INTO `consultations` (`id`, `pet_id`, `clinic_id`, `vet_user_id`, `reason`, `notes`, `diagnosis`, `treatment`, `status`, `vaccine_expiry`, `next_dose`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 'Consulta general', 'diarrea', 'parasitos en el estomago', 'paracetamol cada 2 dias', 'En proceso', NULL, NULL, '2026-10-16 16:26:25', '2026-10-16 16:26:25'),
(2, 2, 3, 3, 'Vacuna', 'dolor fisico', 'dolor estomacal', 'Penisilina cada 3 dias', 'Dado de Alta', '2026-10-23', NULL, '2026-10-21 12:33:13', '2026-10-21 13:01:13'),
(3, 2, 3, 3, 'Vacuna', 'dfdf', 'sdfsdfsd', 'sdfsdfsdf', 'En proceso', '2026-10-21', '2027-01-14', '2026-10-21 14:58:28', '2026-10-21 14:58:28'),
(4, 2, 2, 2, 'Vacuna', '7', '1', '1', 'Dado de Alta', '2026-10-15', '2026-10-06', '2026-10-24 21:23:44', '2026-10-24 21:23:44');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `consultation_files`
--

CREATE TABLE `consultation_files` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `consultation_id` bigint(20) UNSIGNED NOT NULL,
  `uploaded_by` enum('cliente','veterinaria') NOT NULL,
  `uploader_id` bigint(20) UNSIGNED NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `stored_name` varchar(255) NOT NULL,
  `mime_type` varchar(120) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `consultation_medications`
--

CREATE TABLE `consultation_medications` (
  `consultation_id` bigint(20) UNSIGNED NOT NULL,
  `medication_id` bigint(20) UNSIGNED DEFAULT NULL,
  `other_medication` varchar(180) DEFAULT NULL,
  `purchase_needed` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `consultation_medications`
--

INSERT INTO `consultation_medications` (`consultation_id`, `medication_id`, `other_medication`, `purchase_needed`) VALUES
(1, NULL, 'paracetamol', 1),
(2, 1, NULL, 0),
(3, 1, NULL, 0),
(4, NULL, '45', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `medications`
--

CREATE TABLE `medications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `clinic_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(180) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `medications`
--

INSERT INTO `medications` (`id`, `clinic_id`, `name`, `quantity`, `updated_at`) VALUES
(1, 3, 'penisilina', 8, '2026-10-21 14:58:28');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pets`
--

CREATE TABLE `pets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `client_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `animal_type` varchar(80) NOT NULL,
  `sexo` enum('Macho','Hembra') DEFAULT NULL,
  `breed` varchar(120) DEFAULT NULL,
  `weight_kg` decimal(7,2) DEFAULT NULL,
  `height_cm` decimal(7,2) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `pets`
--

INSERT INTO `pets` (`id`, `client_id`, `name`, `animal_type`, `sexo`, `breed`, `weight_kg`, `height_cm`, `birth_date`, `photo_path`, `created_at`) VALUES
(1, 1, 'Tobi', 'Perro', NULL, 'Beagle', 21.00, 41.50, '2021-03-10', NULL, '2026-10-16 16:24:51'),
(2, 2, 'Apolo', 'Perro', 'Macho', 'Shitsu', 8.50, 0.20, '2013-10-11', 'pets/db283741f9eaac9df9cdf39aa99f81d7efc3d566.jpg', '2026-10-21 03:17:32'),
(3, 2, 'Chocolate', 'Perro', 'Macho', 'Salchicha', 0.08, 0.80, '2026-10-23', 'pets/aef5eaf2de35e0917828d6b20a01eb45bfe47de5.jpg', '2026-10-21 12:38:40'),
(5, 4, 'Eustaquio', 'Roedor', 'Macho', 'Chinchilla', 10.00, 10.00, '2008-06-17', 'pets/706a7c913a3fb5d656a3f73b2b97a0d099955963.webp', '2026-10-28 05:23:53');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pet_access_codes`
--

CREATE TABLE `pet_access_codes` (
  `id` int(11) NOT NULL,
  `pet_id` int(11) NOT NULL,
  `code` varchar(10) NOT NULL,
  `action_type` varchar(50) DEFAULT 'modify_history',
  `created_by_client_id` int(11) NOT NULL,
  `used_by_vet_user_id` int(11) DEFAULT NULL,
  `used_at` datetime DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `pet_access_codes`
--

INSERT INTO `pet_access_codes` (`id`, `pet_id`, `code`, `action_type`, `created_by_client_id`, `used_by_vet_user_id`, `used_at`, `expires_at`, `created_at`) VALUES
(1, 2, '234943', 'modify_history', 2, NULL, NULL, '2026-10-24 23:51:04', '2026-10-24 21:21:04'),
(2, 2, '396125', 'modify_history', 2, NULL, NULL, '2026-10-24 23:51:06', '2026-10-24 21:21:06'),
(3, 2, '247998', 'modify_history', 2, NULL, NULL, '2026-10-24 23:51:06', '2026-10-24 21:21:06'),
(4, 2, '204350', 'modify_history', 2, NULL, NULL, '2026-10-24 23:51:06', '2026-10-24 21:21:06'),
(5, 2, '506225', 'modify_history', 2, NULL, NULL, '2026-10-24 23:51:06', '2026-10-24 21:21:06'),
(6, 2, '171325', 'modify_history', 2, NULL, NULL, '2026-10-24 23:51:06', '2026-10-24 21:21:06'),
(7, 2, '399164', 'modify_history', 2, NULL, NULL, '2026-10-24 23:51:07', '2026-10-24 21:21:07'),
(8, 2, '544696', 'modify_history', 2, NULL, NULL, '2026-10-24 23:51:07', '2026-10-24 21:21:07'),
(9, 2, '474794', 'modify_history', 2, 2, '2026-10-24 18:23:44', '2026-10-24 23:51:07', '2026-10-24 21:21:07'),
(10, 2, '613049', 'modify_history', 2, NULL, NULL, '2026-10-27 21:33:18', '2026-10-28 00:03:18'),
(11, 2, '904586', 'modify_history', 2, NULL, NULL, '2026-10-27 21:33:21', '2026-10-28 00:03:21');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pet_co_owners`
--

CREATE TABLE `pet_co_owners` (
  `id` int(11) NOT NULL,
  `pet_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `pet_co_owners`
--

INSERT INTO `pet_co_owners` (`id`, `pet_id`, `client_id`, `created_at`) VALUES
(1, 2, 4, '2026-10-24 22:34:27');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `remember_tokens`
--

CREATE TABLE `remember_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_type` enum('vet','client','admin') NOT NULL DEFAULT 'vet',
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `vet_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `selector` char(24) NOT NULL,
  `validator_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `remember_tokens`
--

INSERT INTO `remember_tokens` (`id`, `user_type`, `user_id`, `vet_user_id`, `selector`, `validator_hash`, `expires_at`, `created_at`) VALUES
(1, 'vet', 0, 0, '57d6ecd2294ae58f7c2f0ce8', '64cd6edd8ebe7b8768b8d0707ec5e140faeb620777053ed474300f9b9a8cf8ba', '2026-12-20 20:33:59', '2026-10-21 23:33:59');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `vaccine_reminders`
--

CREATE TABLE `vaccine_reminders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `consultation_id` bigint(20) UNSIGNED NOT NULL,
  `due_date` date NOT NULL,
  `reminder_number` tinyint(3) UNSIGNED NOT NULL,
  `client_id` bigint(20) UNSIGNED NOT NULL,
  `email` varchar(190) NOT NULL,
  `delivery` enum('smtp','archivo') NOT NULL,
  `sent_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `vet_users`
--

CREATE TABLE `vet_users` (
  `id` int(11) NOT NULL,
  `clinic_id` bigint(20) UNSIGNED NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `vet_users`
--

INSERT INTO `vet_users` (`id`, `clinic_id`, `username`, `email`, `password_hash`, `active`, `created_at`) VALUES
(1, 1, 'Ana', 'ana@gmail.com', '$2y$10$sRBl3iVgFqU1KNS09FnhYOoLNhmq.EmioZGVP810HKmHA2STMKXbC', 1, '2026-10-16 16:23:03'),
(2, 2, 'Teresa', 'teresalasanta@gmail.com', '$2y$10$WN7VOFtdf33QMSwUJV8IU.7jK9nA5FkvF5tRUwWRvhHVWqM/D03A.', 1, '2026-10-21 03:06:45'),
(3, 3, 'Martin', 'martindoc@gmail.com', '$2y$10$V85fHxC/U.KZ9lBFQy66ZuWgp09wJkXWsMp9OT7j.mVRATHU.p8wS', 1, '2026-10-21 03:08:40'),
(4, 1, 'Thiago Mantoani', 'thiago@gmail.com', '$2y$10$6Z4HOcj.j3/Cly3CBdYqdOZeu0iTS9dgc6z.dmPdNC9WOpxbflffm', 1, '2026-10-21 23:37:38');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indices de la tabla `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `pet_id` (`pet_id`),
  ADD KEY `clinic_id` (`clinic_id`,`appointment_at`);

--
-- Indices de la tabla `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `clinic_id` (`clinic_id`),
  ADD KEY `vet_user_id` (`vet_user_id`);

--
-- Indices de la tabla `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `dni` (`dni`);

--
-- Indices de la tabla `clinics`
--
ALTER TABLE `clinics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indices de la tabla `clinic_clients`
--
ALTER TABLE `clinic_clients`
  ADD PRIMARY KEY (`clinic_id`,`client_id`),
  ADD KEY `client_id` (`client_id`);

--
-- Indices de la tabla `consultations`
--
ALTER TABLE `consultations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `clinic_id` (`clinic_id`),
  ADD KEY `vet_user_id` (`vet_user_id`),
  ADD KEY `pet_id` (`pet_id`,`created_at`);

--
-- Indices de la tabla `consultation_files`
--
ALTER TABLE `consultation_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `consultation_id` (`consultation_id`);

--
-- Indices de la tabla `consultation_medications`
--
ALTER TABLE `consultation_medications`
  ADD PRIMARY KEY (`consultation_id`),
  ADD KEY `medication_id` (`medication_id`);

--
-- Indices de la tabla `medications`
--
ALTER TABLE `medications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_med_name` (`clinic_id`,`name`);

--
-- Indices de la tabla `pets`
--
ALTER TABLE `pets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`);

--
-- Indices de la tabla `pet_access_codes`
--
ALTER TABLE `pet_access_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pet_id` (`pet_id`,`code`),
  ADD KEY `expires_at` (`expires_at`);

--
-- Indices de la tabla `pet_co_owners`
--
ALTER TABLE `pet_co_owners`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_pet_client` (`pet_id`,`client_id`),
  ADD KEY `pet_id` (`pet_id`),
  ADD KEY `client_id` (`client_id`);

--
-- Indices de la tabla `remember_tokens`
--
ALTER TABLE `remember_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `selector` (`selector`),
  ADD KEY `vet_user_id` (`vet_user_id`);

--
-- Indices de la tabla `vaccine_reminders`
--
ALTER TABLE `vaccine_reminders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_reminder` (`consultation_id`,`due_date`,`reminder_number`),
  ADD KEY `idx_consultation` (`consultation_id`);

--
-- Indices de la tabla `vet_users`
--
ALTER TABLE `vet_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_vet_email` (`clinic_id`,`email`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `admins`
--
ALTER TABLE `admins`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `clients`
--
ALTER TABLE `clients`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `clinics`
--
ALTER TABLE `clinics`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `consultations`
--
ALTER TABLE `consultations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `consultation_files`
--
ALTER TABLE `consultation_files`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `medications`
--
ALTER TABLE `medications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `pets`
--
ALTER TABLE `pets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `pet_access_codes`
--
ALTER TABLE `pet_access_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de la tabla `pet_co_owners`
--
ALTER TABLE `pet_co_owners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `remember_tokens`
--
ALTER TABLE `remember_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `vaccine_reminders`
--
ALTER TABLE `vaccine_reminders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `vet_users`
--
ALTER TABLE `vet_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
