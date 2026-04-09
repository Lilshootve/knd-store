-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 09-04-2026 a las 14:22:13
-- Versión del servidor: 11.8.6-MariaDB-log
-- Versión de PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `u354862096_kndstore`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users`
--

CREATE TABLE `users` (
  `id` bigint(20) NOT NULL,
  `username` varchar(24) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `firebase_uid` varchar(128) DEFAULT NULL,
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `email_verify_code` varchar(8) DEFAULT NULL,
  `email_verify_expires` datetime DEFAULT NULL,
  `password_reset_code` varchar(8) DEFAULT NULL,
  `password_reset_expires` datetime DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `risk_flag` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `labs_recent_private` tinyint(1) NOT NULL DEFAULT 0,
  `favorite_avatar_id` int(11) DEFAULT NULL,
  `favorite_avatar_item_id` int(11) DEFAULT NULL,
  `last_orb_claim_at` datetime DEFAULT NULL COMMENT 'Last successful holo orb claim (UTC)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `firebase_uid`, `email_verified`, `email_verify_code`, `email_verify_expires`, `password_reset_code`, `password_reset_expires`, `password_hash`, `risk_flag`, `created_at`, `updated_at`, `labs_recent_private`, `favorite_avatar_id`, `favorite_avatar_item_id`, `last_orb_claim_at`) VALUES
(1, 'lol', NULL, NULL, 0, NULL, NULL, NULL, NULL, '$2y$10$2GKTi7o67wNvBiXKTpEjQecBYz3X7Rg90SFdugCmHFBC1OwXBgUPW', 0, '2026-03-02 01:20:31', '2026-03-06 21:01:31', 0, 42, NULL, NULL),
(2, 'nohe', NULL, NULL, 0, NULL, NULL, NULL, NULL, '$2y$10$CeaU7FrOhsiYqdqiHogwheK/UiP7U9VBXt7LVhcDkit9nqWx66E0y', 0, '2026-03-02 01:21:22', '2026-03-20 01:11:41', 0, 177, NULL, NULL),
(3, 'franciscoavr', NULL, NULL, 0, NULL, NULL, NULL, NULL, '$2y$10$SIe57FSYQAbozWmBy7RF2OFVrTAuUYm6LeoKsBMDsBGEKppLlpoNy', 0, '2026-03-02 02:25:04', '2026-03-17 02:30:43', 0, 182, NULL, NULL),
(4, 'lilshoot', NULL, NULL, 0, NULL, NULL, NULL, NULL, '$2y$10$PS7GnfL4ttUrpxtdVST4hOPPGI5MXUqIvDJ4dzizhkABemECQBl5e', 0, '2026-03-02 02:25:16', '2026-03-20 06:43:40', 0, 178, NULL, NULL),
(5, 'Jeje', NULL, NULL, 0, NULL, NULL, NULL, NULL, '$2y$10$l6R4/j2qLgNBdzGVx5ZkJe4LvFMRoP1hkSmZCp1eXcD/X3RsLUydW', 0, '2026-03-02 03:23:44', '2026-03-02 03:23:44', 0, NULL, NULL, NULL),
(6, 'lilshootve', 'lilshootve@gmail.com', NULL, 1, NULL, NULL, NULL, NULL, '$2y$10$N3DYD0P9/p72ns/kloPQLezpNKvYH0AjaAD5lmsOB1zPDTuNA64Yi', 0, '2026-03-02 13:09:43', '2026-04-09 13:02:08', 1, 190, NULL, NULL),
(7, 'Borrocanfor08', 'gkikito4@gmail.com', NULL, 1, NULL, NULL, NULL, NULL, '$2y$10$GLp8jEiLBZd4Mnj9vHbC5udDaz36IXdIVwzs6/rbN27d4YT.x01KO', 0, '2026-03-02 17:31:16', '2026-03-02 17:31:37', 0, NULL, NULL, NULL),
(8, 'Vctr', 'bloodeyes422@gmail.com', NULL, 1, NULL, NULL, NULL, NULL, '$2y$10$haHO3wuJclahcqCbsdUf7u2hKu20l7X5PXkzDt0mebj4rQRqZg5MW', 0, '2026-03-05 00:53:19', '2026-03-05 00:54:41', 0, NULL, NULL, NULL),
(9, 'irimisan', 'serpi.old.58@gmail.com', NULL, 1, NULL, NULL, NULL, NULL, '$2y$10$xDbO8yZColXfbYhpSqp5JuU3LbY8A7YNuqPAI/uu/dcPUy5gYKape', 0, '2026-03-10 03:20:23', '2026-03-15 19:24:57', 0, 197, NULL, NULL),
(10, 'Jesus093', 'lj.alcantara0420@gmail.com', NULL, 0, '704026', '2026-03-23 17:41:03', NULL, NULL, '$2y$10$q5.r7JbwymKgQuHhWoc1SumFJ4QyuefS0r7Yj.nv9CPgkRjCQSgUy', 0, '2026-03-23 17:26:03', '2026-03-23 17:26:03', 0, NULL, NULL, NULL),
(11, 'diegonzs', 'diego.ags04@gmail.com', NULL, 0, '796076', '2026-03-30 19:07:07', NULL, NULL, '$2y$10$Efp6kVuTG66vvyVIzDCDk.zjTZScPM6rmDTo1v.zkLjTJBPOW9X4i', 0, '2026-03-30 18:52:07', '2026-03-31 00:19:12', 0, 336, NULL, NULL),
(12, 'andressul', 'andregonzs.biz@gmail.com', NULL, 0, '765648', '2026-03-31 00:28:21', NULL, NULL, '$2y$10$BXPPzu3fumK/T9ArR4UAR.1JTWN8NAsnmErFodNW11Qd1cxwC7sH2', 0, '2026-03-31 00:13:21', '2026-03-31 00:13:21', 0, NULL, NULL, NULL);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_username` (`username`),
  ADD UNIQUE KEY `uk_email` (`email`),
  ADD UNIQUE KEY `idx_firebase_uid` (`firebase_uid`),
  ADD KEY `fk_user_fav_avatar` (`favorite_avatar_id`),
  ADD KEY `fk_users_favorite_avatar` (`favorite_avatar_item_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_user_fav_avatar` FOREIGN KEY (`favorite_avatar_id`) REFERENCES `knd_avatar_items` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_users_favorite_avatar` FOREIGN KEY (`favorite_avatar_item_id`) REFERENCES `knd_avatar_items` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
