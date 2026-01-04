<?php
session_start();
$err = '';
if($_SERVER['REQUEST_METHOD']==='POST'){
  require_once __DIR__ . '/../../api/db.php';
  $u = $_POST['username'] ?? '';
  $p = $_POST['password'] ?? '';
  $stmt = $pdo->prepare("SELECT id, password_hash FROM admin_users WHERE username=? AND is_active=1 LIMIT 1");
  $stmt->execute([$u]);
  $row = $stmt->fetch();
  if($row && password_verify($p, $row['password_hash'])){
    $_SESSION['s4w_admin'] = true;
    $_SESSION['admin_user_id'] = intval($row['id']);
    header('Location: dashboard.php');
    exit;
  }
  $err = 'Invalid login';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>S4W Admin Login</title>
  <link rel="stylesheet" href="/assets/css/style.css"/>
</head>
<body>
  <main class="wrap" style="max-width:560px">
    <h1>S4W Admin</h1>
    <p class="muted">Restricted area.</p>
    <?php if($err): ?><div class="card" style="border-color:#7c2d12"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>
    <form class="card" method="post">
      <div class="field">
        <label>Username</label>
        <input name="username" required />
      </div>
      <div class="field">
        <label>Password</label>
        <input type="password" name="password" required />
      </div>
      <button class="btn" type="submit">Login</button>
    </form>
  </main>
</body>
</html>