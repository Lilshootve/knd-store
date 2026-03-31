<?php
// Product grouping for services-first layout

$PRODUCT_GROUPS = [
    'consulting' => [
        'asesoria-pc-gamer',
        'compatibilidad-piezas',
        'simulacion-build',
        'analisis-pc',
    ],
    'remote_service' => [
        'instalacion-windows-drivers',
        'optimizacion-gamer',
        'instalacion-software',
        'pc-ready-pack',
        'formateo-limpieza-pc',
    ],
    'digital_asset' => [
        'wallpaper-personalizado',
        'avatar-personalizado',
        'icon-pack-knd',
        'tutorial-knd',
        'activacion-juegos-giftcards',
        'creative-design-consultation',
    ],
    'apparel' => [
        'hoodie-knd-style',
        'tshirt-knd-oversize',
        'hoodie-knd-black-edition',
        'hoodie-anime-style',
        'hoodie-dark-eyes-style',
    ],
];

if (!function_exists('getProductsBySlugs')) {
    function getProductsBySlugs(array $slugs, array $products, string $context = 'products'): array {
        $matched = [];
        foreach ($slugs as $slug) {
            if (isset($products[$slug])) {
                $matched[$slug] = $products[$slug];
                continue;
            }
            error_log('[KND] Missing product slug "' . $slug . '" in ' . $context);
        }
        return $matched;
    }
}
?>
