<?php
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$db    = getDB();
$token = trim($_GET['token'] ?? '');
$err   = $msg = '';

// Validate token
$row = null;
if ($token) {
    $stmt = $db->prepare("SELECT * FROM reset_tokens WHERE token=? AND type='email' AND used=0 AND expires_at > NOW() LIMIT 1");
    $stmt->execute([$token]);
    $row = $stmt->fetch();
}

if (!$row && !$token) {
    header('Location: login.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $row) {
    $newPass  = $_POST['new_pass']  ?? '';
    $confPass = $_POST['confirm_pass'] ?? '';
    if (strlen($newPass) < 6) {
        $err = 'Password must be at least 6 characters.';
    } elseif ($newPass !== $confPass) {
        $err = 'Passwords do not match.';
    } else {
        $hash = $newPass; // plain text
        $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $row['user_id']]);
        $db->prepare("UPDATE reset_tokens SET used=1 WHERE id=?")->execute([$row['id']]);
        header('Location: login.php?reset=done'); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Reset Password — ESP32 SecurePanel</title>
  <link rel="stylesheet" href="style.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Rajdhani:wght@400;600;700&display=swap" rel="stylesheet"/>
</head>
<body>
<div class="scanline"></div><div class="noise"></div>
<div class="form-page">
  <div class="form-box">
    <div class="logo" style="margin-bottom:1.5rem;font-size:1.3rem">
      <span class="logo-bracket">[</span>ESP32<span class="logo-accent">SEC</span><span class="logo-bracket">]</span>
    </div>
    <h1 class="form-title">NEW <span>PASSWORD</span></h1>

    <?php if (!$row): ?>
      <div class="alert alert-error">This reset link is invalid or has expired.</div>
      <div style="text-align:center;margin-top:1rem">
        <a href="forgot_email.php" class="link-dim">Request a new link</a>
      </div>
    <?php else: ?>
      <?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
      <p class="form-sub">Choose a new password for your account</p>
      <form method="POST">
        <div class="form-group">
          <label>NEW PASSWORD</label>
          <input type="password" name="new_pass" minlength="6" required/>
        </div>
        <div class="form-group">
          <label>CONFIRM PASSWORD</label>
          <input type="password" name="confirm_pass" required/>
        </div>
        <button type="submit" class="btn-primary">SET NEW PASSWORD</button>
      </form>
    <?php endif; ?>
    <div style="margin-top:1rem;text-align:center">
      <a href="login.php" class="link-dim">← Back to Login</a>
    </div>
  </div>
</div>
</body>
</html>
