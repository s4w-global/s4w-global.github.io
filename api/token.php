<?php
require __DIR__.'/security_headers.php';
header('Content-Type: application/json');
require __DIR__.'/token_util.php';
require __DIR__.'/session_util.php';
$config = require __DIR__.'/config.php';

// One-time POC entrypoint: ?poc=CODE creates a short-lived session cookie (session cookie)
if(isset($_GET['poc']) && $_GET['poc'] !== ''){
  $sid = create_session_from_poc($_GET['poc']);
  if(!$sid){
    http_response_code(403);
    echo json_encode(['error'=>'invalid_poc']);
    exit;
  }
  set_session_cookie($sid);
}

// Optional: end session (clears cookie + invalidates server session)
if(isset($_GET['end'])){
  $sid = $_COOKIE['s4w_sess'] ?? '';
  if($sid){
    $h = hash_session($sid);
    try{
      $stmt = $pdo->prepare("UPDATE sessions SET is_active=0 WHERE session_hash=?");
      $stmt->execute([$h]);
    }catch(Exception $e){}
  }
  clear_session_cookie();
  echo json_encode(['status'=>'ended']);
  exit;
}

// Gate access (session cookie in poc-mode, open in public-mode)
require_access_for_token();

// Simple rate limiting (per-IP, per-minute) to avoid token farming
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$bucket = date('Y-m-d H:i'); // minute bucket
$hashKey = hash('sha256', $ip.'|'.$bucket);

try{
  $stmt = $pdo->prepare("INSERT INTO rate_limits(hash_key,last_seen,counter) VALUES(?,NOW(),1)
    ON DUPLICATE KEY UPDATE counter=counter+1,last_seen=NOW()");
  $stmt->execute([$hashKey]);
  $c = $pdo->prepare("SELECT counter FROM rate_limits WHERE hash_key=? LIMIT 1");
  $c->execute([$hashKey]);
  $row = $c->fetch();
  if($row && intval($row['counter']) > 60){
    http_response_code(429);
    echo json_encode(['error'=>'rate_limited']);
    exit;
  }
}catch(Exception $e){
  // ignore RL failures in MVP
}

$token = bin2hex(random_bytes(24)); // 48 chars
$hash = hash_token($token);
$exp = date('Y-m-d H:i:s', time() + intval($config['token_ttl']));

$stmt = $pdo->prepare("INSERT INTO tokens (token_hash, expires_at, created_at) VALUES (?,?,NOW())");
$stmt->execute([$hash, $exp]);

echo json_encode(['token'=>$token,'expires_at'=>$exp]);