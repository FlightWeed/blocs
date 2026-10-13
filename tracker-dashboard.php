<?php
define('DASHBOARD_PASSWORD', 'tebet123!'); // ← Change this!

session_start();

$CLICK_FILE     = __DIR__ . '/shein_clicks.json';
$VERIFY_FILE    = __DIR__ . '/shein_verifies.json';
$HEARTBEAT_FILE = __DIR__ . '/active_users.json';

if (isset($_POST['pwd'])) {
    if ($_POST['pwd'] === DASHBOARD_PASSWORD) $_SESSION['auth'] = true;
    else $authError = true;
}
if (isset($_GET['logout'])) { session_destroy(); header('Location: tracker-dashboard.php'); exit; }

if (!isset($_SESSION['auth'])) { ?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Tracker Login</title>
<style>
  body{font-family:system-ui,sans-serif;background:#0a0a0f;color:#f0f0f5;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
  .box{background:#16161f;border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:40px;width:340px}
  h2{margin:0 0 24px;font-size:22px}
  input[type=password]{width:100%;padding:12px 16px;background:#1c1c28;border:1px solid rgba(255,255,255,.1);border-radius:10px;color:#f0f0f5;font-size:15px;box-sizing:border-box;outline:none}
  button{margin-top:14px;width:100%;padding:13px;background:#00b06f;border:none;border-radius:10px;color:#fff;font-size:15px;font-weight:700;cursor:pointer}
  button:hover{background:#009960}
  .err{color:#ff6b6b;font-size:13px;margin-top:10px}
</style></head><body>
<div class="box">
  <h2>🔐 Tracker Dashboard</h2>
  <form method="post">
    <input type="password" name="pwd" placeholder="Enter password" autofocus>
    <button type="submit">Login</button>
    <?php if (isset($authError)): ?><div class="err">Wrong password.</div><?php endif; ?>
  </form>
</div></body></html>
<?php exit; }

// ── Load raw data ─────────────────────────────────────────────
$clicks  = [];
$verifies = [];
if (file_exists($CLICK_FILE))  $clicks  = json_decode(file_get_contents($CLICK_FILE),  true) ?: [];
if (file_exists($VERIFY_FILE)) $verifies = json_decode(file_get_contents($VERIFY_FILE), true) ?: [];

// Active users
$activeCount = 0;
if (file_exists($HEARTBEAT_FILE)) {
    $beats = json_decode(file_get_contents($HEARTBEAT_FILE), true) ?: [];
    $activeCount = count(array_filter($beats, fn($t) => $t >= time() - 30));
}

// Clear logs
if (isset($_GET['clear'])) {
    if ($_GET['clear'] === 'clicks')  { file_put_contents($CLICK_FILE,  json_encode([])); }
    if ($_GET['clear'] === 'verifies'){ file_put_contents($VERIFY_FILE, json_encode([])); }
    if ($_GET['clear'] === 'all')     { file_put_contents($CLICK_FILE, json_encode([])); file_put_contents($VERIFY_FILE, json_encode([])); }
    header('Location: tracker-dashboard.php'); exit;
}

// ── Time window helper ────────────────────────────────────────
$windows = [
    '1min'     => ['label' => '1 Min',    'seconds' => 60],
    '5min'     => ['label' => '5 Min',    'seconds' => 300],
    '30min'    => ['label' => '30 Min',   'seconds' => 1800],
    '1hour'    => ['label' => '1 Hour',   'seconds' => 3600],
    '24hours'  => ['label' => '24 Hours', 'seconds' => 86400],
    'lifetime' => ['label' => 'Lifetime', 'seconds' => null],
];

$window = isset($_GET['w']) && array_key_exists($_GET['w'], $windows) ? $_GET['w'] : 'lifetime';
$now    = time();

function filterByWindow($rows, $window, $windows, $now) {
    if ($windows[$window]['seconds'] === null) return array_values($rows);
    $cutoff = $now - $windows[$window]['seconds'];
    return array_values(array_filter($rows, fn($r) => isset($r['timestamp']) && $r['timestamp'] >= $cutoff));
}

// Filtered sets for current window
$wClicks   = filterByWindow($clicks,   $window, $windows, $now);
$wVerifies = filterByWindow($verifies, $window, $windows, $now);

// Click store breakdown
$wAppstore  = count(array_filter($wClicks, fn($r) => $r['store'] === 'appstore'));
$wPlaystore = count(array_filter($wClicks, fn($r) => $r['store'] === 'playstore'));
$wUnknown   = count($wClicks) - $wAppstore - $wPlaystore;

// Store filter for clicks table
$storeFilter = $_GET['store'] ?? 'all';
$displayedClicks = $wClicks;
if (in_array($storeFilter, ['appstore','playstore','unknown'])) {
    $displayedClicks = array_values(array_filter($wClicks, fn($r) => $r['store'] === $storeFilter));
}

// Pagination — clicks
$perPage   = 50;
$clickPage = max(1, intval($_GET['cp'] ?? 1));
$clickPages = max(1, ceil(count($displayedClicks) / $perPage));
$pagedClicks = array_slice($displayedClicks, ($clickPage-1)*$perPage, $perPage);

// Pagination — verifies
$verifyPage  = max(1, intval($_GET['vp'] ?? 1));
$verifyPages = max(1, ceil(count($wVerifies) / $perPage));
$pagedVerifies = array_slice($wVerifies, ($verifyPage-1)*$perPage, $perPage);

// Pre-compute verify counts for all windows (for the counter row)
$verifyCounts = [];
foreach ($windows as $key => $w) {
    $verifyCounts[$key] = count(filterByWindow($verifies, $key, $windows, $now));
}
$clickCounts = [];
foreach ($windows as $key => $w) {
    $clickCounts[$key] = count(filterByWindow($clicks, $key, $windows, $now));
}
?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>SHEIN Click Tracker</title>
<style>
  *{margin:0;padding:0;box-sizing:border-box}
  body{font-family:system-ui,sans-serif;background:#0a0a0f;color:#f0f0f5;min-height:100vh;padding:32px 24px;max-width:1100px;margin:0 auto}
  h1{font-size:24px;margin-bottom:4px}
  .sub{color:#9898a6;font-size:13px;margin-bottom:24px}
  /* live bar */
  .live-bar{display:flex;align-items:center;gap:14px;background:#16161f;border:1px solid rgba(0,176,111,.25);border-radius:14px;padding:16px 24px;margin-bottom:24px}
  .live-dot{width:11px;height:11px;border-radius:50%;background:#00b06f;flex-shrink:0;box-shadow:0 0 0 0 rgba(0,176,111,.6);animation:pulse 1.8s ease infinite}
  @keyframes pulse{0%{box-shadow:0 0 0 0 rgba(0,176,111,.6)}70%{box-shadow:0 0 0 10px rgba(0,176,111,0)}100%{box-shadow:0 0 0 0 rgba(0,176,111,0)}}
  .live-count{font-size:28px;font-weight:800;color:#00b06f;line-height:1}
  .live-label{color:#9898a6;font-size:13px;margin-top:3px}
  /* window tabs */
  .section-label{color:#9898a6;font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px}
  .window-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:28px}
  .wtab{padding:8px 18px;border-radius:8px;border:1px solid rgba(255,255,255,.1);background:transparent;color:#9898a6;cursor:pointer;font-size:13px;text-decoration:none;transition:all .15s}
  .wtab:hover{border-color:rgba(255,255,255,.25);color:#f0f0f5}
  .wtab.active{background:#e2231a;border-color:#e2231a;color:#fff;font-weight:700}
  /* two panel layout */
  .panels{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:28px}
  @media(max-width:780px){.panels{grid-template-columns:1fr}}
  /* counter cards */
  .panel-card{background:#16161f;border:1px solid rgba(255,255,255,.08);border-radius:16px;overflow:hidden}
  .panel-head{padding:18px 22px;border-bottom:1px solid rgba(255,255,255,.06);display:flex;align-items:center;justify-content:space-between}
  .panel-head h3{font-size:15px;font-weight:700}
  .panel-head .clr{font-size:12px;color:#ff6b6b;text-decoration:none;padding:4px 10px;border:1px solid rgba(226,35,26,.3);border-radius:6px}
  .panel-head .clr:hover{background:#e2231a;color:#fff;border-color:#e2231a}
  .counter-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:1px;background:rgba(255,255,255,.06)}
  .counter-cell{background:#16161f;padding:16px 14px;text-align:center}
  .counter-cell .cnum{font-size:24px;font-weight:800;line-height:1;margin-bottom:4px}
  .counter-cell .clabel{font-size:11px;color:#9898a6;text-transform:uppercase;letter-spacing:.5px}
  .pending .cnum{color:#ffc940}
  .verify-panel .cnum{color:#00b06f}
  /* table section */
  .table-section{background:#16161f;border:1px solid rgba(255,255,255,.08);border-radius:16px;overflow:hidden;margin-bottom:20px}
  .table-head{padding:18px 22px;border-bottom:1px solid rgba(255,255,255,.06);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px}
  .table-head h3{font-size:15px;font-weight:700}
  .filters{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
  .filter-btn{padding:6px 14px;border-radius:7px;border:1px solid rgba(255,255,255,.1);background:transparent;color:#9898a6;cursor:pointer;font-size:12px;text-decoration:none;transition:all .15s}
  .filter-btn:hover{border-color:rgba(255,255,255,.25);color:#f0f0f5}
  .filter-btn.active{background:#00b06f;border-color:#00b06f;color:#fff;font-weight:700}
  table{width:100%;border-collapse:collapse}
  th{padding:12px 18px;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.8px;color:#9898a6;border-bottom:1px solid rgba(255,255,255,.06)}
  td{padding:13px 18px;font-size:13px;border-bottom:1px solid rgba(255,255,255,.04)}
  tr:last-child td{border-bottom:none}
  tr:hover td{background:rgba(255,255,255,.02)}
  .badge{display:inline-block;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700}
  .badge.appstore{background:rgba(0,162,255,.15);color:#00a2ff}
  .badge.playstore{background:rgba(0,176,111,.15);color:#00b06f}
  .badge.unknown{background:rgba(255,255,255,.08);color:#9898a6}
  .badge.pending{background:rgba(255,201,64,.13);color:#ffc940}
  .badge.verified{background:rgba(0,176,111,.15);color:#00b06f}
  .empty{text-align:center;padding:40px;color:#9898a6;font-size:13px}
  .pagination{display:flex;gap:6px;padding:14px 18px;flex-wrap:wrap}
  .pagination a{padding:6px 12px;border-radius:7px;border:1px solid rgba(255,255,255,.1);color:#f0f0f5;text-decoration:none;font-size:12px}
  .pagination a.active,.pagination a:hover{background:#00b06f;border-color:#00b06f;color:#fff}
  .top-bar{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;flex-wrap:wrap;gap:10px}
  .logout{font-size:13px;color:#9898a6;text-decoration:none;padding:6px 14px;border:1px solid rgba(255,255,255,.1);border-radius:8px}
  .logout:hover{color:#f0f0f5}
</style>
</head><body>

<div class="top-bar">
  <div><h1>📊 SHEIN Click Tracker</h1><div class="sub">Live stats, pending downloads &amp; verify attempts</div></div>
  <a href="?logout=1" class="logout">Logout</a>
</div>

<!-- Live visitors -->
<div class="live-bar">
  <div class="live-dot"></div>
  <div>
    <div class="live-count" id="live-count"><?= $activeCount ?></div>
    <div class="live-label">people on the site right now</div>
  </div>
</div>

<!-- Time window -->
<div class="section-label">Time window</div>
<div class="window-tabs">
  <?php foreach ($windows as $key => $w): ?>
    <a href="?w=<?= $key ?>&store=<?= $storeFilter ?>" class="wtab <?= $window===$key?'active':'' ?>"><?= $w['label'] ?></a>
  <?php endforeach; ?>
</div>

<!-- Two counter panels -->
<div class="panels">

  <!-- Pending (tap to download) -->
  <div class="panel-card pending">
    <div class="panel-head">
      <h3>⏳ Tap to Download — Pending</h3>
      <a href="?clear=clicks&w=<?= $window ?>" class="clr" onclick="return confirm('Clear all download click logs?')">Clear</a>
    </div>
    <div class="counter-grid">
      <?php foreach ($windows as $key => $w): ?>
      <div class="counter-cell pending">
        <div class="cnum"><?= $clickCounts[$key] ?></div>
        <div class="clabel"><?= $w['label'] ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Verify & Claim -->
  <div class="panel-card verify-panel">
    <div class="panel-head">
      <h3>✅ Verify &amp; Claim — Attempts</h3>
      <a href="?clear=verifies&w=<?= $window ?>" class="clr" onclick="return confirm('Clear all verify click logs?')">Clear</a>
    </div>
    <div class="counter-grid">
      <?php foreach ($windows as $key => $w): ?>
      <div class="counter-cell verify-panel">
        <div class="cnum"><?= $verifyCounts[$key] ?></div>
        <div class="clabel"><?= $w['label'] ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

</div>

<!-- Pending downloads table -->
<div class="table-section">
  <div class="table-head">
    <h3>⏳ Pending Downloads <span style="color:#9898a6;font-weight:400;font-size:13px">(<?= count($displayedClicks) ?> in window)</span></h3>
    <div class="filters">
      <a href="?w=<?= $window ?>&store=all" class="filter-btn <?= $storeFilter==='all'?'active':'' ?>">All (<?= count($wClicks) ?>)</a>
      <a href="?w=<?= $window ?>&store=appstore" class="filter-btn <?= $storeFilter==='appstore'?'active':'' ?>">🍎 iOS (<?= $wAppstore ?>)</a>
      <a href="?w=<?= $window ?>&store=playstore" class="filter-btn <?= $storeFilter==='playstore'?'active':'' ?>">🤖 Android (<?= $wPlaystore ?>)</a>
      <a href="?w=<?= $window ?>&store=unknown" class="filter-btn <?= $storeFilter==='unknown'?'active':'' ?>">🖥 Unknown (<?= $wUnknown ?>)</a>
    </div>
  </div>
  <?php if (empty($pagedClicks)): ?>
    <div class="empty">No download clicks in this time window.</div>
  <?php else: ?>
  <table>
    <thead><tr><th>#</th><th>Date &amp; Time</th><th>Store</th><th>Status</th></tr></thead>
    <tbody>
      <?php foreach ($pagedClicks as $i => $row): ?>
      <tr>
        <td style="color:#9898a6"><?= ($clickPage-1)*$perPage + $i + 1 ?></td>
        <td><?= htmlspecialchars($row['time']) ?></td>
        <td><span class="badge <?= htmlspecialchars($row['store']) ?>">
          <?= $row['store']==='appstore' ? '🍎 App Store' : ($row['store']==='playstore' ? '🤖 Play Store' : '🖥 Unknown') ?>
        </span></td>
        <td><span class="badge pending">⏳ Pending</span></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php if ($clickPages > 1): ?>
  <div class="pagination">
    <?php for ($p=1;$p<=$clickPages;$p++): ?>
      <a href="?w=<?= $window ?>&store=<?= $storeFilter ?>&cp=<?= $p ?>" class="<?= $p===$clickPage?'active':'' ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>

<!-- Verify & Claim table -->
<div class="table-section">
  <div class="table-head">
    <h3>✅ Verify &amp; Claim Attempts <span style="color:#9898a6;font-weight:400;font-size:13px">(<?= count($wVerifies) ?> in window)</span></h3>
  </div>
  <?php if (empty($pagedVerifies)): ?>
    <div class="empty">No verify attempts in this time window.</div>
  <?php else: ?>
  <table>
    <thead><tr><th>#</th><th>Date &amp; Time</th><th>Status</th></tr></thead>
    <tbody>
      <?php foreach ($pagedVerifies as $i => $row): ?>
      <tr>
        <td style="color:#9898a6"><?= ($verifyPage-1)*$perPage + $i + 1 ?></td>
        <td><?= htmlspecialchars($row['time']) ?></td>
        <td><span class="badge verified">✅ Claimed</span></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php if ($verifyPages > 1): ?>
  <div class="pagination">
    <?php for ($p=1;$p<=$verifyPages;$p++): ?>
      <a href="?w=<?= $window ?>&store=<?= $storeFilter ?>&vp=<?= $p ?>" class="<?= $p===$verifyPage?'active':'' ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>

<script>
function refreshActive() {
  fetch('track_active.php').then(r=>r.json()).then(d=>{
    if(typeof d.active!=='undefined') document.getElementById('live-count').textContent=d.active;
  }).catch(()=>{});
}
setInterval(refreshActive, 10000);
</script>
</body></html>
