<?php
/**
 * KND Neural Link — pack definitions (KP-only).
 * PRODUCCIÓN: tune cost_kp and rates here; keep in sync across endpoints via this file.
 */

declare(strict_types=1);

/**
 * @return array<string, array{cost_kp: int, rates: array<string,int>, pity_legendary: int, pity_epic: int}>
 */
function knl_drop_packs_raw(): array
{
    return [
        'standard' => [
            'cost_kp'        => 100,
            'rates'          => [
                'common'    => 49,
                'rare'      => 28,
                'special'   => 12,
                'epic'      => 9,
                'legendary' => 2,
            ],
            'pity_legendary' => 100,
            'pity_epic'      => 20,
        ],
        'premium' => [
            'cost_kp'        => 500,
            'rates'          => [
                'common'    => 25,
                'rare'      => 35,
                'special'   => 20,
                'epic'      => 15,
                'legendary' => 5,
            ],
            'pity_legendary' => 60,
            'pity_epic'      => 10,
        ],
        'legendary' => [
            'cost_kp'        => 1000,
            'rates'          => [
                'common'    => 0,
                'rare'      => 10,
                'special'   => 14,
                'epic'      => 43,
                'legendary' => 33,
            ],
            'pity_legendary' => 10,
            'pity_epic'      => 3,
        ],
    ];
}

/**
 * Filas de tasas para JSON/UI (mismos pesos que open_drop).
 *
 * @return list<array{rarity: string, label: string, pct: int}>
 */
function knl_pack_rates_ui_rows(string $packId): array
{
    $raw = knl_drop_packs_raw();
    if (!isset($raw[$packId]['rates'])) {
        return [];
    }
    $rates = $raw[$packId]['rates'];
    $labels = [
        'common'    => 'Common',
        'rare'      => 'Rare',
        'special'   => 'Special',
        'epic'      => 'Epic',
        'legendary' => 'Legendary',
    ];
    $order = ['common', 'rare', 'special', 'epic', 'legendary'];
    $rows  = [];
    foreach ($order as $r) {
        if (!array_key_exists($r, $rates)) {
            continue;
        }
        $rows[] = [
            'rarity' => $r,
            'label'  => $labels[$r] ?? $r,
            'pct'    => (int) $rates[$r],
        ];
    }

    return $rows;
}
