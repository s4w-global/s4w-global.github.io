<?php
require __DIR__.'/db.php';
$config = require __DIR__.'/config.php';

function new_session_id(){
  return bin2hex(random_bytes(24)); // 48 chars
}
function hash_session($sid){
  global $config;
  return hash_hmac('sha256', $sid, $config['app_secret']);
}

function set_session_cookie($sid){
  // Session cookie (no expires) => removed when browser closes (client)
  setcookie('s4w_sess', $sid, [
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax'
  ]);
}

function clear_session_cookie(){
  setcookie('s4w_sess', '', [
    'expires' => time() - 3600,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax'
  ]);
}

function setting_value($key, $default=null){
  global $pdo;
  try{
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key=? LIMIT 1");
    $stmt->execute([$key]);
    $r = $stmt->fetch();
    if($r && isset($r['setting_value'])) return $r['setting_value'];
  }catch(Exception $e){}
  return $default;
}


function max_sessions_per_user(){
  global $config;
  $v = setting_value('max_sessions_per_user', 2);
  $n = intval($v);
  return max(1, min(50, $n));
}

function max_sessions_per_poc(){

  global $config;
  $v = setting_value('max_sessions_per_poc', $config['default_max_sessions_per_poc'] ?? 1);
  $n = intval($v);
  return max(1, min(50, $n));
}

function create_session_from_poc($pocCode){
  global $pdo, $config;

  $pocHash = hash('sha256', $pocCode);
  $stmt = $pdo->prepare("SELECT id, expires_at, is_active, use_limit, used_count, pilot_user_id FROM poc_codes WHERE code_hash=? LIMIT 1");
  $stmt->execute([$pocHash]);
  $row = $stmt->fetch();
  if(!$row) return false;
  if(intval($row['is_active']) !== 1) return false;
  if($row['expires_at'] && strtotime($row['expires_at']) < time()) return false;

  // Enforce use_limit (single-use if use_limit=1)
  $useLimit = intval($row['use_limit'] ?? 1);
  $usedCount = intval($row['used_count'] ?? 0);
  if($usedCount >= $useLimit){
    // auto-disable if exceeded
    $pdo->prepare("UPDATE poc_codes SET is_active=0 WHERE id=?")->execute([$row['id']]);
    return false;
  }

  
// Enforce max concurrent sessions per pilot user (optional)
$pilotUserId = $row['pilot_user_id'] ?? null;
if($pilotUserId){
  $maxUser = max_sessions_per_user();
  $uStmt = $pdo->prepare("SELECT COUNT(*) AS c FROM sessions WHERE pilot_user_id=? AND is_active=1 AND expires_at > NOW()");
  $uStmt->execute([$pilotUserId]);
  $uActive = intval(($uStmt->fetch())['c'] ?? 0);
  if($uActive >= $maxUser){
    return false;
  }
}

// Enforce max concurrent sessions per poc (optional)

  $maxSess = max_sessions_per_poc();
  $activeSessStmt = $pdo->prepare("SELECT COUNT(*) AS c FROM sessions WHERE poc_id=? AND is_active=1 AND expires_at > NOW()");
  $activeSessStmt->execute([$row['id']]);
  $active = intval(($activeSessStmt->fetch())['c'] ?? 0);
  if($active >= $maxSess){
    return false;
  }

  $sid = new_session_id();
  $h = hash_session($sid);
  $exp = date('Y-m-d H:i:s', time() + intval($config['session_ttl'] ?? 86400));

  $ins = $pdo->prepare("INSERT INTO sessions (poc_id, pilot_user_id, session_hash, created_at, expires_at, is_active) VALUES (?,?,?,NOW(),?,1)");
  $ins->execute([$row['id'], $pilotUserId, $h, $exp]);

  // Increment used_count and auto-disable when reaching limit
  $pdo->prepare("UPDATE poc_codes SET used_count=used_count+1 WHERE id=?")->execute([$row['id']]);
  $stmt2 = $pdo->prepare("SELECT used_count, use_limit FROM poc_codes WHERE id=?");
  $stmt2->execute([$row['id']]);
  $r2 = $stmt2->fetch();
  if($r2 && intval($r2['used_count']) >= intval($r2['use_limit'])){
    $pdo->prepare("UPDATE poc_codes SET is_active=0 WHERE id=?")->execute([$row['id']]);
  }

  return $sid;
}

function require_session(){
  global $pdo;
  $sid = $_COOKIE['s4w_sess'] ?? '';
  if(!$sid) return false;
  $h = hash_session($sid);
  $stmt = $pdo->prepare("SELECT id, expires_at, is_active FROM sessions WHERE session_hash=? LIMIT 1");
  $stmt->execute([$h]);
  $row = $stmt->fetch();
  if(!$row) return false;
  if(intval($row['is_active']) !== 1) return false;
  if(strtotime($row['expires_at']) < time()) return false;
  return true;
}