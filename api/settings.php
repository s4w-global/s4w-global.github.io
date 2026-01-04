<?php
require __DIR__.'/security_headers.php';
header('Content-Type: application/json');
require __DIR__.'/db.php';

$stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('map_style')");
$stmt->execute();
$rows = $stmt->fetchAll();

$out = ['map_style' => 'dark'];
foreach($rows as $r){
  $out[$r['setting_key']] = $r['setting_value'];
}
echo json_encode($out);