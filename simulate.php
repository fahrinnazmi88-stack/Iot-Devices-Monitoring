<?php
require 'config.php';
require_login();
$title = 'Reading Simulator';
$results = []; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = $_POST['mode'] ?? 'random';
    $target = $_POST['sensor_id'] ?? 'all';
    $sql = "SELECT s.*, d.device_id AS dev FROM sensor s JOIN device d ON s.device_id=d.device_id";
    if ($target !== 'all') $sql .= " WHERE s.sensor_id=" . (int)$target;
    $sensors = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

    foreach ($sensors as $s) {
        if ($s['status'] !== 'online') {
            $results[] = ['sensor' => $s['sensor_name'], 'value' => '-', 'unit' => '', 'result' => 'Skipped (sensor is ' . $s['status'] . ')', 'alert' => '-'];
            continue;
        }
        $min = (float)$s['min_threshold']; $max = (float)$s['max_threshold'];
        $span = max($max - $min, 1);
        $isBool = ($s['unit'] === 'bool');
        $roll = mt_rand(1, 100);
        if ($mode === 'high' || ($mode === 'random' && $roll <= 15)) {
            $v = $max + $span * mt_rand(5, 25) / 100;
        } elseif ($mode === 'low' || ($mode === 'random' && $roll <= 25)) {
            $v = $min - $span * mt_rand(5, 25) / 100;
        } else {
            $v = $min + $span * mt_rand(10, 90) / 100;
        }
        $v = $isBool ? (float)mt_rand(0, 1) : round($v, 2);
        if ($isBool) $v = min(max($v, $min), $max);
        $now = date('Y-m-d H:i:s');

        $st = $conn->prepare("INSERT INTO sensor_reading (sensor_id, reading_value, reading_time, quality, remarks) VALUES (?,?,?,'good','Simulated')");
        $st->bind_param('ids', $s['sensor_id'], $v, $now);
        $st->execute();
        $rid = $st->insert_id;
        $conn->query("UPDATE device SET last_seen=NOW() WHERE device_id=" . (int)$s['dev']);

        $alertTxt = 'none';
        if ($v > $max || $v < $min) {
            $type = $v > $max ? 'high' : 'low';
            $msg  = $s['sensor_name'] . ' reading ' . $v . ' ' . $s['unit'] . ($type === 'high' ? ' exceeded max limit ' . $max : ' fell below min limit ' . $min);
            $ia = $conn->prepare("INSERT INTO alert (sensor_id, reading_id, alert_type, alert_message, alert_status) VALUES (?,?,?,?, 'open')");
            $ia->bind_param('iiss', $s['sensor_id'], $rid, $type, $msg);
            $ia->execute();
            $alertTxt = strtoupper($type) . ' alert #' . $ia->insert_id;
            audit($conn, 'alert_auto', 'alert', $ia->insert_id, $msg);
        }
        audit($conn, 'simulate', 'sensor_reading', $rid, $s['sensor_name'] . ' = ' . $v . ' ' . $s['unit']);
        $results[] = ['sensor' => $s['sensor_name'], 'value' => $v, 'unit' => $s['unit'], 'result' => 'Reading #' . $rid . ' inserted', 'alert' => $alertTxt, 'bad' => $alertTxt !== 'none'];
    }
}
$list = $conn->query("SELECT sensor_id, sensor_name, status FROM sensor ORDER BY sensor_name");
include 'header.php';
?>
<h2>🎲 Reading Simulator</h2>
<div class="card">
  <p style="margin-top:0;color:#6b7280">Generates test readings as if a real IoT device sent them. If a value goes outside the sensor's min/max threshold, an alert is created automatically.</p>
  <form method="post" class="form-grid">
    <div><label>Sensor</label>
      <select name="sensor_id">
        <option value="all">All online sensors</option>
        <?php while ($s = $list->fetch_assoc()): ?>
          <option value="<?= e($s['sensor_id']) ?>" <?= (($_POST['sensor_id'] ?? '') == $s['sensor_id']) ? 'selected' : '' ?>><?= e($s['sensor_name']) ?> (<?= e($s['status']) ?>)</option>
        <?php endwhile; ?>
      </select></div>
    <div><label>Mode</label>
      <select name="mode">
        <option value="random">Random (mostly normal, sometimes abnormal)</option>
        <option value="normal">Always normal</option>
        <option value="high">Force HIGH reading</option>
        <option value="low">Force LOW reading</option>
      </select></div>
    <div style="align-self:end"><button class="btn g">▶ Generate Readings</button></div>
  </form>
</div>

<?php if ($results): ?>
<div class="card wrap">
  <h3>Result</h3>
  <table>
    <tr><th>Sensor</th><th>Value</th><th>Result</th><th>Alert</th></tr>
    <?php foreach ($results as $r): ?>
    <tr class="<?= !empty($r['bad']) ? 'bad' : '' ?>"><td><?= e($r['sensor']) ?></td><td><b><?= e($r['value']) ?></b> <?= e($r['unit']) ?></td><td><?= e($r['result']) ?></td><td><?= e($r['alert']) ?></td></tr>
    <?php endforeach; ?>
  </table>
  <p><a class="btn" href="crud.php?t=sensor_reading">View Readings</a> <a class="btn b" href="crud.php?t=alert">View Alerts</a> <a class="btn o" href="index.php">Dashboard</a></p>
</div>
<?php endif; ?>
<?php include 'footer.php'; ?>