-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 19-10-2026 a las 23:19:27
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `vetesta`
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
(1, 'admingrupo6@gmail.com', '$2y$10$a6lZ93lJQJb0smKYs9OWsOgkpmJaKYmiJplGiAwwfieEsfu5ZW0Wu', '2026-10-16 16:21:02');

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
(1, 1, 1, 'pet', 1, 'created', '{\"name\":\"tobi\",\"client_id\":1}', '2026-10-16 16:24:51');

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `clients`
--

INSERT INTO `clients` (`id`, `dni`, `full_name`, `email`, `phone`, `created_at`, `updated_at`) VALUES
(1, '44649955', 'ivan bruno', 'ivan@gmail.com', '1111111111', '2026-10-16 16:24:10', '2026-10-16 16:24:10');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clinics`
--

CREATE TABLE `clinics` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(160) NOT NULL,
  `email` varchar(190) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `clinics`
--

INSERT INTO `clinics` (`id`, `name`, `email`, `active`, `created_at`) VALUES
(1, 'mau', 'mau@gmail.com', 1, '2026-10-16 16:23:03');

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
(1, 1, '2026-10-16 16:24:10', '2026-10-16 16:39:06');

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
(1, 1, 1, 1, 'Consulta general', 'diarrea', 'parasitos en el estomago', 'paracetamol cada 2 dias', 'En proceso', NULL, NULL, '2026-10-16 16:26:25', '2026-10-16 16:26:25');

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
(1, NULL, 'paracetamol', 1);

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

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pets`
--

CREATE TABLE `pets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `client_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `animal_type` varchar(80) NOT NULL,
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

INSERT INTO `pets` (`id`, `client_id`, `name`, `animal_type`, `breed`, `weight_kg`, `height_cm`, `birth_date`, `photo_path`, `created_at`) VALUES
(1, 1, 'tobi', 'Perro', 'beagle', 20.00, 30.00, '2024-06-03', NULL, '2026-10-16 16:24:51');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `remember_tokens`
--

CREATE TABLE `remember_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `vet_user_id` bigint(20) UNSIGNED NOT NULL,
  `selector` char(24) NOT NULL,
  `validator_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `vet_users`
--

CREATE TABLE `vet_users` (
  `id` bigint(20) UNSIGNED NOT NULL,
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
(1, 1, 'ana', 'ana@gmail.com', '$2y$10$sRBl3iVgFqU1KNS09FnhYOoLNhmq.EmioZGVP810HKmHA2STMKXbC', 1, '2026-10-16 16:23:03');

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
-- Indices de la tabla `remember_tokens`
--
ALTER TABLE `remember_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `selector` (`selector`),
  ADD KEY `vet_user_id` (`vet_user_id`);

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
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `clients`
--
ALTER TABLE `clients`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `clinics`
--
ALTER TABLE `clinics`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `consultations`
--
ALTER TABLE `consultations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `consultation_files`
--
ALTER TABLE `consultation_files`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `medications`
--
ALTER TABLE `medications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pets`
--
ALTER TABLE `pets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `remember_tokens`
--
ALTER TABLE `remember_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `vet_users`
--
ALTER TABLE `vet_users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`),
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  ADD CONSTRAINT `appointments_ibfk_3` FOREIGN KEY (`pet_id`) REFERENCES `pets` (`id`);

--
-- Filtros para la tabla `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`),
  ADD CONSTRAINT `audit_log_ibfk_2` FOREIGN KEY (`vet_user_id`) REFERENCES `vet_users` (`id`);

--
-- Filtros para la tabla `clinic_clients`
--
ALTER TABLE `clinic_clients`
  ADD CONSTRAINT `clinic_clients_ibfk_1` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`),
  ADD CONSTRAINT `clinic_clients_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`);

--
-- Filtros para la tabla `consultations`
--
ALTER TABLE `consultations`
  ADD CONSTRAINT `consultations_ibfk_1` FOREIGN KEY (`pet_id`) REFERENCES `pets` (`id`),
  ADD CONSTRAINT `consultations_ibfk_2` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`),
  ADD CONSTRAINT `consultations_ibfk_3` FOREIGN KEY (`vet_user_id`) REFERENCES `vet_users` (`id`);

--
-- Filtros para la tabla `consultation_files`
--
ALTER TABLE `consultation_files`
  ADD CONSTRAINT `consultation_files_ibfk_1` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`);

--
-- Filtros para la tabla `consultation_medications`
--
ALTER TABLE `consultation_medications`
  ADD CONSTRAINT `consultation_medications_ibfk_1` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`),
  ADD CONSTRAINT `consultation_medications_ibfk_2` FOREIGN KEY (`medication_id`) REFERENCES `medications` (`id`);

--
-- Filtros para la tabla `medications`
--
ALTER TABLE `medications`
  ADD CONSTRAINT `medications_ibfk_1` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`);

--
-- Filtros para la tabla `pets`
--
ALTER TABLE `pets`
  ADD CONSTRAINT `pets_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`);

--
-- Filtros para la tabla `remember_tokens`
--
ALTER TABLE `remember_tokens`
  ADD CONSTRAINT `remember_tokens_ibfk_1` FOREIGN KEY (`vet_user_id`) REFERENCES `vet_users` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `vet_users`
--
ALTER TABLE `vet_users`
  ADD CONSTRAINT `fk_vu_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
