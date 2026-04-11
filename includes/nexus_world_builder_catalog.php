<?php
/**
 * Catálogo 3D del World Builder (Nexus City) — separado de nexus_furniture_catalog (Sanctum / tienda).
 * Cada fila define item_code (coincide con nexus_world_objects.item_id), model_url y metadatos mínimos.
 */
if (!function_exists('nexus_world_builder_catalog_table_exists')) {
    function nexus_world_builder_catalog_table_exists(PDO $pdo): bool {
        try {
            $n = $pdo->query(
                "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'nexus_world_builder_catalog' LIMIT 1"
            );
            return (bool) ($n && $n->fetchColumn());
        } catch (Throwable $_) {
            return false;
        }
    }

    function nexus_world_builder_catalog_row_to_api(array $r): array {
        $asset = [
            'model' => $r['model_url'],
            'wb_scale' => (float) $r['wb_scale'],
        ];
        if (!empty($r['hologram'])) {
            $asset['hologram'] = true;
        }
        $rawLd = $r['default_light_json'] ?? null;
        if ($rawLd !== null && $rawLd !== '') {
            $ld = json_decode((string) $rawLd, true);
            if (is_array($ld)) {
                $asset['light_data'] = $ld;
            }
        }
        return [
            'id' => (int) $r['id'],
            'code' => $r['item_code'],
            'name' => $r['name'],
            'category' => $r['category'],
            'rarity' => $r['rarity'],
            'asset_data' => $asset,
        ];
    }

    /**
     * @return array{rows: array<int, array>, total: int}
     */
    function nexus_world_builder_catalog_fetch_filtered(PDO $pdo, string $search, string $category, int $page, int $limit): array {
        if (!nexus_world_builder_catalog_table_exists($pdo)) {
            return ['rows' => [], 'total' => 0];
        }
        $limit = max(1, min(100, $limit));
        $page = max(1, $page);
        $offset = ($page - 1) * $limit;

        $where = ['is_active = 1'];
        $params = [];
        if ($search !== '') {
            $where[] = '(name LIKE ? OR item_code LIKE ?)';
            $term = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
            $params[] = $term;
            $params[] = $term;
        }
        if ($category !== '') {
            $where[] = 'category = ?';
            $params[] = $category;
        }
        $whereSql = implode(' AND ', $where);

        $c = $pdo->prepare("SELECT COUNT(*) FROM nexus_world_builder_catalog WHERE $whereSql");
        $c->execute($params);
        $total = (int) $c->fetchColumn();

        $sql = "SELECT id, item_code, name, category, rarity, model_url, wb_scale, default_light_json, hologram, sort_order
                FROM nexus_world_builder_catalog
                WHERE $whereSql
                ORDER BY sort_order ASC, name ASC
                LIMIT " . (int) $limit . " OFFSET " . (int) $offset;
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $raw = $st->fetchAll(PDO::FETCH_ASSOC);

        $out = [];
        foreach ($raw as $r) {
            $out[] = nexus_world_builder_catalog_row_to_api($r);
        }
        return ['rows' => $out, 'total' => $total];
    }
}
