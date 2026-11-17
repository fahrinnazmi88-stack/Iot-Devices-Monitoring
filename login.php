<?php
require 'config.php';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $st = $conn->prepare("SELECT user_id, username, password_hash, full_name, role FROM user WHERE username=? AND status='active'");
    $st->bind_param('s', $_POST['username']);
    $st->execute();
    $u = $st->get_result()->fetch_assoc();
    if ($u && password_verify($_POST['password'], $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = ['id'=>$u['user_id'], 'username'=>$u['username'], 'name'=>$u['full_name'], 'role'=>$u['role']];
        audit($conn, 'login');
        header('Location: index.php'); exit;
    }
    audit($conn, 'login_failed', null, null, 'Failed login attempt', substr($_POST['username'], 0, 30));
    $err = 'Incorrect username or password.';
}
?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login - IoT Monitoring</title><link rel="stylesheet" href="style.css"></head>
<body class="auth">
<div class="login card">
  <div class="logo">📡</div>
  <h2>IoT Device Monitoring</h2>
  <p class="sub">Please sign in to continue</p>
  <?php if ($err): ?><div class="msg er">⚠️ <?= e($err) ?></div><?php endif; ?>
  <form method="post">
    <label>👤 Username</label><input name="username" required autofocus>
    <label style="margin-top:12px">🔒 Password</label><input type="password" name="password" required>
    <p><button class="btn" style="width:100%;padding:12px">Sign In</button></p>
  </form>
  <small style="color:#6b7280">Example: admin01 or staff01 / password</small>
</div>
</body></html>