-- =============================================================================
-- Migración: GLB de ciudad Nexus por distrito + transform (todos los usuarios)
-- =============================================================================
--
-- CÓMO EJECUTARLA
-- ---------------
-- 1) Conéctate a tu base MySQL/MariaDB (phpMyAdmin, DBeaver, Railway console, etc.)
-- 2) Selecciona la base del proyecto (la misma que usa KND Store).
-- 3) Ejecuta TODO este archivo de una vez (pegar y "Run").
--
-- Si MySQL dice "Duplicate column name": las columnas ya existen; puedes ignorar
-- ese ALTER o comentar las líneas ADD COLUMN que fallen.
--
-- 4) (Opcional) Asigna GLB por distrito — ejemplo Tesla si subiste tesla.glb:
--    UPDATE nexus_districts SET city_glb_url = '/assets/models/tesla.glb' WHERE id = 'tesla';
--
-- 5) Comprueba la API (sin login):
--    GET https://TU_DOMINIO/api/nexus/district_city_mesh.php
--    Debe devolver JSON con "migration_applied": true y "districts": [ ... ].
--
-- =============================================================================

ALTER TABLE `nexus_districts`
  ADD COLUMN `city_glb_url` VARCHAR(512) NULL DEFAULT NULL
    COMMENT 'Public URL path to GLB for district landmark in Nexus City (e.g. /assets/models/olimpo.glb)' AFTER `game_url`,
  ADD COLUMN `city_mesh_pos_x` FLOAT NULL DEFAULT NULL,
  ADD COLUMN `city_mesh_pos_y` FLOAT NULL DEFAULT NULL,
  ADD COLUMN `city_mesh_pos_z` FLOAT NULL DEFAULT NULL,
  ADD COLUMN `city_mesh_rot_y` FLOAT NULL DEFAULT NULL,
  ADD COLUMN `city_mesh_scale` FLOAT NULL DEFAULT NULL;

-- Opcional: forzar URL desde BD (si no usas solo el default en código para olimpo)
-- UPDATE `nexus_districts` SET `city_glb_url` = '/assets/models/olimpo.glb' WHERE `id` = 'olimpo' AND `city_glb_url` IS NULL;
