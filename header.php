<?php
// Included after require config.php & require_login()
$cur = basename($_SERVER['SCRIPT_NAME']);
$ct  = $_GET['t'] ?? '';
function navlink($href, $icon, $label, $on) {
    echo '<a href="'.$href.'" class="'.($on ? 'on' : '').'">'.$icon.' '.$label.'</a>';
}
?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'IoT Monitoring') ?></title>
<link rel="stylesheet" href="style.css"></head>
<body>
<nav>
  <a class="brand" href="index.php">📡 IoT Monitor</a>
  <?php
  navlink('index.php',                  '🏠', 'Dashboard',   $cur === 'index.php');
  navlink('crud.php?t=device',          '📟', 'Devices',     $cur === 'crud.php' && $ct === 'device');
  navlink('crud.php?t=sensor',          '🌡️', 'Sensors',     $cur === 'crud.php' && $ct === 'sensor');
  navlink('crud.php?t=sensor_reading',  '📈', 'Readings',    $cur === 'crud.php' && $ct === 'sensor_reading');
  navlink('crud.php?t=alert',           '🚨', 'Alerts',      $cur === 'crud.php' && $ct === 'alert');
  navlink('crud.php?t=location',        '📍', 'Locations',   $cur === 'crud.php' && $ct === 'location');
  navlink('crud.php?t=user_device',     '👥', 'Assignments', $cur === 'crud.php' && $ct === 'user_device');
  navlink('report.php',                 '📊', 'Reports',     $cur === 'report.php');
  navlink('performance.php',            '🏆', 'Performance', $cur === 'performance.php');
  navlink('simulate.php',               '🎲', 'Simulator',   $cur === 'simulate.php');
  if (is_staff()) navlink('audit.php',  '📜', 'Audit Log',   $cur === 'audit.php');
  ?>
  <span class="sp"></span>
  <a href="password.php" class="<?= $cur === 'password.php' ? 'on' : '' ?>">👤 <?= e($_SESSION['user']['name']) ?><span class="role"><?= e($_SESSION['user']['role']) ?></span></a>
  <a href="logout.php">🚪 Logout</a>
</nav>
<main>