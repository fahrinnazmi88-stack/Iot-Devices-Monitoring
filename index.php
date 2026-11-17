<?php
require 'config.php';
require_login();
$title = 'Dashboard';

function one($conn, $sql) { return $conn->query($sql)->fetch_assoc(); }
$avgTemp = one($conn, "SELECT ROUND(AVG(r.reading_value),2) v FROM sensor_reading r JOIN sensor s ON r.sensor_id=s.sensor_id WHERE s.sensor_type='temperature'")['v'];
$maxHum  = one($conn, "SELECT r.reading_value v, l.location_name n FROM sensor_reading r JOIN sensor s ON r.sensor_id=s.sensor_id JOIN device d ON s.device_id=d.device_id JOIN location l ON d.location_id=l.location_id WHERE s.sensor_type='humidity' ORDER BY r.reading_value DESC LIMIT 1");

$stats = [
  ['Devices',        '📟', one($conn, "SELECT COUNT(*) c FROM device")['c']],
  ['Sensors',        '🌡️', one($conn, "SELECT COUNT(*) c FROM sensor")['c']],
  ['Readings',       '📈', one($conn, "SELECT COUNT(*) c FROM sensor_reading")['c']],
  ['Open Alerts',    '🚨', one($conn, "SELECT COUNT(*) c FROM alert WHERE alert_status='open'")['c']],
  ['Offline Sensors','📴', one($conn, "SELECT COUNT(*) c FROM sensor WHERE status<>'online'")['c']],
  ['Average Temperature', '🔥', ($avgTemp ?? '-') . ' °C'],
  ['Highest Humidity (' . ($maxHum['n'] ?? '-') . ')', '💧', ($maxHum['v'] ?? '-') . ' %'],
];

$latest  = $conn->query("SELECT v.*, s.min_threshold, s.max_threshold FROM v_latest_reading v JOIN sensor s ON v.sensor_id=s.sensor_id ORDER BY v.reading_time DESC LIMIT 8");
$alerts  = $conn->query("SELECT a.alert_type, a.alert_message, s.sensor_name FROM alert a JOIN sensor s ON a.sensor_id=s.sensor_id WHERE a.alert_status='open' ORDER BY a.created_at DESC LIMIT 5");
$offline = $conn->query("SELECT s.sensor_name, s.status, d.device_name FROM sensor s JOIN device d ON s.device_id=d.device_id WHERE s.status<>'online'");

// Chart data
$tl = []; $tv = [];
$rs = $conn->query("SELECT sensor_type, COUNT(*) c FROM sensor GROUP BY sensor_type ORDER BY c DESC");
while ($r = $rs->fetch_assoc()) { $tl[] = $r['sensor_type']; $tv[] = (int)$r['c']; }

$al = []; $av = [];
$rs = $conn->query("SELECT alert_status, COUNT(*) c FROM alert GROUP BY alert_status");
while ($r = $rs->fetch_assoc()) { $al[] = $r['alert_status']; $av[] = (int)$r['c']; }

$times = []; $series = [];
$rs = $conn->query("SELECT s.sensor_name n, r.reading_time t, r.reading_value v FROM sensor_reading r JOIN sensor s ON r.sensor_id=s.sensor_id WHERE s.sensor_type='temperature' ORDER BY r.reading_time");
while ($r = $rs->fetch_assoc()) { $times[$r['t']] = 1; $series[$r['n']][$r['t']] = (float)$r['v']; }
$labels = array_keys($times); sort($labels);
$datasets = [];
foreach ($series as $name => $pts) {
    $d = [];
    foreach ($labels as $l) $d[] = $pts[$l] ?? null;
    $datasets[] = ['label' => $name, 'data' => $d, 'tension' => 0.35, 'spanGaps' => true, 'borderWidth' => 2];
}
include 'header.php';
?>
<div class="toolbar" style="justify-content:space-between">
  <h2 style="margin:0">🏠 Dashboard</h2>
  <label style="margin:0;font-weight:500;cursor:pointer"><input type="checkbox" id="auto" style="width:auto;vertical-align:middle"> Auto refresh (30s) &middot; <small id="stamp">updated <?= date('H:i:s') ?></small></label>
</div>

<div class="grid">
  <?php foreach ($stats as $s): ?>
  <div class="card stat"><div class="ic"><?= $s[1] ?></div><div><b><?= e($s[2]) ?></b><span><?= e($s[0]) ?></span></div></div>
  <?php endforeach; ?>
</div>

<div class="charts">
  <div class="card" style="grid-column:1/-1">
    <h3>📈 Temperature Reading Trend</h3>
    <div class="chart-box"><canvas id="cTrend"></canvas></div>
  </div>
  <div class="card"><h3>🌡️ Sensors by Type</h3><div class="chart-box"><canvas id="cType"></canvas></div></div>
  <div class="card"><h3>🚨 Alerts by Status</h3><div class="chart-box"><canvas id="cAlert"></canvas></div></div>
</div>

<div class="card">
  <h3>⏱️ Latest Reading per Sensor</h3>
  <div class="wrap"><table>
    <tr><th>Sensor</th><th>Device</th><th>Location</th><th>Value</th><th>Time</th></tr>
    <?php while ($r = $latest->fetch_assoc()):
        $bad = ($r['reading_value'] > $r['max_threshold'] || $r['reading_value'] < $r['min_threshold']); ?>
    <tr class="<?= $bad ? 'bad' : '' ?>"><td><?= e($r['sensor_name']) ?></td><td><?= e($r['device_name']) ?></td><td><?= e($r['location_name']) ?></td>
        <td><b><?= e($r['reading_value']) ?></b> <?= e($r['unit']) ?> <?= $bad ? '<span class="badge b-high">⚠ out of range</span>' : '' ?></td><td><?= e($r['reading_time']) ?></td></tr>
    <?php endwhile; ?>
  </table></div>
</div>

<div class="charts">
  <div class="card">
    <h3>🚨 Open Alerts</h3>
    <table>
      <?php $any = false; while ($r = $alerts->fetch_assoc()): $any = true; ?>
      <tr><td><span class="badge b-<?= e($r['alert_type']) ?>"><?= e($r['alert_type']) ?></span></td>
          <td><?= e($r['sensor_name']) ?><br><small style="color:#6b7280"><?= e($r['alert_message']) ?></small></td></tr>
      <?php endwhile; if (!$any) echo '<tr><td>✅ No open alerts.</td></tr>'; ?>
    </table>
  </div>
  <div class="card">
    <h3>📴 Offline / Faulty Sensors</h3>
    <table>
      <?php $any = false; while ($r = $offline->fetch_assoc()): $any = true; ?>
      <tr><td><?= e($r['sensor_name']) ?></td><td><?= e($r['device_name']) ?></td>
          <td><span class="badge b-<?= e($r['status']) ?>"><?= e($r['status']) ?></span></td></tr>
      <?php endwhile; if (!$any) echo '<tr><td>✅ All sensors are online.</td></tr>'; ?>
    </table>
  </div>
</div>

<script>
// Auto refresh (remembered in this browser)
(function () {
  var box = document.getElementById('auto'), timer = null;
  function apply() {
    clearTimeout(timer);
    if (box.checked) timer = setTimeout(function () { location.reload(); }, 30000);
  }
  try { box.checked = localStorage.getItem('autoRefresh') === '1'; } catch (e) {}
  box.addEventListener('change', function () {
    try { localStorage.setItem('autoRefresh', box.checked ? '1' : '0'); } catch (e) {}
    apply();
  });
  apply();
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
if (typeof Chart !== 'undefined') {
  const pal = ['#0f766e','#14b8a6','#5eead4','#f59e0b','#ef4444','#6366f1','#0ea5e9'];
  const opt = {responsive:true, maintainAspectRatio:false, plugins:{legend:{position:'bottom'}}};

  new Chart(document.getElementById('cTrend'), {
    type: 'line',
    data: {
      labels: <?= json_encode($labels) ?>,
      datasets: <?= json_encode($datasets) ?>.map((d, i) => ({...d, borderColor: pal[i % pal.length], backgroundColor: pal[i % pal.length]}))
    },
    options: opt
  });
  new Chart(document.getElementById('cType'), {
    type: 'doughnut',
    data: {labels: <?= json_encode($tl) ?>, datasets: [{data: <?= json_encode($tv) ?>, backgroundColor: pal}]},
    options: opt
  });
  new Chart(document.getElementById('cAlert'), {
    type: 'bar',
    data: {labels: <?= json_encode($al) ?>, datasets: [{label: 'Number of alerts', data: <?= json_encode($av) ?>, backgroundColor: ['#ef4444','#f59e0b','#10b981'], borderRadius: 8}]},
    options: {...opt, plugins: {legend: {display: false}}, scales: {y: {beginAtZero: true, ticks: {precision: 0}}}}
  });
}
</script>
<?php include 'footer.php'; ?>