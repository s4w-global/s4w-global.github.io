<?php
require __DIR__.'/_guard.php';
require_once __DIR__ . '/../../api/db.php';

// Only superadmin can manage pilot users & sessions in this MVP
$stmt = $pdo->prepare("SELECT is_superadmin FROM admin_users WHERE id=?");
$stmt->execute([$_SESSION['admin_user_id'] ?? 0]);
$row = $stmt->fetch();
if(!$row || intval($row['is_superadmin']) !== 1){
  http_response_code(403);
  echo "Forbidden";
  exit;
}

function get_setting($key, $default){
  global $pdo;
  $s = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key=? LIMIT 1");
  $s->execute([$key]);
  $r = $s->fetch();
  return $r ? $r['setting_value'] : $default;
}

if($_SERVER['REQUEST_METHOD']==='POST'){
  $action = $_POST['action'] ?? '';

  if($action==='create_pilot'){
    $name = trim($_POST['display_name'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    if($name){
      $p = $pdo->prepare("INSERT INTO pilot_users(display_name, notes, is_active, created_at) VALUES(?,?,1,NOW())");
      $p->execute([$name, $notes ?: null]);
    }
  }

  if($action==='set_limits'){
    $msu = intval($_POST['max_sessions_per_user'] ?? 2);
    $msp = intval($_POST['max_sessions_per_poc'] ?? 1);
    if($msu < 1) $msu = 1; if($msu > 50) $msu = 50;
    if($msp < 1) $msp = 1; if($msp > 50) $msp = 50;

    $pdo->prepare("INSERT INTO settings(setting_key, setting_value) VALUES('max_sessions_per_user',?)
      ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)")->execute([strval($msu)]);

    $pdo->prepare("INSERT INTO settings(setting_key, setting_value) VALUES('max_sessions_per_poc',?)
      ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)")->execute([strval($msp)]);
  }

  if($action==='revoke_session'){
    $id = intval($_POST['session_id'] ?? 0);
    if($id){
      $pdo->prepare("UPDATE sessions SET is_active=0 WHERE id=?")->execute([$id]);
      try{
        $pdo->prepare("INSERT INTO audit_log(event_type,event_meta,created_at) VALUES('session_revoked', ?, NOW())")
          ->execute([json_encode(['session_id'=>$id])]);
      }catch(Exception $e){}
    }
  }

  header('Location: pilot.php');
  exit;
}

$pilots = $pdo->query("SELECT id, display_name, notes, is_active, created_at FROM pilot_users ORDER BY id DESC")->fetchAll();
$maxUser = get_setting('max_sessions_per_user','2');
$maxPoc  = get_setting('max_sessions_per_poc','1');

$sessions = $pdo->query("
  SELECT s.id, s.created_at, s.expires_at, s.is_active, p.display_name AS pilot_name, s.poc_id
  FROM sessions s
  LEFT JOIN pilot_users p ON p.id=s.pilot_user_id
  ORDER BY s.id DESC
  LIMIT 200
")->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>S4W Admin • Pilot Users</title>
  <link rel="stylesheet" href="/assets/css/style.css"/>
</head>
<body>
<header class="topbar no-print">
  <div class="wrap">
    <div class="brand">S4W Admin</div>
    <div class="spacer"></div>
    <a class="btn ghost" href="dashboard.php">Back</a>
    <a class="btn ghost" href="logout.php">Logout</a>
  </div>
</header>

<main class="wrap">
  <h1>Pilot Users & Devices</h1>
  <p class="muted">A “device” is represented by an active session (session-cookie). POC codes are never stored client-side.</p>

  <div class="card no-print">
    <h2>Limits</h2>
    <form method="post" style="display:flex; gap:10px; flex-wrap:wrap; align-items:end">
      <input type="hidden" name="action" value="set_limits"/>
      <div class="field">
        <label>Max active sessions per pilot user</label>
        <input name="max_sessions_per_user" type="number" min="1" max="50" value="<?php echo htmlspecialchars($maxUser); ?>"/>
      </div>
      <div class="field">
        <label>Max active sessions per POC code</label>
        <input name="max_sessions_per_poc" type="number" min="1" max="50" value="<?php echo htmlspecialchars($maxPoc); ?>"/>
      </div>
      <button class="btn" type="submit">Save</button>
    </form>
  </div>

  <div class="card no-print">
    <h2>Create pilot user</h2>
    <form method="post" style="display:flex; gap:10px; flex-wrap:wrap; align-items:end">
      <input type="hidden" name="action" value="create_pilot"/>
      <div class="field"><label>Name</label><input name="display_name" required/></div>
      <div class="field" style="min-width:320px"><label>Notes (optional)</label><input name="notes"/></div>
      <button class="btn" type="submit">Create</button>
    </form>
  </div>

  <div class="card">
    <h2>Pilot users</h2>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>ID</th><th>Name</th><th>Notes</th><th>Active</th><th>Created</th></tr></thead>
        <tbody>
        <?php foreach($pilots as $p): ?>
          <tr>
            <td><?php echo intval($p['id']); ?></td>
            <td><b><?php echo htmlspecialchars($p['display_name']); ?></b></td>
            <td class="muted"><?php echo htmlspecialchars($p['notes'] ?? ''); ?></td>
            <td><?php echo intval($p['is_active']) ? 'yes' : 'no'; ?></td>
            <td class="muted"><?php echo htmlspecialchars($p['created_at']); ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <h2>Recent sessions (devices)</h2>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>ID</th><th>Pilot</th><th>POC</th><th>Created</th><th>Expires</th><th>Active</th><th class="no-print">Action</th></tr></thead>
        <tbody>
        <?php foreach($sessions as $s): ?>
          <tr>
            <td><?php echo intval($s['id']); ?></td>
            <td><b><?php echo htmlspecialchars($s['pilot_name'] ?? '—'); ?></b></td>
            <td class="muted"><?php echo htmlspecialchars($s['poc_id'] ?? '—'); ?></td>
            <td class="muted"><?php echo htmlspecialchars($s['created_at']); ?></td>
            <td class="muted"><?php echo htmlspecialchars($s['expires_at']); ?></td>
            <td><?php echo intval($s['is_active']) ? 'yes' : 'no'; ?></td>
            <td class="no-print">
              <?php if(intval($s['is_active'])===1): ?>
                <form method="post" style="display:inline">
                  <input type="hidden" name="action" value="revoke_session"/>
                  <input type="hidden" name="session_id" value="<?php echo intval($s['id']); ?>"/>
                  <button class="btn danger" type="submit">Revoke</button>
                </form>
              <?php else: ?>
                <span class="muted">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
</body>
</html>