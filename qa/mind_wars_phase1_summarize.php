<?php
declare(strict_types=1);

$path = __DIR__ . '/mind_wars_phase1_results.json';
$raw = file_get_contents($path);
if ($raw === false) {
    fwrite(STDERR, "Cannot read results file\n");
    exit(1);
}
$rawUtf8 = mb_convert_encoding($raw, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
$json = json_decode($rawUtf8, true);
if (!is_array($json) || !isset($json['results']) || !is_array($json['results'])) {
    fwrite(STDERR, "Invalid results JSON: " . json_last_error_msg() . "\n");
    exit(1);
}

foreach ($json['results'] as $t) {
    $last = !empty($t['steps']) ? $t['steps'][count($t['steps']) - 1] : ['log_tail' => []];
    $a = $t['after'];
    $logs = implode(' || ', $last['log_tail'] ?? []);
    echo $t['id'] . '|' . $t['status']
        . '|turn=' . (string) ($a['turn'] ?? '')
        . '|pHP=' . (string) ($a['player']['hp'] ?? '')
        . '|eHP=' . (string) ($a['enemy']['hp'] ?? '')
        . '|pEN=' . (string) ($a['player']['energy'] ?? '')
        . '|eEN=' . (string) ($a['enemy']['energy'] ?? '')
        . '|logs=' . $logs . PHP_EOL;
}

