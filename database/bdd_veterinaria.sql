-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 03-11-2026 a las 21:39:37
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
  `vet_user_id` int(11) DEFAULT NULL,
  `requested_by` enum('cliente','veterinaria') NOT NULL,
  `appointment_at` datetime NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `status` enum('Solicitado','Confirmado','Cancelado','Atendido') NOT NULL DEFAULT 'Solicitado',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `appointments`
--

INSERT INTO `appointments` (`id`, `clinic_id`, `client_id`, `pet_id`, `vet_user_id`, `requested_by`, `appointment_at`, `reason`, `status`, `created_at`) VALUES
(1, 3, 2, 2, NULL, 'veterinaria', '2026-10-22 09:29:00', 'vacunacion', 'Atendido', '2026-10-21 12:29:18');

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
(5, NULL, NULL, 'client', 4, 'admin_update', '{\"full_name\":\"Ricardo Rickenbacher\",\"dni\":\"474747\",\"email\":\"ricardorickenbacher@gmail.com\"}', '2026-10-28 05:21:25'),
(6, NULL, NULL, 'pet', 3, 'client_deleted', '{\"client_id\":2,\"reason\":\"Fallecimiento\"}', '2026-11-01 21:58:25'),
(7, 2, 2, 'consultation', 4, 'updated', '{\"before\":{\"reason\":\"Vacuna\",\"notes\":\"7\",\"diagnosis\":\"1\",\"treatment\":\"1\",\"status\":\"Dado de Alta\"},\"after\":{\"reason\":\"Vacuna\",\"notes\":\"Paciente acude para aplicación de refuerzo anual de vacuna séxtuple. Se realiza examen clínico general previo: temperatura normal (38.5 °C), mucosas rosadas, hidratación adecuada, ganglios linfáticos sin alteraciones y auscultación cardiopulmonar sin ruidos agregados. Se administra la vacuna por vía subcutánea sin reacciones adversas inmediatas. Se aconseja reposo relativo durante las próximas 24 horas.\",\"diagnosis\":\"Paciente clínico sano apto para inmunización profiláctica anual.\",\"treatment\":\"Aplicación de vacuna séxtuple anual (realizada en consultorio).\\r\\n\\r\\nControl periódico y observación en domicilio por posible somnolencia leve post-vacunación.\",\"status\":\"Dado de Alta\"}}', '2026-11-03 18:28:38'),
(8, 3, 3, 'consultation', 3, 'updated', '{\"before\":{\"reason\":\"Vacuna\",\"notes\":\"dfdf\",\"diagnosis\":\"sdfsdfsd\",\"treatment\":\"sdfsdfsdf\",\"status\":\"En proceso\"},\"after\":{\"reason\":\"Control\",\"notes\":\"Control de rutina periódico. Apolo se presenta activo, alerta y con excelente condición corporal (peso estable de 14.2 kg). Se revisan cavidad oral (leve placa de sarro incipiente), estado de pelaje y piel (sin ectoparásitos ni lesiones). Se procede a corte de uñas y limpieza superficial de conductos auditivos.\",\"diagnosis\":\"Paciente en buen estado general de salud \\/ Control periódico de rutina sin hallazgos patológicos de relevancia.\",\"treatment\":\"Mantenimiento de dieta equilibrada habitual.\\r\\n\\r\\nPlan de higiene dental preventiva en casa (cepillado periódico con pasta apta para canes).\\r\\n\\r\\nPróximo control general sugerido en 6 meses.\",\"status\":\"En proceso\"}}', '2026-11-03 18:46:56'),
(9, 3, 3, 'consultation', 2, 'updated', '{\"before\":{\"reason\":\"Vacuna\",\"notes\":\"dolor fisico\",\"diagnosis\":\"dolor estomacal\",\"treatment\":\"Penisilina cada 3 dias\",\"status\":\"Dado de Alta\"},\"after\":{\"reason\":\"Desparasitación\",\"notes\":\"Consulta programada para plan trimestral de desparasitación interna y externa. Tutor refiere buen apetito, correcta hidratación y deposiciones normales. Al examen físico, abdomen blando, depresible y indoloro a la palpación. Sin signos de parásitos en materia fecal reciente.\",\"diagnosis\":\"Protocolo preventivo de desparasitación al día \\/ Sin alteraciones digestivas aparentes.\",\"treatment\":\"Administración oral de comprimido antiparasitario interno de amplio espectro (Praziquantel \\/ Pirantel \\/ Febantel) según franja de peso correspondiente.\\r\\n\\r\\nRefuerzo de pipeta tópica externa para control de pulgas y garrapatas.\\r\\n\\r\\nRepetir esquema trimestralmente según fecha sugerida.\",\"status\":\"Dado de Alta\"}}', '2026-11-03 18:51:34'),
(10, 3, 3, 'consultation', 3, 'updated', '{\"before\":{\"reason\":\"Control\",\"notes\":\"Control de rutina periódico. Apolo se presenta activo, alerta y con excelente condición corporal (peso estable de 14.2 kg). Se revisan cavidad oral (leve placa de sarro incipiente), estado de pelaje y piel (sin ectoparásitos ni lesiones). Se procede a corte de uñas y limpieza superficial de conductos auditivos.\",\"diagnosis\":\"Paciente en buen estado general de salud \\/ Control periódico de rutina sin hallazgos patológicos de relevancia.\",\"treatment\":\"Mantenimiento de dieta equilibrada habitual.\\r\\n\\r\\nPlan de higiene dental preventiva en casa (cepillado periódico con pasta apta para canes).\\r\\n\\r\\nPróximo control general sugerido en 6 meses.\",\"status\":\"En proceso\",\"medication\":\"penisilina\",\"medication_id\":1,\"purchase_needed\":0},\"after\":{\"reason\":\"Control\",\"notes\":\"Control de rutina periódico. Apolo se presenta activo, alerta y con excelente condición corporal (peso estable de 14.2 kg). Se revisan cavidad oral (leve placa de sarro incipiente), estado de pelaje y piel (sin ectoparásitos ni lesiones). Se procede a corte de uñas y limpieza superficial de conductos auditivos.\",\"diagnosis\":\"Paciente en buen estado general de salud \\/ Control periódico de rutina sin hallazgos patológicos de relevancia.\",\"treatment\":\"Mantenimiento de dieta equilibrada habitual.\\r\\n\\r\\nPlan de higiene dental preventiva en casa (cepillado periódico con pasta apta para canes).\\r\\n\\r\\nPróximo control general sugerido en 6 meses.\",\"status\":\"En proceso\",\"medication\":null,\"medication_id\":null,\"purchase_needed\":0}}', '2026-11-03 18:58:18'),
(11, 3, 3, 'consultation', 2, 'updated', '{\"before\":{\"reason\":\"Desparasitación\",\"notes\":\"Consulta programada para plan trimestral de desparasitación interna y externa. Tutor refiere buen apetito, correcta hidratación y deposiciones normales. Al examen físico, abdomen blando, depresible y indoloro a la palpación. Sin signos de parásitos en materia fecal reciente.\",\"diagnosis\":\"Protocolo preventivo de desparasitación al día \\/ Sin alteraciones digestivas aparentes.\",\"treatment\":\"Administración oral de comprimido antiparasitario interno de amplio espectro (Praziquantel \\/ Pirantel \\/ Febantel) según franja de peso correspondiente.\\r\\n\\r\\nRefuerzo de pipeta tópica externa para control de pulgas y garrapatas.\\r\\n\\r\\nRepetir esquema trimestralmente según fecha sugerida.\",\"status\":\"Dado de Alta\",\"medication\":\"penisilina\",\"medication_id\":1,\"purchase_needed\":0},\"after\":{\"reason\":\"Desparasitación\",\"notes\":\"Consulta programada para plan trimestral de desparasitación interna y externa. Tutor refiere buen apetito, correcta hidratación y deposiciones normales. Al examen físico, abdomen blando, depresible y indoloro a la palpación. Sin signos de parásitos en materia fecal reciente.\",\"diagnosis\":\"Protocolo preventivo de desparasitación al día \\/ Sin alteraciones digestivas aparentes.\",\"treatment\":\"Administración oral de comprimido antiparasitario interno de amplio espectro (Praziquantel \\/ Pirantel \\/ Febantel) según franja de peso correspondiente.\\r\\n\\r\\nRefuerzo de pipeta tópica externa para control de pulgas y garrapatas.\\r\\n\\r\\nRepetir esquema trimestralmente según fecha sugerida.\",\"status\":\"Dado de Alta\",\"medication\":null,\"medication_id\":null,\"purchase_needed\":0}}', '2026-11-03 18:58:44'),
(12, 3, 3, 'medication', 1, 'deleted', '{\"name\":\"penisilina\",\"quantity_at_deletion\":3,\"consultations_preserved\":0}', '2026-11-03 19:12:45'),
(13, 3, 3, 'medication', 4, 'created', '{\"name\":\"Meloxicam\",\"initial_quantity\":10}', '2026-11-03 19:14:43'),
(14, 3, 3, 'medication', 5, 'created', '{\"name\":\"Amoxicilina + Ácido Clavulánico\",\"initial_quantity\":20}', '2026-11-03 19:15:17'),
(15, 3, 3, 'medication', 6, 'created', '{\"name\":\"Carprofeno\",\"initial_quantity\":15}', '2026-11-03 19:15:37'),
(16, 3, 3, 'medication', 7, 'created', '{\"name\":\"Prednisona \\/ Prednisolona\",\"initial_quantity\":15}', '2026-11-03 19:15:57'),
(17, 3, 3, 'medication', 8, 'created', '{\"name\":\"Afoxolaner \\/ Fluralaner\",\"initial_quantity\":0}', '2026-11-03 19:16:43'),
(18, 3, 3, 'consultation', 3, 'updated', '{\"before\":{\"reason\":\"Control\",\"notes\":\"Control de rutina periódico. Apolo se presenta activo, alerta y con excelente condición corporal (peso estable de 14.2 kg). Se revisan cavidad oral (leve placa de sarro incipiente), estado de pelaje y piel (sin ectoparásitos ni lesiones). Se procede a corte de uñas y limpieza superficial de conductos auditivos.\",\"diagnosis\":\"Paciente en buen estado general de salud \\/ Control periódico de rutina sin hallazgos patológicos de relevancia.\",\"treatment\":\"Mantenimiento de dieta equilibrada habitual.\\r\\n\\r\\nPlan de higiene dental preventiva en casa (cepillado periódico con pasta apta para canes).\\r\\n\\r\\nPróximo control general sugerido en 6 meses.\",\"status\":\"En proceso\",\"medication\":null,\"medication_id\":null,\"purchase_needed\":0},\"after\":{\"reason\":\"Control\",\"notes\":\"Control de rutina periódico. Apolo se presenta activo, alerta y con excelente condición corporal (peso estable de 14.2 kg). Se revisan cavidad oral (leve placa de sarro incipiente), estado de pelaje y piel (sin ectoparásitos ni lesiones). Se procede a corte de uñas y limpieza superficial de conductos auditivos.\",\"diagnosis\":\"Paciente en buen estado general de salud \\/ Control periódico de rutina sin hallazgos patológicos de relevancia.\",\"treatment\":\"Mantenimiento de dieta equilibrada habitual.\\r\\n\\r\\nPlan de higiene dental preventiva en casa (cepillado periódico con pasta apta para canes).\\r\\n\\r\\nPróximo control general sugerido en 6 meses.\",\"status\":\"En proceso\",\"medication\":\"Amoxicilina + Ácido Clavulánico\",\"medication_id\":5,\"purchase_needed\":0}}', '2026-11-03 19:32:14'),
(19, 3, 3, 'consultation', 2, 'updated', '{\"before\":{\"reason\":\"Desparasitación\",\"notes\":\"Consulta programada para plan trimestral de desparasitación interna y externa. Tutor refiere buen apetito, correcta hidratación y deposiciones normales. Al examen físico, abdomen blando, depresible y indoloro a la palpación. Sin signos de parásitos en materia fecal reciente.\",\"diagnosis\":\"Protocolo preventivo de desparasitación al día \\/ Sin alteraciones digestivas aparentes.\",\"treatment\":\"Administración oral de comprimido antiparasitario interno de amplio espectro (Praziquantel \\/ Pirantel \\/ Febantel) según franja de peso correspondiente.\\r\\n\\r\\nRefuerzo de pipeta tópica externa para control de pulgas y garrapatas.\\r\\n\\r\\nRepetir esquema trimestralmente según fecha sugerida.\",\"status\":\"Dado de Alta\",\"medication\":null,\"medication_id\":null,\"purchase_needed\":0},\"after\":{\"reason\":\"Desparasitación\",\"notes\":\"Consulta programada para plan trimestral de desparasitación interna y externa. Tutor refiere buen apetito, correcta hidratación y deposiciones normales. Al examen físico, abdomen blando, depresible y indoloro a la palpación. Sin signos de parásitos en materia fecal reciente.\",\"diagnosis\":\"Protocolo preventivo de desparasitación al día \\/ Sin alteraciones digestivas aparentes.\",\"treatment\":\"Administración oral de comprimido antiparasitario interno de amplio espectro (Praziquantel \\/ Pirantel \\/ Febantel) según franja de peso correspondiente.\\r\\n\\r\\nRefuerzo de pipeta tópica externa para control de pulgas y garrapatas.\\r\\n\\r\\nRepetir esquema trimestralmente según fecha sugerida.\",\"status\":\"Dado de Alta\",\"medication\":null,\"medication_id\":null,\"purchase_needed\":0}}', '2026-11-03 19:33:22'),
(20, 3, 3, 'consultation', 2, 'updated', '{\"before\":{\"reason\":\"Desparasitación\",\"notes\":\"Consulta programada para plan trimestral de desparasitación interna y externa. Tutor refiere buen apetito, correcta hidratación y deposiciones normales. Al examen físico, abdomen blando, depresible y indoloro a la palpación. Sin signos de parásitos en materia fecal reciente.\",\"diagnosis\":\"Protocolo preventivo de desparasitación al día \\/ Sin alteraciones digestivas aparentes.\",\"treatment\":\"Administración oral de comprimido antiparasitario interno de amplio espectro (Praziquantel \\/ Pirantel \\/ Febantel) según franja de peso correspondiente.\\r\\n\\r\\nRefuerzo de pipeta tópica externa para control de pulgas y garrapatas.\\r\\n\\r\\nRepetir esquema trimestralmente según fecha sugerida.\",\"status\":\"Dado de Alta\",\"medication\":null,\"medication_id\":null,\"purchase_needed\":0},\"after\":{\"reason\":\"Desparasitación\",\"notes\":\"Consulta programada para plan trimestral de desparasitación interna y externa. Tutor refiere buen apetito, correcta hidratación y deposiciones normales. Al examen físico, abdomen blando, depresible y indoloro a la palpación. Sin signos de parásitos en materia fecal reciente.\",\"diagnosis\":\"Protocolo preventivo de desparasitación al día \\/ Sin alteraciones digestivas aparentes.\",\"treatment\":\"Administración oral de comprimido antiparasitario interno de amplio espectro (Praziquantel \\/ Pirantel \\/ Febantel) según franja de peso correspondiente.\\r\\n\\r\\nRefuerzo de pipeta tópica externa para control de pulgas y garrapatas.\\r\\n\\r\\nRepetir esquema trimestralmente según fecha sugerida.\",\"status\":\"Dado de Alta\",\"medication\":\"Carprofeno\",\"medication_id\":6,\"purchase_needed\":0}}', '2026-11-03 19:33:49'),
(21, 3, 3, 'medication', 8, 'details_updated', '{\"name\":\"Afoxolaner \\/ Fluralaner\",\"brand\":\"\",\"purpose\":\"\"}', '2026-11-03 19:37:30'),
(22, 3, 3, 'medication', 5, 'details_updated', '{\"name\":\"Amoxicilina + Ácido Clavulánico\",\"brand\":\"Synulox\",\"purpose\":\"Antibiótico de amplio espectro.\"}', '2026-11-03 19:38:00'),
(23, 3, 3, 'medication', 6, 'details_updated', '{\"name\":\"Carprofeno\",\"brand\":\"\",\"purpose\":\"\"}', '2026-11-03 19:38:24'),
(24, 3, 3, 'medication', 8, 'details_updated', '{\"name\":\"Afoxolaner \\/ Fluralaner\",\"brand\":\"NexGard\",\"purpose\":\"Antiparasitario oral\"}', '2026-11-03 19:38:40'),
(25, 3, 3, 'medication', 6, 'details_updated', '{\"name\":\"Carprofeno\",\"brand\":\"Rimadyl\",\"purpose\":\"Antiinflamatorio no esteroideo (AINE) analgésico.\"}', '2026-11-03 19:39:19'),
(26, 3, 3, 'medication', 4, 'details_updated', '{\"name\":\"Meloxicam\",\"brand\":\"Metacam\",\"purpose\":\"Antiinflamatorio no esteroideo (AINE).\"}', '2026-11-03 19:39:45'),
(27, 3, 3, 'medication', 7, 'details_updated', '{\"name\":\"Prednisona \\/ Prednisolona\",\"brand\":\"Deltasona\",\"purpose\":\"Corticosteroide \\/ Antiinflamatorio e inmunosupresor.\"}', '2026-11-03 19:40:16'),
(28, 2, 2, 'medication', 9, 'created', '{\"name\":\"Oclacitinib\",\"initial_quantity\":10}', '2026-11-03 19:48:01'),
(29, 2, 2, 'medication', 10, 'created', '{\"name\":\"Cefalexina\",\"initial_quantity\":2}', '2026-11-03 19:48:26'),
(30, 2, 2, 'medication', 11, 'created', '{\"name\":\"Metronidazol\",\"initial_quantity\":4}', '2026-11-03 19:48:49'),
(31, 2, 2, 'consultation', 4, 'updated', '{\"before\":{\"reason\":\"Vacuna\",\"notes\":\"Paciente acude para aplicación de refuerzo anual de vacuna séxtuple. Se realiza examen clínico general previo: temperatura normal (38.5 °C), mucosas rosadas, hidratación adecuada, ganglios linfáticos sin alteraciones y auscultación cardiopulmonar sin ruidos agregados. Se administra la vacuna por vía subcutánea sin reacciones adversas inmediatas. Se aconseja reposo relativo durante las próximas 24 horas.\",\"diagnosis\":\"Paciente clínico sano apto para inmunización profiláctica anual.\",\"treatment\":\"Aplicación de vacuna séxtuple anual (realizada en consultorio).\\r\\n\\r\\nControl periódico y observación en domicilio por posible somnolencia leve post-vacunación.\",\"status\":\"Dado de Alta\",\"medication\":\"45\",\"medication_id\":null,\"purchase_needed\":1},\"after\":{\"reason\":\"Vacuna\",\"notes\":\"Paciente acude para aplicación de refuerzo anual de vacuna séxtuple. Se realiza examen clínico general previo: temperatura normal (38.5 °C), mucosas rosadas, hidratación adecuada, ganglios linfáticos sin alteraciones y auscultación cardiopulmonar sin ruidos agregados. Se administra la vacuna por vía subcutánea sin reacciones adversas inmediatas. Se aconseja reposo relativo durante las próximas 24 horas.\",\"diagnosis\":\"Paciente clínico sano apto para inmunización profiláctica anual.\",\"treatment\":\"Aplicación de vacuna séxtuple anual (realizada en consultorio).\\r\\n\\r\\nControl periódico y observación en domicilio por posible somnolencia leve post-vacunación.\",\"status\":\"Dado de Alta\",\"medication\":\"Metoclopramida (comprar en farmacia)\",\"medication_id\":null,\"purchase_needed\":1}}', '2026-11-03 19:49:33'),
(32, 2, 2, 'medication', 9, 'details_updated', '{\"name\":\"Oclacitinib\",\"brand\":\"Apoquel\",\"purpose\":\"Inhibidor selectivo de la Janus quinasa (JAK).\"}', '2026-11-03 19:56:34'),
(33, 2, 2, 'medication', 11, 'details_updated', '{\"name\":\"Metronidazol\",\"brand\":\"Flagyl\",\"purpose\":\"Antibiótico y antiparasitario (nitroimidazol).\"}', '2026-11-03 19:57:00'),
(34, 2, 2, 'medication', 10, 'details_updated', '{\"name\":\"Cefalexina\",\"brand\":\"Cephatabs\",\"purpose\":\"Antibiótico cefalosporínico de primera generación.\"}', '2026-11-03 19:57:20'),
(35, 2, 2, 'consultation', 4, 'updated', '{\"before\":{\"reason\":\"Vacuna\",\"notes\":\"Paciente acude para aplicación de refuerzo anual de vacuna séxtuple. Se realiza examen clínico general previo: temperatura normal (38.5 °C), mucosas rosadas, hidratación adecuada, ganglios linfáticos sin alteraciones y auscultación cardiopulmonar sin ruidos agregados. Se administra la vacuna por vía subcutánea sin reacciones adversas inmediatas. Se aconseja reposo relativo durante las próximas 24 horas.\",\"diagnosis\":\"Paciente clínico sano apto para inmunización profiláctica anual.\",\"treatment\":\"Aplicación de vacuna séxtuple anual (realizada en consultorio).\\r\\n\\r\\nControl periódico y observación en domicilio por posible somnolencia leve post-vacunación.\",\"status\":\"Dado de Alta\",\"medication\":\"Metoclopramida\",\"medication_id\":null,\"purchase_needed\":1},\"after\":{\"reason\":\"Vacuna\",\"notes\":\"Paciente acude para aplicación de refuerzo anual de vacuna séxtuple. Se realiza examen clínico general previo: temperatura normal (38.5 °C), mucosas rosadas, hidratación adecuada, ganglios linfáticos sin alteraciones y auscultación cardiopulmonar sin ruidos agregados. Se administra la vacuna por vía subcutánea sin reacciones adversas inmediatas. Se aconseja reposo relativo durante las próximas 24 horas.\",\"diagnosis\":\"Paciente clínico sano apto para inmunización profiláctica anual.\",\"treatment\":\"Aplicación de vacuna séxtuple anual (realizada en consultorio).\\r\\n\\r\\nControl periódico y observación en domicilio por posible somnolencia leve post-vacunación.\",\"status\":\"Dado de Alta\",\"medication\":\"Cefalexina\",\"medication_id\":10,\"purchase_needed\":0}}', '2026-11-03 19:58:12'),
(36, NULL, NULL, 'client', 9, 'admin_update', '{\"full_name\":\"Thiago Mantoani\",\"dni\":\"45219568\",\"email\":\"thiagomantoani0122@gmail.com\"}', '2026-11-03 20:02:21'),
(37, NULL, NULL, 'client', 5, 'admin_update', '{\"full_name\":\"Pedro Smith\",\"dni\":\"484848\",\"email\":\"pedro@gmail.com\"}', '2026-11-03 20:02:57'),
(38, NULL, NULL, 'client', 9, 'admin_update', '{\"full_name\":\"Thiago Mantoani\",\"dni\":\"494949\",\"email\":\"thiagomantoani0122@gmail.com\"}', '2026-11-03 20:03:08'),
(39, NULL, NULL, 'client', 2, 'admin_update', '{\"full_name\":\"Ivan Bruno\",\"dni\":\"464646\",\"email\":\"ivanignaciobruno@gmail.com\"}', '2026-11-03 20:07:28'),
(40, NULL, NULL, 'client', 4, 'admin_update', '{\"full_name\":\"Ricardo Rickenbacher\",\"dni\":\"474747\",\"email\":\"ricardorickenbacher@gmail.com\"}', '2026-11-03 20:07:40'),
(41, NULL, NULL, 'client', 5, 'admin_update', '{\"full_name\":\"Pedro Smith\",\"dni\":\"484848\",\"email\":\"pedro@gmail.com\"}', '2026-11-03 20:07:49'),
(42, NULL, NULL, 'client', 9, 'admin_update', '{\"full_name\":\"Thiago Mantoani\",\"dni\":\"494949\",\"email\":\"thiagomantoani0122@gmail.com\"}', '2026-11-03 20:07:57'),
(43, NULL, NULL, 'client', 1, 'admin_update', '{\"full_name\":\"Ivana Di Bruneli\",\"dni\":\"505050\",\"email\":\"ivanabruneli@gmail.com\"}', '2026-11-03 20:17:10'),
(44, NULL, NULL, 'client', 1, 'admin_update', '{\"full_name\":\"Ivana Di Bruneli\",\"dni\":\"505050\",\"email\":\"ivanabruneli@gmail.com\"}', '2026-11-03 20:17:18'),
(45, NULL, NULL, 'client', 4, 'admin_update', '{\"full_name\":\"Ricardo Rickenbacher\",\"dni\":\"474747\",\"email\":\"ricardorickenbacher@gmail.com\"}', '2026-11-03 20:19:58');

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
(1, '505050', 'Ivana Di Bruneli', 'ivanabruneli@gmail.com', '11 4455-6677', 'avatars/client_1_1fbe135366470f6c.webp', '$2y$10$KcYtasVwTkprRYszfFFUlOwMfWDFtzKjc173byTM.W/gwKZ6mAc7i', '2026-10-16 16:24:10', '2026-11-03 20:17:18'),
(2, '464646', 'Ivan Bruno', 'ivanignaciobruno@gmail.com', '11 4455-6666', 'avatars/client_2_e9c5092f983aad3f.webp', '$2y$10$1Y03rzBQIPTOHHYFBwzcQ.YmPMzvpYzp4OH9cAxbDcI8S68aB0T3y', '2026-10-21 03:12:47', '2026-11-03 20:07:28'),
(4, '474747', 'Ricardo Rickenbacher', 'ricardorickenbacher@gmail.com', '11 4455-6688', 'avatars/client_4_ce0db24f9ed2cad7.webp', '$2y$10$1Y03rzBQIPTOHHYFBwzcQ.YmPMzvpYzp4OH9cAxbDcI8S68aB0T3y', '2026-10-21 03:16:58', '2026-11-03 20:07:40'),
(5, '484848', 'Pedro Smith', 'pedro@gmail.com', '11 4455-6699', 'avatars/client_5_8beb96feffc364c4.webp', '$2y$10$1Y03rzBQIPTOHHYFBwzcQ.YmPMzvpYzp4OH9cAxbDcI8S68aB0T3y', '2026-10-21 14:59:44', '2026-11-03 20:07:49'),
(9, '494949', 'Thiago Mantoani', 'thiagomantoani0122@gmail.com', '11 4455-6600', 'avatars/client_9_dfc9c352645a2d36.webp', '$2y$10$iRi9wk86/HYCXtfdS/w7d.Cgp3ALy.xsTdYDNwpkQ0lagnCGuPmPO', '2026-11-01 22:06:28', '2026-11-03 20:07:57');

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
(1, 'Mautorrales', 'mautorrales@gmail.com', 'Belgrano 3747', 'San Martín', 'Buenos Aires', 1, '2026-10-16 16:23:03', '2010010020', '11 4455-7777', NULL, '', NULL, NULL),
(2, 'Veterinaria Santa Teresita', 'vetsanter@gmail.com', 'Plaza Belgrano', 'La Plata', 'Buenos Aires', 1, '2026-10-21 03:06:45', '2030030020', '444444', NULL, 'Lunes a Viernes', '08:00:00', '22:00:00'),
(3, 'Veterinaria La Matanza', 'vetmatanza@gmail.com', 'Dr. Rodolfo Monte 848', 'Morón', 'Buenos Aires', 1, '2026-10-21 03:08:40', '2020020020', '11111', NULL, 'lun mar', '21:16:00', '17:18:00');

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
(2, 2, '2026-10-21 03:12:47', '2026-11-03 16:52:14'),
(2, 4, '2026-10-21 03:16:58', '2026-11-03 19:55:55'),
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
(2, 2, 3, 3, 'Desparasitación', 'Consulta programada para plan trimestral de desparasitación interna y externa. Tutor refiere buen apetito, correcta hidratación y deposiciones normales. Al examen físico, abdomen blando, depresible y indoloro a la palpación. Sin signos de parásitos en materia fecal reciente.', 'Protocolo preventivo de desparasitación al día / Sin alteraciones digestivas aparentes.', 'Administración oral de comprimido antiparasitario interno de amplio espectro (Praziquantel / Pirantel / Febantel) según franja de peso correspondiente.\r\n\r\nRefuerzo de pipeta tópica externa para control de pulgas y garrapatas.\r\n\r\nRepetir esquema trimestralmente según fecha sugerida.', 'Dado de Alta', NULL, NULL, '2026-10-21 12:33:13', '2026-11-03 18:51:34'),
(3, 2, 3, 3, 'Control', 'Control de rutina periódico. Apolo se presenta activo, alerta y con excelente condición corporal (peso estable de 14.2 kg). Se revisan cavidad oral (leve placa de sarro incipiente), estado de pelaje y piel (sin ectoparásitos ni lesiones). Se procede a corte de uñas y limpieza superficial de conductos auditivos.', 'Paciente en buen estado general de salud / Control periódico de rutina sin hallazgos patológicos de relevancia.', 'Mantenimiento de dieta equilibrada habitual.\r\n\r\nPlan de higiene dental preventiva en casa (cepillado periódico con pasta apta para canes).\r\n\r\nPróximo control general sugerido en 6 meses.', 'En proceso', NULL, NULL, '2026-10-21 14:58:28', '2026-11-03 18:46:56'),
(4, 2, 2, 2, 'Vacuna', 'Paciente acude para aplicación de refuerzo anual de vacuna séxtuple. Se realiza examen clínico general previo: temperatura normal (38.5 °C), mucosas rosadas, hidratación adecuada, ganglios linfáticos sin alteraciones y auscultación cardiopulmonar sin ruidos agregados. Se administra la vacuna por vía subcutánea sin reacciones adversas inmediatas. Se aconseja reposo relativo durante las próximas 24 horas.', 'Paciente clínico sano apto para inmunización profiláctica anual.', 'Aplicación de vacuna séxtuple anual (realizada en consultorio).\r\n\r\nControl periódico y observación en domicilio por posible somnolencia leve post-vacunación.', 'Dado de Alta', '2027-10-15', '2027-10-12', '2026-10-24 21:23:44', '2026-11-03 18:28:38');

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

--
-- Volcado de datos para la tabla `consultation_files`
--

INSERT INTO `consultation_files` (`id`, `consultation_id`, `uploaded_by`, `uploader_id`, `original_name`, `stored_name`, `mime_type`, `created_at`) VALUES
(2, 2, 'veterinaria', 3, 'informe_desparasitacion_apolo_2026-10-21.jpg', 'cf_2_89462eff3cb8c1f4.webp', 'image/webp', '2026-11-03 19:33:56'),
(3, 4, 'veterinaria', 2, 'informe_vacuna_apolo_2026-10-24.pdf', 'cf_4_a145735529dfbdac.pdf', 'application/pdf', '2026-11-03 19:45:40');

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
(2, 6, NULL, 0),
(3, 5, NULL, 0),
(4, 10, NULL, 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `medications`
--

CREATE TABLE `medications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `clinic_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(180) NOT NULL,
  `brand` varchar(180) DEFAULT NULL,
  `purpose` text DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `last_restock_quantity` int(11) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `medications`
--

INSERT INTO `medications` (`id`, `clinic_id`, `name`, `brand`, `purpose`, `quantity`, `last_restock_quantity`, `updated_at`) VALUES
(4, 3, 'Meloxicam', 'Metacam', 'Antiinflamatorio no esteroideo (AINE).', 10, 10, '2026-11-03 19:39:45'),
(5, 3, 'Amoxicilina + Ácido Clavulánico', 'Synulox', 'Antibiótico de amplio espectro.', 19, 19, '2026-11-03 19:38:00'),
(6, 3, 'Carprofeno', 'Rimadyl', 'Antiinflamatorio no esteroideo (AINE) analgésico.', 14, 14, '2026-11-03 19:39:19'),
(7, 3, 'Prednisona / Prednisolona', 'Deltasona', 'Corticosteroide / Antiinflamatorio e inmunosupresor.', 15, 15, '2026-11-03 19:40:16'),
(8, 3, 'Afoxolaner / Fluralaner', 'NexGard', 'Antiparasitario oral', 0, 0, '2026-11-03 19:38:40'),
(9, 2, 'Oclacitinib', 'Apoquel', 'Inhibidor selectivo de la Janus quinasa (JAK).', 10, 10, '2026-11-03 19:56:34'),
(10, 2, 'Cefalexina', 'Cephatabs', 'Antibiótico cefalosporínico de primera generación.', 1, 2, '2026-11-03 19:58:12'),
(11, 2, 'Metronidazol', 'Flagyl', 'Antibiótico y antiparasitario (nitroimidazol).', 4, 4, '2026-11-03 19:57:00');

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
(1, 1, 'Tobi', 'Perro', 'Macho', 'Beagle', 21.00, 41.50, '2021-03-10', 'pets/6adc029637b130ac1108774db758d34e12905ef6.webp', '2026-10-16 16:24:51'),
(2, 2, 'Apolo', 'Perro', 'Macho', 'Shitsu', 8.50, 0.20, '2013-10-11', 'pets/db283741f9eaac9df9cdf39aa99f81d7efc3d566.jpg', '2026-10-21 03:17:32'),
(5, 4, 'Eustaquio', 'Roedor', 'Macho', 'Chinchilla', 10.00, 10.00, '2008-06-17', 'pets/706a7c913a3fb5d656a3f73b2b97a0d099955963.webp', '2026-10-28 05:23:53'),
(7, 2, 'Teseo', 'Roedor', 'Macho', 'Chinchilla', 2.00, 12.00, NULL, NULL, '2026-11-03 16:15:36'),
(8, 2, 'Icaro', 'Ave', 'Macho', 'Guacamayo', 12.00, 15.00, NULL, NULL, '2026-11-03 16:16:09'),
(9, 2, 'Artemisa', 'Reptil', 'Hembra', 'Salamandra', 3.00, 13.00, NULL, NULL, '2026-11-03 16:17:19');

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
(11, 2, '904586', 'modify_history', 2, NULL, NULL, '2026-10-27 21:33:21', '2026-10-28 00:03:21'),
(12, 2, '332838', 'modify_history', 2, NULL, '2026-11-03 11:33:34', '2026-11-03 12:02:24', '2026-11-03 14:32:24'),
(13, 2, '662226', 'modify_history', 2, NULL, NULL, '2026-11-03 12:03:34', '2026-11-03 14:33:34'),
(14, 2, '901660', 'modify_history', 2, NULL, '2026-11-03 15:28:21', '2026-11-03 15:58:15', '2026-11-03 18:28:15'),
(15, 2, '365159', 'modify_history', 2, NULL, '2026-11-03 15:28:23', '2026-11-03 15:58:21', '2026-11-03 18:28:21'),
(16, 2, '675094', 'modify_history', 2, 2, '2026-11-03 15:28:38', '2026-11-03 15:58:23', '2026-11-03 18:28:23'),
(17, 2, '141039', 'modify_history', 2, 3, '2026-11-03 15:46:56', '2026-11-03 16:16:49', '2026-11-03 18:46:49'),
(18, 2, '862258', 'modify_history', 2, 3, '2026-11-03 15:51:34', '2026-11-03 16:21:25', '2026-11-03 18:51:25'),
(19, 2, '144937', 'modify_history', 2, 3, '2026-11-03 15:58:18', '2026-11-03 16:28:04', '2026-11-03 18:58:04'),
(20, 2, '221620', 'modify_history', 2, 3, '2026-11-03 15:58:44', '2026-11-03 16:28:37', '2026-11-03 18:58:37'),
(21, 2, '392414', 'modify_history', 2, 3, '2026-11-03 16:32:14', '2026-11-03 17:02:04', '2026-11-03 19:32:04'),
(22, 2, '578125', 'modify_history', 2, 3, '2026-11-03 16:33:21', '2026-11-03 17:03:15', '2026-11-03 19:33:15'),
(23, 2, '441087', 'modify_history', 2, 3, '2026-11-03 16:33:49', '2026-11-03 17:03:31', '2026-11-03 19:33:31'),
(24, 2, '441106', 'modify_history', 2, 2, '2026-11-03 16:49:33', '2026-11-03 17:19:27', '2026-11-03 19:49:27'),
(25, 2, '910240', 'modify_history', 2, 2, '2026-11-03 16:58:12', '2026-11-03 17:28:02', '2026-11-03 19:58:02');

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
-- Estructura de tabla para la tabla `plan_requests`
--

CREATE TABLE `plan_requests` (
  `id` int(10) UNSIGNED NOT NULL,
  `token` char(32) NOT NULL,
  `plan_code` varchar(30) NOT NULL,
  `billing` enum('mensual','anual') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `clinic_name` varchar(150) NOT NULL,
  `cuit` varchar(20) NOT NULL,
  `clinic_email` varchar(190) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `available_days` varchar(100) DEFAULT NULL,
  `opening_time` varchar(5) DEFAULT NULL,
  `closing_time` varchar(5) DEFAULT NULL,
  `province` varchar(60) NOT NULL,
  `city` varchar(120) NOT NULL,
  `street` varchar(180) NOT NULL,
  `responsible_name` varchar(120) NOT NULL,
  `responsible_email` varchar(190) NOT NULL,
  `responsible_phone` varchar(50) DEFAULT NULL,
  `staff_count` tinyint(3) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `payment_status` enum('pendiente','en_proceso','aprobado','rechazado','cancelado') NOT NULL DEFAULT 'pendiente',
  `mp_preference_id` varchar(80) DEFAULT NULL,
  `mp_payment_id` varchar(40) DEFAULT NULL,
  `mp_status_detail` varchar(80) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `status` enum('nueva','registrada','descartada') NOT NULL DEFAULT 'nueva',
  `clinic_id` int(11) DEFAULT NULL,
  `admin_note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
(1, 'vet', 0, 0, '57d6ecd2294ae58f7c2f0ce8', '64cd6edd8ebe7b8768b8d0707ec5e140faeb620777053ed474300f9b9a8cf8ba', '2026-12-20 20:33:59', '2026-10-21 23:33:59'),
(41, 'client', 2, NULL, '13714c3dbf7a96414078005b', '05e4258a3e4ad0bd0c95b69ee179e471032e0b41e836c975a230d648d831fbbe', '2027-01-02 15:28:09', '2026-11-03 18:28:09'),
(43, 'client', 2, NULL, 'c8ed6c7dbba6b1fe73bbfe27', '7ec6f6745e782d7d46b52ab8121d45bc4637d258295d9bae8221b5befe069ee9', '2027-01-02 15:54:42', '2026-11-03 18:54:42');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `support_messages`
--

CREATE TABLE `support_messages` (
  `id` int(10) UNSIGNED NOT NULL,
  `sender_type` enum('tutor','veterinaria','publico') NOT NULL,
  `client_id` int(11) DEFAULT NULL,
  `vet_user_id` int(11) DEFAULT NULL,
  `clinic_id` int(11) DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(190) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `subject` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `status` enum('nuevo','resuelto') NOT NULL DEFAULT 'nuevo',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `support_messages`
--

INSERT INTO `support_messages` (`id`, `sender_type`, `client_id`, `vet_user_id`, `clinic_id`, `name`, `email`, `phone`, `subject`, `message`, `status`, `created_at`) VALUES
(1, 'publico', NULL, NULL, NULL, 'grogu', 'grogu@gmail.com', NULL, 'Consulta general', 'hola soy grogu', 'nuevo', '2026-11-03 13:56:58'),
(2, 'tutor', NULL, NULL, NULL, 'grogu', 'grogu@gmail.com', NULL, 'Consulta general', 'hola soy grogu', 'nuevo', '2026-11-03 14:07:22'),
(3, 'tutor', NULL, NULL, NULL, 'grogu', 'grogu@gmail.com', NULL, 'Consulta general', 'hola soy grogu', 'nuevo', '2026-11-03 14:07:46'),
(4, 'veterinaria', NULL, NULL, NULL, 'Pedro Pascal', 'pedro@gmail.com', '123123123123', 'Consulta coso', 'hola soy Pedro Pascal', 'nuevo', '2026-11-03 14:08:21'),
(5, 'veterinaria', NULL, NULL, NULL, 'Pedro Pascal', 'pedro@gmail.com', '123123123123', 'Consulta coso', 'hola soy Pedro Pascal', 'nuevo', '2026-11-03 14:08:39'),
(6, 'tutor', NULL, NULL, NULL, 'Lattafa', 'lattafa@gmail.com', '12312123', 'Pregunta', 'hola soy Lattafa', 'nuevo', '2026-11-03 14:11:59'),
(7, 'tutor', 2, NULL, NULL, 'Ivan Bruno', 'ivanignaciobruno@gmail.com', '111111', 'Problema con mis mascotas o su historial', 'ayudaaaaaaaaaaaaaaaaaaaaaaa', 'nuevo', '2026-11-03 14:22:55');

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
  `role` enum('jefe','doctor','recepcionista') NOT NULL DEFAULT 'jefe',
  `password_hash` varchar(255) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `vet_users`
--

INSERT INTO `vet_users` (`id`, `clinic_id`, `username`, `email`, `role`, `password_hash`, `active`, `created_at`) VALUES
(1, 1, 'Ana', 'ana@gmail.com', 'jefe', '$2y$10$sRBl3iVgFqU1KNS09FnhYOoLNhmq.EmioZGVP810HKmHA2STMKXbC', 1, '2026-10-16 16:23:03'),
(2, 2, 'Teresa', 'teresalasanta@gmail.com', 'jefe', '$2y$10$WN7VOFtdf33QMSwUJV8IU.7jK9nA5FkvF5tRUwWRvhHVWqM/D03A.', 1, '2026-10-21 03:06:45'),
(3, 3, 'Martin', 'martindoc@gmail.com', 'jefe', '$2y$10$V85fHxC/U.KZ9lBFQy66ZuWgp09wJkXWsMp9OT7j.mVRATHU.p8wS', 1, '2026-10-21 03:08:40'),
(4, 1, 'Thiago Mantoani', 'thiago@gmail.com', 'jefe', '$2y$10$6Z4HOcj.j3/Cly3CBdYqdOZeu0iTS9dgc6z.dmPdNC9WOpxbflffm', 1, '2026-10-21 23:37:38');

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
-- Indices de la tabla `plan_requests`
--
ALTER TABLE `plan_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_token` (`token`),
  ADD KEY `idx_payment` (`payment_status`),
  ADD KEY `idx_status` (`status`);

--
-- Indices de la tabla `remember_tokens`
--
ALTER TABLE `remember_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `selector` (`selector`),
  ADD KEY `vet_user_id` (`vet_user_id`);

--
-- Indices de la tabla `support_messages`
--
ALTER TABLE `support_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_sender` (`sender_type`);

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
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT de la tabla `clients`
--
ALTER TABLE `clients`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

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
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `medications`
--
ALTER TABLE `medications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de la tabla `pets`
--
ALTER TABLE `pets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `pet_access_codes`
--
ALTER TABLE `pet_access_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT de la tabla `pet_co_owners`
--
ALTER TABLE `pet_co_owners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `plan_requests`
--
ALTER TABLE `plan_requests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `remember_tokens`
--
ALTER TABLE `remember_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT de la tabla `support_messages`
--
ALTER TABLE `support_messages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

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
