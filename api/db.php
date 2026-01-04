<?php
$config = require __DIR__.'/config.php';
$dsn = "mysql:host={$config['db']['host']};dbname={$config['db']['name']};charset={$config['db']['charset']}";
try {
  $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
  ]);
} catch(Exception $e){
  http_response_code(500);
  header('Content-Type: application/json');
  echo json_encode(['error'=>'db_connection_failed']);
  exit;
}