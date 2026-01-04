<?php
require __DIR__.'/_guard.php';
require_once __DIR__ . '/../../api/db.php';

// Only superadmin can manage
$stmt = $pdo->prepare("SELECT is_superadmin FROM admin_users WHERE id=?");
$stmt->execute([$_SESSION['admin_user_id'] ?? 0]);
$row = $stmt->fetch();
if(!$row || intval($row['is_superadmin']) !== 1){
  http_response_code(403);
  echo "Forbidden";
  exit;
}

// Create user
if($_SERVER['REQUEST_METHOD']==='POST'){
  $action = $_POST['action'] ?? '';
  if($action==='create_user'){
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $isSuper = isset($_POST['is_superadmin']) ? 1 : 0;
    if($username && $password){
      $hash = password_hash($password, PASSWORD_DEFAULT);
      $s = $pdo->prepare("INSERT INTO admin_users(username,password_hash,is_superadmin,is_active,created_at) VALUES(?,?,?,1,NOW())");
      $s->execute([$username,$hash,$isSuper]);
    }
  }
  if($action==='grant'){
    $userId = intval($_POST['user_id'] ?? 0);
    $type = $_POST['scope_type'] ?? '';
    $code = $_POST['scope_code'] ?? '';
    if($userId && $type && $code){
      $g = $pdo->prepare("INSERT IGNORE INTO admin_scope_permissions(admin_user_id, scope_type, scope_code) VALUES(?,?,?)");
      $g->execute([$userId,$type,$code]);
    }
  }
  header('Location: users.php');
  exit;
}

$users = $pdo->query("SELECT id, username, is_superadmin, is_active FROM admin_users ORDER BY id DESC")->fetchAll();
$scopes = $pdo->query("SELECT scope_type, scope_code, scope_name FROM scopes ORDER BY scope_type, scope_name")->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>S4W Admin • Users</title>
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
  <h1>Users & Permissions</h1>

  <div class="card no-print">
    <h2>Create admin user</h2>
    <form method="post" style="display:flex; gap:10px; flex-wrap:wrap; align-items:end">
      <input type="hidden" name="action" value="create_user"/>
      <div class="field"><label>Username</label><input name="username" required/></div>
      <div class="field"><label>Password</label><input type="password" name="password" required/></div>
      <label style="display:flex; gap:6px; align-items:center"><input type="checkbox" name="is_superadmin"/> Superadmin</label>
      <button class="btn" type="submit">Create</button>
    </form>
  </div>

  <div class="card no-print">
    <h2>Grant dashboard access</h2>
    <form method="post" style="display:flex; gap:10px; flex-wrap:wrap; align-items:end">
      <input type="hidden" name="action" value="grant"/>
      <div class="field"><label>User</label>
        <select name="user_id">
          <?php foreach($users as $u): ?>
            <option value="<?php echo intval($u['id']); ?>"><?php echo htmlspecialchars($u['username']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label>Scope</label>
        <select name="scope_type">
          <?php foreach(array_unique(array_map(fn($s)=>$s['scope_type'],$scopes)) as $t): ?>
            <option value="<?php echo htmlspecialchars($t); ?>"><?php echo htmlspecialchars($t); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label>Code</label>
        <select name="scope_code">
          <?php foreach($scopes as $s): ?>
            <option value="<?php echo htmlspecialchars($s['scope_code']); ?>"><?php echo htmlspecialchars($s['scope_code'].' — '.$s['scope_name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn" type="submit">Grant</button>
    </form>
    <p class="muted">Superadmins can see all dashboards automatically.</p>
  </div>

  <div class="card">
    <h2>Existing admin users</h2>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>ID</th><th>User</th><th>Super</th><th>Active</th></tr></thead>
        <tbody>
        <?php foreach($users as $u): ?>
          <tr>
            <td><?php echo intval($u['id']); ?></td>
            <td><b><?php echo htmlspecialchars($u['username']); ?></b></td>
            <td><?php echo intval($u['is_superadmin']) ? 'yes':'no'; ?></td>
            <td><?php echo intval($u['is_active']) ? 'yes':'no'; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
</body>
</html>