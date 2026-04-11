-- ============================================================
-- Migration: add material_data column to nexus_world_objects
-- Run once on production DB.
-- ============================================================

ALTER TABLE `nexus_world_objects`
  ADD COLUMN `material_data` LONGTEXT DEFAULT NULL
    COMMENT 'JSON: { color, emissive, emissiveIntensity, metalness, roughness, opacity, wireframe }'
  AFTER `light_data`;

-- Verify
SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_COMMENT
FROM information_schema.COLUMNS
WHERE TABLE_NAME = 'nexus_world_objects'
ORDER BY ORDINAL_POSITION;
