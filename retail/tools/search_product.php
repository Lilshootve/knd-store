<?php
/**
 * Tool: search_product
 * Búsqueda fuzzy segura usando Levenshtein sobre nombres de productos.
 * Si hay 0 matches → sugiere crear restock_request.
 * Si hay múltiples matches → retorna lista para que el usuario elija.
 * Si hay match único con alta confianza → retorna directamente.
 *
 * NUNCA hace SQL LIKE con input sin sanitizar — carga nombres en memoria
 * (eficiente para catálogos <= 50,000 productos por negocio).
 */

function retail_search_product(PDO $pdo, array $input): array
{
    $bizId = retail_business_id();
    $query = trim($input['query'] ?? '');

    if (strlen($query) < 2) {
        return ['error' => 'query muy corto (mínimo 2 caracteres).'];
    }

    // Cargar todos los productos activos del negocio (solo id + name para Levenshtein)
    $stmt = $pdo->prepare(
        'SELECT id, sku, name, price_base, stock, min_stock
         FROM retail_products
         WHERE business_id = ? AND active = 1
         ORDER BY name ASC'
    );
    $stmt->execute([$bizId]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($products)) {
        return [
            'found'   => false,
            'matches' => [],
            'message' => 'No hay productos registrados para este negocio.',
        ];
    }

    $queryNorm    = _retail_normalize($query);
    $queryLen     = mb_strlen($queryNorm, 'UTF-8');
    $scored       = [];

    foreach ($products as $p) {
        $nameNorm = _retail_normalize($p['name']);
        $nameLen  = mb_strlen($nameNorm, 'UTF-8');

        // 1. Coincidencia exacta normalizada
        if ($nameNorm === $queryNorm) {
            $p['_score'] = 0;
            $p['_match'] = 'exact';
            $scored[]    = $p;
            continue;
        }

        // 2. Contiene la query completa
        if (mb_strpos($nameNorm, $queryNorm, 0, 'UTF-8') !== false) {
            $p['_score'] = 1;
            $p['_match'] = 'contains';
            $scored[]    = $p;
            continue;
        }

        // 3. Levenshtein — comparar contra nombre completo y tokens
        $lev = levenshtein($queryNorm, $nameNorm);
        // Umbral dinámico: 30% de la longitud del nombre, mínimo 1, máximo 4
        $threshold = min(4, max(1, (int) round($nameLen * 0.30)));

        if ($lev <= $threshold) {
            $p['_score'] = $lev + 2; // Offset para que exactos queden primero
            $p['_match'] = 'fuzzy';
            $scored[]    = $p;
            continue;
        }

        // 4. Comparar token a token (ej: query="coca" vs name="Coca Cola 600ml")
        $nameTokens = explode(' ', $nameNorm);
        foreach ($nameTokens as $token) {
            $tokLen   = mb_strlen($token, 'UTF-8');
            $tokLev   = levenshtein($queryNorm, $token);
            $tokThres = min(3, max(1, (int) round($tokLen * 0.35)));
            if ($tokLev <= $tokThres && $tokLen >= 3) {
                $p['_score'] = $tokLev + 5;
                $p['_match'] = 'token_fuzzy';
                $scored[]    = $p;
                break;
            }
        }
    }

    if (empty($scored)) {
        // Sin matches — sugerir restock
        return [
            'found'    => false,
            'matches'  => [],
            'message'  => "No se encontró ningún producto similar a \"$query\".",
            'suggest'  => [
                'action'  => 'create_restock_request',
                'product' => $query,
                'message' => '¿Deseas agregar una solicitud de restock para este producto?',
            ],
        ];
    }

    // Ordenar por score (menor = más relevante)
    usort($scored, fn($a, $b) => $a['_score'] <=> $b['_score']);

    // Limpiar campos internos
    foreach ($scored as &$p) {
        unset($p['_score'], $p['_match']);
    }
    unset($p);

    if (count($scored) === 1) {
        return [
            'found'   => true,
            'unique'  => true,
            'product' => $scored[0],
        ];
    }

    // Múltiples matches — devolver lista para que el usuario elija
    return [
        'found'    => true,
        'unique'   => false,
        'matches'  => array_slice($scored, 0, 8), // Máximo 8 sugerencias
        'message'  => 'Varios productos encontrados. ¿Cuál es el correcto?',
    ];
}

/**
 * Normalizar string para comparación: minúsculas, sin tildes, sin puntuación extra.
 */
function _retail_normalize(string $s): string
{
    $s = mb_strtolower(trim($s), 'UTF-8');
    // Remover tildes
    $search  = ['á','é','í','ó','ú','ü','ñ','à','è','ì','ò','ù'];
    $replace = ['a','e','i','o','u','u','n','a','e','i','o','u'];
    $s = str_replace($search, $replace, $s);
    // Colapsar espacios y remover caracteres no alfanuméricos excepto espacios
    $s = preg_replace('/[^a-z0-9 ]/', ' ', $s);
    return trim(preg_replace('/\s+/', ' ', $s));
}
