<?php
header('Content-Type: application/json');
require __DIR__.'/db.php';
$config = require __DIR__.'/config.php';

/**
 * API keys are stored hashed in DB.
 * Client sends plaintext in header: X-API-Key: S4W-RTM-<random...>
 * Server hashes it (HMAC-SHA256 with pepper) and looks up active key.
 */
function hash_api_key($key){
  global $config;
  return hash('sha256', $config['api_key_pepper'].$key);
}

/**
 * Returns key row as associative array or exits 401.
 */
function require_api_key(){
  global $pdo;
  $key = $_SERVER['HTTP_X_API_KEY'] ?? ($_GET['key'] ?? '');
  $key = trim($key);

  if($key === ''){
    http_response_code(401);
    echo json_encode(['error'=>'missing_api_key']);
    exit;
  }

  $hash = hash_api_key($key);

  $stmt = $pdo->prepare("SELECT id, scope_type, scope_value, label FROM api_keys WHERE key_hash=? AND is_active=1 LIMIT 1");
  $stmt->execute([$hash]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  if(!$row){
    http_response_code(401);
    echo json_encode(['error'=>'unauthorized']);
    exit;
  }

  return $row;
}

/**
 * Check whether given key row is allowed for a municipality (e.g. 'rotterdam')
 */
function require_scope_municipality($keyRow, $municipality){
  $scopeType = $keyRow['scope_type'];
  $scopeVal  = $keyRow['scope_value'];

  if($scopeType === 'global') return;
  if($scopeType === 'country' && strtolower($scopeVal)==='nl') return;
  if($scopeType === 'municipality' && strtolower($scopeVal)===strtolower($municipality)) return;

  http_response_code(403);
  echo json_encode(['error'=>'forbidden_scope']);
  exit;
}
