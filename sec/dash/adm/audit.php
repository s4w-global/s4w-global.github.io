<?php
require __DIR__.'/_guard.php';
require_once __DIR__ . '/../../api/db.php';

$rows = $pdo->query("SELECT event_type, event_meta, created_at FROM audit_log ORDER BY id DESC LIMIT 200")->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>S4W Admin • Audit</title>
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
  <h1>Audit log</h1>
  <p class="muted">Last 200 events.</p>
  <div class="card">
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Time</th><th>Type</th><th>Meta</th></tr></thead>
        <tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <td class="muted"><?php echo htmlspecialchars($r['created_at']); ?></td>
            <td><b><?php echo htmlspecialchars($r['event_type']); ?></b></td>
            <td class="muted"><code style="white-space:pre-wrap"><?php echo htmlspecialchars($r['event_meta']); ?></code></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
</body>
</html>