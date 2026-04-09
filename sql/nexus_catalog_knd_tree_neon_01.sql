-- Árbol GLB para Sanctum (tienda + habitación en grid).
-- Archivo: assets/models/knd_tree_neon_01.glb → URL pública con barra inicial.
--
-- `code` debe ser único. `shape` ≠ 'lamp' y `code` sin "lamp" → no se añade farol procedural extra.
-- Opcional: quita `light_data` del JSON si el árbol ya brilla solo con emissive en el GLB.
-- Ajusta width/depth si quieres que ocupe más celdas (máx. encaje en grid 10×10).

INSERT INTO nexus_furniture_catalog
    (`code`,`name`,`category`,`rarity`,`width`,`depth`,`price_kp`,`asset_data`,`is_active`)
VALUES (
    'tree_neon_01',
    'Neon Tree',
    'decoration',
    'rare',
    1,
    1,
    320,
    JSON_OBJECT(
        'model', '/assets/models/knd_tree_neon_01.glb',
        'color', '#00ff88',
        'shape', 'tree',
        'light_data', JSON_OBJECT(
            'type', 'point',
            'color', '#00ff88',
            'intensity', 2.0,
            'distance', 5.5,
            'height', 1.55
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
