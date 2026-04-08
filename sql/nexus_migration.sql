-- ════════════════════════════════════════════════════════════════
--  NEXUS WORLD — MIGRATION v1.0
--  Run once on a clean DB. Safe to re-run: uses IF NOT EXISTS.
-- ════════════════════════════════════════════════════════════════

SET FOREIGN_KEY_CHECKS = 0;

-- ────────────────────────────────────────────────────────────────
-- PATCH 1: points_ledger — add nexus source types
-- ────────────────────────────────────────────────────────────────
ALTER TABLE `points_ledger`
MODIFY `source_type` ENUM(
  'support_payment','redemption','adjustment','avatar_shop',
  '3d_generation','3d_generation_refund','ai_job_spend','ai_job_refund',
  'ai_job_complete','character_lab_spend','character_lab_refund',
  '3d_lab_spend','3d_lab_refund','drop_entry','knl_neural_link',
  'nexus_invoke','nexus_cosmetic','nexus_furniture','nexus_plot'
) NOT NULL;

-- ────────────────────────────────────────────────────────────────
-- PATCH 2: mw_avatars — fix broken image paths
-- ────────────────────────────────────────────────────────────────
UPDATE `mw_avatars` SET `image` = '/assets/avatars/thumbs/lee.png'               WHERE `id` = 67;
UPDATE `mw_avatars` SET `image` = '/assets/avatars/thumbs/barton.png'            WHERE `id` = 74;
UPDATE `mw_avatars` SET `image` = '/assets/avatars/thumbs/vinci.png'             WHERE `id` = 77;
UPDATE `mw_avatars` SET `image` = '/assets/avatars/thumbs/dorian_grey.png'       WHERE `id` = 24;
UPDATE `mw_avatars` SET `image` = '/assets/avatars/thumbs/cid.png'               WHERE `id` = 68;
UPDATE `mw_avatars` SET `image` = '/assets/avatars/thumbs/barca.png'             WHERE `id` = 70;
UPDATE `mw_avatars` SET `image` = '/assets/avatars/thumbs/arc.png'               WHERE `id` = 55;
UPDATE `mw_avatars` SET `image` = '/assets/avatars/thumbs/abraham_lincoln.png'   WHERE `id` = 93;
UPDATE `mw_avatars` SET `image` = '/assets/avatars/thumbs/machiavellico.png'     WHERE `id` = 62;
UPDATE `mw_avatars` SET `image` = '/assets/avatars/thumbs/curie.png'             WHERE `id` = 72;
UPDATE `mw_avatars` SET `image` = '/assets/avatars/thumbs/napoleon_bonaparte.png' WHERE `id` = 95;
UPDATE `mw_avatars` SET `image` = '/assets/avatars/thumbs/lothbrok.png'          WHERE `id` = 57;
UPDATE `mw_avatars` SET `image` = '/assets/avatars/thumbs/bin.png'               WHERE `id` = 78;
UPDATE `mw_avatars` SET `image` = '/assets/avatars/thumbs/tzu.png'               WHERE `id` = 61;
UPDATE `mw_avatars` SET `image` = '/assets/avatars/thumbs/empaler.png'           WHERE `id` = 71;
UPDATE `mw_avatars` SET `image` = '/assets/avatars/thumbs/george_washington.png' WHERE `id` = 98;

-- ────────────────────────────────────────────────────────────────
-- PATCH 3: Corrupted Zeus — nuevo avatar legendary (imagen existe)
-- ────────────────────────────────────────────────────────────────
INSERT IGNORE INTO `mw_avatars` (`id`, `name`, `rarity`, `class`, `subrole`, `image`, `created_at`) VALUES
(99, 'Corrupted Zeus', 'legendary', 'Controller', 'Corrupted', '/assets/avatars/thumbs/corrupted_zeus.png', NOW());

INSERT IGNORE INTO `mw_avatar_stats` (`id`, `avatar_id`, `mind`, `focus`, `speed`, `luck`) VALUES
(99, 99, 88, 62, 55, 45);

INSERT IGNORE INTO `mw_avatar_skills` (
  `id`, `avatar_id`,
  `passive`, `ability`, `special`,
  `passive_code`, `ability_code`, `special_code`,
  `status_effect`, `status_chance`,
  `basic_data`, `passive_data`, `ability_data`, `special_data`
) VALUES (
  99, 99,
  'Voltage Accumulation: Cada golpe acumula carga; al tercer stack explota en AoE',
  'Storm Fracture: Daño AoE + aplica Static + reduce Focus del objetivo',
  'Omega Collapse: Daño masivo único + stun + elimina todos los buffs del objetivo',
  'voltage_accumulation', 'storm_fracture', 'omega_collapse',
  'static', 0.320,
  -- basic_data
  '{"base_power":72,"scaling_stat":"mind","hit_type":"single","can_crit":true,"energy_cost":0,"cooldown":0,"target_scope":"single_enemy","fallback_rules":"highest_mind","aoe_mode":"none","tags":["damage"],"effect_payload":[{"type":"status_apply","status":"static","chance":0.32,"duration":2}]}',
  -- passive_data
  '{"type":"stack_mechanic","stack_name":"voltage_charge","max_stacks":3,"trigger":"on_hit","effect_at_max":{"type":"aoe_burst","base_power":55,"scaling_stat":"mind","target_scope":"all_enemies","aoe_mode":"full","tags":["damage","aoe"],"effect_payload":[{"type":"status_apply","status":"static","chance":0.45,"duration":1}]}}',
  -- ability_data
  '{"base_power":105,"scaling_stat":"mind","hit_type":"aoe","can_crit":true,"energy_cost":2,"cooldown":2,"target_scope":"all_enemies","fallback_rules":"highest_mind","aoe_mode":"reduced","tags":["damage","debuff","aoe"],"effect_payload":[{"type":"status_apply","status":"static","chance":0.55,"duration":2},{"type":"stat_down","stat":"focus","value":0.20,"duration":2}]}',
  -- special_data
  '{"base_power":165,"scaling_stat":"mind","hit_type":"single","can_crit":true,"energy_cost":5,"cooldown":4,"target_scope":"single_enemy","fallback_rules":"highest_mind","aoe_mode":"none","tags":["damage","control","purge"],"effect_payload":[{"type":"stun","duration":1},{"type":"buff_purge","target":"enemy","count":99}]}'
);

-- ────────────────────────────────────────────────────────────────
-- TABLE 1: nexus_districts — config estático de cada zona
-- ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `nexus_districts` (
  `id`           VARCHAR(20) NOT NULL,
  `name`         VARCHAR(80) NOT NULL,
  `era`          VARCHAR(80) NOT NULL,
  `tag`          VARCHAR(120) NOT NULL,
  `color_hex`    VARCHAR(7) NOT NULL DEFAULT '#00e8ff',
  `icon`         VARCHAR(8) NOT NULL DEFAULT '⬡',
  `game_url`     VARCHAR(255) DEFAULT NULL,
  `pos_x`        FLOAT NOT NULL DEFAULT 0,
  `pos_z`        FLOAT NOT NULL DEFAULT 0,
  `sort_order`   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `nexus_districts` VALUES
('olimpo',  'Monte Olimpo',      'Era de los Dioses',     'Combate · PvP · Arena',              '#ffd600', '⚡', '/mind-wars-lobby.php',        -26, -14, 1),
('tesla',   'Laboratorio Tesla', 'Era de la Ciencia',     'Conocimiento · XP · Lore',           '#00e8ff', '⚗️', '/knowledge-duel-lobby.php',   26, -14, 2),
('casino',  'Casino del Destino','Era del Azar',          'Economía · Fragmentos · Riesgo',     '#9b30ff', '🎰', '/above-under.php',             -26,  18, 3),
('agora',   'Ágora Social',      'Era de los Guardianes', 'Social · Housing · Comunidad',       '#00ff88', '🏛', '/agora.php',                    26,  18, 4),
('central', 'Nexus Central',     'Era del Nexus',         'Rankings · Meta · Leaderboards',     '#ff6600', '⬡', NULL,                             0,   0, 0);

-- ────────────────────────────────────────────────────────────────
-- TABLE 2: nexus_player_state — posición en tiempo real
-- ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `nexus_player_state` (
  `user_id`      BIGINT(20) NOT NULL,
  `pos_x`        FLOAT NOT NULL DEFAULT 0,
  `pos_z`        FLOAT NOT NULL DEFAULT 0,
  `rotation_y`   FLOAT NOT NULL DEFAULT 0,
  `district_id`  VARCHAR(20) DEFAULT NULL,
  `last_active`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────────
-- TABLE 3: nexus_player_appearance — apariencia 3D del Guardian
-- ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `nexus_player_appearance` (
  `user_id`            BIGINT(20) NOT NULL,
  `display_name`       VARCHAR(20) DEFAULT NULL,
  `color_body`         VARCHAR(7) NOT NULL DEFAULT '#00e8ff',
  `color_visor`        VARCHAR(7) NOT NULL DEFAULT '#00e8ff',
  `color_echo`         VARCHAR(7) NOT NULL DEFAULT '#ffd600',
  `cosmetic_head`      INT(11) DEFAULT NULL,
  `cosmetic_back`      INT(11) DEFAULT NULL,
  `cosmetic_trail`     INT(11) DEFAULT NULL,
  `cosmetic_echo`      INT(11) DEFAULT NULL,
  `cosmetic_nameplate` INT(11) DEFAULT NULL,
  `updated_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────────
-- TABLE 4: nexus_cosmetics — catálogo 3D (separado de knd_avatar_items)
-- ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `nexus_cosmetics` (
  `id`           INT(11) NOT NULL AUTO_INCREMENT,
  `code`         VARCHAR(64) NOT NULL,
  `name`         VARCHAR(100) NOT NULL,
  `slot`         ENUM('head','back','trail','echo_shape','nameplate_fx','color_pack') NOT NULL,
  `rarity`       ENUM('common','rare','special','epic','legendary') NOT NULL DEFAULT 'common',
  `price_kp`     INT(11) NOT NULL DEFAULT 0,
  `is_earnable`  TINYINT(1) NOT NULL DEFAULT 1,
  `preview_data` JSON DEFAULT NULL,
  `is_active`    TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed inicial de cosméticos
INSERT IGNORE INTO `nexus_cosmetics` (`code`,`name`,`slot`,`rarity`,`price_kp`,`is_earnable`,`preview_data`) VALUES
('trail_cyan_sparks',    'Cyan Sparks',       'trail',      'common',   150, 1, '{"color":"#00e8ff","type":"sparks"}'),
('trail_purple_smoke',   'Purple Smoke',      'trail',      'rare',     350, 1, '{"color":"#9b30ff","type":"smoke"}'),
('trail_gold_flame',     'Gold Flame',        'trail',      'epic',     800, 1, '{"color":"#ffd600","type":"flame"}'),
('trail_void',           'Void Walker',       'trail',      'legendary',2000,0, '{"color":"#000000","type":"void"}'),
('echo_ring',            'Echo Ring',         'echo_shape', 'common',   100, 1, '{"shape":"ring"}'),
('echo_hex',             'Hex Orbit',         'echo_shape', 'rare',     400, 1, '{"shape":"hex"}'),
('echo_star',            'Star Burst',        'echo_shape', 'epic',     900, 1, '{"shape":"star"}'),
('head_crown',           'Static Crown',      'head',       'epic',     700, 1, '{"mesh":"crown","color":"#ffd600"}'),
('head_halo',            'Neon Halo',         'head',       'rare',     300, 1, '{"mesh":"halo","color":"#00e8ff"}'),
('nameplate_fire',       'Blazing Title',     'nameplate_fx','rare',    250, 1, '{"fx":"fire"}'),
('nameplate_electric',   'Electric Title',    'nameplate_fx','epic',    600, 1, '{"fx":"electric"}'),
('color_pack_crimson',   'Crimson Override',  'color_pack', 'rare',     500, 1, '{"body":"#cc2244","visor":"#ff4466","echo":"#ff0022"}'),
('color_pack_emerald',   'Emerald Override',  'color_pack', 'epic',     900, 1, '{"body":"#00aa55","visor":"#00ff88","echo":"#00ffcc"}');

-- ────────────────────────────────────────────────────────────────
-- TABLE 5: nexus_player_cosmetics — cosméticos poseídos
-- ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `nexus_player_cosmetics` (
  `user_id`       BIGINT(20) NOT NULL,
  `cosmetic_id`   INT(11) NOT NULL,
  `obtained_via`  ENUM('purchase','drop','event','invoke_reward','achievement') NOT NULL DEFAULT 'purchase',
  `obtained_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `cosmetic_id`),
  FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`)         ON DELETE CASCADE,
  FOREIGN KEY (`cosmetic_id`) REFERENCES `nexus_cosmetics`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────────
-- TABLE 6: nexus_echo — resonancia viva de cada avatar
-- ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `nexus_echo` (
  `avatar_id`          INT(11) NOT NULL,
  `resonance`          TINYINT UNSIGNED NOT NULL DEFAULT 100,
  `district_id`        VARCHAR(20) NOT NULL DEFAULT 'central',
  `status`             ENUM('active','fading','ghost','forgotten') NOT NULL DEFAULT 'active',
  `last_invoked`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `total_invocations`  INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`avatar_id`),
  FOREIGN KEY (`avatar_id`)   REFERENCES `mw_avatars`(`id`)       ON DELETE CASCADE,
  FOREIGN KEY (`district_id`) REFERENCES `nexus_districts`(`id`)  ON DELETE SET DEFAULT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed: asignar distrito por class, ajustar por lore
INSERT IGNORE INTO `nexus_echo` (`avatar_id`, `resonance`, `district_id`, `status`, `last_invoked`)
SELECT
  a.id,
  100,
  CASE a.class
    WHEN 'Striker'    THEN 'olimpo'
    WHEN 'Tank'       THEN 'olimpo'
    WHEN 'Controller' THEN 'casino'
    WHEN 'Strategist' THEN 'tesla'
    ELSE 'agora'
  END,
  'active',
  NOW()
FROM mw_avatars a;

-- Ajustes de lore
UPDATE `nexus_echo` SET `district_id` = 'tesla'   WHERE `avatar_id` IN (1, 3, 75, 76, 77, 94, 72, 85, 84, 89);  -- Einstein, Franklin, Tesla, Turing, Da Vinci, Newton, Curie, Paracelsus, Avicenna, Hawking
UPDATE `nexus_echo` SET `district_id` = 'olimpo'  WHERE `avatar_id` IN (33, 21, 91, 5, 22, 8, 25);               -- Zeus, Odin, Thor, Kraken, Hercules, Wukong, Genghis
UPDATE `nexus_echo` SET `district_id` = 'casino'  WHERE `avatar_id` IN (92, 32, 35, 19, 26, 27, 18);             -- Loki, Corrupted Loki, Krampus, Mad Hatter, Sandman, Headless, Dracula
UPDATE `nexus_echo` SET `district_id` = 'agora'   WHERE `avatar_id` IN (7, 87, 86, 88, 90, 97, 2, 38);           -- Sherlock, Freud, Jung, Nash, Confucius, Rapunzel, Alice, Queen Grimhilde
UPDATE `nexus_echo` SET `district_id` = 'central' WHERE `avatar_id` IN (99);                                      -- Corrupted Zeus (boss del nexo)

-- ────────────────────────────────────────────────────────────────
-- TABLE 7: nexus_echo_invocations — historial de restauraciones
-- ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `nexus_echo_invocations` (
  `id`                INT(11) NOT NULL AUTO_INCREMENT,
  `user_id`           BIGINT(20) NOT NULL,
  `avatar_id`         INT(11) NOT NULL,
  `resonance_before`  TINYINT UNSIGNED NOT NULL,
  `resonance_after`   TINYINT UNSIGNED NOT NULL,
  `kp_spent`          INT(11) NOT NULL DEFAULT 0,
  `reward_type`       ENUM('kp','drop','cosmetic','xp','none') NOT NULL DEFAULT 'none',
  `reward_amount`     INT(11) NOT NULL DEFAULT 0,
  `reward_ref_id`     INT(11) DEFAULT NULL,
  `invoked_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_user_id`   (`user_id`),
  INDEX `idx_avatar_id` (`avatar_id`),
  FOREIGN KEY (`user_id`)   REFERENCES `users`(`id`)      ON DELETE CASCADE,
  FOREIGN KEY (`avatar_id`) REFERENCES `mw_avatars`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────────
-- TABLE 8: nexus_plots — parcelas de vivienda
-- ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `nexus_plots` (
  `id`               INT(11) NOT NULL AUTO_INCREMENT,
  `user_id`          BIGINT(20) NOT NULL,
  `grid_x`           TINYINT(4) NOT NULL DEFAULT 0,
  `grid_z`           TINYINT(4) NOT NULL DEFAULT 0,
  `plot_size`        TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `exterior_theme`   VARCHAR(50) NOT NULL DEFAULT 'cyber',
  `exterior_color`   VARCHAR(7) NOT NULL DEFAULT '#00e8ff',
  `house_name`       VARCHAR(40) DEFAULT NULL,
  `is_public`        TINYINT(1) NOT NULL DEFAULT 1,
  `acquired_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user`  (`user_id`),
  UNIQUE KEY `uq_grid`  (`grid_x`, `grid_z`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────────
-- TABLE 9: nexus_furniture_catalog
-- ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `nexus_furniture_catalog` (
  `id`          INT(11) NOT NULL AUTO_INCREMENT,
  `code`        VARCHAR(64) NOT NULL,
  `name`        VARCHAR(100) NOT NULL,
  `category`    ENUM('floor','wall','decoration','interactive','rare') NOT NULL DEFAULT 'floor',
  `rarity`      ENUM('common','rare','special','epic','legendary') NOT NULL DEFAULT 'common',
  `width`       TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `depth`       TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `price_kp`    INT(11) NOT NULL DEFAULT 0,
  `asset_data`  JSON DEFAULT NULL,
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `nexus_furniture_catalog` (`code`,`name`,`category`,`rarity`,`width`,`depth`,`price_kp`,`asset_data`) VALUES
('chair_cyber',    'Cyber Chair',        'floor',       'common',   1,1,   80, '{"color":"#00e8ff","shape":"chair"}'),
('table_hologram', 'Hologram Table',     'floor',       'rare',     2,2,  300, '{"color":"#9b30ff","shape":"table","fx":"hologram"}'),
('lamp_neon',      'Neon Lamp',          'floor',       'common',   1,1,   60, '{"color":"#ff3d56","shape":"lamp"}'),
('rug_hex',        'Hex Rug',            'floor',       'common',   2,2,  120, '{"color":"#00e8ff","shape":"rug","pattern":"hex"}'),
('poster_tesla',   'Tesla Portrait',     'wall',        'rare',     1,1,  200, '{"avatar_id":75,"type":"portrait"}'),
('poster_alice',   'Alice Portrait',     'wall',        'legendary',1,1,  600, '{"avatar_id":2,"type":"portrait"}'),
('trophy_gold',    'Gold Trophy',        'decoration',  'epic',     1,1,  500, '{"color":"#ffd600","shape":"trophy"}'),
('orb_floating',   'Floating Orb',       'interactive', 'rare',     1,1,  400, '{"color":"#00e8ff","fx":"float","interact":"toggle_glow"}'),
('bed_capsule',    'Capsule Bed',        'floor',       'rare',     2,1,  350, '{"color":"#050c18","shape":"capsule_bed"}'),
('bookshelf',      'Data Shelf',         'wall',        'common',   2,1,  150, '{"color":"#0a1420","shape":"shelf"}'),
('terminal_nexus', 'Nexus Terminal',     'floor',       'epic',     2,1,  750, '{"color":"#00e8ff","shape":"terminal"}'),
('sofa_luxe',      'Luxe Sofa',          'floor',       'rare',     2,1,  420, '{"color":"#1a0038","shape":"sofa"}'),
('aquarium_holo',  'Holo Aquarium',      'floor',       'legendary',2,2, 1200, '{"color":"#0044ff","shape":"aquarium"}'),
('neon_sign_nexus','NEXUS Neon Sign',    'wall',        'rare',     2,1,  280, '{"color":"#ff0080","shape":"neon_sign","text":"⬡ NEXUS ⬡"}'),
('neon_sign_knd',  'KND Neon Sign',      'wall',        'special',  2,1,  380, '{"color":"#00e8ff","shape":"neon_sign","text":"◈ K N D ◈"}'),
('arcade_nexus',   'Nexus Arcade Cabinet','floor',      'epic',     1,1,  680, '{"color":"#9b30ff","shape":"arcade"}'),
('lamp_purple',    'Echo Lamp',          'floor',       'special',  1,1,   90, '{"color":"#c040ff","shape":"lamp"}'),
('orb_red',        'Crimson Orb',        'interactive', 'special',  1,1,  450, '{"color":"#ff3040","fx":"float"}'),
('trophy_nexus',   'Nexus Champion Cup', 'decoration',  'legendary',1,1, 1000, '{"color":"#00e8ff","shape":"trophy"}');

-- ────────────────────────────────────────────────────────────────
-- TABLE 10: nexus_room_furniture — muebles del usuario
-- ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `nexus_room_furniture` (
  `id`              INT(11) NOT NULL AUTO_INCREMENT,
  `user_id`         BIGINT(20) NOT NULL,
  `furniture_id`    INT(11) NOT NULL,
  `room`            ENUM('main','exterior') NOT NULL DEFAULT 'main',
  `cell_x`          TINYINT UNSIGNED NOT NULL,
  `cell_y`          TINYINT UNSIGNED NOT NULL,
  `rotation`        TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `color_override`  VARCHAR(7) DEFAULT NULL,
  `placed_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cell` (`user_id`, `room`, `cell_x`, `cell_y`),
  FOREIGN KEY (`user_id`)      REFERENCES `users`(`id`)                  ON DELETE CASCADE,
  FOREIGN KEY (`furniture_id`) REFERENCES `nexus_furniture_catalog`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────────────────
-- TABLE 11: nexus_chat_log — persistencia y moderación
-- ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `nexus_chat_log` (
  `id`          BIGINT(20) NOT NULL AUTO_INCREMENT,
  `user_id`     BIGINT(20) NOT NULL,
  `channel`     ENUM('global','agora','district') NOT NULL DEFAULT 'global',
  `district_id` VARCHAR(20) DEFAULT NULL,
  `message`     VARCHAR(200) NOT NULL,
  `sent_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_channel_time` (`channel`, `sent_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ════════════════════════════════════════════════════════════════
-- DONE — Nexus migration v1.0
-- ════════════════════════════════════════════════════════════════
