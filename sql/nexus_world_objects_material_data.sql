-- ============================================================
-- Migration: add material_data column to nexus_world_objects
-- Safe version — uses IF NOT EXISTS (MariaDB 10.0.2+ / MySQL 8.0+)
-- Run once on production DB.
-- ============================================================

-- Step 1: Add column only if it doesn't already exist
ALTER TABLE `nexus_world_objects`
  ADD COLUMN IF NOT EXISTS `material_data` LONGTEXT DEFAULT NULL;

-- Step 2: Verify the final table structure
SELECT
  COLUMN_NAME,
  COLUMN_TYPE,
  IS_NULLABLE,
  COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME   = 'nexus_world_objects'
ORDER BY ORDINAL_POSITION;

-- ============================================================
-- FALLBACK (only run if Step 1 fails on older MySQL < 8.0):
-- Check manually first:
--   SHOW COLUMNS FROM nexus_world_objects LIKE 'material_data';
-- If empty result → run this:
--   ALTER TABLE nexus_world_objects ADD COLUMN material_data LONGTEXT DEFAULT NULL;
-- ============================================================
