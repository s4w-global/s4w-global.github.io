<?php
$config = require __DIR__.'/config.php';
$ph = $config['privacy_headers'] ?? ['enabled'=>false];
if(empty($ph['enabled'])) return;

// Basic hardening
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(self), microphone=(), camera=(), payment=()');
header('Cross-Origin-Opener-Policy: same-origin');
header('Cross-Origin-Resource-Policy: same-origin');
header('Cross-Origin-Embedder-Policy: require-corp');

// HSTS only when on HTTPS (avoid breaking localhost/dev)
if(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'){
  header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
}

// CSP
$csp = $ph['csp'] ?? '';
if($csp) header('Content-Security-Policy: '.$csp);

// Cache control for API responses
header('Cache-Control: no-store, max-age=0');