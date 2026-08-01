<?php
require_once '../../config/database.php';
include '../../includes/header.php';
include '../../includes/navbar.php';
include '../../includes/sidebar.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) die('Invalid schedule.');

// Messages
$message = '';
$error = '';

// Handle POST actions: update_status, make_inspection, create_pdca
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {
        $status = trim($_POST['status'] ?? '');
        $remarks_post = trim($_POST['remarks'] ?? '');
        $allowed = ['Planned','Assigned','In Progress','Completed','Verified','Closed','Cancelled'];
        if (!in_array($status, $allowed)) {
            $error = 'Invalid status.';
        } else {
            try {
                $upd = $pdo->prepare("UPDATE inspection_schedule SET status=?, remarks=? WHERE id=?");
                $upd->execute([$status, $remarks_post, $id]);
                $message = 'Status updated successfully!';
            } catch (Exception $e) {
                $error = 'Error updating status: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'make_inspection') {
        // Create an inspections record and set schedule status to In Progress
        try {
            $stmtSched = $pdo->prepare("SELECT * FROM inspection_schedule WHERE id=?");
            $stmtSched->execute([$id]);
            $sched = $stmtSched->fetch(PDO::FETCH_ASSOC);
            if (!$sched) throw new Exception('Schedule not found');

            // Build inspection number
            $year = date("Y");
            $next = $pdo->query("SELECT IFNULL(MAX(id),0)+1 FROM inspections")->fetchColumn();
            $inspectionNo = "INS-" . $year . str_pad($next, 4, "0", STR_PAD_LEFT);

            $insSql = "INSERT INTO inspections (inspection_no, inspection_date, site_id, area_id, inspector, template_id, status, schedule_id, created_at) VALUES (?, ?, ?, ?, ?, ?, 'Draft', ?, NOW())";
            $insStmt = $pdo->prepare($insSql);
            $insStmt->execute([
                $inspectionNo,
                $sched['inspection_date'],
                $sched['site_id'] ?: null,
                $sched['area_id'] ?: null,
                $sched['inspector_id'] ?: null,
                $sched['template_id'] ?: null,
                $sched['id']
            ]);
            $inspection_id = $pdo->lastInsertId();

            // Update schedule status to In Progress
            $upd = $pdo->prepare("UPDATE inspection_schedule SET status='In Progress' WHERE id=?");
            $upd->execute([$id]);

            header('Location: ../inspections/view.php?id=' . $inspection_id);
            exit;
        } catch (Exception $e) {
            $error = 'Error creating inspection: ' . $e->getMessage();
        }
    } elseif ($action === 'create_pdca') {
        $anomaly_id = (int)($_POST['anomaly_id'] ?? 0);
        $action_required = trim($_POST['action_required'] ?? '');
        $assigned_to = (int)($_POST['assigned_to'] ?? 0) ?: null;
        $target_date = trim($_POST['target_date'] ?? '') ?: null;

        if (!$anomaly_id || $action_required === '') {
            $error = 'Anomaly and action required are required to create PDCA.';
        } else {
            try {
                $ins = $pdo->prepare("INSERT INTO corrective_actions (anomaly_id, action_required, assigned_to, target_date, status) VALUES (?, ?, ?, ?, 'Open')");
                $ins->execute([$anomaly_id, $action_required, $assigned_to, $target_date]);
                $message = 'PDCA (corrective action) created successfully.';
            } catch (Exception $e) {
                $error = 'Error creating PDCA: ' . $e->getMessage();
            }
        }
    }
}

// Refetch schedule data (to reflect updates)
$stmt = $pdo->prepare(
    "SELECT s.*, u.fullname as inspector, t.template_name, si.site_name, a.area_name
     FROM inspection_schedule s
     LEFT JOIN users u ON u.id=s.inspector_id
     LEFT JOIN checklist_templates t ON t.id=s.template_id
     LEFT JOIN sites si ON si.id=s.site_id
     LEFT JOIN areas a ON a.id=s.area_id
     WHERE s.id=?"
);
$stmt->execute([$id]);
$schedule = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$schedule) die('Schedule not found.');

// Get linked anomalies (if inspection was created from this schedule)
$insp_stmt = $pdo->prepare("SELECT id FROM inspections WHERE schedule_id=? LIMIT 1");
$insp_stmt->execute([$id]);
$inspection_id = $insp_stmt->fetchColumn();

$anomalies = [];
if ($inspection_id) {
    $anom_stmt = $pdo->prepare(
        "SELECT a.*, u.fullname as reported_by_name
         FROM anomalies a
         LEFT JOIN users u ON u.id=a.reported_by
         WHERE a.inspection_id=?
         ORDER BY a.reported_date DESC"
    );
    $anom_stmt->execute([$inspection_id]);
    $anomalies = $anom_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch users for assigning PDCA
$users = $pdo->query("SELECT id, fullname FROM users WHERE status='Active' ORDER BY fullname")->fetchAll(PDO::FETCH_ASSOC);

// Fetch existing corrective actions for the anomalies (grouped by anomaly_id)
$pdca_map = [];
if (!empty($anomalies)) {
    $ids = array_column($anomalies, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $ca_stmt = $pdo->prepare("SELECT * FROM corrective_actions WHERE anomaly_id IN ({$placeholders}) ORDER BY id DESC");
    $ca_stmt->execute($ids);
    $all_ca = $ca_stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($all_ca as $c) {
        $pdca_map[$c['anomaly_id']][] = $c;
    }
}

$severity_colors = [
    'Low' => 'success',
    'Medium' => 'warning',
    'High' => 'danger',
    'Critical' => 'dark'
];

$status_colors = [
    'Planned' => 'info',
    'Assigned' => 'primary',
    'In Progress' => 'warning',
    'Completed' => 'success',
    'Verified' => 'success',
    'Closed' => 'secondary',
    'Cancelled' => 'danger'
];
?>

<div class="content-wrapper">
<section class="content-header">
<div class="container-fluid">
<div class="row mb-2">
<div class="col-sm-6"><h1>Inspection Schedule Details</h1></div>
<div class="col-sm-6 text-end"><a href="monthly.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a></div>
</div>
</div>
</section>
<section class="content">
<div class="container-fluid">

<?php if ($message): ?>
<div class="alert alert-success alert-dismissible fade show">
<i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show">
<i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Schedule Information -->
<div class="card card-primary">
<div class="card-header"><h3 class="card-title">Schedule Information</h3></div>
<div class="card-body">
<div class="row">
<div class="col-md-6">
<table class="table table-borderless">
<tr><th width="150">Date</th><td><?= date('d/m/Y (l)', strtotime($schedule['inspection_date'])) ?></td></tr>
<tr><th>Inspector</th><td><?= htmlspecialchars($schedule['inspector'] ?? '-') ?></td></tr>
<tr><th>Checklist</th><td><?= htmlspecialchars($schedule['template_name'] ?? '-') ?></td></tr>
<tr><th>Site</th><td><?= htmlspecialchars($schedule['site_name'] ?? '-') ?></td></tr>
</table>
</div>
<div class="col-md-6">
<table class="table table-borderless">
<tr><th width="150">Location</th><td><?= htmlspecialchars($schedule['location'] ?? '-') ?></td></tr>
<tr><th>Status</th><td><span class="badge bg-<?= $status_colors[$schedule['status']] ?? 'secondary' ?>"><?= htmlspecialchars($schedule['status']) ?></span></td></tr>
<tr><th>Remarks</th><td><?= nl2br(htmlspecialchars($schedule['remarks'] ?? '-')) ?></td></tr>
</table>
</div>
</div>
</div>
<div class="card-footer">
<form method="POST" class="d-inline">
<input type="hidden" name="action" value="make_inspection">
<button type="submit" class="btn btn-success"><i class="fas fa-play"></i> Make Inspection</button>
</form>
<a href="edit_schedule.php?id=<?= $schedule['id'] ?>" class="btn btn-warning"><i class="fas fa-edit"></i> Edit</a>
</div>
</div>

<!-- Status change form -->
<div class="card">
<div class="card-header"><h3 class="card-title">Change Status / Remarks</h3></div>
<div class="card-body">
<form method="POST">
<input type="hidden" name="action" value="update_status">
<div class="row">
<div class="col-md-4">
<div class="form-group">
<label>Status</label>
<select name="status" class="form-control">
<?php foreach (array_keys($status_colors) as $st): ?>
<option value="<?= $st ?>" <?= $schedule['status'] === $st ? 'selected' : '' ?>><?= $st ?></option>
<?php endforeach; ?>
</select>
</div>
</div>
<div class="col-md-8">
<div class="form-group">
<label>Remarks</label>
<textarea name="remarks" class="form-control"><?= isset($schedule['remarks']) ? htmlspecialchars($schedule['remarks']) : '' ?></textarea>
</div>
</div>
</div>
<div class="mt-2"><button class="btn btn-primary" type="submit">Update</button></div>
</form>
</div>
</div>

<!-- Anomalies and PDCA -->
<div class="card mt-3">
<div class="card-header"><h3 class="card-title">Anomalies</h3></div>
<div class="card-body">
<?php if (!$inspection_id): ?>
<p class="text-muted">No inspection created yet for this schedule. Click "Make Inspection" to start and create an inspection record.</p>
<?php endif; ?>

<?php if (empty($anomalies)): ?>
<p class="text-center text-muted">No anomalies recorded.</p>
<?php else: ?>
<?php foreach ($anomalies as $an): ?>
<div class="card mb-2">
<div class="card-body">
<div class="d-flex justify-content-between">
<div>
<strong><?= htmlspecialchars($an['title']) ?></strong>
<p class="mb-1 small text-muted">Reported on <?= htmlspecialchars($an['reported_date']) ?> by <?= htmlspecialchars($an['reported_by_name'] ?? '-') ?></p>
<p><?= nl2br(htmlspecialchars($an['description'])) ?></p>
</div>
<div class="text-end">
<span class="badge bg-<?= $severity_colors[$an['severity']] ?? 'secondary' ?>"><?= htmlspecialchars($an['severity']) ?></span>
</div>
</div>
<hr>
<!-- Existing PDCA items for this anomaly -->
<?php if (!empty($pdca_map[$an['id']])): ?>
<h6>PDCA / Corrective Actions</h6>
<ul>
<?php foreach ($pdca_map[$an['id']] as $ca): ?>
<li>
<strong><?= htmlspecialchars($ca['action_required']) ?></strong>
<?php if ($ca['assigned_to']): ?>
 — assigned to <?= htmlspecialchars((array_values(array_filter($users, function($u) use ($ca){ return $u['id']==$ca['assigned_to']; })))[0]['fullname'] ?? $ca['assigned_to']) ?>
<?php endif; ?>
 <?php if ($ca['target_date']): ?> (target <?= htmlspecialchars($ca['target_date']) ?>)<?php endif; ?>
 — status: <?= htmlspecialchars($ca['status'] ?? 'Open') ?>
</li>
<?php endforeach; ?>
</ul>
<?php endif; ?>

<!-- Create PDCA form -->
<form method="POST" class="row g-2">
<input type="hidden" name="action" value="create_pdca">
<input type="hidden" name="anomaly_id" value="<?= $an['id'] ?>">
<div class="col-md-6">
<input type="text" name="action_required" class="form-control" placeholder="Action required (PDCA)" required>
</div>
<div class="col-md-3">
<select name="assigned_to" class="form-control">
<option value="">-- Assign to --</option>
<?php foreach ($users as $u): ?>
<option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['fullname']) ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="col-md-2">
<input type="date" name="target_date" class="form-control">
</div>
<div class="col-md-1">
<button class="btn btn-success w-100" type="submit">Create</button>
</div>
</form>

</div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>
</div>

</div>
</section>
</div>
<?php include '../../includes/footer.php'; ?>
