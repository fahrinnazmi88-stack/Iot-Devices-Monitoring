<?php
require 'config.php';
require_login();
$title = 'Audit Log';
include 'header.php';
if (!is_staff()) {
    echo '<h2>📜 Audit Log</h2><div class="msg er">⚠️ Only Staff accounts can view the audit log.</div>';
    include 'footer.php'; exit;
}
$q = trim($_GET['q'] ?? '');
$act = $_GET['act'] ?? '';
$PER = 20;

$where = []; $p = []; $ty = '';
if ($q !== '')   { $where[] = "(username LIKE ? OR table_name LIKE ? OR details LIKE ? OR record_id LIKE ?)"; array_push($p, "%$q%", "%$q%", "%$q%", "%$q%"); $ty .= 'ssss'; }
if ($act !== '') { $where[] = "action = ?"; $p[] = $act; $ty .= 's'; }
$w = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$st = $conn->prepare("SELECT COUNT(*) c FROM audit_log$w");
if ($p) $st->bind_param($ty, ...$p);
$st->execute();
$total = (int)$st->get_result()->fetch_assoc()['c'];
$pages = max(1, (int)ceil($total / $PER));
$pg = min(max(1, (int)($_GET['pg'] ?? 1)), $pages);

$st = $conn->prepare("SELECT * FROM audit_log$w ORDER BY log_id DESC LIMIT $PER OFFSET " . (($pg - 1) * $PER));
if ($p) $st->bind_param($ty, ...$p);
$st->execute();
$rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$acts = $conn->query("SELECT DISTINCT action FROM audit_log ORDER BY action")->fetch_all(MYSQLI_NUM);
$base = array_filter(['q' => $q, 'act' => $act], fn($v) => $v !== '');
?>
<h2>📜 Audit Log</h2>
<div class="card">
  <div class="toolbar">
    <form method="get">
      <input name="q" value="<?= e($q) ?>" placeholder="Search user, table, details...">
      <select name="act" style="max-width:180px"><option value="">All actions</option>
        <?php foreach ($acts as $a): ?><option value="<?= e($a[0]) ?>" <?= $act === $a[0] ? 'selected' : '' ?>><?= e($a[0]) ?></option><?php endforeach; ?>
      </select>
      <button class="btn">🔍 Filter</button>
      <?php if ($q !== '' || $act !== ''): ?><a class="btn o" href="audit.php">Reset</a><?php endif; ?>
    </form>
  </div>
  <div class="wrap"><table>
    <tr><th>#</th><th>Time</th><th>User</th><th>Action</th><th>Table</th><th>Record</th><th>Details</th></tr>
    <?php foreach ($rows as $r): ?>
    <tr class="<?= in_array($r['action'], ['delete', 'login_failed']) ? 'bad' : '' ?>">
      <td><?= e($r['log_id']) ?></td><td style="white-space:nowrap"><?= e($r['created_at']) ?></td><td><?= e($r['username']) ?></td>
      <td><span class="badge"><?= e($r['action']) ?></span></td><td><?= e($r['table_name'] ?? '') ?></td><td><?= e($r['record_id'] ?? '') ?></td>
      <td style="font-size:12px;color:#4b5563;max-width:380px;word-break:break-word"><?= e($r['details'] ?? '') ?></td>
    </tr>
    <?php endforeach; if (!$rows) echo '<tr><td colspan="7">No log entries.</td></tr>'; ?>
  </table></div>
  <small style="color:#667">Total: <?= $total ?> entries</small>
  <?php if ($pages > 1): ?>
  <div class="pager">
    <?php if ($pg > 1): ?><a href="audit.php?<?= e(http_build_query($base + ['pg' => $pg - 1])) ?>">« Prev</a><?php else: ?><span class="dis">« Prev</span><?php endif; ?>
    <?php for ($i = max(1, $pg - 2); $i <= min($pages, $pg + 2); $i++): ?>
      <?php if ($i == $pg): ?><span class="cur"><?= $i ?></span><?php else: ?><a href="audit.php?<?= e(http_build_query($base + ['pg' => $i])) ?>"><?= $i ?></a><?php endif; ?>
    <?php endfor; ?>
    <?php if ($pg < $pages): ?><a href="audit.php?<?= e(http_build_query($base + ['pg' => $pg + 1])) ?>">Next »</a><?php else: ?><span class="dis">Next »</span><?php endif; ?>
  </div>
  <?php endif; ?>
</div>
<?php include 'footer.php'; ?>