<?php
require __DIR__.'/security_headers.php';
header('Content-Type: application/json');
require __DIR__.'/token_util.php';

require_bearer_token();

// 14 Rotterdam areas
$areas = [
  'Centrum','Charlois','Delfshaven','Feijenoord','Hillegersberg-Schiebroek','Hoek van Holland',
  'Hoogvliet','IJsselmonde','Kralingen-Crooswijk','Noord','Overschie','Pernis','Prins Alexander','Rozenburg'
];

// Aggregate last 14 days to compute trend
$q = $pdo->prepare("
  SELECT geohash,
         SUM(created_at >= (NOW() - INTERVAL 7 DAY)) as last7,
         SUM(created_at < (NOW() - INTERVAL 7 DAY) AND created_at >= (NOW() - INTERVAL 14 DAY)) as prev7
  FROM reports
  WHERE report_type='report' AND created_at >= (NOW() - INTERVAL 14 DAY)
  GROUP BY geohash
");
$q->execute();
$rows = $q->fetchAll();

function area_from_geohash($gh, $areas){
  $i = ord(substr($gh,0,1)) % max(1,count($areas));
  return $areas[$i];
}

$agg = [];
foreach($areas as $a){ $agg[$a] = ['area'=>$a,'last7'=>0,'prev7'=>0]; }

foreach($rows as $r){
  $area = area_from_geohash($r['geohash'], $areas);
  $agg[$area]['last7'] += intval($r['last7']);
  $agg[$area]['prev7'] += intval($r['prev7']);
}

$out = array_values($agg);
foreach($out as &$a){
  $delta = $a['last7'] - $a['prev7'];
  $a['delta'] = $delta;
  if($a['prev7'] == 0 && $a['last7'] > 0) $a['trend'] = 'up';
  else if($delta >= 6) $a['trend'] = 'upup';
  else if($delta >= 1) $a['trend'] = 'up';
  else $a['trend'] = 'flat';
}
unset($a);

echo json_encode(['municipality'=>'Rotterdam','window_days'=>7,'areas'=>$out]);