<?php
require __DIR__.'/_guard.php';
require_once __DIR__ . '/../../api/db.php';

// Handle actions
if($_SERVER['REQUEST_METHOD']==='POST'){
  $action = $_POST['action'] ?? '';
  if($action === 'set_map_style'){
    $style = $_POST['map_style'] ?? 'dark';
    if(!in_array($style, ['dark','semi','normal'], true)) $style = 'dark';
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('map_style', ?)
      ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
    $stmt->execute([$style]);
  }
  if($action === 'set_max_sessions_per_poc'){
    $v = intval($_POST['max_sessions_per_poc'] ?? 1);
    if($v < 1) $v = 1; if($v > 50) $v = 50;
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('max_sessions_per_poc', ?)
      ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
    $stmt->execute([strval($v)]);
  }
  if($action === 'create_poc'){
    $code = bin2hex(random_bytes(8)); // 16 chars shareable
    $hash = hash('sha256', $code);
    $exp = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
    $useLimit = intval($_POST['use_limit'] ?? 1);
    if($useLimit < 1) $useLimit = 1; if($useLimit > 50) $useLimit = 50;
    $pilotUserId = !empty($_POST['pilot_user_id']) ? intval($_POST['pilot_user_id']) : null;
    $stmt = $pdo->prepare("INSERT INTO poc_codes (code_hash, is_active, created_at, expires_at, use_limit, used_count, pilot_user_id) VALUES (?,1,NOW(),?, ?, 0, ?)");
    $stmt->execute([$hash, $exp, $useLimit, $pilotUserId]);
    $_SESSION['new_poc_code'] = $code;
  }
  if($action === 'disable_poc'){
    $id = intval($_POST['id'] ?? 0);
    $stmt = $pdo->prepare("UPDATE poc_codes SET is_active=0 WHERE id=?");
    $stmt->execute([$id]);
  }
  header('Location: dashboard.php');
  exit;
}

// Load current map style
$style = 'dark';
$row = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='map_style'")->fetch();
if($row && $row['setting_value']) $style = $row['setting_value'];

// POC codes
$poc = $pdo->query("SELECT id, is_active, created_at, expires_at, use_limit, used_count FROM poc_codes c
      LEFT JOIN pilot_users p ON p.id=c.pilot_user_id ORDER BY id DESC LIMIT 20")->fetchAll();

// Rotterdam areas (simple count last 7 days)
$areas = $pdo->query("SELECT area_name, COUNT(*) as cnt
  FROM reports
  WHERE report_type='report' AND created_at >= (NOW() - INTERVAL 7 DAY)
  GROUP BY area_name
  ORDER BY cnt DESC")->fetchAll();

$newCode = $_SESSION['new_poc_code'] ?? null;
unset($_SESSION['new_poc_code']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>S4W Admin Dashboard</title>
  <link rel="stylesheet" href="/assets/css/style.css"/>
</head>
<body>
<header class="topbar no-print">
  <div class="wrap">
    <div class="brand">S4W Admin</div>
    <div class="spacer"></div>
    <a class="btn ghost" href="portals.php">Dashboards</a>
    <a class="btn ghost" href="users.php">Users</a>
    <a class="btn ghost" href="audit.php">Audit</a>
    <a class="btn ghost" href="pilot.php">Pilot</a>
    <a class="btn ghost" href="logout.php">Logout</a>
  </div>
</header>

<main class="wrap">
  <h1>Admin Dashboard</h1>
  <p class="muted">This portal is only accessible via <code>/sec/dash/adm/</code> and requires login.</p>

  <?php if($newCode): ?>
  <div class="card">
    <b>New POC access code created:</b>
    <div style="font-size:18px;margin-top:6px"><code><?php echo htmlspecialchars($newCode); ?></code></div>
    <div class="muted" style="margin-top:6px">Distribute as: <code>https://YOURDOMAIN/api/token.php?poc=<?php echo htmlspecialchars($newCode); ?></code></div>
  </div>
  <?php endif; ?>

  <div class="card">
    <div class="card-h">
      <h2>Map style (global)</h2>
      <div class="spacer"></div>
      <button class="btn no-print" onclick="window.print()">Export to PDF</button>
    </div>
    <form method="post" class="no-print" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap">
      <input type="hidden" name="action" value="set_map_style"/>
      <div class="field" style="min-width:220px">
        <label>Map style</label>
        <select name="map_style">
          <option value="dark" <?php if($style==='dark') echo 'selected'; ?>>Dark</option>
          <option value="semi" <?php if($style==='semi') echo 'selected'; ?>>Semi-dark</option>
          <option value="normal" <?php if($style==='normal') echo 'selected'; ?>>Normal</option>
        </select>
      </div>
      <button class="btn" type="submit">Save</button>
    </form>
    <p class="muted">Applies to Safety Map tiles (client fetches <code>/api/settings.php</code>).</p>
  

<div class="card no-print">
  <div class="card-h">
    <h2>POC Session Control</h2>
    <div class="spacer"></div>
  </div>
  <form method="post" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap">
    <input type="hidden" name="action" value="set_max_sessions_per_poc"/>
    <div class="field" style="min-width:260px">
      <label>Max concurrent sessions per POC code</label>
      <input name="max_sessions_per_poc" type="number" min="1" max="50" value="<?php
        $r = $pdo->query("SELECT setting_value FROM settings WHERE setting_key='max_sessions_per_poc'")->fetch();
        echo htmlspecialchars($r['setting_value'] ?? '1');
      ?>"/>
    </div>
    <button class="btn" type="submit">Save</button>
  </form>
  <p class="muted">Single-use default = 1. Increase only if you want the same POC code to be used on multiple devices.</p>
</div>
</div>

  <div class="card">
    <div class="card-h">
      <h2>POC Access Codes</h2>
      <div class="spacer"></div>
    </div>
    <form method="post" class="no-print" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap">
      <input type="hidden" name="action" value="create_poc"/>
      <div class="field">
        <label>Optional expiry (YYYY-MM-DD HH:MM:SS)</label>
        <input name="expires_at" placeholder="2026-01-31 23:59:59"/>
      </div>
      <div class="field" style="min-width:220px">
        <label>Use limit (single-use = 1)</label>
        <input name="use_limit" type="number" min="1" max="50" value="1"/>
      </div>
      <button class="btn" type="submit">Create code</button>
    </form>

    <div class="table-wrap" style="margin-top:12px">
      <table class="table">
        <thead><tr><th>ID</th><th>Active</th><th>Created</th><th>Expires</th><th>Use</th><th class="no-print">Action</th></tr></thead>
        <tbody>
        <?php foreach($poc as $c): ?>
          <tr>
            <td><?php echo intval($c['id']); ?></td>
            <td><?php echo $c['is_active'] ? 'yes' : 'no'; ?></td>
            <td class="muted"><?php echo htmlspecialchars($c['created_at']); ?></td>
            <td class="muted"><?php echo htmlspecialchars($c['expires_at'] ?? '—'); ?></td>
            <td class="muted"><?php echo intval($c['used_count']).' / '.intval($c['use_limit']); ?></td>
            <td class="no-print">
              <?php if($c['is_active']): ?>
              <form method="post" style="display:inline">
                <input type="hidden" name="action" value="disable_poc"/>
                <input type="hidden" name="id" value="<?php echo intval($c['id']); ?>"/>
                <button class="btn ghost" type="submit">Disable</button>
              </form>
              <?php else: ?>—<?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <h2>Rotterdam (last 7 days) — area totals</h2>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Area</th><th>Reports</th></tr></thead>
        <tbody>
          <?php foreach($areas as $a): ?>
            <tr><td><b><?php echo htmlspecialchars($a['area_name'] ?? 'Unknown'); ?></b></td><td><?php echo intval($a['cnt']); ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</main>
</body>
</html>