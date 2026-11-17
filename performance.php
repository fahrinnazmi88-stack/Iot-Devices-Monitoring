<?php
require 'config.php';
require_login();
$title = 'Sensor Performance';
$print = isset($_GET['print']);

$sql = "SELECT s.sensor_id, s.sensor_name, s.sensor_type, s.unit, s.status, s.min_threshold, s.max_threshold,
               d.device_name, l.location_name,
               COUNT(r.reading_id) AS total,
               ROUND(AVG(r.reading_value),2) AS avg_val,
               MIN(r.reading_value) AS min_val,
               MAX(r.reading_value) AS max_val,
               COALESCE(SUM(r.reading_value > s.max_threshold OR r.reading_value < s.min_threshold),0) AS out_range,
               MAX(r.reading_time) AS last_time,
               (SELECT COUNT(*) FROM alert a WHERE a.sensor_id = s.sensor_id) AS alerts
        FROM sensor s
        JOIN device d   ON s.device_id = d.device_id
        JOIN location l ON d.location_id = l.location_id
        LEFT JOIN sensor_reading r ON r.sensor_id = s.sensor_id
        GROUP BY s.sensor_id, s.sensor_name, s.sensor_type, s.unit, s.status, s.min_threshold, s.max_threshold, d.device_name, l.location_name
        ORDER BY s.sensor_id";
$rows = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

foreach ($rows as &$r) {
    if ($r['total'] > 0) {
        $r['score'] = round(100 * ($r['total'] - $r['out_range']) / $r['total'], 1);
        $r['rating'] = $r['status'] !== 'online' ? 'Poor' : ($r['score'] >= 90 ? 'Excellent' : ($r['score'] >= 70 ? 'Good' : 'Poor'));
    } else {
        $r['score'] = null; $r['rating'] = 'No data';
    }
}
unset($r);

if (isset($_GET['export'])) {
    audit($conn, 'export_csv', 'performance');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sensor_performance_' . date('Ymd_His') . '.csv"');
    $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF");
    fputcsv($o, ['Sensor','Device','Location','Status','Readings','Average','Min','Max','Out of range','Within range %','Alerts','Last reading','Rating'], ',', '"', '\\');
    foreach ($rows as $r) fputcsv($o, [$r['sensor_name'],$r['device_name'],$r['location_name'],$r['status'],$r['total'],$r['avg_val'],$r['min_val'],$r['max_val'],$r['out_range'],$r['score'],$r['alerts'],$r['last_time'],$r['rating']], ',', '"', '\\');
    exit;
}

$withData = array_values(array_filter($rows, fn($r) => $r['total'] > 0));
$best  = $withData ? array_reduce($withData, fn($c, $r) => (!$c || $r['score'] > $c['score']) ? $r : $c) : null;
$worst = $withData ? array_reduce($withData, fn($c, $r) => (!$c || $r['score'] < $c['score']) ? $r : $c) : null;
$avgScore = $withData ? round(array_sum(array_column($withData, 'score')) / count($withData), 1) : null;
include 'header.php';
?>
<div class="toolbar" style="justify-content:space-between">
  <h2 style="margin:0">🏆 Sensor Performance Report</h2>
  <div class="noprint">
    <a class="btn o" href="performance.php?export=1">⬇️ Export CSV</a>
    <a class="btn b" target="_blank" href="performance.php?print=1">📄 Export PDF</a>
  </div>
</div>
<?php if ($print): ?>
  <p>Generated <?= date('Y-m-d H:i') ?> by <?= e($_SESSION['user']['name']) ?></p>
  <script>window.addEventListener('load', function(){ window.print(); });</script>
<?php endif; ?>

<div class="grid">
  <div class="card stat"><div class="ic">📡</div><div><b><?= count($rows) ?></b><span>Sensors evaluated</span></div></div>
  <div class="card stat"><div class="ic">✅</div><div><b><?= $avgScore !== null ? $avgScore . '%' : '-' ?></b><span>Average within-range score</span></div></div>
  <div class="card stat"><div class="ic">🥇</div><div><b style="font-size:16px"><?= e($best['sensor_name'] ?? '-') ?></b><span>Best performer (<?= e($best['score'] ?? '-') ?>%)</span></div></div>
  <div class="card stat"><div class="ic">⚠️</div><div><b style="font-size:16px"><?= e($worst['sensor_name'] ?? '-') ?></b><span>Needs attention (<?= e($worst['score'] ?? '-') ?>%)</span></div></div>
</div>

<div class="card wrap">
  <table>
    <tr><th>Sensor</th><th>Device / Location</th><th>Status</th><th>Readings</th><th>Avg</th><th>Min</th><th>Max</th><th>Out of range</th><th>Within range</th><th>Alerts</th><th>Rating</th></tr>
    <?php foreach ($rows as $r): ?>
    <tr class="<?= $r['rating'] === 'Poor' ? 'bad' : '' ?>">
      <td><b><?= e($r['sensor_name']) ?></b><br><small style="color:#6b7280"><?= e($r['min_threshold']) ?>–<?= e($r['max_threshold']) ?> <?= e($r['unit']) ?></small></td>
      <td><?= e($r['device_name']) ?><br><small style="color:#6b7280"><?= e($r['location_name']) ?></small></td>
      <td><span class="badge b-<?= e($r['status']) ?>"><?= e($r['status']) ?></span></td>
      <td><?= e($r['total']) ?></td>
      <td><?= e($r['avg_val'] ?? '-') ?></td><td><?= e($r['min_val'] ?? '-') ?></td><td><?= e($r['max_val'] ?? '-') ?></td>
      <td><?= e($r['out_range']) ?></td>
      <td style="min-width:120px"><?php if ($r['score'] !== null): ?><?= e($r['score']) ?>%<div class="meter"><i style="width:<?= e($r['score']) ?>%"></i></div><?php else: ?>-<?php endif; ?></td>
      <td><?= e($r['alerts']) ?></td>
      <td><span class="badge b-<?= $r['rating'] === 'No data' ? 'nodata' : strtolower($r['rating']) ?>"><?= e($r['rating']) ?></span></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <small style="color:#667">Within range = readings between the sensor's min and max threshold. Rating: Excellent &ge; 90%, Good &ge; 70%, otherwise Poor (offline or faulty sensors are always Poor).</small>
</div>
<?php include 'footer.php'; ?>