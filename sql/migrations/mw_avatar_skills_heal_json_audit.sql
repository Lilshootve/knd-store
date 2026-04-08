-- mw_avatar_skills — auditoría y plantillas: curación solo en *_data (columna heal legado)
-- Ejecutar en MariaDB/MySQL con JSON_* disponible. Haz backup antes de cualquier UPDATE.
--
-- Convención usada por squad_battle_bootstrap / effect_payload:
--   {"type":"heal","value":0.20}           → fracción de max HP (0–1)
--   {"type":"regen","value":0.04,"duration":2,"target":"self"}
--   target_scope: single_ally | all_allies + fallback_rules: lowest_hp
--   tags: ["support","heal"] para soporte puro (base_power 0)

-- ═══ 1) AUDITORÍA: quién tiene texto en `heal` y si el JSON ya declara heal ═══
SELECT
  id,
  avatar_id,
  NULLIF(TRIM(heal), '') AS heal_text,
  CASE
    WHEN ability_data  IS NOT NULL AND JSON_VALID(ability_data)
     AND JSON_SEARCH(ability_data, 'one', 'heal', NULL, '$**.type') IS NOT NULL THEN 1
    ELSE 0
  END AS ability_json_has_heal_type,
  CASE
    WHEN special_data IS NOT NULL AND JSON_VALID(special_data)
     AND JSON_SEARCH(special_data, 'one', 'heal', NULL, '$**.type') IS NOT NULL THEN 1
    ELSE 0
  END AS special_json_has_heal_type
FROM mw_avatar_skills
ORDER BY avatar_id;

-- Resumen: filas con heal textual pero sin heal en ningún JSON (revisar a mano)
SELECT
  id,
  avatar_id,
  heal
FROM mw_avatar_skills
WHERE NULLIF(TRIM(heal), '') IS NOT NULL
  AND NULLIF(TRIM(heal), '') NOT IN ('0')
  AND (
    ability_data IS NULL OR NOT JSON_VALID(ability_data)
    OR JSON_SEARCH(ability_data, 'one', 'heal', NULL, '$**.type') IS NULL
  )
  AND (
    special_data IS NULL OR NOT JSON_VALID(special_data)
    OR JSON_SEARCH(special_data, 'one', 'heal', NULL, '$**.type') IS NULL
  );

-- ═══ 2) LIMPIEZA OPCIONAL de la columna `heal` solo donde el JSON ya cubre curación ═══
-- Descomenta tras revisar el SELECT anterior.
/*
UPDATE mw_avatar_skills
SET heal = '0'
WHERE (
  JSON_VALID(ability_data)
  AND JSON_SEARCH(ability_data, 'one', 'heal', NULL, '$**.type') IS NOT NULL
) OR (
  JSON_VALID(special_data)
  AND JSON_SEARCH(special_data, 'one', 'heal', NULL, '$**.type') IS NOT NULL
);
*/

-- ═══ 3) PLANTILLA: añadir un efecto heal al final de effect_payload en ability_data ═══
-- Sustituye :avatar_id y ajusta value / target. Requiere que ability_data sea JSON válido
-- y que exista la ruta $.effect_payload como array.
/*
UPDATE mw_avatar_skills
SET ability_data = JSON_ARRAY_APPEND(
  ability_data,
  '$.effect_payload',
  CAST('{"type":"heal","value":0.15,"target":"self"}' AS JSON)
)
WHERE avatar_id = :avatar_id
  AND JSON_VALID(ability_data)
  AND JSON_TYPE(JSON_EXTRACT(ability_data, '$.effect_payload')) = 'ARRAY';
*/

-- ═══ 4) PLANTILLA: skill de soporte puro (solo curación + cleanse) en ability_data ═══
-- Sustituye :avatar_id. Sobrescribe ability_data completo — úsalo solo si reemplazas el skill.
/*
UPDATE mw_avatar_skills
SET ability_data = CAST(
'{
  "base_power": 0,
  "scaling_stat": "focus",
  "hit_type": "single",
  "can_crit": false,
  "energy_cost": 2,
  "cooldown": 2,
  "target_scope": "single_ally",
  "fallback_rules": "lowest_hp",
  "aoe_mode": "none",
  "tags": ["support", "heal"],
  "effect_payload": [
    {"type": "cleanse"},
    {"type": "heal", "value": 0.18}
  ]
}' AS JSON)
WHERE avatar_id = :avatar_id;
*/

-- ═══ 5) BALANCE global (ejemplo): subir/bajar solo el valor de heal en ability_data ═══
-- Ajusta el path si el heal no es el primer elemento del array.
/*
UPDATE mw_avatar_skills
SET ability_data = JSON_REPLACE(ability_data, '$.effect_payload[1].value', 0.22)
WHERE avatar_id = 31
  AND JSON_VALID(ability_data);
*/
