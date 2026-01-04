<?php
require __DIR__.'/db.php';
require __DIR__.'/session_util.php';
$config = require __DIR__.'/config.php';

function hash_token($token){
  global $config;
  return hash_hmac('sha256', $token, $config['app_secret']);
}

function require_bearer_token(){
  global $pdo;
  $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
  if(!preg_match('/^Bearer\s+(.*)$/i', $hdr, $m)){
    http_response_code(401);
    echo json_encode(['error'=>'missing_token']);
    exit;
  }
  $token = trim($m[1]);
  $h = hash_token($token);

  $stmt = $pdo->prepare("SELECT id, expires_at FROM tokens WHERE token_hash=? LIMIT 1");
  $stmt->execute([$h]);
  $row = $stmt->fetch();
  if(!$row){
    http_response_code(401);
    echo json_encode(['error'=>'invalid_token']);
    exit;
  }
  if(strtotime($row['expires_at']) < time()){
    http_response_code(401);
    echo json_encode(['error'=>'expired_token']);
    exit;
  }
  return true;
}

function require_poc_cookie(){
  global $pdo;
  $code = $_COOKIE['s4w_poc'] ?? '';
  if(!$code){ return false; }
  $h = hash('sha256', $code);
  $stmt = $pdo->prepare("SELECT id, expires_at FROM poc_codes WHERE code_hash=? AND is_active=1 LIMIT 1");
  $stmt->execute([$h]);
  $row = $stmt->fetch();
  if(!$row) return false;
  if($row['expires_at'] && strtotime($row['expires_at']) < time()) return false;
  return true;
}
function require_access_for_token(){
  global $config;
  $env = $config['env'] ?? 'poc';
  $modeMap = $config['access_mode'] ?? ['poc'=>'poc','prod'=>'public'];
  $mode = $modeMap[$env] ?? 'poc';

  if($mode === 'public'){
    return true; // production: handle anti-abuse in token.php
  }
  // poc mode: require a valid session cookie (not a POC code cookie)
  require_session_cookie();
  return true;
}

function require_session_cookie(){
  if(!require_session()){
    http_response_code(403);
    echo json_encode(['error'=>'session_required']);
    exit;
  }
  return true;
}
