<?php
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (isLoggedIn()) { header('Location: dashboard.php'); exit; }

$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $step  = $_POST['step'] ?? '1';
    $db    = getDB();

    // Step 1: User enters email
    if ($step === '1') {
        $email = trim($_POST['email'] ?? '');
        $user  = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $user->execute([$email]);
        $found = $user->fetch();
        if ($found) {
            // Generate token
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600);
            $db->prepare("DELETE FROM reset_tokens WHERE user_id=? AND type='email'")->execute([$found['id']]);
            $db->prepare("INSERT INTO reset_tokens (user_id, token, type, expires_at) VALUES (?,?,'email',?)")
               ->execute([$found['id'], $token, $expires]);

            // Send email (uses PHP mail() — works on localhost with SMTP config)
            $link    = "http://{$_SERVER['HTTP_HOST']}/esp32/reset_password.php?token={$token}";
            $subject = "[ESP32 SecurePanel] Password Reset";
            $body    = "Click this link to reset your password (valid 1 hour):\n\n{$link}\n\nIf you did not request this, ignore this email.";
            mail($email, $subject, $body, "From: " . MAIL_FROM);

            $msg = "If that email exists in our system, a reset link has been sent.";
        } else {
            // Don't reveal whether email exists
            $msg = "If that email exists in our system, a reset link has been sent.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Forgot Password — ESP32 SecurePanel</title>
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
    <h1 class="form-title">RESET <span>PASSWORD</span></h1>
    <p class="form-sub">Enter your account email address</p>

    <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <?php if (!$msg): ?>
    <form method="POST">
      <input type="hidden" name="step" value="1"/>
      <div class="form-group">
        <label>EMAIL ADDRESS</label>
        <input type="email" name="email" required/>
      </div>
      <button type="submit" class="btn-primary">SEND RESET LINK</button>
    </form>
    <?php endif; ?>

    <div style="margin-top:1rem;text-align:center">
      <a href="login.php" class="link-dim">← Back to Login</a>
    </div>
  </div>
</div>
</body>
</html>
