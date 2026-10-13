<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$CLICK_FILE     = __DIR__ . '/shein_clicks.json';
$VERIFY_FILE    = __DIR__ . '/shein_verifies.json';
$HEARTBEAT_FILE = __DIR__ . '/active_users.json';

$body   = json_decode(file_get_contents('php://input'), true);
$action = isset($body['action']) ? $body['action'] : 'click';

// ── Heartbeat ─────────────────────────────────────────────────
if ($action === 'heartbeat') {
    $uid = isset($body['uid']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $body['uid']) : '';
    if (!$uid) { echo json_encode(['ok' => false]); exit; }
    $beats = [];
    if (file_exists($HEARTBEAT_FILE)) $beats = json_decode(file_get_contents($HEARTBEAT_FILE), true) ?: [];
    $beats[$uid] = time();
    $beats = array_filter($beats, fn($t) => $t >= time() - 30);
    file_put_contents($HEARTBEAT_FILE, json_encode($beats));
    echo json_encode(['ok' => true, 'active' => count($beats)]);
    exit;
}

// ── Verify & Claim click ──────────────────────────────────────
if ($action === 'verify') {
    $entry = [
        'time'      => date('Y-m-d H:i:s'),
        'timestamp' => time(),
    ];
    $logs = [];
    if (file_exists($VERIFY_FILE)) $logs = json_decode(file_get_contents($VERIFY_FILE), true) ?: [];
    array_unshift($logs, $entry);
    file_put_contents($VERIFY_FILE, json_encode($logs, JSON_PRETTY_PRINT));
    echo json_encode(['ok' => true]);
    exit;
}

// ── Download / Tap to Download click ─────────────────────────
$store = isset($body['store']) ? $body['store'] : 'unknown';
if (!in_array($store, ['appstore', 'playstore', 'unknown'])) $store = 'unknown';

$entry = [
    'time'      => date('Y-m-d H:i:s'),
    'timestamp' => time(),
    'store'     => $store,
];

$logs = [];
if (file_exists($CLICK_FILE)) $logs = json_decode(file_get_contents($CLICK_FILE), true) ?: [];
array_unshift($logs, $entry);
file_put_contents($CLICK_FILE, json_encode($logs, JSON_PRETTY_PRINT));
echo json_encode(['ok' => true]);
