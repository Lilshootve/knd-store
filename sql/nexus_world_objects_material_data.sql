-- Migration: add material_data to nexus_world_objects
-- Table confirmed columns: id, item_id, model_url, pos_x, pos_y, pos_z,
--   rot_y, scale, light_data, created_by, created_at, is_active, zone, metadata
-- material_data does NOT exist yet — run this once:

ALTER TABLE `nexus_world_objects`
  ADD COLUMN `material_data` LONGTEXT DEFAULT NULL;

-- Verify:
SHOW COLUMNS FROM `nexus_world_objects`;
