<?php
/**
 * Salas instanciadas por distrito (Habbo-like): una URL por zona, escena 3D + NPCs + enlaces a minijuegos.
 * La city (NEXUS) enlaza aquí; cada sala no carga el mundo completo de nexus-city.
 *
 * @see games/arena-protocol/district-room.php
 * @see api/nexus/district_room_config.php
 */

declare(strict_types=1);

/** Distritos que usan la capa district-room (no toca sanctum ni central). */
function nexus_district_room_layer_ids(): array
{
    return ['tesla', 'olimpo', 'casino', 'agora'];
}

function nexus_district_room_entry_url(string $districtId): string
{
    return '/games/arena-protocol/district-room.php?district=' . rawurlencode($districtId);
}

/**
 * Enlaza tesla, olimpo, casino y agora a district-room.php (sala 3D + NPCs).
 * sanctum / central no se modifican. Para enlazar otro destino, quita el id de nexus_district_room_layer_ids().
 */
function nexus_district_room_apply_game_urls(array $districts): array
{
    $layer = array_flip(nexus_district_room_layer_ids());
    foreach ($districts as &$row) {
        $id = (string)($row['id'] ?? '');
        if ($id === '' || !isset($layer[$id])) {
            continue;
        }
        $row['game_url'] = nexus_district_room_entry_url($id);
    }
    unset($row);

    return $districts;
}

/**
 * Configuración por distrito: tema + NPCs (ampliar sin tocar el HTML principal).
 *
 * game_url: ruta absoluta bajo el sitio; el cliente añade ?return= para volver a la sala.
 * mw_avatar_id: opcional, futuro GLB del catálogo; si null se usa figura procedural.
 */
function nexus_district_room_registry(): array
{
    return [
        'tesla' => [
            'title'       => 'Laboratorio Tesla',
            'subtitle'    => 'Ciencia · conocimiento · experimentos',
            'accent_hex'  => '#00e8ff',
            'ambient'     => 'lab',
            'npcs'        => [
                [
                    'id'          => 'nikola_tesla',
                    'name'        => 'Nikola Tesla',
                    'title'       => 'Ingeniero',
                    'color_hex'   => '#00e8ff',
                    'pos'         => [3.5, 0, 2],
                    'rot_y'       => -0.7,
                    'blurb'       => '¿Listo para un duelo de conocimiento? El que domina los datos domina el futuro.',
                    'game_url'    => '/games/knowledge-duel.php',
                    'game_label'  => 'Knowledge Duel',
                ],
                [
                    'id'          => 'albert_einstein',
                    'name'        => 'Albert Einstein',
                    'title'       => 'Físico',
                    'color_hex'   => '#9b30ff',
                    'pos'         => [-2, 0, 4],
                    'rot_y'       => 0.5,
                    'blurb'       => 'La música y la física comparten patrones. Prueba tu oído.',
                    'game_url'    => null,
                    'game_label'  => 'Adivina la canción',
                    'coming_soon' => true,
                ],
                [
                    'id'          => 'isaac_newton',
                    'name'        => 'Isaac Newton',
                    'title'       => 'Matemático',
                    'color_hex'   => '#ffd600',
                    'pos'         => [0, 0, -3.5],
                    'rot_y'       => 3.14,
                    'blurb'       => 'Por cada acción hay una reacción… y por cada bandera, un país.',
                    'game_url'    => null,
                    'game_label'  => 'Adivina la bandera',
                    'coming_soon' => true,
                ],
            ],
        ],
        'olimpo' => [
            'title'       => 'Monte Olimpo',
            'subtitle'    => 'Combate · honor · arena',
            'accent_hex'  => '#ffd600',
            'ambient'     => 'temple',
            'npcs'        => [
                [
                    'id'          => 'zeus',
                    'name'        => 'Zeus',
                    'title'       => 'Rey del Olimpo',
                    'color_hex'   => '#ffd600',
                    'pos'         => [4, 0, 1],
                    'rot_y'       => -0.9,
                    'blurb'       => 'Solo los dignos entran en la arena de Mind Wars.',
                    'game_url'    => '/games/mind-wars/lobby.php',
                    'game_label'  => 'Mind Wars — lobby',
                ],
                [
                    'id'          => 'thor',
                    'name'        => 'Thor',
                    'title'       => 'Dios del trueno',
                    'color_hex'   => '#66ccff',
                    'pos'         => [-3.5, 0, 2],
                    'rot_y'       => 0.6,
                    'blurb'       => 'Batallas rápidas 1v1. El martillo no perdona.',
                    'game_url'    => null,
                    'game_label'  => 'Duelo 1v1',
                    'coming_soon' => true,
                ],
            ],
        ],
        'casino' => [
            'title'       => 'Casino del Destino',
            'subtitle'    => 'Azar · fragmentos · riesgo calculado',
            'accent_hex'  => '#9b30ff',
            'ambient'     => 'casino',
            'npcs'        => [
                [
                    'id'          => 'dealer_neon',
                    'name'        => 'Crupier Nébula',
                    'title'       => 'Anfitrión',
                    'color_hex'   => '#ff44aa',
                    'pos'         => [0, 0, 2],
                    'rot_y'       => 3.14,
                    'blurb'       => 'Las cartas están echadas. ¿Subes o te retiras?',
                    'game_url'    => '/above-under.php',
                    'game_label'  => 'Above / Under',
                ],
                [
                    'id'          => 'dealer_shadow',
                    'name'        => 'Sombra',
                    'title'       => 'Mesa alta',
                    'color_hex'   => '#9b30ff',
                    'pos'         => [-4, 0, -2],
                    'rot_y'       => 0.4,
                    'blurb'       => 'Solo para jugadores con nervios de acero.',
                    'game_url'    => null,
                    'game_label'  => 'Mesa exclusiva',
                    'coming_soon' => true,
                ],
            ],
        ],
        'agora' => [
            'title'       => 'Ágora Social',
            'subtitle'    => 'Comunidad · lucimiento · charla',
            'accent_hex'  => '#00ff88',
            'ambient'     => 'plaza',
            'npcs'        => [
                [
                    'id'          => 'herald',
                    'name'        => 'Heraldo del Nexus',
                    'title'       => 'Guía',
                    'color_hex'   => '#00ff88',
                    'pos'         => [2, 0, 1],
                    'rot_y'       => -0.5,
                    'blurb'       => 'Aquí la gente se reúne para hablar y enseñar su avatar. Usa el chat en la ciudad o permanece en esta sala para explorar.',
                    'game_url'    => null,
                    'game_label'  => null,
                    'coming_soon' => false,
                ],
            ],
        ],
    ];
}

function nexus_district_room_get(string $districtId): ?array
{
    $id = strtolower(trim($districtId));
    $reg = nexus_district_room_registry();

    return $reg[$id] ?? null;
}
