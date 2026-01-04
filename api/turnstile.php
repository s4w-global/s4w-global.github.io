<?php
function turnstile_profile(){
  $config = require __DIR__.'/config.php';
  $env = $config['env'] ?? 'poc';
  $all = $config['turnstile'] ?? [];
  return $all[$env] ?? ['enabled'=>false];
}

function verify_turnstile($token, &$err = null){
  $ts = turnstile_profile();
  if(empty($ts['enabled'])) return true;

  $secret = $ts['secret_key'] ?? '';
  if(!$secret || !$token){
    $err = 'missing';
    return false;
  }

  $ip = $_SERVER['REMOTE_ADDR'] ?? '';

  $data = http_build_query([
    'secret' => $secret,
    'response' => $token,
    'remoteip' => $ip
  ]);

  $resp = null;

  // Prefer curl if available
  if(function_exists('curl_init')){
    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    $resp = curl_exec($ch);
    curl_close($ch);
  } else {
    $opts = [
      'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => $data,
        'timeout' => 8
      ]
    ];
    $resp = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, stream_context_create($opts));
  }

  if(!$resp){
    $err = 'network';
    return false;
  }

  $json = json_decode($resp, true);
  if(!is_array($json)){
    $err = 'parse';
    return false;
  }
  if(!empty($json['success'])) return true;

  $err = 'invalid';
  return false;
}