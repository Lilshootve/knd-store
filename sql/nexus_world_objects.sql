-- ============================================================
-- MIGRACIÓN: nexus_world_objects
-- Tabla para objetos 3D colocados por admins en el Nexus World.
-- Ejecutar una sola vez sobre la base de datos de producción.
-- ============================================================

CREATE TABLE IF NOT EXISTS `nexus_world_objects` (
  `id`          INT(11)       NOT NULL AUTO_INCREMENT,

  -- Identificador del ítem de catálogo (string libre, ej. "lamp_post")
  `item_id`     VARCHAR(64)   NOT NULL DEFAULT '',

  -- URL pública del modelo GLB (NULL = usar fallback procedural)
  `model_url`   VARCHAR(512)  DEFAULT NULL,

  -- Posición en el mundo
  `pos_x`       FLOAT         NOT NULL DEFAULT 0,
  `pos_y`       FLOAT         NOT NULL DEFAULT 0,
  `pos_z`       FLOAT         NOT NULL DEFAULT 0,

  -- Rotación en eje Y (radianes)
  `rot_y`       FLOAT         NOT NULL DEFAULT 0,

  -- Factor de escala uniforme
  `scale`       FLOAT         NOT NULL DEFAULT 1,

  -- Configuración de luz como JSON opcional
  -- Ej: {"type":"point","color":16777215,"intensity":1.5,"distance":12,"height":3.5}
  `light_data`  TEXT          DEFAULT NULL,

  -- Quién lo colocó
  `created_by`  INT(11)       DEFAULT NULL,
  `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_item_id`    (`item_id`),

  CONSTRAINT `fk_nwo_user`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Objetos 3D colocados por admins en el mundo Nexus';

-- ============================================================
-- ÍNDICE de consulta rápida para la carga inicial del mundo
-- ============================================================
-- La API los carga ordenados por id ASC al entrar al Nexus.
-- Con pocos objetos (< 10k) no necesita índice adicional.

-- ============================================================
-- DATOS DE EJEMPLO (opcionales — borrar en producción)
-- ============================================================
-- INSERT INTO nexus_world_objects (item_id, pos_x, pos_y, pos_z, rot_y, scale, created_by)
-- VALUES ('lamp_post', 8.0, 0, 0.0, 0, 1.0, 1),
--        ('crystal',  -8.0, 0, 0.0, 1.5708, 1.2, 1),
--        ('terminal',  0.0, 0, 8.0, 0, 1.0, 1);
