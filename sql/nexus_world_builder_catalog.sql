-- ============================================================
-- Catálogo exclusivo del World Builder (Nexus City 3D).
-- Independiente de nexus_furniture_catalog (Sanctum / tienda KP).
-- item_code debe coincidir con nexus_world_objects.item_id al colocar.
-- ============================================================

CREATE TABLE IF NOT EXISTS `nexus_world_builder_catalog` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_code` varchar(64) NOT NULL,
  `name` varchar(100) NOT NULL,
  `category` varchar(32) NOT NULL DEFAULT 'decoration',
  `rarity` varchar(32) NOT NULL DEFAULT 'common',
  `model_url` varchar(512) NOT NULL,
  `wb_scale` decimal(8,4) NOT NULL DEFAULT 1.0000,
  `default_light_json` longtext DEFAULT NULL,
  `hologram` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wb_item_code` (`item_code`),
  KEY `idx_wb_active_sort` (`is_active`,`sort_order`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Seed opcional: copiar desde muebles que ya tienen GLB en asset_data.
-- Ejecutar una vez; INSERT IGNORE evita duplicar item_code.
-- ------------------------------------------------------------

INSERT IGNORE INTO `nexus_world_builder_catalog`
  (`item_code`, `name`, `category`, `rarity`, `model_url`, `wb_scale`, `hologram`, `default_light_json`, `sort_order`, `is_active`)
SELECT
  fc.`code`,
  fc.`name`,
  fc.`category`,
  fc.`rarity`,
  COALESCE(
    NULLIF(JSON_UNQUOTE(JSON_EXTRACT(fc.`asset_data`, '$.model')), ''),
    NULLIF(JSON_UNQUOTE(JSON_EXTRACT(fc.`asset_data`, '$.model_url')), '')
  ),
  COALESCE(
    CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(fc.`asset_data`, '$.wb_scale')), '') AS DECIMAL(8,4)),
    CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(fc.`asset_data`, '$.scale')), '') AS DECIMAL(8,4)),
    1.0
  ),
  IF(JSON_UNQUOTE(JSON_EXTRACT(fc.`asset_data`, '$.fx')) = 'hologram', 1, 0),
  IF(
    JSON_EXTRACT(fc.`asset_data`, '$.light_data') IS NOT NULL,
    CAST(JSON_EXTRACT(fc.`asset_data`, '$.light_data') AS CHAR),
    NULL
  ),
  fc.`id`,
  fc.`is_active`
FROM `nexus_furniture_catalog` fc
WHERE fc.`is_active` = 1
  AND (
    (JSON_UNQUOTE(JSON_EXTRACT(fc.`asset_data`, '$.model')) IS NOT NULL
      AND JSON_UNQUOTE(JSON_EXTRACT(fc.`asset_data`, '$.model')) != '')
    OR (JSON_UNQUOTE(JSON_EXTRACT(fc.`asset_data`, '$.model_url')) IS NOT NULL
      AND JSON_UNQUOTE(JSON_EXTRACT(fc.`asset_data`, '$.model_url')) != '')
  );
