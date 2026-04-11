-- Cristales GLB (Sanctum / tienda / World Builder).
-- Rutas públicas: /assets/models/crystal_N.glb (barra inicial, sin comillas anidadas en JSON).
-- `code` único; `shape` ≠ 'lamp' y `code` sin "lamp" → sin farol procedural extra.
--
-- Luz más suave que tree_neon_01 (intensity 2.0): aquí ~0.42 y alcance menor.
-- Para casi solo emissive del GLB sin PointLight extra, borra el bloque 'light_data' del JSON_OBJECT.

INSERT INTO nexus_furniture_catalog
    (`code`,`name`,`category`,`rarity`,`width`,`depth`,`price_kp`,`asset_data`,`is_active`)
VALUES
(
    'crystal_1',
    'Crystal 1',
    'decoration',
    'common',
    1, 1, 95,
    JSON_OBJECT(
        'model', '/assets/models/crystal_1.glb',
        'color', '#8ec5e0',
        'shape', 'crystal',
        'light_data', JSON_OBJECT(
            'type', 'point',
            'color', '#7eb8d8',
            'intensity', 0.42,
            'distance', 3.6,
            'height', 0.72
        )
    ),
    1
),
(
    'crystal_2',
    'Crystal 2',
    'decoration',
    'common',
    1, 1, 98,
    JSON_OBJECT(
        'model', '/assets/models/crystal_2.glb',
        'color', '#8ec5e0',
        'shape', 'crystal',
        'light_data', JSON_OBJECT(
            'type', 'point',
            'color', '#7eb8d8',
            'intensity', 0.42,
            'distance', 3.6,
            'height', 0.72
        )
    ),
    1
),
(
    'crystal_4',
    'Crystal 4',
    'decoration',
    'common',
    1, 1, 102,
    JSON_OBJECT(
        'model', '/assets/models/crystal_4.glb',
        'color', '#8ec5e0',
        'shape', 'crystal',
        'light_data', JSON_OBJECT(
            'type', 'point',
            'color', '#7eb8d8',
            'intensity', 0.42,
            'distance', 3.6,
            'height', 0.72
        )
    ),
    1
),
(
    'crystal_5',
    'Crystal 5',
    'decoration',
    'common',
    1, 1, 105,
    JSON_OBJECT(
        'model', '/assets/models/crystal_5.glb',
        'color', '#8ec5e0',
        'shape', 'crystal',
        'light_data', JSON_OBJECT(
            'type', 'point',
            'color', '#7eb8d8',
            'intensity', 0.42,
            'distance', 3.6,
            'height', 0.72
        )
    ),
    1
),
(
    'crystal_6',
    'Crystal 6',
    'decoration',
    'common',
    1, 1, 108,
    JSON_OBJECT(
        'model', '/assets/models/crystal_6.glb',
        'color', '#8ec5e0',
        'shape', 'crystal',
        'light_data', JSON_OBJECT(
            'type', 'point',
            'color', '#7eb8d8',
            'intensity', 0.42,
            'distance', 3.6,
            'height', 0.72
        )
    ),
    1
),
(
    'crystal_8',
    'Crystal 8',
    'decoration',
    'common',
    1, 1, 118,
    JSON_OBJECT(
        'model', '/assets/models/crystal_8.glb',
        'color', '#9ab0e8',
        'shape', 'crystal',
        'light_data', JSON_OBJECT(
            'type', 'point',
            'color', '#8899d8',
            'intensity', 0.42,
            'distance', 3.6,
            'height', 0.72
        )
    ),
    1
),
(
    'crystal_9',
    'Crystal 9',
    'decoration',
    'common',
    1, 1, 120,
    JSON_OBJECT(
        'model', '/assets/models/crystal_9.glb',
        'color', '#9ab0e8',
        'shape', 'crystal',
        'light_data', JSON_OBJECT(
            'type', 'point',
            'color', '#8899d8',
            'intensity', 0.42,
            'distance', 3.6,
            'height', 0.72
        )
    ),
    1
),
(
    'crystal_10',
    'Crystal 10',
    'decoration',
    'common',
    1, 1, 125,
    JSON_OBJECT(
        'model', '/assets/models/crystal_10.glb',
        'color', '#9ab0e8',
        'shape', 'crystal',
        'light_data', JSON_OBJECT(
            'type', 'point',
            'color', '#8899d8',
            'intensity', 0.42,
            'distance', 3.6,
            'height', 0.72
        )
    ),
    1
),
(
    'crystal_11',
    'Crystal 11',
    'decoration',
    'common',
    1, 1, 128,
    JSON_OBJECT(
        'model', '/assets/models/crystal_11.glb',
        'color', '#9ab0e8',
        'shape', 'crystal',
        'light_data', JSON_OBJECT(
            'type', 'point',
            'color', '#8899d8',
            'intensity', 0.42,
            'distance', 3.6,
            'height', 0.72
        )
    ),
    1
),
(
    'crystal_13',
    'Crystal 13',
    'decoration',
    'rare',
    1, 1, 145,
    JSON_OBJECT(
        'model', '/assets/models/crystal_13.glb',
        'color', '#b8a8e8',
        'shape', 'crystal',
        'light_data', JSON_OBJECT(
            'type', 'point',
            'color', '#a898d8',
            'intensity', 0.45,
            'distance', 3.8,
            'height', 0.78
        )
    ),
    1
),
(
    'crystal_15',
    'Crystal 15',
    'decoration',
    'rare',
    1, 1, 150,
    JSON_OBJECT(
        'model', '/assets/models/crystal_15.glb',
        'color', '#b8a8e8',
        'shape', 'crystal',
        'light_data', JSON_OBJECT(
            'type', 'point',
            'color', '#a898d8',
            'intensity', 0.45,
            'distance', 3.8,
            'height', 0.78
        )
    ),
    1
),
(
    'crystal_16',
    'Crystal 16',
    'decoration',
    'rare',
    1, 1, 152,
    JSON_OBJECT(
        'model', '/assets/models/crystal_16.glb',
        'color', '#b8a8e8',
        'shape', 'crystal',
        'light_data', JSON_OBJECT(
            'type', 'point',
            'color', '#a898d8',
            'intensity', 0.45,
            'distance', 3.8,
            'height', 0.78
        )
    ),
    1
),
(
    'crystal_18',
    'Crystal 18',
    'decoration',
    'rare',
    1, 1, 158,
    JSON_OBJECT(
        'model', '/assets/models/crystal_18.glb',
        'color', '#b8a8e8',
        'shape', 'crystal',
        'light_data', JSON_OBJECT(
            'type', 'point',
            'color', '#a898d8',
            'intensity', 0.45,
            'distance', 3.8,
            'height', 0.78
        )
    ),
    1
),
(
    'crystal_20',
    'Crystal 20',
    'decoration',
    'epic',
    1, 1, 175,
    JSON_OBJECT(
        'model', '/assets/models/crystal_20.glb',
        'color', '#c8b0f0',
        'shape', 'crystal',
        'light_data', JSON_OBJECT(
            'type', 'point',
            'color', '#b0a0e0',
            'intensity', 0.48,
            'distance', 4.0,
            'height', 0.82
        )
    ),
    1
),
(
    'crystal_21',
    'Crystal 21',
    'decoration',
    'epic',
    1, 1, 178,
    JSON_OBJECT(
        'model', '/assets/models/crystal_21.glb',
        'color', '#c8b0f0',
        'shape', 'crystal',
        'light_data', JSON_OBJECT(
            'type', 'point',
            'color', '#b0a0e0',
            'intensity', 0.48,
            'distance', 4.0,
            'height', 0.82
        )
    ),
    1
),
(
    'crystal_22',
    'Crystal 22',
    'decoration',
    'epic',
    1, 1, 182,
    JSON_OBJECT(
        'model', '/assets/models/crystal_22.glb',
        'color', '#c8b0f0',
        'shape', 'crystal',
        'light_data', JSON_OBJECT(
            'type', 'point',
            'color', '#b0a0e0',
            'intensity', 0.48,
            'distance', 4.0,
            'height', 0.82
        )
    ),
    1
),
(
    'crystal_23',
    'Crystal 23',
    'decoration',
    'epic',
    1, 1, 185,
    JSON_OBJECT(
        'model', '/assets/models/crystal_23.glb',
        'color', '#c8b0f0',
        'shape', 'crystal',
        'light_data', JSON_OBJECT(
            'type', 'point',
            'color', '#b0a0e0',
            'intensity', 0.48,
            'distance', 4.0,
            'height', 0.82
        )
    ),
    1
),
(
    'crystal_25',
    'Crystal 25',
    'decoration',
    'epic',
    1, 1, 192,
    JSON_OBJECT(
        'model', '/assets/models/crystal_25.glb',
        'color', '#c8b0f0',
        'shape', 'crystal',
        'light_data', JSON_OBJECT(
            'type', 'point',
            'color', '#b0a0e0',
            'intensity', 0.48,
            'distance', 4.0,
            'height', 0.82
        )
    ),
    1
),
(
    'crystal_26',
    'Crystal 26',
    'decoration',
    'epic',
    1, 1, 195,
    JSON_OBJECT(
        'model', '/assets/models/crystal_26.glb',
        'color', '#c8b0f0',
        'shape', 'crystal',
        'light_data', JSON_OBJECT(
            'type', 'point',
            'color', '#b0a0e0',
            'intensity', 0.48,
            'distance', 4.0,
            'height', 0.82
        )
    ),
    1
),
(
    'crystal_27',
    'Crystal 27',
    'decoration',
    'legendary',
    1, 1, 220,
    JSON_OBJECT(
        'model', '/assets/models/crystal_27.glb',
        'color', '#d0c0f8',
        'shape', 'crystal',
        'light_data', JSON_OBJECT(
            'type', 'point',
            'color', '#c0b0e8',
            'intensity', 0.5,
            'distance', 4.2,
            'height', 0.88
        )
    ),
    1
),
(
    'crystal_28',
    'Crystal 28',
    'decoration',
    'legendary',
    1, 1, 235,
    JSON_OBJECT(
        'model', '/assets/models/crystal_28.glb',
        'color', '#d0c0f8',
        'shape', 'crystal',
        'light_data', JSON_OBJECT(
            'type', 'point',
            'color', '#c0b0e8',
            'intensity', 0.5,
            'distance', 4.2,
            'height', 0.88
        )
    ),
    1
)
ON DUPLICATE KEY UPDATE
    `name`       = VALUES(`name`),
    `category`   = VALUES(`category`),
    `rarity`     = VALUES(`rarity`),
    `width`      = VALUES(`width`),
    `depth`      = VALUES(`depth`),
    `price_kp`   = VALUES(`price_kp`),
    `asset_data` = VALUES(`asset_data`),
    `is_active`  = VALUES(`is_active`);
