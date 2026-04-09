-- Catálogo Nexus / Sanctum + World Builder (misma tabla, model en asset_data).
-- Ejecutar en MySQL/MariaDB. Códigos únicos: ajusta price_kp / width / depth si lo necesitas.

-- Orbo energético (animación float en Sanctum si fx=float y shape=orb)
INSERT INTO nexus_furniture_catalog
    (`code`,`name`,`category`,`rarity`,`width`,`depth`,`price_kp`,`asset_data`,`is_active`)
VALUES (
    'energy_orb_neon_01',
    'Neon Energy Orb',
    'interactive',
    'rare',
    1,
    1,
    420,
    JSON_OBJECT(
        'model', '/assets/models/knd_energy_orb_neon_01.glb',
        'color', '#c080ff',
        'shape', 'orb',
        'fx', 'float',
        'light_data', JSON_OBJECT(
            'type', 'point',
            'color', '#9b30ff',
            'intensity', 2.4,
            'distance', 6.0,
            'height', 1.1
        )
    ),
    1
)
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`), `category` = VALUES(`category`), `rarity` = VALUES(`rarity`),
    `width` = VALUES(`width`), `depth` = VALUES(`depth`), `price_kp` = VALUES(`price_kp`),
    `asset_data` = VALUES(`asset_data`), `is_active` = VALUES(`is_active`);

-- Plataforma (huella 2×2; wb_scale opcional para tamaño en mundo abierto)
INSERT INTO nexus_furniture_catalog
    (`code`,`name`,`category`,`rarity`,`width`,`depth`,`price_kp`,`asset_data`,`is_active`)
VALUES (
    'plataform_neon_01',
    'Neon Platform',
    'floor',
    'special',
    2,
    2,
    340,
    JSON_OBJECT(
        'model', '/assets/models/knd_plataform_neon_01.glb',
        'color', '#00a8ff',
        'shape', 'prop',
        'wb_scale', 1.0,
        'light_data', JSON_OBJECT(
            'type', 'point',
            'color', '#00e8ff',
            'intensity', 1.2,
            'distance', 8.0,
            'height', 0.35
        )
    ),
    1
)
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`), `category` = VALUES(`category`), `rarity` = VALUES(`rarity`),
    `width` = VALUES(`width`), `depth` = VALUES(`depth`), `price_kp` = VALUES(`price_kp`),
    `asset_data` = VALUES(`asset_data`), `is_active` = VALUES(`is_active`);

-- Banco
INSERT INTO nexus_furniture_catalog
    (`code`,`name`,`category`,`rarity`,`width`,`depth`,`price_kp`,`asset_data`,`is_active`)
VALUES (
    'bench_neon_01',
    'Neon Bench',
    'floor',
    'common',
    2,
    1,
    165,
    JSON_OBJECT(
        'model', '/assets/models/knd_bench_neon_01.glb',
        'color', '#00e8ff',
        'shape', 'prop',
        'wb_scale', 1.0
    ),
    1
)
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`), `category` = VALUES(`category`), `rarity` = VALUES(`rarity`),
    `width` = VALUES(`width`), `depth` = VALUES(`depth`), `price_kp` = VALUES(`price_kp`),
    `asset_data` = VALUES(`asset_data`), `is_active` = VALUES(`is_active`);

-- Montaña / monumento (huella 2×2)
INSERT INTO nexus_furniture_catalog
    (`code`,`name`,`category`,`rarity`,`width`,`depth`,`price_kp`,`asset_data`,`is_active`)
VALUES (
    'mountain_neon_01',
    'Neon Mountain',
    'decoration',
    'epic',
    2,
    2,
    520,
    JSON_OBJECT(
        'model', '/assets/models/knd_mountain_neon_01.glb',
        'color', '#5ad8ff',
        'shape', 'prop',
        'wb_scale', 1.0,
        'light_data', JSON_OBJECT(
            'type', 'point',
            'color', '#00ccff',
            'intensity', 1.6,
            'distance', 7.5,
            'height', 2.0
        )
    ),
    1
)
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`), `category` = VALUES(`category`), `rarity` = VALUES(`rarity`),
    `width` = VALUES(`width`), `depth` = VALUES(`depth`), `price_kp` = VALUES(`price_kp`),
    `asset_data` = VALUES(`asset_data`), `is_active` = VALUES(`is_active`);
