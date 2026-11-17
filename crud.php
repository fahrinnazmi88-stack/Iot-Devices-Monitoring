<?php
require 'config.php';
require_login();
require 'tables.php';

$t = $_GET['t'] ?? 'device';
if (!isset($TABLES[$t])) $t = 'device';
$cfg = $TABLES[$t]; $pk = $cfg['pk']; $fields = $cfg['fields'];
$a = $_GET['a'] ?? 'list';
$q = trim($_GET['q'] ?? '');
$err = ''; $form = [];
$PER = 10;                              // records per page
$printMode = isset($_GET['print']);     // "Export PDF" view (all rows, opens print dialog)

// ---- Build list query (JOIN for FKs) + keyword search ----
function list_query($t, $cfg, $q) {
    $joins = ''; $sel = 't.*'; $conds = []; $i = 0;
    foreach ($cfg['fields'] as $f => $d) {
        if ($d['type'] === 'fk') {
            $i++; $al = "j$i";
            $joins .= " LEFT JOIN {$d['table']} $al ON t.$f = $al.{$d['id']}";
            $sel   .= ", $al.{$d['show']} AS {$f}__label";
            $conds[] = "$al.{$d['show']} LIKE ?";
        } elseif (in_array($d['type'], ['text', 'select'])) {
            $conds[] = "t.$f LIKE ?";
        }
    }
    // thresholds of the sensor (j1) so abnormal readings can be highlighted
    if ($t === 'sensor_reading') $sel .= ", j1.min_threshold AS _min, j1.max_threshold AS _max";
    $sql = "SELECT $sel FROM $t t $joins";
    $params = [];
    if ($q !== '') {
        $sql .= ' WHERE ' . implode(' OR ', $conds);
        $params = array_fill(0, count($conds), "%$q%");
    }
    $sql .= " ORDER BY t.{$cfg['pk']} DESC";
    return [$sql, $params];
}
function run($conn, $sql, $params = []) {
    $st = $conn->prepare($sql);
    if ($params) $st->bind_param(str_repeat('s', count($params)), ...$params);
    $st->execute();
    return $st;
}

// ---- C5: Export CSV ----
if ($a === 'export') {
    list($sql, $p) = list_query($t, $cfg, $q);
    $res = run($conn, $sql, $p)->get_result();
    audit($conn, 'export_csv', $t, null, $q !== '' ? "search: $q" : null);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $t . '_' . date('Ymd_His') . '.csv"');
    $o = fopen('php://output', 'w');
    fwrite($o, "\xEF\xBB\xBF");
    $head = ['ID'];
    foreach ($fields as $f => $d) $head[] = $d['label'];
    fputcsv($o, $head, ',', '"', '\\');
    while ($r = $res->fetch_assoc()) {
        $row = [$r[$pk]];
        foreach ($fields as $f => $d) $row[] = ($d['type'] === 'fk') ? $r[$f . '__label'] : $r[$f];
        fputcsv($o, $row, ',', '"', '\\');
    }
    exit;
}

// ---- Create / Update / Delete / Resolve (POST) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $do = $_POST['do'] ?? '';
    $backPg = (int)($_POST['pg'] ?? 1);
    if ($do === 'delete') {
        if (!is_staff()) {
            $err = 'The Admin role is not allowed to delete records. Please use a Staff account.';
        } else {
            try {
                $old = run($conn, "SELECT * FROM $t WHERE $pk=?", [$_POST['id']])->get_result()->fetch_assoc();
                run($conn, "DELETE FROM $t WHERE $pk=?", [$_POST['id']]);
                audit($conn, 'delete', $t, $_POST['id'], json_encode($old));
                header("Location: crud.php?t=$t&pg=$backPg&msg=del"); exit;
            } catch (Throwable $ex) {
                $err = 'This record cannot be deleted because it is still used by another table.';
            }
        }
    } elseif ($do === 'resolve' && $t === 'alert') {
        run($conn, "UPDATE alert SET alert_status='resolved', resolved_by=? WHERE alert_id=? AND alert_status<>'resolved'",
            [$_SESSION['user']['id'], $_POST['id']]);
        audit($conn, 'resolve', 'alert', $_POST['id'], 'Alert marked as resolved');
        header("Location: crud.php?t=alert&pg=$backPg&msg=res"); exit;
    } elseif ($do === 'save') {
        $id = $_POST['id'] ?? ''; $vals = [];
        foreach ($fields as $f => $d) {
            $v = trim($_POST[$f] ?? '');
            if ($d['type'] === 'datetime') $v = str_replace('T', ' ', $v);
            if ($v === '') {
                if (!empty($d['req'])) { $err = "The field \"{$d['label']}\" is required."; break; }
                $v = null;
            }
            $vals[] = $v;
        }
        $form = $_POST; $a = ($id === '' ? 'add' : 'edit');
        if (!$err) {
            try {
                $cols = array_keys($fields);
                if ($id === '') {
                    $sql = "INSERT INTO $t (" . implode(',', $cols) . ") VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ")";
                    $st = run($conn, $sql, $vals);
                    audit($conn, 'create', $t, $st->insert_id, json_encode(array_combine($cols, $vals)));
                } else {
                    $sql = "UPDATE $t SET " . implode('=?,', $cols) . "=? WHERE $pk=?";
                    $vals2 = $vals; $vals2[] = $id;
                    run($conn, $sql, $vals2);
                    audit($conn, 'update', $t, $id, json_encode(array_combine($cols, $vals)));
                }
                header("Location: crud.php?t=$t&msg=" . ($id === '' ? 'add' : 'upd')); exit;
            } catch (Throwable $ex) {
                $err = (strpos($ex->getMessage(), 'Duplicate') !== false)
                    ? 'Duplicate data: a UNIQUE value (e.g. code / MAC / name) already exists.'
                    : 'Database error: ' . $ex->getMessage();
            }
        }
    }
}

// Load record for editing
if ($a === 'edit' && !$form) {
    $form = run($conn, "SELECT * FROM $t WHERE $pk=?", [$_GET['id'] ?? 0])->get_result()->fetch_assoc() ?: [];
    if (!$form) { $a = 'list'; }
}

$msgs = ['add'=>'Record added successfully.', 'upd'=>'Record updated successfully.', 'del'=>'Record deleted successfully.', 'res'=>'Alert marked as resolved.'];
$title = $cfg['title'];
include 'header.php';
?>
<h2><?= e($cfg['title']) ?></h2>
<?php if (isset($_GET['msg'], $msgs[$_GET['msg']])): ?><div class="msg ok noprint">✅ <?= $msgs[$_GET['msg']] ?></div><?php endif; ?>
<?php if ($err): ?><div class="msg er">⚠️ <?= e($err) ?></div><?php endif; ?>

<?php if ($a === 'add' || $a === 'edit'): ?>
  <div class="card">
    <h3><?= $a === 'add' ? 'Add' : 'Update' ?> Record</h3>
    <form method="post" action="crud.php?t=<?= e($t) ?>">
      <input type="hidden" name="do" value="save">
      <input type="hidden" name="id" value="<?= $a === 'edit' ? e($form[$pk] ?? $form['id'] ?? '') : '' ?>">
      <div class="form-grid">
      <?php foreach ($fields as $f => $d):
          $val = $form[$f] ?? '';
          if ($d['type'] === 'datetime') {
              if ($val === '' && $a === 'add') $val = date('Y-m-d\TH:i');
              $val = str_replace(' ', 'T', substr($val, 0, 16));
          }
          if ($d['type'] === 'date' && $val === '' && $a === 'add') $val = date('Y-m-d');
      ?>
        <div>
          <label><?= e($d['label']) ?><?= !empty($d['req']) ? ' *' : '' ?></label>
          <?php if ($d['type'] === 'select'): ?>
            <select name="<?= $f ?>">
              <?php foreach ($d['options'] as $o): ?><option value="<?= e($o) ?>" <?= $val === $o ? 'selected' : '' ?>><?= e($o) ?></option><?php endforeach; ?>
            </select>
          <?php elseif ($d['type'] === 'fk'): ?>
            <select name="<?= $f ?>">
              <option value="">-- Select --</option>
              <?php $rs = $conn->query("SELECT {$d['id']} AS i, {$d['show']} AS s FROM {$d['table']} ORDER BY {$d['show']}");
                    while ($o = $rs->fetch_assoc()): ?>
                <option value="<?= e($o['i']) ?>" <?= (string)$val === (string)$o['i'] ? 'selected' : '' ?>><?= e($o['s']) ?></option>
              <?php endwhile; ?>
            </select>
          <?php else:
              $type = ['number'=>'number','date'=>'date','datetime'=>'datetime-local'][$d['type']] ?? 'text'; ?>
            <input type="<?= $type ?>" name="<?= $f ?>" value="<?= e($val) ?>" <?= $d['type'] === 'number' ? 'step="any"' : '' ?>>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      </div>
      <p><button class="btn g">💾 Save</button> <a class="btn" href="crud.php?t=<?= e($t) ?>">Cancel</a></p>
    </form>
  </div>

<?php else:
  list($sql, $p) = list_query($t, $cfg, $q);
  // total + pagination
  $total = (int)run($conn, "SELECT COUNT(*) c FROM ($sql) x", $p)->get_result()->fetch_assoc()['c'];
  $pages = max(1, (int)ceil($total / $PER));
  $pg    = min(max(1, (int)($_GET['pg'] ?? 1)), $pages);
  if (!$printMode) $sql .= " LIMIT $PER OFFSET " . (($pg - 1) * $PER);
  $res = run($conn, $sql, $p)->get_result();
  $base = ['t' => $t] + ($q !== '' ? ['q' => $q] : []);
?>
  <?php if ($printMode): ?>
    <p>Generated <?= date('Y-m-d H:i') ?> by <?= e($_SESSION['user']['name']) ?><?= $q !== '' ? ' | Search: ' . e($q) : '' ?></p>
    <script>window.addEventListener('load', function(){ window.print(); });</script>
  <?php endif; ?>
  <div class="card">
    <div class="toolbar noprint">
      <form method="get">
        <input type="hidden" name="t" value="<?= e($t) ?>">
        <input name="q" value="<?= e($q) ?>" placeholder="Search keyword...">
        <button class="btn">🔍 Search</button>
        <?php if ($q !== ''): ?><a class="btn o" href="crud.php?t=<?= e($t) ?>">Reset</a><?php endif; ?>
      </form>
      <a class="btn g" href="crud.php?t=<?= e($t) ?>&a=add">➕ Add New</a>
      <a class="btn o" href="crud.php?<?= e(http_build_query($base + ['a' => 'export'])) ?>">⬇️ Export CSV</a>
      <a class="btn b" target="_blank" href="crud.php?<?= e(http_build_query($base + ['print' => 1])) ?>">📄 Export PDF</a>
    </div>
    <div class="wrap">
    <table>
      <tr><th>ID</th><?php foreach ($fields as $d): ?><th><?= e($d['label']) ?></th><?php endforeach; ?><th class="noprint">Actions</th></tr>
      <?php $n = 0; while ($r = $res->fetch_assoc()): $n++;
          $bad = '';
          if ($t === 'sensor_reading' && $r['_min'] !== null) {
              if ((float)$r['reading_value'] > (float)$r['_max']) $bad = 'HIGH';
              elseif ((float)$r['reading_value'] < (float)$r['_min']) $bad = 'LOW';
          } ?>
      <tr class="<?= $bad ? 'bad' : '' ?>">
        <td><?= e($r[$pk]) ?></td>
        <?php foreach ($fields as $f => $d): ?>
          <td>
          <?php if ($t === 'sensor_reading' && $f === 'reading_value'): ?>
            <b><?= e($r[$f]) ?></b> <?php if ($bad): ?><span class="badge b-<?= strtolower($bad) ?>">⚠ <?= $bad ?></span><?php endif; ?>
          <?php elseif (in_array($f, ['status','alert_status','alert_type','quality'])): ?>
            <span class="badge b-<?= e($r[$f]) ?>"><?= e($r[$f]) ?></span>
          <?php else: ?>
            <?= e($d['type'] === 'fk' ? $r[$f . '__label'] : $r[$f]) ?>
          <?php endif; ?>
          </td>
        <?php endforeach; ?>
        <td class="noprint" style="white-space:nowrap">
          <?php if ($t === 'alert' && $r['alert_status'] !== 'resolved'): ?>
          <form class="inline" method="post" action="crud.php?t=alert">
            <input type="hidden" name="do" value="resolve"><input type="hidden" name="id" value="<?= e($r[$pk]) ?>"><input type="hidden" name="pg" value="<?= $pg ?>">
            <button class="btn s b">✔ Resolve</button>
          </form>
          <?php endif; ?>
          <a class="btn s g" href="crud.php?t=<?= e($t) ?>&a=edit&id=<?= e($r[$pk]) ?>">✏️ Edit</a>
          <?php if (is_staff()): ?>
          <form class="inline" method="post" action="crud.php?t=<?= e($t) ?>" onsubmit="return confirm('Are you sure you want to delete this record?');">
            <input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= e($r[$pk]) ?>"><input type="hidden" name="pg" value="<?= $pg ?>">
            <button class="btn s r">🗑️ Delete</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endwhile; ?>
      <?php if ($n === 0): ?><tr><td colspan="<?= count($fields) + 2 ?>">No records found.</td></tr><?php endif; ?>
    </table>
    </div>
    <?php $from = $total ? ($printMode ? 1 : ($pg - 1) * $PER + 1) : 0; $to = $printMode ? $total : min($total, $pg * $PER); ?>
    <small style="color:#667">Showing <?= $from ?>-<?= $to ?> of <?= $total ?> record(s)</small>

    <?php if (!$printMode && $pages > 1): ?>
    <div class="pager noprint">
      <?php if ($pg > 1): ?><a href="crud.php?<?= e(http_build_query($base + ['pg' => $pg - 1])) ?>">« Prev</a><?php else: ?><span class="dis">« Prev</span><?php endif; ?>
      <?php for ($i = max(1, $pg - 2); $i <= min($pages, $pg + 2); $i++): ?>
        <?php if ($i == $pg): ?><span class="cur"><?= $i ?></span><?php else: ?><a href="crud.php?<?= e(http_build_query($base + ['pg' => $i])) ?>"><?= $i ?></a><?php endif; ?>
      <?php endfor; ?>
      <?php if ($pg < $pages): ?><a href="crud.php?<?= e(http_build_query($base + ['pg' => $pg + 1])) ?>">Next »</a><?php else: ?><span class="dis">Next »</span><?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
<?php endif; ?>
<?php include 'footer.php'; ?>