-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 08-04-2026 a las 16:40:54
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
-- Estructura de tabla para la tabla `mw_avatars`
--

CREATE TABLE `mw_avatars` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `rarity` enum('common','rare','special','epic','legendary') NOT NULL,
  `class` enum('Striker','Tank','Support','Controller','Strategist') NOT NULL,
  `subrole` varchar(50) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `mw_avatars`
--

INSERT INTO `mw_avatars` (`id`, `name`, `rarity`, `class`, `subrole`, `image`, `created_at`) VALUES
(1, 'Albert Einstein', 'legendary', 'Strategist', NULL, '/assets/avatars/thumbs/albert_einstein.png', '2026-03-17 22:27:02'),
(2, 'Alice', 'legendary', 'Controller', NULL, '/assets/avatars/thumbs/alice.png', '2026-03-17 22:27:02'),
(3, 'Benjamin Franklin', 'legendary', 'Strategist', NULL, '/assets/avatars/thumbs/benjamin_franklin.png', '2026-03-17 22:27:02'),
(4, 'Jack Frost', 'legendary', 'Controller', NULL, '/assets/avatars/thumbs/jack_frost.png', '2026-03-17 22:27:02'),
(5, 'Kraken', 'legendary', 'Tank', NULL, '/assets/avatars/thumbs/kraken.png', '2026-03-17 22:27:02'),
(6, 'Medusa', 'legendary', 'Controller', NULL, '/assets/avatars/thumbs/medusa.png', '2026-03-17 22:27:02'),
(7, 'Sherlock Holmes', 'legendary', 'Strategist', NULL, '/assets/avatars/thumbs/sherlock_holmes.png', '2026-03-17 22:27:02'),
(8, 'Wukong', 'legendary', 'Tank', NULL, '/assets/avatars/thumbs/wukong.png', '2026-03-17 22:27:20'),
(16, 'Fenrir', 'special', 'Striker', NULL, '/assets/avatars/thumbs/fenrir.png', '2026-03-18 12:43:09'),
(17, 'Frankenstein', 'rare', 'Tank', NULL, '/assets/avatars/thumbs/frankenstein.png', '2026-03-18 12:43:09'),
(18, 'Dracula', 'epic', 'Controller', NULL, '/assets/avatars/thumbs/dracula_new.png', '2026-03-18 12:43:09'),
(19, 'Mad Hatter', 'rare', 'Controller', NULL, '/assets/avatars/thumbs/mad_hatter.png', '2026-03-18 12:43:09'),
(20, 'Anubis', 'rare', 'Controller', NULL, '/assets/avatars/thumbs/anubis.png', '2026-03-18 12:43:09'),
(21, 'Odin', 'epic', 'Strategist', NULL, '/assets/avatars/thumbs/odin.png', '2026-03-18 13:26:58'),
(22, 'Hercules', 'epic', 'Tank', NULL, '/assets/avatars/thumbs/hercules.png', '2026-03-18 13:26:58'),
(23, 'Hydra', 'common', 'Tank', NULL, '/assets/avatars/thumbs/hydra.png', '2026-03-18 13:26:58'),
(24, 'Dorian Gray', 'special', 'Strategist', NULL, '/assets/avatars/thumbs/dorian_grey.png', '2026-03-18 13:26:58'),
(25, 'Genghis Khan', 'epic', 'Striker', NULL, '/assets/avatars/thumbs/genghis_khan.png', '2026-03-18 13:26:58'),
(26, 'Sandman', 'epic', 'Controller', NULL, '/assets/avatars/thumbs/sandman.png', '2026-03-18 13:29:16'),
(27, 'Headless Horseman', 'epic', 'Striker', NULL, '/assets/avatars/thumbs/headless_horseman.png', '2026-03-18 13:29:16'),
(28, 'Morgana', 'epic', 'Controller', NULL, '/assets/avatars/thumbs/morgana.png', '2026-03-18 13:29:16'),
(29, 'Wendigo', 'epic', 'Striker', NULL, '/assets/avatars/thumbs/wendigo.png', '2026-03-18 13:29:16'),
(30, 'Julio César', 'rare', 'Strategist', NULL, '/assets/avatars/thumbs/julio_cesar.png', '2026-03-18 13:29:16'),
(31, 'Merlin', 'epic', 'Strategist', NULL, '/assets/avatars/thumbs/merlin.png', '2026-03-18 13:31:38'),
(32, 'Corrupted Loki', 'legendary', 'Controller', NULL, '/assets/avatars/thumbs/corrupted_loki.png', '2026-03-18 13:31:38'),
(33, 'Zeus', 'epic', 'Striker', NULL, '/assets/avatars/thumbs/zeus.png', '2026-03-18 13:31:38'),
(34, 'Simon Bolivar', 'epic', 'Strategist', NULL, '/assets/avatars/thumbs/simon_bolivar.png', '2026-03-18 13:31:38'),
(35, 'Krampus', 'epic', 'Controller', NULL, '/assets/avatars/thumbs/krampus.png', '2026-03-18 13:31:38'),
(36, 'Puss in Boots', 'epic', 'Striker', NULL, '/assets/avatars/thumbs/puss_in_boots.png', '2026-03-18 13:41:24'),
(37, 'Little Red Riding Hood', 'common', 'Striker', NULL, '/assets/avatars/thumbs/little_red_riding_hood.png', '2026-03-18 13:41:24'),
(38, 'Queen Grimhilde', 'special', 'Strategist', NULL, '/assets/avatars/thumbs/queen_grimhilde.png', '2026-03-18 13:41:24'),
(39, 'Ichabod Crane', 'special', 'Strategist', NULL, '/assets/avatars/thumbs/ichabod_crane.png', '2026-03-18 13:41:24'),
(40, 'Aladdín', 'epic', 'Strategist', NULL, '/assets/avatars/thumbs/aladdin.png', '2026-03-18 13:41:24'),
(41, 'Long John Silver', 'special', 'Strategist', NULL, '/assets/avatars/thumbs/long_john_silver.png', '2026-03-18 13:44:44'),
(42, 'Pinocchio', 'rare', 'Strategist', NULL, '/assets/avatars/thumbs/pinocchio.png', '2026-03-18 13:44:44'),
(43, 'Arthur King', 'special', 'Tank', NULL, '/assets/avatars/thumbs/arthur_king.png', '2026-03-18 13:44:44'),
(53, 'Spartacus', 'common', 'Striker', NULL, '/assets/avatars/thumbs/spartacus.png', '2026-03-18 16:38:58'),
(54, 'Miyamoto Musashi', 'common', 'Striker', NULL, '/assets/avatars/thumbs/musashi.png', '2026-03-18 16:39:07'),
(55, 'Joan of Arc', 'common', 'Striker', NULL, '/assets/avatars/thumbs/arc.png', '2026-03-18 16:39:12'),
(56, 'Leonidas', 'common', 'Tank', NULL, '/assets/avatars/thumbs/leonidas.png', '2026-03-18 16:39:17'),
(57, 'Ragnar Lothbrok', 'common', 'Tank', NULL, '/assets/avatars/thumbs/lothbrok.png', '2026-03-18 16:39:22'),
(58, 'William Wallace', 'common', 'Tank', NULL, '/assets/avatars/thumbs/wallace.png', '2026-03-18 16:39:41'),
(59, 'Florence Nightingale', 'common', 'Strategist', NULL, '/assets/avatars/thumbs/nightingale.png', '2026-03-18 16:39:47'),
(60, 'Hippocrates', 'common', 'Strategist', NULL, '/assets/avatars/thumbs/hippocrates.png', '2026-03-18 16:39:52'),
(61, 'Sun Tzu', 'common', 'Controller', NULL, '/assets/avatars/thumbs/tzu.png', '2026-03-18 16:39:58'),
(62, 'Niccolo Machiavelli', 'common', 'Controller', NULL, '/assets/avatars/thumbs/machiavellico.png', '2026-03-18 16:40:02'),
(63, 'Rasputin', 'common', 'Controller', NULL, '/assets/avatars/thumbs/rasputin.png', '2026-03-18 16:40:07'),
(64, 'Alexander the Great', 'common', 'Strategist', NULL, '/assets/avatars/thumbs/alexander.png', '2026-03-18 16:40:11'),
(65, 'Pericles', 'common', 'Strategist', NULL, '/assets/avatars/thumbs/pericles.png', '2026-03-18 16:40:17'),
(66, 'Otto von Bismarck', 'common', 'Strategist', NULL, '/assets/avatars/thumbs/bismarck.png', '2026-03-18 16:40:23'),
(67, 'Bruce Lee', 'rare', 'Striker', NULL, '/assets/avatars/thumbs/lee.png', '2026-03-18 16:42:07'),
(68, 'El Cid', 'common', 'Striker', NULL, '/assets/avatars/thumbs/cid.png', '2026-03-18 16:42:33'),
(69, 'Achilles', 'rare', 'Striker', NULL, '/assets/avatars/thumbs/achilles.png', '2026-03-18 16:42:39'),
(70, 'Hannibal Barca', 'rare', 'Tank', NULL, '/assets/avatars/thumbs/barca.png', '2026-03-18 16:42:49'),
(71, 'Vlad the Impaler', 'rare', 'Tank', NULL, '/assets/avatars/thumbs/empaler.png', '2026-03-18 16:42:54'),
(72, 'Marie Curie', 'rare', 'Strategist', NULL, '/assets/avatars/thumbs/curie.png', '2026-03-18 16:43:00'),
(73, 'Louis Pasteur', 'special', 'Strategist', NULL, '/assets/avatars/thumbs/pasteur.png', '2026-03-18 16:43:04'),
(74, 'Clara Barton', 'special', 'Strategist', NULL, '/assets/avatars/thumbs/barton.png', '2026-03-18 16:43:09'),
(75, 'Nikola Tesla', 'rare', 'Controller', NULL, '/assets/avatars/thumbs/tesla.png', '2026-03-18 16:43:16'),
(76, 'Alan Turing', 'rare', 'Controller', NULL, '/assets/avatars/thumbs/turing.png', '2026-03-18 16:43:23'),
(77, 'Leonardo da Vinci', 'rare', 'Strategist', NULL, '/assets/avatars/thumbs/vinci.png', '2026-03-18 16:43:41'),
(78, 'Sun Bin', 'rare', 'Strategist', NULL, '/assets/avatars/thumbs/bin.png', '2026-03-18 16:43:48'),
(79, 'Samson', 'common', 'Tank', NULL, '/assets/avatars/thumbs/samson.png', '2026-03-18 16:44:34'),
(80, 'Edgar Allan Poe', 'rare', 'Controller', NULL, '/assets/avatars/thumbs/poe.png', '2026-03-18 16:44:42'),
(81, 'Chanakya', 'rare', 'Strategist', NULL, '/assets/avatars/thumbs/chanakya.png', '2026-03-18 16:44:49'),
(82, 'Beowulf', 'common', 'Striker', NULL, '/assets/avatars/thumbs/beowulf.png', '2026-03-18 16:48:07'),
(83, 'Atlas', 'common', 'Tank', NULL, '/assets/avatars/thumbs/atlas.png', '2026-03-18 16:48:12'),
(84, 'Avicenna', 'rare', 'Strategist', NULL, '/assets/avatars/thumbs/avicenna.png', '2026-03-18 16:48:17'),
(85, 'Paracelsus', 'rare', 'Strategist', NULL, '/assets/avatars/thumbs/paracelsus.png', '2026-03-18 16:48:22'),
(86, 'Carl Jung', 'special', 'Controller', NULL, '/assets/avatars/thumbs/jung.png', '2026-03-18 16:48:27'),
(87, 'Sigmund Freud', 'special', 'Controller', NULL, '/assets/avatars/thumbs/freud.png', '2026-03-18 16:48:33'),
(88, 'John Nash', 'special', 'Controller', NULL, '/assets/avatars/thumbs/nash.png', '2026-03-18 16:48:38'),
(89, 'Stephen Hawking', 'special', 'Strategist', NULL, '/assets/avatars/thumbs/hawking.png', '2026-03-18 16:48:43'),
(90, 'Confucius', 'rare', 'Strategist', NULL, '/assets/avatars/thumbs/confucius.png', '2026-03-18 16:48:49'),
(91, 'Thor', 'epic', 'Striker', NULL, '/assets/avatars/thumbs/thor.png', '2026-03-18 18:31:03'),
(92, 'Loki', 'epic', 'Controller', NULL, '/assets/avatars/thumbs/loki.png', '2026-03-18 18:31:03'),
(93, 'Abraham Lincoln', 'epic', 'Strategist', NULL, '/assets/avatars/thumbs/abraham_lincoln.png', '2026-03-18 18:31:03'),
(94, 'Isaac Newton', 'epic', 'Strategist', NULL, '/assets/avatars/thumbs/newton.png', '2026-03-18 18:31:03'),
(95, 'Napoleon Bonaparte', 'epic', 'Strategist', NULL, '/assets/avatars/thumbs/napoleon_bonaparte.png', '2026-03-18 18:31:03'),
(96, 'Corrupted Odin', 'epic', 'Controller', NULL, '/assets/avatars/thumbs/corrupted_odin.png', '2026-03-18 18:31:03'),
(97, 'Rapunzel', 'epic', 'Strategist', NULL, '/assets/avatars/thumbs/rapunzel.png', '2026-03-18 18:31:03'),
(98, 'George Washington', 'epic', 'Strategist', NULL, '/assets/avatars/thumbs/george_washington.png', '2026-03-18 18:31:03'),
(99, 'Corrupted Zeus', 'legendary', 'Controller', 'Corrupted', '/assets/avatars/thumbs/corrupted_zeus.png', '2026-04-08 04:59:59');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `mw_avatars`
--
ALTER TABLE `mw_avatars`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `mw_avatars`
--
ALTER TABLE `mw_avatars`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=100;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
