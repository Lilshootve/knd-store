<?php
// Centralized pricing map keyed by product id.

$PRICING_MAP = [
    1 => [
        'slug' => 'formateo-limpieza-pc',
        'name' => 'PC Format & Cleanup (Remote)',
        'type' => 'digital',
        'base_price_usd' => 10.00,
        'allowed_variants' => null,
        'shipping_rules' => ['requires_shipping' => false],
    ],
    2 => [
        'slug' => 'instalacion-windows-drivers',
        'name' => 'Windows + Drivers Installation',
        'type' => 'digital',
        'base_price_usd' => 49.00,
        'allowed_variants' => null,
        'shipping_rules' => ['requires_shipping' => false],
    ],
    3 => [
        'slug' => 'optimizacion-gamer',
        'name' => 'Gamer Optimization (FPS, temperatures, storage)',
        'type' => 'digital',
        'base_price_usd' => 49.00,
        'allowed_variants' => null,
        'shipping_rules' => ['requires_shipping' => false],
    ],
    4 => [
        'slug' => 'activacion-juegos-giftcards',
        'name' => 'Game & Gift Card Activation',
        'type' => 'digital',
        'base_price_usd' => 3.00,
        'allowed_variants' => null,
        'shipping_rules' => ['requires_shipping' => false],
    ],
    5 => [
        'slug' => 'asesoria-pc-gamer',
        'name' => 'PC Gamer Consulting (Custom Budget)',
        'type' => 'digital',
        'base_price_usd' => 19.00,
        'allowed_variants' => null,
        'shipping_rules' => ['requires_shipping' => false],
    ],
    7 => [
        'slug' => 'wallpaper-personalizado',
        'name' => 'AI Custom Wallpaper',
        'type' => 'digital',
        'base_price_usd' => 6.00,
        'allowed_variants' => null,
        'shipping_rules' => ['requires_shipping' => false],
    ],
    8 => [
        'slug' => 'avatar-personalizado',
        'name' => 'Custom Gamer Avatar',
        'type' => 'digital',
        'base_price_usd' => 6.00,
        'allowed_variants' => null,
        'shipping_rules' => ['requires_shipping' => false],
    ],
    9 => [
        'slug' => 'icon-pack-knd',
        'name' => 'Icon Pack (Special Edition)',
        'type' => 'digital',
        'base_price_usd' => 15.00,
        'allowed_variants' => null,
        'shipping_rules' => ['requires_shipping' => false],
    ],
    10 => [
        'slug' => 'instalacion-software',
        'name' => 'Office, Adobe, OBS Installation',
        'type' => 'digital',
        'base_price_usd' => 39.00,
        'allowed_variants' => null,
        'shipping_rules' => ['requires_shipping' => false],
    ],
    11 => [
        'slug' => 'pc-ready-pack',
        'name' => 'PC Ready Pack (Software + Setup)',
        'type' => 'digital',
        'base_price_usd' => 79.00,
        'allowed_variants' => null,
        'shipping_rules' => ['requires_shipping' => false],
    ],
    12 => [
        'slug' => 'tutorial-knd',
        'name' => 'Mini Tutorial (PDF or Express Video)',
        'type' => 'digital',
        'base_price_usd' => 12.00,
        'allowed_variants' => null,
        'shipping_rules' => ['requires_shipping' => false],
    ],
    13 => [
        'slug' => 'compatibilidad-piezas',
        'name' => 'Parts Compatibility Check (Do they work together?)',
        'type' => 'digital',
        'base_price_usd' => 9.00,
        'allowed_variants' => null,
        'shipping_rules' => ['requires_shipping' => false],
    ],
    14 => [
        'slug' => 'simulacion-build',
        'name' => 'Build Simulation with Custom PDF',
        'type' => 'digital',
        'base_price_usd' => 29.00,
        'allowed_variants' => null,
        'shipping_rules' => ['requires_shipping' => false],
    ],
    15 => [
        'slug' => 'analisis-pc',
        'name' => 'PC Performance Analysis',
        'type' => 'digital',
        'base_price_usd' => 19.00,
        'allowed_variants' => null,
        'shipping_rules' => ['requires_shipping' => false],
    ],
    16 => [
        'slug' => 'hoodie-knd-style',
        'name' => 'Hoodie KND Style',
        'type' => 'apparel',
        'base_price_usd' => 44.99,
        'allowed_variants' => [
            'colors' => ['magenta', 'black', 'turquoise'],
            'sizes' => ['S', 'M', 'L', 'XL'],
        ],
        'shipping_rules' => ['requires_shipping' => true],
    ],
    17 => [
        'slug' => 'tshirt-knd-oversize',
        'name' => 'T-Shirt KND Oversize',
        'type' => 'apparel',
        'base_price_usd' => 24.99,
        'allowed_variants' => [
            'colors' => ['magenta', 'black', 'turquoise'],
            'sizes' => ['S', 'M', 'L', 'XL'],
        ],
        'shipping_rules' => ['requires_shipping' => true],
    ],
    18 => [
        'slug' => 'hoodie-knd-black-edition',
        'name' => 'Hoodie KND Black Edition',
        'type' => 'apparel',
        'base_price_usd' => 49.99,
        'allowed_variants' => [
            'colors' => ['black'],
            'sizes' => ['S', 'M', 'L', 'XL'],
        ],
        'shipping_rules' => ['requires_shipping' => true],
    ],
    19 => [
        'slug' => 'hoodie-anime-style',
        'name' => 'Hoodie Anime Style (Limited Drop)',
        'type' => 'apparel',
        'base_price_usd' => 59.99,
        'allowed_variants' => [
            'colors' => ['tri-tone'],
            'sizes' => ['S', 'M', 'L', 'XL'],
        ],
        'shipping_rules' => ['requires_shipping' => true],
    ],
    20 => [
        'slug' => 'hoodie-dark-eyes-style',
        'name' => 'Hoodie Dark Eyes Style (Limited Drop)',
        'type' => 'apparel',
        'base_price_usd' => 59.99,
        'allowed_variants' => [
            'colors' => ['tri-tone'],
            'sizes' => ['S', 'M', 'L', 'XL'],
        ],
        'shipping_rules' => ['requires_shipping' => true],
    ],
    21 => [
        'slug' => 'custom-tshirt-design',
        'name' => 'Custom T-Shirt Design (Service)',
        'type' => 'service',
        'base_price_usd' => 24.99,
        'allowed_variants' => null,
        'shipping_rules' => ['requires_shipping' => false],
    ],
    22 => [
        'slug' => 'custom-hoodie-design',
        'name' => 'Custom Hoodie Design (Service)',
        'type' => 'service',
        'base_price_usd' => 34.99,
        'allowed_variants' => null,
        'shipping_rules' => ['requires_shipping' => false],
    ],
    23 => [
        'slug' => 'custom-full-outfit-concept',
        'name' => 'Custom Full Outfit Concept (Service)',
        'type' => 'service',
        'base_price_usd' => 69.99,
        'allowed_variants' => null,
        'shipping_rules' => ['requires_shipping' => false],
    ],
    24 => [
        'slug' => 'creative-design-consultation',
        'name' => 'Creative Design Consultation',
        'type' => 'digital',
        'base_price_usd' => 3.00,
        'allowed_variants' => null,
        'shipping_rules' => ['requires_shipping' => false],
    ],
];

if (!function_exists('getPricingItemById')) {
    function getPricingItemById(int $id): ?array {
        global $PRICING_MAP;
        return $PRICING_MAP[$id] ?? null;
    }
}

if (!function_exists('getProductPriceValue')) {
    function getProductPriceValue(int $id, array $product = []): float {
        $item = getPricingItemById($id);
        if ($item && isset($item['base_price_usd'])) {
            return (float) $item['base_price_usd'];
        }
        if (isset($product['precio'])) {
            return (float) $product['precio'];
        }
        return 0.0;
    }
}

if (!function_exists('validateItemVariants')) {
    function validateItemVariants(?array $pricingItem, ?array $variants): bool {
        if (!$variants || !$pricingItem || empty($pricingItem['allowed_variants'])) {
            return empty($variants);
        }

        $allowed = $pricingItem['allowed_variants'];
        if (isset($variants['color']) && isset($allowed['colors'])) {
            if (!in_array($variants['color'], $allowed['colors'], true)) {
                return false;
            }
        } elseif (isset($variants['color'])) {
            return false;
        }

        if (isset($variants['size']) && isset($allowed['sizes'])) {
            if (!in_array($variants['size'], $allowed['sizes'], true)) {
                return false;
            }
        } elseif (isset($variants['size'])) {
            return false;
        }

        return true;
    }
}
?>
