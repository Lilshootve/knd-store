-- Opcional: usar tu GLB en el catálogo (ruta web bajo la raíz del sitio).
-- Ajusta `code` si tu fila es otra. El nombre de archivo real es knd_lamp_neon_01.glb (sin .glb duplicado).

UPDATE nexus_furniture_catalog
SET asset_data = JSON_MERGE_PATCH(
    COALESCE(asset_data, JSON_OBJECT()),
    JSON_OBJECT(
        'model', '/assets/models/knd_lamp_neon_01.glb',
        'color', '#ff3d56',
        'shape', 'lamp'
    )
)
WHERE code = 'lamp_neon' LIMIT 1;
