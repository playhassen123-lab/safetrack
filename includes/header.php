<?php
require_once __DIR__ . '/../config/database.php';

// Determine the base URL dynamically based on the current file location
$pathDepth = substr_count($_SERVER['PHP_SELF'], '/') - 2; // -2 for /safetrack and the file
$basePath = str_repeat('../', $pathDepth);
if($pathDepth < 0) $basePath = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SafeTrack HSE</title>
<link rel="stylesheet" href="<?= $basePath ?>assets/adminlte/plugins/fontawesome-free/css/all.min.css">
<link rel="stylesheet" href="<?= $basePath ?>assets/adminlte/dist/css/adminlte.min.css">
<link rel="stylesheet" href="<?= $basePath ?>assets/css/calendar.css">
<link rel="stylesheet" href="<?= $basePath ?>assets/css/style.css">
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
