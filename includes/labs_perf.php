<?php
/**
 * KND Labs - Temporary performance audit logging.
 * Add checkpoints via labs_perf_checkpoint($label).
 * Output via labs_perf_output() or labs_perf_comment().
 *
 * Usage:
 *   labs_perf_start();
 *   ... code ...
 *   labs_perf_checkpoint('after_db');
 *   ... code ...
 *   echo labs_perf_comment();
 */
function labs_perf_start(): void {
    if (!isset($GLOBALS['_labs_perf'])) {
        $GLOBALS['_labs_perf'] = [
            'start' => microtime(true),
            'checkpoints' => [],
            'last' => microtime(true),
        ];
    }
}

function labs_perf_checkpoint(string $label): void {
    labs_perf_start();
    $now = microtime(true);
    $GLOBALS['_labs_perf']['checkpoints'][$label] = [
        'ms' => round(($now - $GLOBALS['_labs_perf']['start']) * 1000, 2),
        'delta_ms' => round(($now - $GLOBALS['_labs_perf']['last']) * 1000, 2),
    ];
    $GLOBALS['_labs_perf']['last'] = $now;
}

function labs_perf_comment(): string {
    if (empty($GLOBALS['_labs_perf']['checkpoints'])) {
        return '';
    }
    $total = round((microtime(true) - $GLOBALS['_labs_perf']['start']) * 1000, 2);
    $rows = [];
    foreach ($GLOBALS['_labs_perf']['checkpoints'] as $label => $d) {
        $rows[] = $label . ': ' . $d['ms'] . 'ms (Δ' . $d['delta_ms'] . 'ms)';
    }
    return "\n<!-- LABS_PERF total=" . $total . "ms | " . implode(' | ', $rows) . " -->\n";
}

function labs_perf_log(): void {
    $logDir = defined('KND_LOGS_DIR') ? KND_LOGS_DIR : (__DIR__ . '/../logs');
    if (!is_dir($logDir) || !is_writable($logDir)) {
        return;
    }
    $total = round((microtime(true) - ($GLOBALS['_labs_perf']['start'] ?? microtime(true))) * 1000, 2);
    $page = $_SERVER['REQUEST_URI'] ?? 'unknown';
    $line = date('Y-m-d H:i:s') . " LABS_PERF page={$page} total={$total}ms " . json_encode($GLOBALS['_labs_perf']['checkpoints'] ?? []) . "\n";
    @file_put_contents($logDir . '/labs-perf.log', $line, FILE_APPEND | LOCK_EX);
}
