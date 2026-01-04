<?php
function geohash_encode($lat, $lng, $precision = 8) {
  $base32 = '0123456789bcdefghjkmnpqrstuvwxyz';
  $lat_interval = [-90.0, 90.0];
  $lng_interval = [-180.0, 180.0];
  $geohash = '';
  $is_even = true; $bit = 0; $ch = 0;
  while (strlen($geohash) < $precision) {
    if ($is_even) {
      $mid = ($lng_interval[0] + $lng_interval[1]) / 2;
      if ($lng > $mid) { $ch |= 1 << (4-$bit); $lng_interval[0] = $mid; }
      else { $lng_interval[1] = $mid; }
    } else {
      $mid = ($lat_interval[0] + $lat_interval[1]) / 2;
      if ($lat > $mid) { $ch |= 1 << (4-$bit); $lat_interval[0] = $mid; }
      else { $lat_interval[1] = $mid; }
    }
    $is_even = !$is_even;
    if ($bit < 4) $bit++;
    else { $geohash .= $base32[$ch]; $bit = 0; $ch = 0; }
  }
  return $geohash;
}

function geohash_decode_bbox($geohash){
  $base32 = '0123456789bcdefghjkmnpqrstuvwxyz';
  $bits = [16,8,4,2,1];
  $lat_interval = [-90.0, 90.0];
  $lng_interval = [-180.0, 180.0];
  $is_even = true;

  for($i=0;$i<strlen($geohash);$i++){
    $c = $geohash[$i];
    $cd = strpos($base32, $c);
    if($cd === false) continue;
    for($j=0;$j<5;$j++){
      $mask = $bits[$j];
      if($is_even){
        $mid = ($lng_interval[0] + $lng_interval[1]) / 2;
        if(($cd & $mask) != 0) $lng_interval[0] = $mid;
        else $lng_interval[1] = $mid;
      } else {
        $mid = ($lat_interval[0] + $lat_interval[1]) / 2;
        if(($cd & $mask) != 0) $lat_interval[0] = $mid;
        else $lat_interval[1] = $mid;
      }
      $is_even = !$is_even;
    }
  }
  return [
    'lat_min' => $lat_interval[0], 'lat_max' => $lat_interval[1],
    'lng_min' => $lng_interval[0], 'lng_max' => $lng_interval[1]
  ];
}
function geohash_center($geohash){
  $b = geohash_decode_bbox($geohash);
  return [
    'lat' => ($b['lat_min'] + $b['lat_max']) / 2,
    'lng' => ($b['lng_min'] + $b['lng_max']) / 2
  ];
}