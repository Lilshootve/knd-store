-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 11-04-2026 a las 22:52:52
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
-- Estructura de tabla para la tabla `nexus_world_builder_catalog`
--

CREATE TABLE `nexus_world_builder_catalog` (
  `id` int(11) NOT NULL,
  `item_code` varchar(64) NOT NULL,
  `name` varchar(100) NOT NULL,
  `category` varchar(32) NOT NULL DEFAULT 'decoration',
  `rarity` varchar(32) NOT NULL DEFAULT 'common',
  `model_url` varchar(512) NOT NULL,
  `wb_scale` decimal(8,4) NOT NULL DEFAULT 1.0000,
  `default_light_json` longtext DEFAULT NULL,
  `hologram` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `nexus_world_builder_catalog`
--

INSERT INTO `nexus_world_builder_catalog` (`id`, `item_code`, `name`, `category`, `rarity`, `model_url`, `wb_scale`, `default_light_json`, `hologram`, `sort_order`, `is_active`, `created_at`) VALUES
(1, 'lamp_neon', 'Neon Lamp', 'floor', 'common', '/assets/models/knd_lamp_neon_01.glb', 1.0000, NULL, 0, 3, 1, '2026-04-11 22:43:00'),
(2, 'crystal_2', 'Crystal 2', 'decoration', 'common', '/assets/models/crystal_2.glb', 1.0000, '{\"type\": \"point\", \"color\": \"#7eb8d8\", \"intensity\": 0.1, \"distance\": 3.6, \"height\": 0.72}', 0, 74, 1, '2026-04-11 22:43:00'),
(3, 'crystal_4', 'Crystal 4', 'decoration', 'common', '/assets/models/crystal_4.glb', 1.0000, '{\"type\": \"point\", \"color\": \"#7eb8d8\", \"intensity\": 0.1, \"distance\": 3.6, \"height\": 0.72}', 0, 75, 1, '2026-04-11 22:43:00'),
(4, 'crystal_5', 'Crystal 5', 'decoration', 'common', '/assets/models/crystal_5.glb', 1.0000, '{\"type\": \"point\", \"color\": \"#7eb8d8\", \"intensity\": 0.1, \"distance\": 3.6, \"height\": 0.72}', 0, 76, 1, '2026-04-11 22:43:00'),
(5, 'crystal_6', 'Crystal 6', 'decoration', 'common', '/assets/models/crystal_6.glb', 1.0000, '{\"type\": \"point\", \"color\": \"#7eb8d8\", \"intensity\": 0.1, \"distance\": 3.6, \"height\": 0.72}', 0, 77, 1, '2026-04-11 22:43:00'),
(6, 'crystal_8', 'Crystal 8', 'decoration', 'common', '/assets/models/crystal_8.glb', 1.0000, '{\"type\": \"point\", \"color\": \"#8899d8\", \"intensity\": 0.1, \"distance\": 3.6, \"height\": 0.72}', 0, 78, 1, '2026-04-11 22:43:00'),
(7, 'crystal_9', 'Crystal 9', 'decoration', 'common', '/assets/models/crystal_9.glb', 1.0000, '{\"type\": \"point\", \"color\": \"#8899d8\", \"intensity\": 0.1, \"distance\": 3.6, \"height\": 0.72}', 0, 79, 1, '2026-04-11 22:43:00'),
(8, 'crystal_10', 'Crystal 10', 'decoration', 'common', '/assets/models/crystal_10.glb', 1.0000, '{\"type\": \"point\", \"color\": \"#8899d8\", \"intensity\": 0.1, \"distance\": 3.6, \"height\": 0.72}', 0, 80, 1, '2026-04-11 22:43:00'),
(9, 'crystal_11', 'Crystal 11', 'decoration', 'common', '/assets/models/crystal_11.glb', 1.0000, '{\"type\": \"point\", \"color\": \"#8899d8\", \"intensity\": 0.1, \"distance\": 3.6, \"height\": 0.72}', 0, 81, 1, '2026-04-11 22:43:00'),
(10, 'crystal_13', 'Crystal 13', 'decoration', 'rare', '/assets/models/crystal_13.glb', 1.0000, '{\"type\": \"point\", \"color\": \"#a898d8\", \"intensity\": 0.1, \"distance\": 3.8, \"height\": 0.78}', 0, 82, 1, '2026-04-11 22:43:00'),
(11, 'crystal_15', 'Crystal 15', 'decoration', 'rare', '/assets/models/crystal_15.glb', 1.0000, '{\"type\": \"point\", \"color\": \"#a898d8\", \"intensity\": 0.1, \"distance\": 3.8, \"height\": 0.78}', 0, 83, 1, '2026-04-11 22:43:00'),
(12, 'crystal_16', 'Crystal 16', 'decoration', 'rare', '/assets/models/crystal_16.glb', 1.0000, '{\"type\": \"point\", \"color\": \"#a898d8\", \"intensity\": 0.1, \"distance\": 3.8, \"height\": 0.78}', 0, 84, 1, '2026-04-11 22:43:00'),
(13, 'crystal_18', 'Crystal 18', 'decoration', 'rare', '/assets/models/crystal_18.glb', 1.0000, '{\"type\": \"point\", \"color\": \"#a898d8\", \"intensity\": 0.1, \"distance\": 3.8, \"height\": 0.78}', 0, 85, 1, '2026-04-11 22:43:00'),
(14, 'crystal_20', 'Crystal 20', 'decoration', 'epic', '/assets/models/crystal_20.glb', 1.0000, '{\"type\": \"point\", \"color\": \"#b0a0e0\", \"intensity\": 0.1, \"distance\": 4.0, \"height\": 0.82}', 0, 86, 1, '2026-04-11 22:43:00'),
(15, 'crystal_21', 'Crystal 21', 'decoration', 'epic', '/assets/models/crystal_21.glb', 1.0000, '{\"type\": \"point\", \"color\": \"#b0a0e0\", \"intensity\": 0.1, \"distance\": 4.0, \"height\": 0.82}', 0, 87, 1, '2026-04-11 22:43:00'),
(16, 'crystal_22', 'Crystal 22', 'decoration', 'epic', '/assets/models/crystal_22.glb', 1.0000, '{\"type\": \"point\", \"color\": \"#b0a0e0\", \"intensity\": 0.1, \"distance\": 4.0, \"height\": 0.82}', 0, 88, 1, '2026-04-11 22:43:00'),
(17, 'crystal_23', 'Crystal 23', 'decoration', 'epic', '/assets/models/crystal_23.glb', 1.0000, '{\"type\": \"point\", \"color\": \"#b0a0e0\", \"intensity\": 0.1, \"distance\": 4.0, \"height\": 0.82}', 0, 89, 1, '2026-04-11 22:43:00'),
(18, 'crystal_25', 'Crystal 25', 'decoration', 'epic', '/assets/models/crystal_25.glb', 1.0000, '{\"type\": \"point\", \"color\": \"#b0a0e0\", \"intensity\": 0.1, \"distance\": 4.0, \"height\": 0.82}', 0, 90, 1, '2026-04-11 22:43:00'),
(19, 'crystal_26', 'Crystal 26', 'decoration', 'epic', '/assets/models/crystal_26.glb', 1.0000, '{\"type\": \"point\", \"color\": \"#b0a0e0\", \"intensity\": 0.1, \"distance\": 4.0, \"height\": 0.82}', 0, 91, 1, '2026-04-11 22:43:00'),
(20, 'crystal_27', 'Crystal 27', 'decoration', 'legendary', '/assets/models/crystal_27.glb', 1.0000, '{\"type\": \"point\", \"color\": \"#c0b0e8\", \"intensity\": 0.1, \"distance\": 4.2, \"height\": 0.88}', 0, 92, 1, '2026-04-11 22:43:00'),
(21, 'crystal_28', 'Crystal 28', 'decoration', 'legendary', '/assets/models/crystal_28.glb', 1.0000, '{\"type\": \"point\", \"color\": \"#c0b0e8\", \"intensity\": 0.1, \"distance\": 4.2, \"height\": 0.88}', 0, 93, 1, '2026-04-11 22:43:00'),
(32, 'knd_bench_neon_01', 'KND Neon Bench', 'props', 'common', '/assets/models/knd_bench_neon_01.glb', 1.0000, NULL, 0, 10, 1, '2026-04-11 22:47:35'),
(33, 'knd_energy_orb_neon_01', 'KND Energy Orb Neon', 'effects', 'rare', '/assets/models/knd_energy_orb_neon_01.glb', 1.0000, NULL, 0, 20, 1, '2026-04-11 22:47:35'),
(34, 'knd_mountain_neon_01', 'KND Neon Mountain', 'environment', 'rare', '/assets/models/knd_mountain_neon_01.glb', 1.0000, NULL, 0, 30, 1, '2026-04-11 22:47:35'),
(35, 'knd_plataform_neon_01', 'KND Neon Platform', 'structures', 'common', '/assets/models/knd_plataform_neon_01.glb', 1.0000, NULL, 0, 40, 1, '2026-04-11 22:47:35'),
(36, 'knd_tree_neon_01', 'KND Neon Tree', 'environment', 'common', '/assets/models/knd_tree_neon_01.glb', 1.0000, NULL, 0, 50, 1, '2026-04-11 22:47:35'),
(37, 'cactus_D', 'Cactus D', 'environment', 'common', '/assets/models/cactus_D.glb', 1.0000, NULL, 0, 60, 1, '2026-04-11 22:47:35');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `nexus_world_builder_catalog`
--
ALTER TABLE `nexus_world_builder_catalog`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_wb_item_code` (`item_code`),
  ADD KEY `idx_wb_active_sort` (`is_active`,`sort_order`,`name`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `nexus_world_builder_catalog`
--
ALTER TABLE `nexus_world_builder_catalog`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
