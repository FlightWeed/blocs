<?php
// track_active.php — called by dashboard every 10s to refresh live count
header('Content-Type: application/json');

$HEARTBEAT_FILE = __DIR__ . '/active_users.json';

$activeCount = 0;
if (file_exists($HEARTBEAT_FILE)) {
    $beats       = json_decode(file_get_contents($HEARTBEAT_FILE), true) ?: [];
    $cutoff      = time() - 30;
    $activeCount = count(array_filter($beats, fn($t) => $t >= $cutoff));
}

echo json_encode(['active' => $activeCount]);
