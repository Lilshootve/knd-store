<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

/**
 * Valida columnas JSON de mw_avatar_skills contra el catalogo soportado.
 *
 * Uso:
 *   php scripts/validate_mw_skills_json.php           # rango por defecto 1..6
 *   php scripts/validate_mw_skills_json.php 7 22    # avatar_id entre 7 y 22
 *
 * Si la BD falla, se muestra el error PDO concreto y pistas (.env, MySQL).
 */

try {
    require_once __DIR__ . '/../config/bootstrap.php';
    require_once BASE_PATH . '/includes/config.php';
} catch (Throwable $e) {
    fwrite(STDERR, '[validate_mw_skills_json] bootstrap failed: ' . $e->getMessage() . "\n");
    exit(1);
}

if (php_sapi_name() !== 'cli') {
    echo "CLI only\n";
    exit(1);
}

$allowedTargetScope = ['single_enemy', 'single_ally', 'slot_enemy', 'row_enemy', 'all_enemies', 'all_allies', 'self'];
$allowedFallback = ['highest_mind', 'random_alive', 'lowest_hp', 'highest_hp', 'most_debuffed'];
$allowedEffectTypes = [
    'weaken', 'damage_amp', 'bonus_vs_condition', 'damage_reduction', 'shield', 'regen',
    'energy_gain', 'energy_drain', 'energy_block', 'mind_down', 'focus_down', 'speed_down', 'speed_up',
    'stun', 'freeze', 'ability_lock', 'anti_heal', 'heal', 'bleed', 'ignore_defense', 'bonus_per_debuff',
    'action_efficiency_down', 'random_debuff', 'chaos', 'stat_shuffle', 'cleanse',
];

/**
 * @param array<int, string> $argv
 * @return array{min:int, max:int}
 */
function knd_validate_skills_parse_argv(array $argv): array
{
    $min = 1;
    $max = 6;
    $args = array_slice($argv, 1);
    if (isset($args[0]) && preg_match('/^\d+$/', (string) $args[0])) {
        $min = (int) $args[0];
        $max = $min;
    }
    if (isset($args[1]) && preg_match('/^\d+$/', (string) $args[1])) {
        $max = (int) $args[1];
    }
    if ($max < $min) {
        $t = $min;
        $min = $max;
        $max = $t;
    }
    return ['min' => $min, 'max' => $max];
}

function knd_validate_skills_db_error_detail(): string
{
    if (!defined('DB_HOST')) {
        return 'constants DB_* not defined (.env no cargado o incompleto)';
    }
    try {
        $portDsn = defined('DB_PORT') && DB_PORT !== '' ? ';port=' . DB_PORT : '';
        $dsn = 'mysql:host=' . DB_HOST . $portDsn . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        return '';
    } catch (Throwable $e) {
        return $e->getMessage();
    }
}

/**
 * @return array<int, string>
 */
function knd_validate_skill_block(
    int $avatarId,
    string $key,
    string $rawJson,
    array $scopes,
    array $fallback,
    array $effects
): array {
    $errors = [];
    $trim = trim($rawJson);
    if ($trim === '' || strcasecmp($trim, 'null') === 0) {
        return ["avatar {$avatarId} {$key}: empty_or_null"];
    }
    $d = json_decode($rawJson, true);
    if (!is_array($d)) {
        return ["avatar {$avatarId} {$key}: invalid_json"];
    }
    if ($key !== 'passive_data') {
        foreach (['base_power', 'scaling_stat', 'target_scope', 'fallback_rules'] as $req) {
            if (!array_key_exists($req, $d)) {
                $errors[] = "avatar {$avatarId} {$key}: missing_{$req}";
            }
        }
        if (isset($d['target_scope']) && !in_array((string) $d['target_scope'], $scopes, true)) {
            $errors[] = "avatar {$avatarId} {$key}: invalid_target_scope";
        }
        if (isset($d['fallback_rules']) && !in_array((string) $d['fallback_rules'], $fallback, true)) {
            $errors[] = "avatar {$avatarId} {$key}: invalid_fallback_rules";
        }
    } else {
        if (!isset($d['type'])) {
            $errors[] = "avatar {$avatarId} passive_data: missing_type";
        } elseif (!in_array((string) $d['type'], $effects, true)) {
            $errors[] = "avatar {$avatarId} passive_data: unsupported_effect_type {$d['type']}";
        }
    }
    $payload = $d['effect_payload'] ?? [];
    if (is_array($payload)) {
        foreach ($payload as $idx => $fx) {
            if (!is_array($fx)) {
                $errors[] = "avatar {$avatarId} {$key}: effect_payload[{$idx}] not_object";
                continue;
            }
            $type = (string) ($fx['type'] ?? '');
            if (!in_array($type, $effects, true)) {
                $errors[] = "avatar {$avatarId} {$key}: unsupported_effect_type {$type}";
            }
        }
    }
    return $errors;
}

$opts = knd_validate_skills_parse_argv($argv);

$pdo = getDBConnection();
if (!$pdo instanceof PDO) {
    $detail = knd_validate_skills_db_error_detail();
    fwrite(STDERR, "DB connection failed.\n");
    if ($detail !== '') {
        fwrite(STDERR, "PDO: {$detail}\n");
    }
    fwrite(STDERR, 'Revisa ' . BASE_PATH . "/.env (DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS) y que el servidor MySQL/MariaDB este accesible desde esta maquina.\n");
    exit(1);
}

$stmt = $pdo->prepare(
    'SELECT avatar_id, basic_data, passive_data, ability_data, special_data
     FROM mw_avatar_skills
     WHERE avatar_id >= :min AND avatar_id <= :max
     ORDER BY avatar_id ASC'
);
$stmt->execute(['min' => $opts['min'], 'max' => $opts['max']]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($rows === []) {
    fwrite(STDERR, "No hay filas en mw_avatar_skills para avatar_id {$opts['min']}..{$opts['max']}.\n");
    exit(3);
}

$errors = [];
foreach ($rows as $r) {
    $avatarId = (int) ($r['avatar_id'] ?? 0);
    foreach (['basic_data', 'ability_data', 'special_data'] as $k) {
        $errors = array_merge(
            $errors,
            knd_validate_skill_block(
                $avatarId,
                $k,
                (string) ($r[$k] ?? ''),
                $allowedTargetScope,
                $allowedFallback,
                $allowedEffectTypes
            )
        );
    }
    $errors = array_merge(
        $errors,
        knd_validate_skill_block(
            $avatarId,
            'passive_data',
            (string) ($r['passive_data'] ?? ''),
            $allowedTargetScope,
            $allowedFallback,
            $allowedEffectTypes
        )
    );
}

if ($errors === []) {
    echo 'OK: avatar_id ' . $opts['min'] . '..' . $opts['max'] . ' sin errores estructurales (' . count($rows) . " filas).\n";
    exit(0);
}

echo "VALIDATION ERRORS:\n";
foreach ($errors as $e) {
    echo " - {$e}\n";
}
exit(2);
