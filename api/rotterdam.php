<?php
header('Content-Type: application/json');
require __DIR__.'/db.php';
require __DIR__.'/auth.php';

$keyRow = require_api_key();
require_scope_municipality($keyRow, 'rotterdam');

$mode = $_GET['mode'] ?? 'areas'; // areas|trend
$days = 7;

if($mode === 'trend'){
  // 7-day trend for Rotterdam overall
  $stmt = $pdo->prepare("
    SELECT DATE(created_at) as d, COUNT(*) as cnt
    FROM reports
    WHERE municipality_code='rotterdam' AND created_at >= (NOW() - INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY d ASC
  ");
  $stmt->execute();
  echo json_encode(['scope'=>'rotterdam','mode'=>'trend','days'=>7,'data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
  exit;
}

// Areas: last 7d vs previous 7d (trend badges)
$stmt = $pdo->prepare("
  SELECT
    COALESCE(area_name,'(Onbekend)') as area,
    SUM(created_at >= (NOW() - INTERVAL 7 DAY)) as last7,
    SUM(created_at < (NOW() - INTERVAL 7 DAY) AND created_at >= (NOW() - INTERVAL 14 DAY)) as prev7
  FROM reports
  WHERE municipality_code='rotterdam' AND created_at >= (NOW() - INTERVAL 14 DAY)
  GROUP BY COALESCE(area_name,'(Onbekend)')
  ORDER BY last7 DESC
");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

function trend_class($last7, $prev7){
  $last7=intval($last7); $prev7=intval($prev7);
  if($prev7===0 && $last7===0) return 'stable';
  if($prev7===0 && $last7>0) return 'strongup';
  $delta = ($last7-$prev7)/max(1,$prev7);
  if($delta >= 0.5) return 'strongup';
  if($delta >= 0.15) return 'up';
  if($delta <= -0.15) return 'down';
  return 'stable';
}

$out=[];
foreach($rows as $r){
  $out[]=[
    'name'=>$r['area'],
    'reports'=>intval($r['last7']),
    'trend'=>trend_class($r['last7'],$r['prev7'])
  ];
}

echo json_encode([
  'scope'=>'rotterdam',
  'label'=>$keyRow['label'] ?? null,
  'mode'=>'areas',
  'data'=>$out
]);
