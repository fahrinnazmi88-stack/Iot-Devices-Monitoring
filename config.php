<?php
// ===== Database connection (Laragon) =====
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli('localhost', 'root', 'root', 'iot_monitoring');
$conn->set_charset('utf8mb4');

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function require_login() {
    if (empty($_SESSION['user'])) { header('Location: login.php'); exit; }
}
function is_staff() { return ($_SESSION['user']['role'] ?? '') === 'staff'; }

// Write one line to the audit log (a logging problem never breaks the page)
function audit($conn, $action, $table = null, $rid = null, $details = null, $username = null) {
    try {
        $uid  = $_SESSION['user']['id'] ?? null;
        $name = $username ?? ($_SESSION['user']['username'] ?? 'unknown');
        $rid  = $rid === null ? null : (string)$rid;
        $details = $details === null ? null : substr((string)$details, 0, 250);
        $st = $conn->prepare("INSERT INTO audit_log (user_id, username, action, table_name, record_id, details) VALUES (?,?,?,?,?,?)");
        $st->bind_param('isssss', $uid, $name, $action, $table, $rid, $details);
        $st->execute();
    } catch (Throwable $ex) { /* ignore */ }
}

// Returns 'high', 'low' or '' depending on the sensor thresholds
function reading_flag($value, $min, $max) {
    if ($min === null || $max === null) return '';
    if ((float)$value > (float)$max) return 'high';
    if ((float)$value < (float)$min) return 'low';
    return '';
}

// Printable page: the browser opens the print dialog -> choose "Save as PDF"
function print_page($title, $headers, $rows) {
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . e($title) . '</title>';
    echo '<style>body{font-family:Arial,sans-serif;margin:24px;color:#000}h2{margin:0 0 4px}small{color:#444}';
    echo 'table{border-collapse:collapse;width:100%;margin-top:14px;font-size:12px}';
    echo 'th,td{border:1px solid #444;padding:5px 7px;text-align:left}th{background:#e5e5e5}';
    echo '.noprint{margin-bottom:12px}@media print{.noprint{display:none}}</style></head><body>';
    echo '<div class="noprint"><button onclick="window.print()">Print / Save as PDF</button> <button onclick="window.close()">Close</button></div>';
    echo '<h2>' . e($title) . '</h2><small>IoT Device Monitoring System &middot; Generated ' . date('Y-m-d H:i') . ' by ' . e($_SESSION['user']['name'] ?? '') . '</small>';
    echo '<table><tr>';
    foreach ($headers as $h) echo '<th>' . e($h) . '</th>';
    echo '</tr>';
    foreach ($rows as $r) { echo '<tr>'; foreach ($r as $c) echo '<td>' . e($c) . '</td>'; echo '</tr>'; }
    echo '</table><script>window.onload=function(){window.print();}</script></body></html>';
}