<?php
require_once '../../config/database.php';
require_once '../../config/auth.php';
include '../../includes/header.php';
include '../../includes/navbar.php';
include '../../includes/sidebar.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inspection_date = trim($_POST['inspection_date'] ?? '');
    $inspector_id = (int)($_POST['inspector_id'] ?? 0);
    $new_inspector = trim($_POST['new_inspector'] ?? '');
    $template_id = (int)($_POST['template_id'] ?? 0);
    $remarks = trim($_POST['remarks'] ?? '');

    if (empty($inspection_date)) {
        $error = 'Inspection date is required.';
    } elseif (!$inspector_id && $new_inspector === '') {
        $error = 'Inspector is required.';
    } elseif (!$template_id) {
        $error = 'Checklist template is required.';
    } else {
        try {
            // If a new inspector name was provided, create or reuse user record
            if (!$inspector_id && $new_inspector !== '') {
                // Try to find existing user by fullname (exact match)
                $find = $pdo->prepare("SELECT id FROM users WHERE fullname = ? AND status = 'Active' LIMIT 1");
                $find->execute([$new_inspector]);
                $foundId = $find->fetchColumn();
                if ($foundId) {
                    $inspector_id = (int)$foundId;
                } else {
                    $insIns = $pdo->prepare("INSERT INTO users (fullname, role_id, status, created_at) VALUES (?, ?, 'Active', NOW())");
                    $insIns->execute([$new_inspector, 3]);
                    $inspector_id = $pdo->lastInsertId();
                }
            }

            $stmt = $pdo->prepare(
                "INSERT INTO inspection_schedule (inspection_date, inspector_id, template_id, site_id, location, status, remarks, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
            );

            // site_id and location removed from the form — set to NULL / empty string
            $stmt->execute([
                $inspection_date,
                $inspector_id ?: null,
                $template_id,
                null,
                '',
                'Planned',
                $remarks
            ]);
            $schedule_id = $pdo->lastInsertId();
            header("Location: schedule_details.php?id=$schedule_id&created=1");
            exit;
        } catch (Exception $e) {
            $error = 'Error creating schedule: ' . $e->getMessage();
        }
    }
}

$inspectors = $pdo->query("SELECT id, fullname FROM users WHERE role_id=3 AND status='Active' ORDER BY fullname")->fetchAll(PDO::FETCH_ASSOC);
$templates = $pdo->query("SELECT id, template_name FROM checklist_templates ORDER BY template_name")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="content-wrapper">
<section class="content-header">
<div class="container-fluid">
<div class="row mb-2">
<div class="col-sm-6"><h1>Create Inspection Schedule</h1></div>
<div class="col-sm-6 text-end"><a href="monthly.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a></div>
</div>
</div>
</section>
<section class="content">
<div class="container-fluid">

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show">
<i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card card-primary">
<div class="card-header"><h3 class="card-title">Schedule Details</h3></div>
<form method="POST">
<div class="card-body">
<div class="form-group mb-3">
<label>Inspection Date *</label>
<input type="date" name="inspection_date" class="form-control" required value="<?= isset($inspection_date) ? htmlspecialchars($inspection_date) : '' ?>">
</div>
<div class="form-group mb-3">
<label>Inspector *</label>
<select name="inspector_id" class="form-control">
<option value="">-- Select Inspector --</option>
<?php foreach ($inspectors as $insp): ?>
<option value="<?= $insp['id'] ?>" <?= (isset($inspector_id) && $inspector_id == $insp['id']) ? 'selected' : '' ?>><?= htmlspecialchars($insp['fullname']) ?></option>
<?php endforeach; ?>
</select>
<small class="form-text text-muted">Or enter a new inspector name below (will be created automatically).</small>
</div>
<div class="form-group mb-3">
<label>New Inspector (optional)</label>
<input type="text" name="new_inspector" class="form-control" placeholder="Full name of inspector" value="<?= isset($new_inspector) ? htmlspecialchars($new_inspector) : '' ?>">
</div>
<div class="form-group mb-3">
<label>Checklist Template *</label>
<select name="template_id" class="form-control" required>
<option value="">-- Select Template --</option>
<?php foreach ($templates as $tmpl): ?>
<option value="<?= $tmpl['id'] ?>" <?= (isset($template_id) && $template_id == $tmpl['id']) ? 'selected' : '' ?>><?= htmlspecialchars($tmpl['template_name']) ?></option>
<?php endforeach; ?>
</select>
</div>
<!-- Removed Site and Location fields as requested -->
<div class="form-group mb-3">
<label>Remarks</label>
<textarea name="remarks" rows="3" class="form-control"><?= isset($remarks) ? htmlspecialchars($remarks) : '' ?></textarea>
</div>
</div>
<div class="card-footer">
<button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Create Schedule</button>
<a href="monthly.php" class="btn btn-secondary">Cancel</a>
</div>
</form>
</div>

</div>
</section>
</div>
<?php include '../../includes/footer.php'; ?>
