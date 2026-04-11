-- ============================================================
-- nexus_rooms_migration.sql
-- Introduces a proper room system for Sanctum.
-- Run once. Safe to re-run (uses IF NOT EXISTS / IGNORE).
-- ============================================================

-- ── Step 1: Create nexus_rooms ────────────────────────────
CREATE TABLE IF NOT EXISTS nexus_rooms (
    id            INT          AUTO_INCREMENT PRIMARY KEY,
    owner_user_id BIGINT       NOT NULL,
    name          VARCHAR(100) NOT NULL DEFAULT 'My Sanctum',
    is_public     TINYINT(1)   NOT NULL DEFAULT 0,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_owner (owner_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Step 2: Add room_id to nexus_room_furniture ───────────
-- Safe: only adds the column if it does not exist yet.
SET @col_exists = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'nexus_room_furniture'
      AND COLUMN_NAME  = 'room_id'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE nexus_room_furniture ADD COLUMN room_id INT NULL AFTER user_id',
    'SELECT ''room_id column already exists'' AS info'
);
PREPARE _stmt FROM @sql;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

-- ── Step 3: Seed nexus_rooms for every existing user ──────
-- Picks the house_name / is_public from nexus_plots if available.
INSERT INTO nexus_rooms (owner_user_id, name, is_public, created_at)
SELECT
    rf.user_id,
    COALESCE(
        NULLIF(TRIM(np.house_name), ''),
        CONCAT(COALESCE(u.username, 'Player'), '''s Sanctum')
    ),
    COALESCE(np.is_public, 0),
    NOW()
FROM (
    SELECT DISTINCT user_id FROM nexus_room_furniture
) rf
LEFT JOIN users      u  ON u.id  = rf.user_id
LEFT JOIN nexus_plots np ON np.user_id = rf.user_id
WHERE NOT EXISTS (
    SELECT 1 FROM nexus_rooms r WHERE r.owner_user_id = rf.user_id
);

-- ── Step 4: Back-fill room_id on existing furniture ───────
UPDATE nexus_room_furniture rf
JOIN   nexus_rooms r ON r.owner_user_id = rf.user_id
SET    rf.room_id = r.id
WHERE  rf.room_id IS NULL;

-- ── Done ──────────────────────────────────────────────────
SELECT 'Migration complete' AS status,
       (SELECT COUNT(*) FROM nexus_rooms) AS total_rooms;
