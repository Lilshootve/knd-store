-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 11-04-2026 a las 22:52:48
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
-- Estructura de tabla para la tabla `nexus_world_objects`
--

CREATE TABLE `nexus_world_objects` (
  `id` int(11) NOT NULL,
  `item_id` varchar(64) NOT NULL DEFAULT '',
  `model_url` varchar(512) DEFAULT NULL,
  `pos_x` float NOT NULL DEFAULT 0,
  `pos_y` float NOT NULL DEFAULT 0,
  `pos_z` float NOT NULL DEFAULT 0,
  `rot_y` float NOT NULL DEFAULT 0,
  `scale` float NOT NULL DEFAULT 1,
  `light_data` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  `zone` varchar(50) DEFAULT 'default',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `material_data` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `nexus_world_objects`
--

INSERT INTO `nexus_world_objects` (`id`, `item_id`, `model_url`, `pos_x`, `pos_y`, `pos_z`, `rot_y`, `scale`, `light_data`, `created_by`, `created_at`, `is_active`, `zone`, `metadata`, `material_data`) VALUES
(28, 'mountain_neon_01', '/assets/models/knd_mountain_neon_01.glb', -29.011, -0.311498, 1.07004, 0, 1, NULL, 6, '2026-04-09 16:42:49', 1, 'default', NULL, '{\"color\":\"#ffffff\",\"emissive\":\"#00ccff\",\"emissiveIntensity\":1.6,\"metalness\":1,\"roughness\":1,\"opacity\":1,\"wireframe\":false,\"hasTexture\":true}'),
(40, 'energy_orb_neon_01', '/assets/models/knd_energy_orb_neon_01.glb', -18.6835, 0, -29.4695, 2.35619, 1.3, NULL, 6, '2026-04-09 17:32:07', 1, 'default', NULL, '{\"color\":\"#ffffff\",\"emissive\":\"#9b30ff\",\"emissiveIntensity\":1.6,\"metalness\":1,\"roughness\":1,\"opacity\":1,\"wireframe\":false,\"hasTexture\":true}'),
(61, 'bench_neon_01', '/assets/models/knd_bench_neon_01.glb', -13.2088, -1.22096, 13.0016, 10.2102, 2.1, NULL, 6, '2026-04-10 14:59:49', 1, 'default', NULL, NULL),
(62, 'bench_neon_01', '/assets/models/knd_bench_neon_01.glb', -21.148, 0, -31.8134, 0, 1, NULL, 6, '2026-04-11 12:08:20', 1, 'default', NULL, '{\"color\":\"#ffffff\",\"emissive\":\"#000000\",\"emissiveIntensity\":1,\"metalness\":1,\"roughness\":1,\"opacity\":1,\"wireframe\":false,\"hasTexture\":true}'),
(78, 'tree_neon_01', '/assets/models/knd_tree_neon_01.glb', 8.435, -0.085, 29.555, 0, 2.6, NULL, 6, '2026-04-11 21:16:51', 1, 'default', NULL, '{\"color\":\"#ffffff\",\"emissive\":\"#00ff88\",\"emissiveIntensity\":0,\"metalness\":1,\"roughness\":1,\"opacity\":1,\"wireframe\":false,\"hasTexture\":true}'),
(79, 'tree_neon_01', '/assets/models/knd_tree_neon_01.glb', 30.606, 0, 0.308, 0, 2.8, NULL, 6, '2026-04-11 22:10:25', 1, 'default', NULL, '{\"color\":\"#ffffff\",\"emissive\":\"#00ff88\",\"emissiveIntensity\":0,\"metalness\":1,\"roughness\":1,\"opacity\":1,\"wireframe\":false,\"hasTexture\":true}');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `nexus_world_objects`
--
ALTER TABLE `nexus_world_objects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_item_id` (`item_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `nexus_world_objects`
--
ALTER TABLE `nexus_world_objects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
