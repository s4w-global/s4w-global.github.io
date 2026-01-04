<?php
require __DIR__.'/security_headers.php';
header('Content-Type: application/json');
require __DIR__.'/token_util.php';
require __DIR__.'/geohash.php';
require __DIR__.'/turnstile.php';
$config = require __DIR__.'/config.php';

require_bearer_token();

$input = json_decode(file_get_contents('php://input'), true);
$lat = $input['lat'] ?? null;
$lng = $input['lng'] ?? null;
$type = $input['type'] ?? 'report';
$cat = $input['category'] ?? 'unspecified';
$tsToken = $input['turnstile_token'] ?? '';

$env = $config['env'] ?? 'poc';
$ts = turnstile_profile();
$protectReports = !empty($ts['protect_reports']);
$protectPanic = !empty($ts['protect_panic']);
$failurePolicy = $ts['failure_policy'] ?? 'fail_closed';

// Enforce Turnstile based on action + profile
if(!empty($ts['enabled'])){
  $needs = ($type==='report' && $protectReports) || ($type==='panic' && $protectPanic);
  if($needs){
    $err = null;
    $ok = verify_turnstile($tsToken, $err);
    if(!$ok){
      // failure_policy controls behavior on network/provider issues
      if(in_array($err, ['network','parse'], true) && $failurePolicy==='fail_open'){
        // allow, but log
        try{
          $stmt = $pdo->prepare("INSERT INTO audit_log(event_type, event_meta, created_at) VALUES('turnstile_fail_open', ?, NOW())");
          $stmt->execute([json_encode(['err'=>$err,'env'=>$env,'type'=>$type])]);
        }catch(Exception $e){}
      } else {
        // block
        try{
          $stmt = $pdo->prepare("INSERT INTO audit_log(event_type, event_meta, created_at) VALUES('turnstile_block', ?, NOW())");
          $stmt->execute([json_encode(['err'=>$err,'env'=>$env,'type'=>$type])]);
        }catch(Exception $e){}
        http_response_code(403);
        echo json_encode(['error'=>'turnstile_failed','reason'=>$err]);
        exit;
      }
    }
  }
}

if(!is_numeric($lat) || !is_numeric($lng)){
  http_response_code(400);
  echo json_encode(['error'=>'invalid_location']);
  exit;
}

$lat = floatval($lat); $lng = floatval($lng);
if(abs($lat) > 90 || abs($lng) > 180){
  http_response_code(400);
  echo json_encode(['error'=>'invalid_location']);
  exit;
}

$level = $type==='panic' ? $config['masking']['panic_geohash'] : $config['masking']['report_geohash'];
$gh = geohash_encode($lat, $lng, $level);
$exp = null;

if($type==='panic'){
  $exp = date('Y-m-d H:i:s', time()+($config['masking']['panic_ttl_min']*60));
}

// Optional: store area_name from rotterdam_areas (MVP)
$area = null;
try{
  $areas = $pdo->query("SELECT area_name FROM rotterdam_areas")->fetchAll();
  if($areas){
    $i = ord(substr($gh,0,1)) % count($areas);
    $area = $areas[$i]['area_name'];
  }
}catch(Exception $e){}

$stmt = $pdo->prepare("INSERT INTO reports (geohash, grid_level, area_name, category, report_type, expires_at)
VALUES (?,?,?,?,?,?)");
$stmt->execute([$gh,$level,$area,$cat,$type,$exp]);

echo json_encode(['status'=>'ok']);