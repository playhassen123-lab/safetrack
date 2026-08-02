<?php
require_once '../../config/database.php';

$schedule = (int)($_GET['schedule'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM inspection_schedule WHERE id=?");
$stmt->execute([$schedule]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$data) die("Planning not found");

$year = date("Y");
$next = $pdo->query("SELECT IFNULL(MAX(id),0)+1 FROM inspections")->fetchColumn();
$inspectionNo = "INS-" . $year . str_pad($next, 4, "0", STR_PAD_LEFT);

$sql = "INSERT INTO inspections (inspection_no, inspection_date, site_id, area_id, inspector, template_id, status, schedule_id, created_at) VALUES (?, ?, ?, ?, ?, ?, 'In Progress', ?, NOW())";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    $inspectionNo,
    $data['inspection_date'],
    $data['site_id'] ?: null,
    null,
    $data['inspector_id'],
    $data['template_id'],
    $data['id']
]);

$id = $pdo->lastInsertId();

// Update schedule status to In Progress
$upd = $pdo->prepare("UPDATE inspection_schedule SET status='In Progress' WHERE id=?");
$upd->execute([$schedule]);

header("Location: ../inspections/view.php?id=" . $id);
exit;
