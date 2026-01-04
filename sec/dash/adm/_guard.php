<?php
require __DIR__.'/../../api/security_headers.php';
session_start();
if(!isset($_SESSION['s4w_admin']) || $_SESSION['s4w_admin'] !== true){
  header('Location: login.php');
  exit;
}