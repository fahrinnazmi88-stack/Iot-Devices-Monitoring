<?php
require 'config.php';
require_login();
$title = 'Change Password';
$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cur = $_POST['current'] ?? ''; $new = $_POST['new'] ?? ''; $cf = $_POST['confirm'] ?? '';
    $st = $conn->prepare("SELECT password_hash FROM user WHERE user_id=?");
    $st->bind_param('i', $_SESSION['user']['id']);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    if (!$row || !password_verify($cur, $row['password_hash'])) {
        $err = 'Current password is incorrect.';
    } elseif (strlen($new) < 6) {
        $err = 'New password must be at least 6 characters.';
    } elseif ($new !== $cf) {
        $err = 'New password and confirmation do not match.';
    } elseif ($new === $cur) {
        $err = 'New password must be different from the current password.';
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $up = $conn->prepare("UPDATE user SET password_hash=? WHERE user_id=?");
        $up->bind_param('si', $hash, $_SESSION['user']['id']);
        $up->execute();
        audit($conn, 'password_change', 'user', $_SESSION['user']['id'], 'Password changed');
        $msg = 'Password changed successfully.';
    }
}
include 'header.php';
?>
<h2>👤 My Account</h2>
<div class="card" style="max-width:460px">
  <p style="margin-top:0"><b><?= e($_SESSION['user']['name']) ?></b> &middot; <?= e($_SESSION['user']['username']) ?> &middot; <span class="badge"><?= e($_SESSION['user']['role']) ?></span></p>
  <h3>🔒 Change Password</h3>
  <?php if ($msg): ?><div class="msg ok">✅ <?= e($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="msg er">⚠️ <?= e($err) ?></div><?php endif; ?>
  <form method="post">
    <label>Current password</label><input type="password" name="current" required>
    <label style="margin-top:12px">New password</label><input type="password" name="new" required minlength="6">
    <label style="margin-top:12px">Confirm new password</label><input type="password" name="confirm" required minlength="6">
    <p><button class="btn g">💾 Update Password</button></p>
  </form>
</div>
<?php include 'footer.php'; ?>