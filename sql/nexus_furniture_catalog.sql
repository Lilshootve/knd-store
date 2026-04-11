-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 11-04-2026 a las 22:33:39
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
-- Estructura de tabla para la tabla `nexus_furniture_catalog`
--

CREATE TABLE `nexus_furniture_catalog` (
  `id` int(11) NOT NULL,
  `code` varchar(64) NOT NULL,
  `name` varchar(100) NOT NULL,
  `category` enum('floor','wall','decoration','interactive','rare') NOT NULL DEFAULT 'floor',
  `rarity` enum('common','rare','special','epic','legendary') NOT NULL DEFAULT 'common',
  `width` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `depth` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `price_kp` int(11) NOT NULL DEFAULT 0,
  `asset_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`asset_data`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `nexus_furniture_catalog`
--

INSERT INTO `nexus_furniture_catalog` (`id`, `code`, `name`, `category`, `rarity`, `width`, `depth`, `price_kp`, `asset_data`, `is_active`, `created_at`) VALUES
(1, 'chair_cyber', 'Cyber Chair', 'floor', 'common', 1, 1, 80, '{\"color\":\"#00e8ff\",\"shape\":\"chair\"}', 1, '2026-04-08 04:59:59'),
(2, 'table_hologram', 'Hologram Table', 'floor', 'rare', 2, 2, 300, '{\"color\":\"#9b30ff\",\"shape\":\"table\",\"fx\":\"hologram\"}', 1, '2026-04-08 04:59:59'),
(3, 'lamp_neon', 'Neon Lamp', 'floor', 'common', 1, 1, 60, '{\"color\": \"#ff3d56\", \"shape\": \"lamp\", \"model\": \"/assets/models/knd_lamp_neon_01.glb\", \"light_intensity\": 0.1, \"light_distance\": 8.5, \"light_height\": 1.25}', 1, '2026-04-08 04:59:59'),
(4, 'rug_hex', 'Hex Rug', 'floor', 'common', 2, 2, 120, '{\"color\":\"#00e8ff\",\"shape\":\"rug\",\"pattern\":\"hex\"}', 1, '2026-04-08 04:59:59'),
(5, 'poster_tesla', 'Tesla Portrait', 'wall', 'rare', 1, 1, 200, '{\"avatar_id\":75,\"type\":\"portrait\"}', 1, '2026-04-08 04:59:59'),
(6, 'poster_alice', 'Alice Portrait', 'wall', 'legendary', 1, 1, 600, '{\"avatar_id\":2,\"type\":\"portrait\"}', 1, '2026-04-08 04:59:59'),
(7, 'trophy_gold', 'Gold Trophy', 'decoration', 'epic', 1, 1, 500, '{\"color\":\"#ffd600\",\"shape\":\"trophy\"}', 1, '2026-04-08 04:59:59'),
(8, 'orb_floating', 'Floating Orb', 'interactive', 'rare', 1, 1, 400, '{\"color\":\"#00e8ff\",\"fx\":\"float\",\"interact\":\"toggle_glow\"}', 1, '2026-04-08 04:59:59'),
(9, 'bed_capsule', 'Capsule Bed', 'floor', 'rare', 2, 1, 350, '{\"color\":\"#050c18\",\"shape\":\"capsule_bed\"}', 1, '2026-04-08 04:59:59'),
(10, 'bookshelf', 'Data Shelf', 'wall', 'common', 2, 1, 150, '{\"color\":\"#0a1420\",\"shape\":\"shelf\"}', 1, '2026-04-08 04:59:59'),
(11, 'terminal_nexus', 'Nexus Terminal', 'floor', 'epic', 2, 1, 750, '{\"color\":\"#00e8ff\",\"shape\":\"terminal\"}', 1, '2026-04-08 08:03:00'),
(12, 'sofa_luxe', 'Luxe Sofa', 'floor', 'rare', 2, 1, 420, '{\"color\":\"#1a0038\",\"shape\":\"sofa\"}', 1, '2026-04-08 08:03:00'),
(13, 'aquarium_holo', 'Holo Aquarium', 'floor', 'legendary', 2, 2, 1200, '{\"color\":\"#0044ff\",\"shape\":\"aquarium\"}', 1, '2026-04-08 08:03:00'),
(14, 'neon_sign_nexus', 'NEXUS Neon Sign', 'wall', 'rare', 2, 1, 280, '{\"color\":\"#ff0080\",\"shape\":\"neon_sign\",\"text\":\"⬡ NEXUS ⬡\"}', 1, '2026-04-08 08:03:00'),
(15, 'neon_sign_knd', 'KND Neon Sign', 'wall', 'special', 2, 1, 380, '{\"color\":\"#00e8ff\",\"shape\":\"neon_sign\",\"text\":\"◈ K N D ◈\"}', 1, '2026-04-08 08:03:00'),
(16, 'arcade_nexus', 'Nexus Arcade Cabinet', 'floor', 'epic', 1, 1, 680, '{\"color\":\"#9b30ff\",\"shape\":\"arcade\"}', 1, '2026-04-08 08:03:00'),
(17, 'lamp_purple', 'Echo Lamp', 'floor', 'special', 1, 1, 90, '{\"color\":\"#c040ff\",\"shape\":\"lamp\"}', 1, '2026-04-08 08:03:00'),
(18, 'orb_red', 'Crimson Orb', 'interactive', 'special', 1, 1, 450, '{\"color\":\"#ff3040\",\"fx\":\"float\"}', 1, '2026-04-08 08:03:00'),
(19, 'trophy_nexus', 'Nexus Champion Cup', 'decoration', 'legendary', 1, 1, 1000, '{\"color\":\"#00e8ff\",\"shape\":\"trophy\"}', 1, '2026-04-08 08:03:00'),
(74, 'crystal_2', 'Crystal 2', 'decoration', 'common', 1, 1, 98, '{\"model\": \"/assets/models/crystal_2.glb\", \"color\": \"#8ec5e0\", \"shape\": \"crystal\", \"light_data\": {\"type\": \"point\", \"color\": \"#7eb8d8\", \"intensity\": 0.1, \"distance\": 3.6, \"height\": 0.72}}', 1, '2026-04-11 18:55:27'),
(75, 'crystal_4', 'Crystal 4', 'decoration', 'common', 1, 1, 102, '{\"model\": \"/assets/models/crystal_4.glb\", \"color\": \"#8ec5e0\", \"shape\": \"crystal\", \"light_data\": {\"type\": \"point\", \"color\": \"#7eb8d8\", \"intensity\": 0.1, \"distance\": 3.6, \"height\": 0.72}}', 1, '2026-04-11 18:55:27'),
(76, 'crystal_5', 'Crystal 5', 'decoration', 'common', 1, 1, 105, '{\"model\": \"/assets/models/crystal_5.glb\", \"color\": \"#8ec5e0\", \"shape\": \"crystal\", \"light_data\": {\"type\": \"point\", \"color\": \"#7eb8d8\", \"intensity\": 0.1, \"distance\": 3.6, \"height\": 0.72}}', 1, '2026-04-11 18:55:27'),
(77, 'crystal_6', 'Crystal 6', 'decoration', 'common', 1, 1, 108, '{\"model\": \"/assets/models/crystal_6.glb\", \"color\": \"#8ec5e0\", \"shape\": \"crystal\", \"light_data\": {\"type\": \"point\", \"color\": \"#7eb8d8\", \"intensity\": 0.1, \"distance\": 3.6, \"height\": 0.72}}', 1, '2026-04-11 18:55:27'),
(78, 'crystal_8', 'Crystal 8', 'decoration', 'common', 1, 1, 118, '{\"model\": \"/assets/models/crystal_8.glb\", \"color\": \"#9ab0e8\", \"shape\": \"crystal\", \"light_data\": {\"type\": \"point\", \"color\": \"#8899d8\", \"intensity\": 0.1, \"distance\": 3.6, \"height\": 0.72}}', 1, '2026-04-11 18:55:27'),
(79, 'crystal_9', 'Crystal 9', 'decoration', 'common', 1, 1, 120, '{\"model\": \"/assets/models/crystal_9.glb\", \"color\": \"#9ab0e8\", \"shape\": \"crystal\", \"light_data\": {\"type\": \"point\", \"color\": \"#8899d8\", \"intensity\": 0.1, \"distance\": 3.6, \"height\": 0.72}}', 1, '2026-04-11 18:55:27'),
(80, 'crystal_10', 'Crystal 10', 'decoration', 'common', 1, 1, 125, '{\"model\": \"/assets/models/crystal_10.glb\", \"color\": \"#9ab0e8\", \"shape\": \"crystal\", \"light_data\": {\"type\": \"point\", \"color\": \"#8899d8\", \"intensity\": 0.1, \"distance\": 3.6, \"height\": 0.72}}', 1, '2026-04-11 18:55:27'),
(81, 'crystal_11', 'Crystal 11', 'decoration', 'common', 1, 1, 128, '{\"model\": \"/assets/models/crystal_11.glb\", \"color\": \"#9ab0e8\", \"shape\": \"crystal\", \"light_data\": {\"type\": \"point\", \"color\": \"#8899d8\", \"intensity\": 0.1, \"distance\": 3.6, \"height\": 0.72}}', 1, '2026-04-11 18:55:27'),
(82, 'crystal_13', 'Crystal 13', 'decoration', 'rare', 1, 1, 145, '{\"model\": \"/assets/models/crystal_13.glb\", \"color\": \"#b8a8e8\", \"shape\": \"crystal\", \"light_data\": {\"type\": \"point\", \"color\": \"#a898d8\", \"intensity\": 0.1, \"distance\": 3.8, \"height\": 0.78}}', 1, '2026-04-11 18:55:27'),
(83, 'crystal_15', 'Crystal 15', 'decoration', 'rare', 1, 1, 150, '{\"model\": \"/assets/models/crystal_15.glb\", \"color\": \"#b8a8e8\", \"shape\": \"crystal\", \"light_data\": {\"type\": \"point\", \"color\": \"#a898d8\", \"intensity\": 0.1, \"distance\": 3.8, \"height\": 0.78}}', 1, '2026-04-11 18:55:27'),
(84, 'crystal_16', 'Crystal 16', 'decoration', 'rare', 1, 1, 152, '{\"model\": \"/assets/models/crystal_16.glb\", \"color\": \"#b8a8e8\", \"shape\": \"crystal\", \"light_data\": {\"type\": \"point\", \"color\": \"#a898d8\", \"intensity\": 0.1, \"distance\": 3.8, \"height\": 0.78}}', 1, '2026-04-11 18:55:27'),
(85, 'crystal_18', 'Crystal 18', 'decoration', 'rare', 1, 1, 158, '{\"model\": \"/assets/models/crystal_18.glb\", \"color\": \"#b8a8e8\", \"shape\": \"crystal\", \"light_data\": {\"type\": \"point\", \"color\": \"#a898d8\", \"intensity\": 0.1, \"distance\": 3.8, \"height\": 0.78}}', 1, '2026-04-11 18:55:27'),
(86, 'crystal_20', 'Crystal 20', 'decoration', 'epic', 1, 1, 175, '{\"model\": \"/assets/models/crystal_20.glb\", \"color\": \"#c8b0f0\", \"shape\": \"crystal\", \"light_data\": {\"type\": \"point\", \"color\": \"#b0a0e0\", \"intensity\": 0.1, \"distance\": 4.0, \"height\": 0.82}}', 1, '2026-04-11 18:55:27'),
(87, 'crystal_21', 'Crystal 21', 'decoration', 'epic', 1, 1, 178, '{\"model\": \"/assets/models/crystal_21.glb\", \"color\": \"#c8b0f0\", \"shape\": \"crystal\", \"light_data\": {\"type\": \"point\", \"color\": \"#b0a0e0\", \"intensity\": 0.1, \"distance\": 4.0, \"height\": 0.82}}', 1, '2026-04-11 18:55:27'),
(88, 'crystal_22', 'Crystal 22', 'decoration', 'epic', 1, 1, 182, '{\"model\": \"/assets/models/crystal_22.glb\", \"color\": \"#c8b0f0\", \"shape\": \"crystal\", \"light_data\": {\"type\": \"point\", \"color\": \"#b0a0e0\", \"intensity\": 0.1, \"distance\": 4.0, \"height\": 0.82}}', 1, '2026-04-11 18:55:27'),
(89, 'crystal_23', 'Crystal 23', 'decoration', 'epic', 1, 1, 185, '{\"model\": \"/assets/models/crystal_23.glb\", \"color\": \"#c8b0f0\", \"shape\": \"crystal\", \"light_data\": {\"type\": \"point\", \"color\": \"#b0a0e0\", \"intensity\": 0.1, \"distance\": 4.0, \"height\": 0.82}}', 1, '2026-04-11 18:55:27'),
(90, 'crystal_25', 'Crystal 25', 'decoration', 'epic', 1, 1, 192, '{\"model\": \"/assets/models/crystal_25.glb\", \"color\": \"#c8b0f0\", \"shape\": \"crystal\", \"light_data\": {\"type\": \"point\", \"color\": \"#b0a0e0\", \"intensity\": 0.1, \"distance\": 4.0, \"height\": 0.82}}', 1, '2026-04-11 18:55:27'),
(91, 'crystal_26', 'Crystal 26', 'decoration', 'epic', 1, 1, 195, '{\"model\": \"/assets/models/crystal_26.glb\", \"color\": \"#c8b0f0\", \"shape\": \"crystal\", \"light_data\": {\"type\": \"point\", \"color\": \"#b0a0e0\", \"intensity\": 0.1, \"distance\": 4.0, \"height\": 0.82}}', 1, '2026-04-11 18:55:27'),
(92, 'crystal_27', 'Crystal 27', 'decoration', 'legendary', 1, 1, 220, '{\"model\": \"/assets/models/crystal_27.glb\", \"color\": \"#d0c0f8\", \"shape\": \"crystal\", \"light_data\": {\"type\": \"point\", \"color\": \"#c0b0e8\", \"intensity\": 0.1, \"distance\": 4.2, \"height\": 0.88}}', 1, '2026-04-11 18:55:27'),
(93, 'crystal_28', 'Crystal 28', 'decoration', 'legendary', 1, 1, 235, '{\"model\": \"/assets/models/crystal_28.glb\", \"color\": \"#d0c0f8\", \"shape\": \"crystal\", \"light_data\": {\"type\": \"point\", \"color\": \"#c0b0e8\", \"intensity\": 0.1, \"distance\": 4.2, \"height\": 0.88}}', 1, '2026-04-11 18:55:27');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `nexus_furniture_catalog`
--
ALTER TABLE `nexus_furniture_catalog`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_code` (`code`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `nexus_furniture_catalog`
--
ALTER TABLE `nexus_furniture_catalog`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=94;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
