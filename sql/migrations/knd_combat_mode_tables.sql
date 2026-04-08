-- KND Combat: tablas separadas por modo (plan arquitectónico).
-- Ejecutar manualmente o vía pipeline de migraciones del proyecto.
-- La app legacy sigue usando knd_mind_wars_battles hasta cutover por flags.

CREATE TABLE IF NOT EXISTS knd_mw1v1_battles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  battle_token CHAR(64) NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  mode VARCHAR(32) NOT NULL DEFAULT 'pve',
  state_json LONGTEXT NOT NULL,
  result VARCHAR(16) NULL,
  turns_played INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_mw1v1_token (battle_token),
  KEY idx_mw1v1_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS knd_mw3v3_battles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  battle_token CHAR(64) NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  mode VARCHAR(32) NOT NULL DEFAULT 'pve',
  state_json LONGTEXT NOT NULL,
  result VARCHAR(16) NULL,
  turns_played INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_mw3v3_token (battle_token),
  KEY idx_mw3v3_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS knd_squadwars_battles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  battle_token CHAR(64) NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  state_json LONGTEXT NOT NULL,
  result VARCHAR(16) NULL,
  round INT UNSIGNED NOT NULL DEFAULT 1,
  state_version INT UNSIGNED NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_squadwars_token (battle_token),
  KEY idx_squadwars_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS knd_squad_skills (
  id VARCHAR(64) NOT NULL PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  type ENUM('attack','skill','special','passive') NOT NULL,
  target_scope ENUM('single_enemy','single_ally','slot_enemy','row_enemy','all_enemies','all_allies','self') NOT NULL,
  fallback_rules JSON NOT NULL,
  scaling_stat ENUM('mind','focus','speed','luck') NOT NULL,
  base_power DECIMAL(10,2) NOT NULL DEFAULT 0,
  energy_cost TINYINT UNSIGNED NOT NULL DEFAULT 0,
  cooldown TINYINT UNSIGNED NOT NULL DEFAULT 0,
  aoe_mode ENUM('full','reduced','custom') NOT NULL DEFAULT 'full',
  effect_type ENUM('damage','heal','buff','debuff','control','energy') NOT NULL,
  effect_payload JSON NOT NULL,
  rarity ENUM('common','rare','epic','legendary') NOT NULL DEFAULT 'common',
  tags JSON NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
