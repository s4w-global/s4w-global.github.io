<?php
require __DIR__.'/security_headers.php';
header('Content-Type: application/json');
require __DIR__.'/token_util.php';
require __DIR__.'/geohash.php';

require_bearer_token();

$since = $_GET['since'] ?? '7d';
$days = 7;
if(preg_match('/^(\d+)d$/', $since, $m)) $days = max(1, min(90, intval($m[1])));

$stmt = $pdo->prepare("
  SELECT geohash, grid_level, category, COUNT(*) AS cnt
  FROM reports
  WHERE report_type='report' AND created_at >= (NOW() - INTERVAL ? DAY)
  GROUP BY geohash, grid_level, category
");
$stmt->execute([$days]);
$rows = $stmt->fetchAll();

$out = [];
foreach($rows as $r){
  $gh = $r['geohash'];
  if(!isset($out[$gh])){
    $c = geohash_center($gh);
    $out[$gh] = [
      'geohash' => $gh,
      'grid_level' => intval($r['grid_level']),
      'lat' => $c['lat'],
      'lng' => $c['lng'],
      'count' => 0,
      'categories' => []
    ];
  }
  $out[$gh]['count'] += intval($r['cnt']);
  $out[$gh]['categories'][$r['category']] = intval($r['cnt']);
}

echo json_encode(array_values($out));