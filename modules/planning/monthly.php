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
    $events[] = [
        'id' => $row['id'],
        'title' => $title,
        'start' => $start,
        'url' => "schedule_details.php?id=".$row['id'],
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

<div class="card">
<div class="card-header"><h3 class="card-title">Monthly Calendar</h3></div>
<div class="card-body">
<!-- FullCalendar: include core + daygrid plugin global bundles -->
<link href='https://cdn.jsdelivr.net/npm/@fullcalendar/daygrid@6.1.8/main.min.css' rel='stylesheet' />
<div id='calendar'></div>
<script src='https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.8/index.global.min.js'></script>
<script src='https://cdn.jsdelivr.net/npm/@fullcalendar/daygrid@6.1.8/index.global.min.js'></script>
<script>
// Debug: ensure FullCalendar is loaded
if(typeof FullCalendar === 'undefined' && typeof FullCalendarCore === 'undefined'){
    console.error('FullCalendar not loaded. Check CDN script includes.');
}

document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    // plugin global name exposed by the daygrid global bundle is FullCalendarDayGrid
    var plugins = [];
    if(typeof FullCalendarDayGrid !== 'undefined') plugins.push(FullCalendarDayGrid);
    else if(typeof FullCalendar !== 'undefined' && FullCalendar.DayGrid) plugins.push(FullCalendar.DayGrid);

    var calendar = new FullCalendar.Calendar(calendarEl, {
        plugins: plugins,
        initialView: 'dayGridMonth',
        initialDate: '<?= $year . '-' . str_pad($month,2,'0',STR_PAD_LEFT) ?>',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,dayGridWeek,dayGridDay'
        },
        events: <?= $events_json ?>,
        eventClick: function(info){
            if(info.event.url){
                info.jsEvent.preventDefault();
                // open in same tab
                window.location.href = info.event.url;
            }
        }
    });
    calendar.render();
});
</script>
</div>
</div>

</div>
</section>
</div>
<?php include '../../includes/footer.php'; ?>
