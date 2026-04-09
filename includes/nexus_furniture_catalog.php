<?php
/**
 * Catálogo único de muebles Nexus (Sanctum + consumidores API).
 * Misma consulta y decodificación JSON que api/nexus/sanctum.php (GET → catalog).
 */
if (!function_exists('nexus_furniture_catalog_fetch_active')) {
    function nexus_furniture_catalog_fetch_active(PDO $pdo): array {
        $cs = $pdo->query(
            'SELECT id, code, name, category, rarity, width, depth, price_kp, asset_data
             FROM nexus_furniture_catalog WHERE is_active = 1
             ORDER BY category, price_kp'
        );
        if (!$cs) {
            return [];
        }
        $catalog = $cs->fetchAll(PDO::FETCH_ASSOC);
        foreach ($catalog as &$c) {
            $raw = $c['asset_data'] ?? null;
            if ($raw === null || $raw === '') {
                $c['asset_data'] = [];
                continue;
            }
            $decoded = json_decode((string) $raw, true);
            $c['asset_data'] = is_array($decoded) ? $decoded : [];
        }
        unset($c);

        return $catalog;
    }
}
