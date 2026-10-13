<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$username = isset($_GET['username']) ? trim($_GET['username']) : '';

if (!$username) {
    echo json_encode(['found' => false]);
    exit;
}

// Step 1: Look up user ID by username
$postData = json_encode(['usernames' => [$username], 'excludeBannedUsers' => false]);

$ch = curl_init('https://users.roblox.com/v1/usernames/users');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $postData,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
    CURLOPT_TIMEOUT        => 8,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$userRaw  = curl_exec($ch);
curl_close($ch);

$userData = json_decode($userRaw, true);

if (empty($userData['data'])) {
    echo json_encode(['found' => false]);
    exit;
}

$user        = $userData['data'][0];
$userId      = $user['id'];
$displayName = $user['displayName'] ?? $user['name'];
$userName    = $user['name'];

// Step 2: Get avatar headshot thumbnail
$thumbUrl = "https://thumbnails.roblox.com/v1/users/avatar-headshot?userIds={$userId}&size=420x420&format=Png&isCircular=false";

$ch = curl_init($thumbUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 8,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER     => ['Accept: application/json'],
]);
$thumbRaw = curl_exec($ch);
curl_close($ch);

$thumbData = json_decode($thumbRaw, true);
$avatarUrl = '';

if (!empty($thumbData['data'][0]['imageUrl'])) {
    $avatarUrl = $thumbData['data'][0]['imageUrl'];
}

echo json_encode([
    'found'       => true,
    'userId'      => $userId,
    'username'    => $userName,
    'displayName' => $displayName,
    'avatarUrl'   => $avatarUrl,
]);
