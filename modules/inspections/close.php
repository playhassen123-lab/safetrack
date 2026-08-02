<?php
require_once '../../config/database.php';

$inspection_id = (int)($_POST['inspection_id'] ?? 0);
$final_remarks = trim($_POST['final_remarks'] ?? '');
$auto_create_pdca = isset($_POST['auto_create_pdca']) && $_POST['auto_create_pdca'] == '1';

if (!$inspection_id) {
    die('Invalid inspection.');
}

try {
    $pdo->beginTransaction();

    // Load inspection and schedule
    $stmt = $pdo->prepare("SELECT id, schedule_id FROM inspections WHERE id=? LIMIT 1");
    $stmt->execute([$inspection_id]);
    $inspection = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$inspection) throw new Exception('Inspection not found.');

    $schedule_id = $inspection['schedule_id'];

    // Save final remarks on inspections and set status = Completed
    $upd = $pdo->prepare("UPDATE inspections SET remarks = ?, status = 'Completed' WHERE id = ?");
    $upd->execute([$final_remarks, $inspection_id]);

    // If auto-create PDCA: for each anomaly for this inspection create corrective_actions if none exists
    if ($auto_create_pdca) {
        // find default supervisor (role_id = 2)
        $sup_stmt = $pdo->prepare("SELECT id FROM users WHERE role_id = 2 AND status = 'Active' LIMIT 1");
        $sup_stmt->execute();
        $supervisor_id = $sup_stmt->fetchColumn();

        $anom_stmt = $pdo->prepare("SELECT id, title FROM anomalies WHERE inspection_id = ?");
        $anom_stmt->execute([$inspection_id]);
        $anomalies = $anom_stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($anomalies as $an) {
            // Skip if corrective_actions already exists for this anomaly
            $check = $pdo->prepare("SELECT COUNT(*) FROM corrective_actions WHERE anomaly_id = ?");
            $check->execute([$an['id']]);
            $exists = (int)$check->fetchColumn();
            if ($exists) continue;

            $action_required = $an['title'] ?: ('Follow-up for anomaly #' . $an['id']);
            // assigned_to = supervisor if available, otherwise NULL
            if ($supervisor_id) {
                $ins = $pdo->prepare("INSERT INTO corrective_actions (anomaly_id, action_required, assigned_to, target_date, status) VALUES (?, ?, ?, NULL, 'Open')");
                $ins->execute([$an['id'], $action_required, $supervisor_id]);
            } else {
                $ins = $pdo->prepare("INSERT INTO corrective_actions (anomaly_id, action_required, assigned_to, target_date, status) VALUES (?, ?, NULL, NULL, 'Open')");
                $ins->execute([$an['id'], $action_required]);
            }

            // mark anomaly as Assigned so it appears in follow-up lists
            $pdo->prepare("UPDATE anomalies SET status = 'Assigned' WHERE id = ?")->execute([$an['id']]);
        }
    }

    // Mark schedule as Completed
    if ($schedule_id) {
        $pdo->prepare("UPDATE inspection_schedule SET status='Completed' WHERE id=?")->execute([$schedule_id]);
    }

    $pdo->commit();
    header('Location: view.php?id=' . $inspection_id . '&closed=1');
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    die('Error closing inspection: ' . $e->getMessage());
}
