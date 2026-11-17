<?php
require 'config.php';
require_login();
$title = 'Readings Report';
$loc  = $_GET['loc'] ?? '';
$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';
$print = isset($_GET['print']);

$sql = "SELECT l.location_name, d.device_name, s.sensor_name, s.unit, r.reading_value, r.reading_time, s.min_threshold, s.max_threshold
        FROM sensor_reading r JOIN sensor s ON r.sensor_id=s.sensor_id
        JOIN device d ON s.device_id=d.device_id JOIN location l ON d.location_id=l.location_id WHERE 1=1";
$p = []; $ty = '';
if ($loc !== '')  { $sql .= " AND l.location_id=?";        $p[] = $loc;  $ty .= 's'; }
if ($from !== '') { $sql .= " AND DATE(r.reading_time)>=?"; $p[] = $from; $ty .= 's'; }
if ($to !== '')   { $sql .= " AND DATE(r.reading_time)<=?"; $p[] = $to;   $ty .= 's'; }
$sql .= " ORDER BY r.reading_time DESC";
$st = $conn->prepare($sql);
if ($p) $st->bind_param($ty, ...$p);
$st->execute();
$rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);

if (isset($_GET['export'])) {
    audit($conn, 'export_csv', 'report');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="readings_report_' . date('Ymd_His') . '.csv"');
    $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF");
    fputcsv($o, ['Location','Device','Sensor','Unit','Value','Time'], ',', '"', '\\');
    foreach ($rows as $r) fputcsv($o, [$r['location_name'], $r['device_name'], $r['sensor_name'], $r['unit'], $r['reading_value'], $r['reading_time']], ',', '"', '\\');
    exit;
}
$vals = array_column($rows, 'reading_value');
$qs = ['loc' => $loc, 'from' => $from, 'to' => $to];
include 'header.php';
?>
<h2>📊 Sensor Readings Report</h2>
<?php if ($print): ?>
  <p>Generated <?= date('Y-m-d H:i') ?> by <?= e($_SESSION['user']['name']) ?></p>
  <script>window.addEventListener('load', function(){ window.print(); });</script>
<?php else: ?>
<div class="card noprint">
  <form method="get" class="form-grid">
    <div><label>Location</label><select name="loc"><option value="">All</option>
      <?php $ls = $conn->query("SELECT location_id, location_name FROM location ORDER BY location_name");
      while ($l = $ls->fetch_assoc()): ?><option value="<?= e($l['location_id']) ?>" <?= $loc == $l['location_id'] ? 'selected' : '' ?>><?= e($l['location_name']) ?></option><?php endwhile; ?>
    </select></div>
    <div><label>From Date</label><input type="date" name="from" value="<?= e($from) ?>"></div>
    <div><label>To Date</label><input type="date" name="to" value="<?= e($to) ?>"></div>
    <div style="align-self:end"><button class="btn">🔍 Filter</button>
      <a class="btn o" href="report.php?<?= e(http_build_query($qs + ['export' => 1])) ?>">⬇️ Export CSV</a>
      <a class="btn b" target="_blank" href="report.php?<?= e(http_build_query($qs + ['print' => 1])) ?>">📄 Export PDF</a></div>
  </form>
</div>
<?php endif; ?>
<div class="grid">
  <div class="card stat"><div class="ic">🔢</div><div><b><?= count($rows) ?></b><span>Number of Readings</span></div></div>
  <div class="card stat"><div class="ic">📊</div><div><b><?= $vals ? round(array_sum($vals) / count($vals), 2) : '-' ?></b><span>Average</span></div></div>
  <div class="card stat"><div class="ic">⬆️</div><div><b><?= $vals ? max($vals) : '-' ?></b><span>Highest</span></div></div>
  <div class="card stat"><div class="ic">⬇️</div><div><b><?= $vals ? min($vals) : '-' ?></b><span>Lowest</span></div></div>
</div>
<div class="card wrap"><table>
  <tr><th>Location</th><th>Device</th><th>Sensor</th><th>Value</th><th>Time</th></tr>
  <?php foreach ($rows as $r):
      $bad = ($r['reading_value'] > $r['max_threshold'] || $r['reading_value'] < $r['min_threshold']); ?>
  <tr class="<?= $bad ? 'bad' : '' ?>"><td><?= e($r['location_name']) ?></td><td><?= e($r['device_name']) ?></td><td><?= e($r['sensor_name']) ?></td>
      <td><?= e($r['reading_value']) ?> <?= e($r['unit']) ?> <?= $bad ? '<span class="badge b-high">⚠ out of range</span>' : '' ?></td><td><?= e($r['reading_time']) ?></td></tr>
  <?php endforeach; if (!$rows) echo '<tr><td colspan="5">No data found.</td></tr>'; ?>
</table></div>
<?php include 'footer.php'; ?>