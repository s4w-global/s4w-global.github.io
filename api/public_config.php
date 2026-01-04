<?php
require __DIR__.'/security_headers.php';
header('Content-Type: application/json');
$config = require __DIR__.'/config.php';
$env = $config['env'] ?? 'poc';
$tsAll = $config['turnstile'] ?? [];
$ts = $tsAll[$env] ?? ['enabled'=>false,'site_key'=>'','protect_reports'=>true,'protect_panic'=>false];
echo json_encode([
  'env' => $env,
  'turnstile' => [
    'enabled' => !empty($ts['enabled']),
    'site_key' => $ts['site_key'] ?? '',
    'protect_reports' => !empty($ts['protect_reports']),
    'protect_panic' => !empty($ts['protect_panic'])
  ]
]);