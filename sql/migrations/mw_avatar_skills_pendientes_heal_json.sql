-- Pendientes audit v2: columna `heal` con texto/número pero sin heal/regen en ability_data/special_data.
-- Añade efectos al final de $.effect_payload (no toca el resto del JSON).
-- Ajusta los `value` si quieres otro balance.
--
-- Convención: value = fracción de max HP (0.15 = 15%). target: self | all_allies | single_ally (PHP usa fallback_rules en JSON del skill).
--
-- Ejecutar UNA VEZ (re-ejecutar duplica entradas en effect_payload). Backup antes.
-- Requiere JSON_VALID y JSON_ARRAY_APPEND (MariaDB / MySQL 5.7+).
-- No pegar varias líneas en una sola: el UPDATE debe ir después de un salto de línea o de un comentario /* ... */ cerrado.

START TRANSACTION;

/* avatar_id 3 — columna heal '30': si era 30% max HP usa 0.30; aquí 15% equipo como punto medio */
UPDATE mw_avatar_skills
SET special_data = JSON_ARRAY_APPEND(
  special_data,
  '$.effect_payload',
  JSON_OBJECT('type', 'heal', 'value', 0.15, 'target', 'all_allies')
)
WHERE avatar_id = 3 AND JSON_VALID(special_data)
  AND UPPER(COALESCE(JSON_TYPE(JSON_EXTRACT(special_data, '$.effect_payload')), '')) = 'ARRAY';

/* 7 — Strategic Recovery (self) */
UPDATE mw_avatar_skills
SET special_data = JSON_ARRAY_APPEND(
  special_data,
  '$.effect_payload',
  JSON_OBJECT('type', 'heal', 'value', 0.12, 'target', 'self')
)
WHERE avatar_id = 7 AND JSON_VALID(special_data)
  AND UPPER(COALESCE(JSON_TYPE(JSON_EXTRACT(special_data, '$.effect_payload')), '')) = 'ARRAY';

/* 17 — Forced Regeneration (burst self) */
UPDATE mw_avatar_skills
SET special_data = JSON_ARRAY_APPEND(
  special_data,
  '$.effect_payload',
  JSON_OBJECT('type', 'heal', 'value', 0.15, 'target', 'self')
)
WHERE avatar_id = 17 AND JSON_VALID(special_data)
  AND UPPER(COALESCE(JSON_TYPE(JSON_EXTRACT(special_data, '$.effect_payload')), '')) = 'ARRAY';

/* 19 — Unstable Recovery */
UPDATE mw_avatar_skills
SET special_data = JSON_ARRAY_APPEND(
  special_data,
  '$.effect_payload',
  JSON_OBJECT('type', 'heal', 'value', 0.12, 'target', 'self')
)
WHERE avatar_id = 19 AND JSON_VALID(special_data)
  AND UPPER(COALESCE(JSON_TYPE(JSON_EXTRACT(special_data, '$.effect_payload')), '')) = 'ARRAY';

/* 21 — Divine Insight (team) */
UPDATE mw_avatar_skills
SET special_data = JSON_ARRAY_APPEND(
  special_data,
  '$.effect_payload',
  JSON_OBJECT('type', 'heal', 'value', 0.08, 'target', 'all_allies')
)
WHERE avatar_id = 21 AND JSON_VALID(special_data)
  AND UPPER(COALESCE(JSON_TYPE(JSON_EXTRACT(special_data, '$.effect_payload')), '')) = 'ARRAY';

/* 23 — Hydra Regeneration (HoT en special) */
UPDATE mw_avatar_skills
SET special_data = JSON_ARRAY_APPEND(
  special_data,
  '$.effect_payload',
  JSON_OBJECT('type', 'regen', 'value', 0.05, 'duration', 3, 'target', 'self')
)
WHERE avatar_id = 23 AND JSON_VALID(special_data)
  AND UPPER(COALESCE(JSON_TYPE(JSON_EXTRACT(special_data, '$.effect_payload')), '')) = 'ARRAY';

/* 24 — Aesthetic Recovery */
UPDATE mw_avatar_skills
SET special_data = JSON_ARRAY_APPEND(
  special_data,
  '$.effect_payload',
  JSON_OBJECT('type', 'heal', 'value', 0.12, 'target', 'self')
)
WHERE avatar_id = 24 AND JSON_VALID(special_data)
  AND UPPER(COALESCE(JSON_TYPE(JSON_EXTRACT(special_data, '$.effect_payload')), '')) = 'ARRAY';

/* 26 — Deep Rest: heal burst */
UPDATE mw_avatar_skills
SET special_data = JSON_ARRAY_APPEND(
  special_data,
  '$.effect_payload',
  JSON_OBJECT('type', 'heal', 'value', 0.15, 'target', 'self')
)
WHERE avatar_id = 26 AND JSON_VALID(special_data)
  AND UPPER(COALESCE(JSON_TYPE(JSON_EXTRACT(special_data, '$.effect_payload')), '')) = 'ARRAY';

UPDATE mw_avatar_skills
SET special_data = JSON_ARRAY_APPEND(
  special_data,
  '$.effect_payload',
  JSON_OBJECT('type', 'cleanse', 'target', 'self')
)
WHERE avatar_id = 26 AND JSON_VALID(special_data)
  AND UPPER(COALESCE(JSON_TYPE(JSON_EXTRACT(special_data, '$.effect_payload')), '')) = 'ARRAY';

/* 28 — Dark Ritual */
UPDATE mw_avatar_skills
SET special_data = JSON_ARRAY_APPEND(
  special_data,
  '$.effect_payload',
  JSON_OBJECT('type', 'heal', 'value', 0.12, 'target', 'self')
)
WHERE avatar_id = 28 AND JSON_VALID(special_data)
  AND UPPER(COALESCE(JSON_TYPE(JSON_EXTRACT(special_data, '$.effect_payload')), '')) = 'ARRAY';

/* 30 — Roman Discipline (team) */
UPDATE mw_avatar_skills
SET special_data = JSON_ARRAY_APPEND(
  special_data,
  '$.effect_payload',
  JSON_OBJECT('type', 'heal', 'value', 0.08, 'target', 'all_allies')
)
WHERE avatar_id = 30 AND JSON_VALID(special_data)
  AND UPPER(COALESCE(JSON_TYPE(JSON_EXTRACT(special_data, '$.effect_payload')), '')) = 'ARRAY';

/* 34 — Strategic Recovery heal team */
UPDATE mw_avatar_skills
SET special_data = JSON_ARRAY_APPEND(
  special_data,
  '$.effect_payload',
  JSON_OBJECT('type', 'heal', 'value', 0.12, 'target', 'all_allies')
)
WHERE avatar_id = 34 AND JSON_VALID(special_data)
  AND UPPER(COALESCE(JSON_TYPE(JSON_EXTRACT(special_data, '$.effect_payload')), '')) = 'ARRAY';

/* 40 — Wish Recovery */
UPDATE mw_avatar_skills
SET special_data = JSON_ARRAY_APPEND(
  special_data,
  '$.effect_payload',
  JSON_OBJECT('type', 'heal', 'value', 0.14, 'target', 'self')
)
WHERE avatar_id = 40 AND JSON_VALID(special_data)
  AND UPPER(COALESCE(JSON_TYPE(JSON_EXTRACT(special_data, '$.effect_payload')), '')) = 'ARRAY';

/* 41 — Hidden Reserves (heal en ability) */
UPDATE mw_avatar_skills
SET ability_data = JSON_ARRAY_APPEND(
  ability_data,
  '$.effect_payload',
  JSON_OBJECT('type', 'heal', 'value', 0.12, 'target', 'self')
)
WHERE avatar_id = 41 AND JSON_VALID(ability_data)
  AND UPPER(COALESCE(JSON_TYPE(JSON_EXTRACT(ability_data, '$.effect_payload')), '')) = 'ARRAY';

UPDATE mw_avatar_skills
SET ability_data = JSON_ARRAY_APPEND(
  ability_data,
  '$.effect_payload',
  JSON_OBJECT('type', 'energy_gain', 'value', 1, 'target', 'self')
)
WHERE avatar_id = 41 AND JSON_VALID(ability_data)
  AND UPPER(COALESCE(JSON_TYPE(JSON_EXTRACT(ability_data, '$.effect_payload')), '')) = 'ARRAY';

/* 43 — Noble Stand (team slight) */
UPDATE mw_avatar_skills
SET special_data = JSON_ARRAY_APPEND(
  special_data,
  '$.effect_payload',
  JSON_OBJECT('type', 'heal', 'value', 0.08, 'target', 'all_allies')
)
WHERE avatar_id = 43 AND JSON_VALID(special_data)
  AND UPPER(COALESCE(JSON_TYPE(JSON_EXTRACT(special_data, '$.effect_payload')), '')) = 'ARRAY';

/* 63 — heal '10' → 10% self */
UPDATE mw_avatar_skills
SET special_data = JSON_ARRAY_APPEND(
  special_data,
  '$.effect_payload',
  JSON_OBJECT('type', 'heal', 'value', 0.10, 'target', 'self')
)
WHERE avatar_id = 63 AND JSON_VALID(special_data)
  AND UPPER(COALESCE(JSON_TYPE(JSON_EXTRACT(special_data, '$.effect_payload')), '')) = 'ARRAY';

/* 68 — heal '5' → 5% self */
UPDATE mw_avatar_skills
SET special_data = JSON_ARRAY_APPEND(
  special_data,
  '$.effect_payload',
  JSON_OBJECT('type', 'heal', 'value', 0.05, 'target', 'self')
)
WHERE avatar_id = 68 AND JSON_VALID(special_data)
  AND UPPER(COALESCE(JSON_TYPE(JSON_EXTRACT(special_data, '$.effect_payload')), '')) = 'ARRAY';

/* 77 — heal '10' */
UPDATE mw_avatar_skills
SET special_data = JSON_ARRAY_APPEND(
  special_data,
  '$.effect_payload',
  JSON_OBJECT('type', 'heal', 'value', 0.10, 'target', 'self')
)
WHERE avatar_id = 77 AND JSON_VALID(special_data)
  AND UPPER(COALESCE(JSON_TYPE(JSON_EXTRACT(special_data, '$.effect_payload')), '')) = 'ARRAY';

/* 93 — heal '25' → 25% equipo */
UPDATE mw_avatar_skills
SET special_data = JSON_ARRAY_APPEND(
  special_data,
  '$.effect_payload',
  JSON_OBJECT('type', 'heal', 'value', 0.25, 'target', 'all_allies')
)
WHERE avatar_id = 93 AND JSON_VALID(special_data)
  AND UPPER(COALESCE(JSON_TYPE(JSON_EXTRACT(special_data, '$.effect_payload')), '')) = 'ARRAY';

/* 98 — heal '20' */
UPDATE mw_avatar_skills
SET special_data = JSON_ARRAY_APPEND(
  special_data,
  '$.effect_payload',
  JSON_OBJECT('type', 'heal', 'value', 0.20, 'target', 'all_allies')
)
WHERE avatar_id = 98 AND JSON_VALID(special_data)
  AND UPPER(COALESCE(JSON_TYPE(JSON_EXTRACT(special_data, '$.effect_payload')), '')) = 'ARRAY';

/* Limpiar columna legado `heal` solo en estas filas */
UPDATE mw_avatar_skills
SET heal = '0'
WHERE avatar_id IN (3, 7, 17, 19, 21, 23, 24, 26, 28, 30, 34, 40, 41, 43, 63, 68, 77, 93, 98);

COMMIT;
