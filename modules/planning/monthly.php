<?php
require_once '../../config/database.php';
include '../../includes/header.php';
include '../../includes/navbar.php';
include '../../includes/sidebar.php';

$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');
$status = $_GET['status'] ?? '';

$planned = $pdo->query("SELECT COUNT(*) FROM inspection_schedule WHERE status='Planned'")->fetchColumn();
$assigned = $pdo->query("SELECT COUNT(*) FROM inspection_schedule WHERE status='Assigned'")->fetchColumn();
$progress = $pdo->query("SELECT COUNT(*) FROM inspection_schedule WHERE status='In Progress'")->fetchColumn();
$completed = $pdo->query("SELECT COUNT(*) FROM inspection_schedule WHERE status='Completed'")->fetchColumn();

$sql = "SELECT s.*, u.fullname, t.template_name, si.site_name, a.area_name,
               COUNT(DISTINCT an.id) as anomaly_count
        FROM inspection_schedule s
        LEFT JOIN users u ON u.id=s.inspector_id
        LEFT JOIN checklist_templates t ON t.id=s.template_id
        LEFT JOIN sites si ON si.id=s.site_id
        LEFT JOIN areas a ON a.id=s.area_id
        LEFT JOIN inspections i ON i.schedule_id=s.id
        LEFT JOIN anomalies an ON an.inspection_id=i.id
        WHERE MONTH(s.inspection_date)=? AND YEAR(s.inspection_date)=?";
$params = [$month, $year];

if($status != ''){
    $sql .= " AND s.status=?";
    $params[] = $status;
}

$sql .= " GROUP BY s.id ORDER BY s.inspection_date";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare events for calendar
$color_map = ['Planned'=>'#17a2b8','Assigned'=>'#007bff','In Progress'=>'#ffc107','Completed'=>'#28a745','Verified'=>'#28a745','Closed'=>'#6c757d','Cancelled'=>'#dc3545'];
$events = [];
foreach($schedules as $row){
    $title_parts = [];
    if(!empty($row['template_name'])) $title_parts[] = $row['template_name'];
    if(!empty($row['fullname'])) $title_parts[] = $row['fullname'];
    $title_parts[] = $row['status'];
    $title = implode(' - ', $title_parts);
    $start = date('Y-m-d', strtotime($row['inspection_date']));
    $color = $color_map[$row['status']] ?? '#6c757d';
    // Use basePath from header to build correct URL
    $event_url = (isset($basePath) ? $basePath : '') . 'modules/planning/schedule_details.php?id=' . $row['id'];
    $events[] = [
        'id' => $row['id'],
        'title' => $title,
        'start' => $start,
        'url' => $event_url,
        'backgroundColor' => $color,
        'borderColor' => $color,
    ];
}

// Safely encode events for JS
$events_json = json_encode($events, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);

?>

<div class="content-wrapper">
<section class="content-header">
<div class="container-fluid">
<div class="row">
<div class="col-sm-6"><h1><i class="fas fa-calendar-alt"></i> Monthly Inspection Planning</h1></div>
<div class="col-sm-6 text-end">
<a href="create_schedule.php" class="btn btn-success"><i class="fas fa-plus"></i> New Planning</a>
<a href="import.php" class="btn btn-primary"><i class="fas fa-upload"></i> Import Schedules</a>
</div>
</div>
</div>
</section>
<section class="content">
<div class="container-fluid">
<div class="row">
<div class="col-lg-3"><div class="small-box bg-info"><div class="inner"><h3><?= $planned ?></h3><p>Planned</p></div><div class="icon"><i class="fas fa-calendar"></i></div></div></div>
<div class="col-lg-3"><div class="small-box bg-primary"><div class="inner"><h3><?= $assigned ?></h3><p>Assigned</p></div><div class="icon"><i class="fas fa-user-check"></i></div></div></div>
<div class="col-lg-3"><div class="small-box bg-warning"><div class="inner"><h3><?= $progress ?></h3><p>In Progress</p></div><div class="icon"><i class="fas fa-spinner"></i></div></div></div>
<div class="col-lg-3"><div class="small-box bg-success"><div class="inner"><h3><?= $completed ?></h3><p>Completed</p></div><div class="icon"><i class="fas fa-check-circle"></i></div></div></div>
</div>
<div class="card">
<div class="card-header"><h3 class="card-title">Filters</h3></div>
<div class="card-body">
<form method="GET">
<div class="row">
<div class="col-md-3"><label>Month</label>
<select name="month" class="form-control">
<?php for($i=1;$i<=12;$i++){ $sel = ((int)$month===$i)?'selected':''; echo "<option value='$i' $sel>".date("F",mktime(0,0,0,$i,10))."</option>"; } ?>
</select>
</div>
<div class="col-md-2"><label>Year</label><input type="number" name="year" value="<?= $year ?>" class="form-control"></div>
<div class="col-md-3"><label>Status</label>
<select name="status" class="form-control">
<option value="">All</option>
<?php
$statuses = ['Planned','Assigned','In Progress','Completed','Verified','Closed','Cancelled'];
foreach($statuses as $st){
    $sel = $status == $st ? 'selected' : '';
    echo "<option value='$st' $sel>$st</option>";
}
?>
</select>
</div>
<div class="col-md-4"><label>&nbsp;</label><button class="btn btn-primary w-100"><i class="fas fa-search"></i> Search</button></div>
</div>
</form>
</div>
</div>

<!-- Calendar card placed immediately after filters so it is always visible -->
<div class="card">
<div class="card-header"><h3 class="card-title">Monthly Calendar</h3></div>
<div class="card-body">
<style>#calendar{max-width:100%;margin:0 auto;min-height:600px;height:auto;}</style>
<!-- FullCalendar (single bundle) -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.css" rel="stylesheet" />
<div id="calendar"></div>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  var calendarEl = document.getElementById('calendar');
  if (!calendarEl) {
    console.error('Calendar element not found');
    return;
  }

  // debug: print events data coming from server
  console.log('calendar events data:', <?= $events_json ?>);

  try {
    // force a fixed height to ensure calendar is visible
    calendarEl.style.minHeight = '600px';

    var calendar = new FullCalendar.Calendar(calendarEl, {
      initialView: 'dayGridMonth',
      initialDate: '<?= $year . '-' . str_pad($month,2,'0',STR_PAD_LEFT) ?>',
      headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,dayGridWeek,dayGridDay'
      },
      events: <?= $events_json ?>,
      eventClick: function(info) {
        if (info.event.url) {
          info.jsEvent.preventDefault();
          window.location.href = info.event.url;
        }
      }
    });

    calendar.render();
    console.log('FullCalendar initialized successfully');
  } catch (err) {
    console.error('FullCalendar initialization error:', err);
  }
});
</script>
</div>
</div>

<!-- Existing inspection planning table remains below the calendar -->
<div class="card">
<div class="card-header"><h3 class="card-title">Inspection Planning</h3></div>
<div class="card-body table-responsive">
<table class="table table-bordered table-hover">
<thead class="table-light">
<tr>
<th>Date</th>
<th>Day</th>
<th>Inspector</th>
<th>Checklist</th>
<th>Location</th>
<th>Status</th>
<th>Anomalies</th>
<th>Actions</th>
</tr>
</thead>
<tbody>
<?php if(count($schedules)>0): ?>
<?php foreach($schedules as $row): ?>
<tr>
<td><?= date("d/m/Y",strtotime($row['inspection_date'])) ?></td>
<td><?= date("l",strtotime($row['inspection_date'])) ?></td>
<td><?= htmlspecialchars($row['fullname'] ?? '-') ?></td>
<td><?= htmlspecialchars($row['template_name'] ?? '-') ?></td>
<td><?= htmlspecialchars($row['location'] ?? $row['area_name'] ?? '-') ?></td>
<td>
<?php 
$color_map = ['Planned'=>'info','Assigned'=>'primary','In Progress'=>'warning','Completed'=>'success','Verified'=>'success','Closed'=>'secondary','Cancelled'=>'danger'];
?>
<span class="badge bg-<?= $color_map[$row['status']] ?? 'secondary' ?>"><?= htmlspecialchars($row['status']) ?></span>
</td>
<td>
<?php if($row['anomaly_count'] > 0): ?>
<span class="badge bg-danger"><?= $row['anomaly_count'] ?> anomalies</span>
<?php else: ?>
<span class="badge bg-success">OK</span>
<?php endif; ?>
</td>
<td>
<a href="schedule_details.php?id=<?= $row['id'] ?>" class="btn btn-info btn-sm"><i class="fas fa-eye"></i> View</a>
<a href="edit_schedule.php?id=<?= $row['id'] ?>" class="btn btn-warning btn-sm"><i class="fas fa-edit"></i></a>
<a href="delete_schedule.php?id=<?= $row['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete?')"><i class="fas fa-trash"></i></a>
</td>
</tr>
<?php endforeach; ?>
<?php else: ?>
<tr><td colspan="8" class="text-center">No inspection planning found.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>

</div>
</section>
</div>
<?php include '../../includes/footer.php'; ?>
