-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 07-04-2026 a las 18:29:22
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
-- Estructura de tabla para la tabla `mw_avatar_stats`
--

CREATE TABLE `mw_avatar_stats` (
  `id` int(11) NOT NULL,
  `avatar_id` int(11) NOT NULL,
  `mind` int(11) DEFAULT 0,
  `focus` int(11) DEFAULT 0,
  `speed` int(11) DEFAULT 0,
  `luck` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `mw_avatar_stats`
--

INSERT INTO `mw_avatar_stats` (`id`, `avatar_id`, `mind`, `focus`, `speed`, `luck`) VALUES
(1, 1, 75, 67, 60, 70),
(2, 2, 75, 57, 85, 95),
(3, 3, 65, 77, 65, 60),
(4, 4, 51, 62, 85, 60),
(5, 5, 80, 33, 40, 50),
(6, 6, 83, 65, 60, 65),
(7, 7, 92, 72, 70, 75),
(8, 8, 62, 57, 62, 88),
(14, 16, 70, 47, 95, 75),
(15, 17, 65, 72, 45, 50),
(16, 18, 80, 56, 70, 75),
(17, 19, 70, 52, 85, 80),
(18, 20, 85, 62, 65, 75),
(19, 21, 85, 72, 60, 70),
(20, 22, 70, 62, 55, 100),
(21, 23, 57, 72, 50, 100),
(22, 24, 75, 52, 85, 75),
(23, 25, 60, 52, 90, 75),
(24, 26, 85, 67, 65, 70),
(25, 27, 63, 52, 90, 75),
(26, 28, 85, 62, 65, 75),
(27, 29, 65, 47, 90, 75),
(28, 30, 85, 72, 60, 70),
(29, 31, 80, 77, 60, 70),
(30, 32, 75, 52, 90, 70),
(31, 33, 36, 52, 85, 75),
(32, 34, 85, 72, 60, 70),
(33, 35, 80, 62, 65, 80),
(34, 36, 70, 52, 90, 75),
(35, 37, 70, 52, 85, 80),
(36, 38, 80, 77, 60, 70),
(37, 39, 90, 67, 55, 75),
(38, 40, 75, 57, 85, 70),
(39, 41, 85, 67, 65, 70),
(40, 42, 75, 62, 85, 65),
(41, 43, 75, 72, 55, 85),
(51, 53, 50, 45, 70, 55),
(52, 54, 55, 50, 75, 60),
(53, 55, 60, 55, 65, 60),
(54, 56, 45, 85, 40, 50),
(55, 57, 50, 80, 55, 55),
(56, 58, 50, 82, 50, 60),
(57, 59, 70, 65, 50, 60),
(58, 60, 75, 60, 45, 55),
(59, 61, 80, 60, 55, 65),
(60, 62, 75, 55, 60, 70),
(61, 63, 65, 60, 50, 75),
(62, 64, 70, 65, 65, 60),
(63, 65, 75, 60, 50, 65),
(64, 66, 80, 65, 45, 60),
(65, 67, 65, 60, 90, 75),
(66, 68, 70, 70, 65, 65),
(67, 69, 60, 65, 80, 60),
(68, 70, 75, 90, 50, 60),
(69, 71, 65, 88, 55, 70),
(70, 72, 90, 70, 50, 60),
(71, 73, 85, 75, 45, 55),
(72, 74, 75, 80, 55, 65),
(73, 75, 95, 65, 60, 70),
(74, 76, 92, 70, 55, 65),
(75, 77, 95, 70, 55, 70),
(76, 78, 88, 80, 55, 65),
(77, 79, 60, 95, 45, 65),
(78, 80, 85, 60, 55, 80),
(79, 81, 92, 80, 50, 65),
(80, 82, 65, 70, 75, 80),
(81, 83, 70, 100, 40, 60),
(82, 84, 95, 80, 55, 65),
(83, 85, 90, 75, 60, 75),
(84, 86, 100, 70, 55, 70),
(85, 87, 95, 65, 50, 75),
(86, 88, 100, 75, 60, 85),
(87, 89, 100, 80, 40, 70),
(88, 90, 95, 85, 50, 65),
(89, 91, 75, 60, 80, 65),
(90, 92, 70, 75, 70, 80),
(91, 93, 80, 85, 55, 65),
(92, 94, 90, 80, 50, 70),
(93, 95, 78, 82, 60, 68),
(94, 96, 85, 75, 65, 75),
(95, 97, 75, 90, 60, 70),
(96, 98, 82, 78, 65, 72);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `mw_avatar_stats`
--
ALTER TABLE `mw_avatar_stats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_stats_avatar` (`avatar_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `mw_avatar_stats`
--
ALTER TABLE `mw_avatar_stats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=97;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `mw_avatar_stats`
--
ALTER TABLE `mw_avatar_stats`
  ADD CONSTRAINT `fk_stats_avatar` FOREIGN KEY (`avatar_id`) REFERENCES `mw_avatars` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `mw_avatar_stats_ibfk_1` FOREIGN KEY (`avatar_id`) REFERENCES `mw_avatars` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
