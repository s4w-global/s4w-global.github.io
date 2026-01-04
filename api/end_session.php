<?php
require __DIR__.'/security_headers.php';
header('Content-Type: application/json');
require __DIR__.'/session_util.php';
require __DIR__.'/db.php';

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