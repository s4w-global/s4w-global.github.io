<?php
require __DIR__.'/_guard.php';
require_once __DIR__ . '/../../api/db.php';

// Load current admin username
$me = $pdo->query("SELECT username FROM admin_users WHERE username='admin' LIMIT 1")->fetch();

// Determine access: superadmin if flag in admin_users
$u = $pdo->prepare("SELECT id, username, is_superadmin FROM admin_users WHERE id = ? LIMIT 1");
$u->execute([$_SESSION['admin_user_id'] ?? 0]);
$user = $u->fetch();
$isSuper = $user && intval($user['is_superadmin']) === 1;

$dash = [];
if($isSuper){
  $dash = $pdo->query("SELECT scope_type, scope_code, scope_name, dashboard_path FROM scopes ORDER BY scope_type, scope_name")->fetchAll();
} else {
  $stmt = $pdo->prepare("
    SELECT s.scope_type, s.scope_code, s.scope_name, s.dashboard_path
    FROM admin_scope_permissions p
    JOIN scopes s ON s.scope_type=p.scope_type AND s.scope_code=p.scope_code
    WHERE p.admin_user_id=?
    ORDER BY s.scope_type, s.scope_name
  ");
  $stmt->execute([$user['id'] ?? 0]);
  $dash = $stmt->fetchAll();
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>S4W Admin • Dashboards</title>
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
  <h1>Dashboards</h1>
  <p class="muted">Not linked from public pages. Visible here only based on permissions.</p>

  <div class="card">
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Scope</th><th>Name</th><th>Path</th></tr></thead>
        <tbody>
        <?php foreach($dash as $d): ?>
          <tr>
            <td><span class="badge"><?php echo htmlspecialchars($d['scope_type']); ?></span></td>
            <td><b><?php echo htmlspecialchars($d['scope_name']); ?></b></td>
            <td><a href="<?php echo htmlspecialchars($d['dashboard_path']); ?>"><?php echo htmlspecialchars($d['dashboard_path']); ?></a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
</body>
</html>