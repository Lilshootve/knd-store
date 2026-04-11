-- Ajuste de intensidad de luz en muebles (asset_data) — ejecutar en phpMyAdmin (import / SQL).
-- Usa WHERE code = ... para no depender del id ni reinsertar filas.
-- Valores: light_data.intensity y light_intensity = 0.1

SET NAMES utf8mb4;
START TRANSACTION;

UPDATE `nexus_furniture_catalog` SET `asset_data` = '{"color": "#ff3d56", "shape": "lamp", "model": "/assets/models/knd_lamp_neon_01.glb", "light_intensity": 0.1, "light_distance": 8.5, "light_height": 1.25}' WHERE `code` = 'lamp_neon';

UPDATE `nexus_furniture_catalog` SET `asset_data` = '{"model": "/assets/models/knd_tree_neon_01.glb", "color": "#00ff88", "shape": "tree", "light_data": {"type": "point", "color": "#00ff88", "intensity": 0.1, "distance": 5.5, "height": 1.55}}' WHERE `code` = 'tree_neon_01';

UPDATE `nexus_furniture_catalog` SET `asset_data` = '{"model": "/assets/models/knd_energy_orb_neon_01.glb", "color": "#c080ff", "shape": "orb", "fx": "float", "light_data": {"type": "point", "color": "#9b30ff", "intensity": 0.1, "distance": 6.0, "height": 1.1}}' WHERE `code` = 'energy_orb_neon_01';

UPDATE `nexus_furniture_catalog` SET `asset_data` = '{"model": "/assets/models/knd_plataform_neon_01.glb", "color": "#00a8ff", "shape": "prop", "wb_scale": 1.0, "light_data": {"type": "point", "color": "#00e8ff", "intensity": 0.1, "distance": 8.0, "height": 0.35}}' WHERE `code` = 'plataform_neon_01';

UPDATE `nexus_furniture_catalog` SET `asset_data` = '{"model": "/assets/models/knd_mountain_neon_01.glb", "color": "#5ad8ff", "shape": "prop", "wb_scale": 1.0, "light_data": {"type": "point", "color": "#00ccff", "intensity": 0.1, "distance": 7.5, "height": 2.0}}' WHERE `code` = 'mountain_neon_01';

UPDATE `nexus_furniture_catalog` SET `asset_data` = '{"model": "/assets/models/crystal_1.glb", "color": "#8ec5e0", "shape": "crystal", "light_data": {"type": "point", "color": "#7eb8d8", "intensity": 0.1, "distance": 3.6, "height": 0.72}}' WHERE `code` = 'crystal_1';
UPDATE `nexus_furniture_catalog` SET `asset_data` = '{"model": "/assets/models/crystal_2.glb", "color": "#8ec5e0", "shape": "crystal", "light_data": {"type": "point", "color": "#7eb8d8", "intensity": 0.1, "distance": 3.6, "height": 0.72}}' WHERE `code` = 'crystal_2';
UPDATE `nexus_furniture_catalog` SET `asset_data` = '{"model": "/assets/models/crystal_4.glb", "color": "#8ec5e0", "shape": "crystal", "light_data": {"type": "point", "color": "#7eb8d8", "intensity": 0.1, "distance": 3.6, "height": 0.72}}' WHERE `code` = 'crystal_4';
UPDATE `nexus_furniture_catalog` SET `asset_data` = '{"model": "/assets/models/crystal_5.glb", "color": "#8ec5e0", "shape": "crystal", "light_data": {"type": "point", "color": "#7eb8d8", "intensity": 0.1, "distance": 3.6, "height": 0.72}}' WHERE `code` = 'crystal_5';
UPDATE `nexus_furniture_catalog` SET `asset_data` = '{"model": "/assets/models/crystal_6.glb", "color": "#8ec5e0", "shape": "crystal", "light_data": {"type": "point", "color": "#7eb8d8", "intensity": 0.1, "distance": 3.6, "height": 0.72}}' WHERE `code` = 'crystal_6';
UPDATE `nexus_furniture_catalog` SET `asset_data` = '{"model": "/assets/models/crystal_8.glb", "color": "#9ab0e8", "shape": "crystal", "light_data": {"type": "point", "color": "#8899d8", "intensity": 0.1, "distance": 3.6, "height": 0.72}}' WHERE `code` = 'crystal_8';
UPDATE `nexus_furniture_catalog` SET `asset_data` = '{"model": "/assets/models/crystal_9.glb", "color": "#9ab0e8", "shape": "crystal", "light_data": {"type": "point", "color": "#8899d8", "intensity": 0.1, "distance": 3.6, "height": 0.72}}' WHERE `code` = 'crystal_9';
UPDATE `nexus_furniture_catalog` SET `asset_data` = '{"model": "/assets/models/crystal_10.glb", "color": "#9ab0e8", "shape": "crystal", "light_data": {"type": "point", "color": "#8899d8", "intensity": 0.1, "distance": 3.6, "height": 0.72}}' WHERE `code` = 'crystal_10';
UPDATE `nexus_furniture_catalog` SET `asset_data` = '{"model": "/assets/models/crystal_11.glb", "color": "#9ab0e8", "shape": "crystal", "light_data": {"type": "point", "color": "#8899d8", "intensity": 0.1, "distance": 3.6, "height": 0.72}}' WHERE `code` = 'crystal_11';
UPDATE `nexus_furniture_catalog` SET `asset_data` = '{"model": "/assets/models/crystal_13.glb", "color": "#b8a8e8", "shape": "crystal", "light_data": {"type": "point", "color": "#a898d8", "intensity": 0.1, "distance": 3.8, "height": 0.78}}' WHERE `code` = 'crystal_13';
UPDATE `nexus_furniture_catalog` SET `asset_data` = '{"model": "/assets/models/crystal_15.glb", "color": "#b8a8e8", "shape": "crystal", "light_data": {"type": "point", "color": "#a898d8", "intensity": 0.1, "distance": 3.8, "height": 0.78}}' WHERE `code` = 'crystal_15';
UPDATE `nexus_furniture_catalog` SET `asset_data` = '{"model": "/assets/models/crystal_16.glb", "color": "#b8a8e8", "shape": "crystal", "light_data": {"type": "point", "color": "#a898d8", "intensity": 0.1, "distance": 3.8, "height": 0.78}}' WHERE `code` = 'crystal_16';
UPDATE `nexus_furniture_catalog` SET `asset_data` = '{"model": "/assets/models/crystal_18.glb", "color": "#b8a8e8", "shape": "crystal", "light_data": {"type": "point", "color": "#a898d8", "intensity": 0.1, "distance": 3.8, "height": 0.78}}' WHERE `code` = 'crystal_18';
UPDATE `nexus_furniture_catalog` SET `asset_data` = '{"model": "/assets/models/crystal_20.glb", "color": "#c8b0f0", "shape": "crystal", "light_data": {"type": "point", "color": "#b0a0e0", "intensity": 0.1, "distance": 4.0, "height": 0.82}}' WHERE `code` = 'crystal_20';
UPDATE `nexus_furniture_catalog` SET `asset_data` = '{"model": "/assets/models/crystal_21.glb", "color": "#c8b0f0", "shape": "crystal", "light_data": {"type": "point", "color": "#b0a0e0", "intensity": 0.1, "distance": 4.0, "height": 0.82}}' WHERE `code` = 'crystal_21';
UPDATE `nexus_furniture_catalog` SET `asset_data` = '{"model": "/assets/models/crystal_22.glb", "color": "#c8b0f0", "shape": "crystal", "light_data": {"type": "point", "color": "#b0a0e0", "intensity": 0.1, "distance": 4.0, "height": 0.82}}' WHERE `code` = 'crystal_22';
UPDATE `nexus_furniture_catalog` SET `asset_data` = '{"model": "/assets/models/crystal_23.glb", "color": "#c8b0f0", "shape": "crystal", "light_data": {"type": "point", "color": "#b0a0e0", "intensity": 0.1, "distance": 4.0, "height": 0.82}}' WHERE `code` = 'crystal_23';
UPDATE `nexus_furniture_catalog` SET `asset_data` = '{"model": "/assets/models/crystal_25.glb", "color": "#c8b0f0", "shape": "crystal", "light_data": {"type": "point", "color": "#b0a0e0", "intensity": 0.1, "distance": 4.0, "height": 0.82}}' WHERE `code` = 'crystal_25';
UPDATE `nexus_furniture_catalog` SET `asset_data` = '{"model": "/assets/models/crystal_26.glb", "color": "#c8b0f0", "shape": "crystal", "light_data": {"type": "point", "color": "#b0a0e0", "intensity": 0.1, "distance": 4.0, "height": 0.82}}' WHERE `code` = 'crystal_26';
UPDATE `nexus_furniture_catalog` SET `asset_data` = '{"model": "/assets/models/crystal_27.glb", "color": "#d0c0f8", "shape": "crystal", "light_data": {"type": "point", "color": "#c0b0e8", "intensity": 0.1, "distance": 4.2, "height": 0.88}}' WHERE `code` = 'crystal_27';
UPDATE `nexus_furniture_catalog` SET `asset_data` = '{"model": "/assets/models/crystal_28.glb", "color": "#d0c0f8", "shape": "crystal", "light_data": {"type": "point", "color": "#c0b0e8", "intensity": 0.1, "distance": 4.2, "height": 0.88}}' WHERE `code` = 'crystal_28';

COMMIT;
