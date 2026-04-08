-- Tras correr el audit: limpieza segura de columna `heal` + audit ampliado (heal | regen en aliados)
-- MariaDB / MySQL 5.7+ JSON_*

-- ═══ Audit v2: curación en JSON vía "heal" O "regen" (cualquier ruta) ═══
SELECT
  id,
  avatar_id,
  NULLIF(TRIM(heal), '') AS heal_text,
  CASE WHEN ability_data  IS NOT NULL AND JSON_VALID(ability_data)
        AND (JSON_SEARCH(ability_data, 'one', 'heal', NULL, '$**.type') IS NOT NULL
          OR JSON_SEARCH(ability_data, 'one', 'regen', NULL, '$**.type') IS NOT NULL)
       THEN 1 ELSE 0 END AS ability_json_heal_or_regen,
  CASE WHEN special_data IS NOT NULL AND JSON_VALID(special_data)
        AND (JSON_SEARCH(special_data, 'one', 'heal', NULL, '$**.type') IS NOT NULL
          OR JSON_SEARCH(special_data, 'one', 'regen', NULL, '$**.type') IS NOT NULL)
       THEN 1 ELSE 0 END AS special_json_heal_or_regen
FROM mw_avatar_skills
ORDER BY avatar_id;

-- ═══ Pendientes: columna heal con contenido pero JSON sin heal ni regen ═══
SELECT id, avatar_id, heal
FROM mw_avatar_skills
WHERE NULLIF(TRIM(COALESCE(heal, '')), '') IS NOT NULL
  AND NULLIF(TRIM(COALESCE(heal, '')), '') NOT IN ('0')
  AND NOT (
    (JSON_VALID(ability_data) AND (
      JSON_SEARCH(ability_data, 'one', 'heal', NULL, '$**.type') IS NOT NULL
      OR JSON_SEARCH(ability_data, 'one', 'regen', NULL, '$**.type') IS NOT NULL
    ))
    OR
    (JSON_VALID(special_data) AND (
      JSON_SEARCH(special_data, 'one', 'heal', NULL, '$**.type') IS NOT NULL
      OR JSON_SEARCH(special_data, 'one', 'regen', NULL, '$**.type') IS NOT NULL
    ))
  );

-- ═══ LIMPIEZA: poner heal = '0' donde el JSON ya tiene heal o regen ═══
-- Revisa el SELECT de arriba; luego ejecuta:
UPDATE mw_avatar_skills
SET heal = '0'
WHERE (
  JSON_VALID(ability_data) AND (
    JSON_SEARCH(ability_data, 'one', 'heal', NULL, '$**.type') IS NOT NULL
    OR JSON_SEARCH(ability_data, 'one', 'regen', NULL, '$**.type') IS NOT NULL
  )
) OR (
  JSON_VALID(special_data) AND (
    JSON_SEARCH(special_data, 'one', 'heal', NULL, '$**.type') IS NOT NULL
    OR JSON_SEARCH(special_data, 'one', 'regen', NULL, '$**.type') IS NOT NULL
  )
);

-- ═══ Referencia: avatar_id que en tu dump seguían con texto/número en heal y 0|0
--    en el audit solo-heal (hay que modelar curación en ability_data o special_data):
--    3, 7, 17, 19, 21, 23, 24, 26, 28, 30, 34, 38, 39, 43, 63, 68, 71, 93, 98
--    (tras audit v2 algunos pueden pasar a cubiertos si tenían solo "regen".)
--    avatar_id 65: tu fila tenía special_json_has_heal_type=1 → el UPDATE de limpieza
--    ya puede poner heal='0' sin tocar JSON.
