-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 11-04-2026 a las 23:04:43
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
-- Estructura de tabla para la tabla `nexus_room_furniture`
--

CREATE TABLE `nexus_room_furniture` (
  `id` int(11) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `furniture_id` int(11) NOT NULL,
  `room` enum('main','exterior') NOT NULL DEFAULT 'main',
  `cell_x` tinyint(3) UNSIGNED NOT NULL,
  `cell_y` tinyint(3) UNSIGNED NOT NULL,
  `rotation` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `color_override` varchar(7) DEFAULT NULL,
  `placed_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `nexus_room_furniture`
--

INSERT INTO `nexus_room_furniture` (`id`, `user_id`, `furniture_id`, `room`, `cell_x`, `cell_y`, `rotation`, `color_override`, `placed_at`) VALUES
(6, 6, 1, 'main', 8, 5, 3, NULL, '2026-04-09 16:05:03'),
(7, 6, 2, 'main', 1, 7, 0, NULL, '2026-04-09 16:05:18'),
(8, 6, 14, 'main', 1, 0, 0, NULL, '2026-04-09 16:05:30'),
(9, 6, 9, 'main', 7, 0, 0, NULL, '2026-04-09 16:05:43'),
(11, 6, 10, 'main', 1, 2, 0, NULL, '2026-04-09 16:06:15'),
(12, 6, 12, 'main', 0, 4, 0, NULL, '2026-04-09 16:06:22'),
(13, 6, 5, 'main', 3, 0, 0, NULL, '2026-04-09 16:06:27'),
(14, 6, 18, 'main', 8, 2, 0, NULL, '2026-04-09 16:06:35'),
(16, 6, 8, 'main', 5, 2, 0, NULL, '2026-04-09 16:07:03'),
(17, 6, 7, 'main', 7, 8, 0, NULL, '2026-04-09 16:07:12'),
(18, 6, 15, 'main', 5, 0, 0, NULL, '2026-04-09 16:07:23'),
(19, 6, 6, 'main', 9, 0, 0, NULL, '2026-04-09 16:07:29'),
(20, 6, 17, 'main', 6, 0, 0, NULL, '2026-04-09 16:07:42'),
(21, 6, 16, 'main', 9, 2, 0, NULL, '2026-04-09 16:07:57'),
(23, 6, 4, 'main', 5, 3, 0, NULL, '2026-04-09 16:08:17'),
(24, 6, 13, 'main', 4, 8, 0, NULL, '2026-04-09 16:11:24'),
(25, 6, 3, 'main', 9, 6, 0, NULL, '2026-04-09 16:13:17'),
(26, 6, 19, 'main', 9, 8, 0, NULL, '2026-04-09 16:39:35'),
(32, 3, 3, 'main', 0, 6, 3, NULL, '2026-04-09 17:00:20'),
(33, 3, 7, 'main', 1, 7, 1, NULL, '2026-04-09 17:00:56'),
(34, 3, 12, 'main', 0, 9, 3, NULL, '2026-04-09 17:01:20'),
(35, 3, 14, 'main', 0, 2, 3, NULL, '2026-04-09 17:03:20'),
(37, 9, 12, 'main', 0, 7, 3, NULL, '2026-04-10 04:19:12'),
(39, 9, 15, 'main', 0, 4, 3, NULL, '2026-04-10 04:20:39'),
(40, 6, 88, 'main', 9, 5, 0, NULL, '2026-04-11 20:37:09');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `nexus_room_furniture`
--
ALTER TABLE `nexus_room_furniture`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cell` (`user_id`,`room`,`cell_x`,`cell_y`),
  ADD KEY `furniture_id` (`furniture_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `nexus_room_furniture`
--
ALTER TABLE `nexus_room_furniture`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `nexus_room_furniture`
--
ALTER TABLE `nexus_room_furniture`
  ADD CONSTRAINT `nexus_room_furniture_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `nexus_room_furniture_ibfk_2` FOREIGN KEY (`furniture_id`) REFERENCES `nexus_furniture_catalog` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
