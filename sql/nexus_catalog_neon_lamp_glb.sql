-- Opcional: usar tu GLB en el catálogo (ruta web bajo la raíz del sitio).
-- Ajusta `code` si tu fila es otra. El nombre de archivo real es knd_lamp_neon_01.glb (sin .glb duplicado).

-- shape 'lamp' (o code con "lamp") activa en Sanctum PointLight + halo en suelo aunque no pongas light_data.
-- Opcional: 'light_height', 'light_intensity', 'light_distance' (números) para afinar.

UPDATE nexus_furniture_catalog
SET asset_data = JSON_MERGE_PATCH(
    COALESCE(asset_data, JSON_OBJECT()),
    JSON_OBJECT(
        'model', '/assets/models/knd_lamp_neon_01.glb',
        'color', '#ff3d56',
        'shape', 'lamp',
        'light_intensity', 4.8,
        'light_distance', 8.5,
        'light_height', 1.25
    )
)
WHERE code = 'lamp_neon' LIMIT 1;
