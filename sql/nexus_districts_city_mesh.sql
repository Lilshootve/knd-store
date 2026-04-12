-- Nexus city: optional GLB landmark per district + transform (shared by all users).
-- Run after nexus_migration.sql / existing nexus_districts table.

ALTER TABLE `nexus_districts`
  ADD COLUMN `city_glb_url` VARCHAR(512) NULL DEFAULT NULL
    COMMENT 'Public URL path to GLB for district landmark in Nexus City (e.g. /assets/models/olimpo.glb)' AFTER `game_url`,
  ADD COLUMN `city_mesh_pos_x` FLOAT NULL DEFAULT NULL,
  ADD COLUMN `city_mesh_pos_y` FLOAT NULL DEFAULT NULL,
  ADD COLUMN `city_mesh_pos_z` FLOAT NULL DEFAULT NULL,
  ADD COLUMN `city_mesh_rot_y` FLOAT NULL DEFAULT NULL,
  ADD COLUMN `city_mesh_scale` FLOAT NULL DEFAULT NULL;

-- Optional: set Olimpo default (remove or edit if you prefer only code-side defaults)
-- UPDATE `nexus_districts` SET `city_glb_url` = '/assets/models/olimpo.glb' WHERE `id` = 'olimpo' AND `city_glb_url` IS NULL;
