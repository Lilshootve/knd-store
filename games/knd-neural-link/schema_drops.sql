-- ═══════════════════════════════════════════════════════════════
--  KND NEURAL LINK — Database schema (sandbox module)
--  Run once on your kndstore database after core tables exist:
--    users, mw_avatars, knd_avatar_items, knd_user_avatar_inventory,
--    points_ledger
-- ═══════════════════════════════════════════════════════════════

-- ── 1. Neural Link pity (no ALTER on users) ─────────────────────
CREATE TABLE IF NOT EXISTS `knl_neural_link_pity` (
  `user_id`          BIGINT NOT NULL,
  `pity_legendary`   INT NOT NULL DEFAULT 0,
  `pity_epic`        INT NOT NULL DEFAULT 0,
  `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_knl_pity_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 2. Pull audit log (FK to mw_avatars for resolved entity) ────
CREATE TABLE IF NOT EXISTS `knd_drop_log` (
  `id`               BIGINT NOT NULL AUTO_INCREMENT,
  `user_id`          BIGINT NOT NULL,
  `drop_type`        ENUM('standard','premium','legendary') NOT NULL DEFAULT 'standard',
  `avatar_id`        INT NOT NULL COMMENT 'mw_avatars.id',
  `item_id`          INT NULL DEFAULT NULL COMMENT 'knd_avatar_items.id granted/rolled',
  `rarity_resolved`  ENUM('common','rare','special','epic','legendary') NOT NULL,
  `is_duplicate`     TINYINT(1) NOT NULL DEFAULT 0,
  `ke_gained`        INT NOT NULL DEFAULT 0,
  `cost_kp`          INT NOT NULL DEFAULT 0,
  `cost_coins`       INT NOT NULL DEFAULT 0 COMMENT 'legacy; unused with KP-only packs',
  `cost_gems`        INT NOT NULL DEFAULT 0 COMMENT 'legacy; unused with KP-only packs',
  `opened_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user`    (`user_id`),
  KEY `idx_avatar`  (`avatar_id`),
  KEY `idx_opened`  (`opened_at`),
  CONSTRAINT `fk_drop_log_user`
    FOREIGN KEY (`user_id`)   REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_drop_log_av`
    FOREIGN KEY (`avatar_id`) REFERENCES `mw_avatars` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Si ya creaste knd_drop_log antes sin item_id / cost_kp, ejecuta manualmente:
-- ALTER TABLE `knd_drop_log` ADD COLUMN `item_id` INT NULL DEFAULT NULL AFTER `avatar_id`;
-- ALTER TABLE `knd_drop_log` ADD COLUMN `cost_kp` INT NOT NULL DEFAULT 0 AFTER `ke_gained`;

-- ── 3. PELIGRO: NO ejecutar a ciegas — moneda del módulo es KP (ledger) ──
-- El módulo Neural Link NO usa coins/gems en users. Si añades columnas aquí,
-- valida primero con SHOW COLUMNS FROM users; y revisa compatibilidad con tu app.
/*
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `coins`                INT NOT NULL DEFAULT 1000,
  ADD COLUMN IF NOT EXISTS `gems`                 INT NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `drop_pity_legendary`  INT NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `drop_pity_epic`       INT NOT NULL DEFAULT 0;
*/

-- ── 4. points_ledger: valor ENUM para gastos Neural Link ─────────
-- PRODUCCIÓN: ajusta el ENUM al listado real de tu BD (phpMyAdmin / SHOW COLUMNS)
-- y añade 'knl_neural_link'. Ver también sql/points_ledger_add_3d_lab.sql cadena.
-- Ejemplo (amplía según tu servidor):
/*
ALTER TABLE `points_ledger` MODIFY COLUMN `source_type`
  ENUM(
    'support_payment','redemption','adjustment','avatar_shop',
    '3d_generation','3d_generation_refund',
    'ai_job_spend','ai_job_refund','ai_job_complete',
    'character_lab_spend','character_lab_refund',
    '3d_lab_spend','3d_lab_refund',
    'drop_entry','knl_neural_link'
  ) NOT NULL;
*/

-- ── 5. Vista analítica (nombre distinto del drop principal) ──────
CREATE OR REPLACE VIEW `v_knl_drop_stats` AS
  SELECT
    u.id                                AS user_id,
    COUNT(l.id)                         AS total_pulls,
    SUM(l.is_duplicate)                 AS total_duplicates,
    SUM(l.rarity_resolved = 'legendary') AS legendary_count,
    SUM(l.rarity_resolved = 'epic')     AS epic_count,
    MAX(l.opened_at)                    AS last_pull,
    SUM(l.cost_kp)                      AS total_kp_spent
  FROM users u
  LEFT JOIN knd_drop_log l ON l.user_id = u.id
  GROUP BY u.id;
