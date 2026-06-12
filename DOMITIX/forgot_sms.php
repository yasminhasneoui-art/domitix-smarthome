<?php
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (isLoggedIn()) { header('Location: dashboard.php'); exit; }

$msg = $err = '';
$showCode = false;
$userId   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $step = $_POST['step'] ?? '1';
    $db   = getDB();

    // Step 1: User enters phone number → generate 6-digit SMS code
    if ($step === '1') {
        $phone = trim($_POST['phone'] ?? '');
        $user  = $db->prepare("SELECT id FROM users WHERE phone = ? LIMIT 1");
        $user->execute([$phone]);
        $found = $user->fetch();

        if ($found) {
            $code    = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $token   = hash('sha256', $code . $found['id']);
            $expires = date('Y-m-d H:i:s', time() + 600); // 10 min

            $db->prepare("DELETE FROM reset_tokens WHERE user_id=? AND type='sms'")->execute([$found['id']]);
            $db->prepare("INSERT INTO reset_tokens (user_id, token, type, expires_at) VALUES (?,?,'sms',?)")
               ->execute([$found['id'], $token, $expires]);

            // Send SMS via Twilio (optional — requires credentials in config.php)
            if (TWILIO_SID && TWILIO_TOKEN) {
                $url  = "https://api.twilio.com/2010-04-01/Accounts/" . TWILIO_SID . "/Messages.json";
                $data = http_build_query([
                    'To'   => $phone,
                    'From' => TWILIO_FROM,
                    'Body' => "[ESP32 SecurePanel] Your reset code: {$code} (valid 10 min)"
                ]);
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => $data,
                    CURLOPT_USERPWD        => TWILIO_SID . ':' . TWILIO_TOKEN,
                    CURLOPT_RETURNTRANSFER => true,
                ]);
                curl_exec($ch);
                curl_close($ch);
                $msg = "Code sent to your phone number.";
            } else {
                // No Twilio — show code on screen (dev mode)
                $msg = "DEV MODE (no SMS configured) — Your code is: <strong>{$code}</strong>";
            }

            $_SESSION['sms_reset_phone']   = $phone;
            $_SESSION['sms_reset_user_id'] = $found['id'];
            $showCode = true;
        } else {
            $msg = "If that number is registered, a code has been sent.";
        }
    }

    // Step 2: User enters code + new password
    if ($step === '2') {
        $inputCode = trim($_POST['code'] ?? '');
        $newPass   = $_POST['new_pass'] ?? '';
        $confPass  = $_POST['confirm_pass'] ?? '';
        $uid       = $_SESSION['sms_reset_user_id'] ?? 0;

        if (!$uid) { $err = 'Session expired. Start again.'; }
        elseif (strlen($newPass) < 6) { $err = 'Password must be at least 6 characters.'; }
        elseif ($newPass !== $confPass) { $err = 'Passwords do not match.'; }
        else {
            $expectedToken = hash('sha256', $inputCode . $uid);
            $tokenRow = $db->prepare("SELECT id FROM reset_tokens WHERE user_id=? AND token=? AND type='sms' AND used=0 AND expires_at > NOW()");
            $tokenRow->execute([$uid, $expectedToken]);
            $found = $tokenRow->fetch();
            if ($found) {
                $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([$newPass, $uid]);
                $db->prepare("UPDATE reset_tokens SET used=1 WHERE id=?")->execute([$found['id']]);
                unset($_SESSION['sms_reset_phone'], $_SESSION['sms_reset_user_id']);
                header('Location: login.php?reset=done'); exit;
            } else {
                $err = 'Invalid or expired code.';
                $showCode = true;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Reset via SMS — ESP32 SecurePanel</title>
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
    <h1 class="form-title">RESET <span>VIA SMS</span></h1>

    <?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <?php if (!$showCode && !isset($_SESSION['sms_reset_user_id'])): ?>
    <!-- Step 1 -->
    <p class="form-sub">Enter your registered phone number</p>
    <form method="POST">
      <input type="hidden" name="step" value="1"/>
      <div class="form-group">
        <label>PHONE NUMBER</label>
        <input type="tel" name="phone" placeholder="+21612345678" required/>
      </div>
      <button type="submit" class="btn-primary">SEND CODE</button>
    </form>

    <?php else: ?>
    <!-- Step 2 -->
    <p class="form-sub">Enter the 6-digit code sent to your phone</p>
    <form method="POST">
      <input type="hidden" name="step" value="2"/>
      <div class="form-group">
        <label>SMS CODE</label>
        <input type="text" name="code" maxlength="6" pattern="\d{6}" placeholder="000000" required
               style="letter-spacing:8px;font-size:1.4rem;text-align:center"/>
      </div>
      <div class="form-group">
        <label>NEW PASSWORD</label>
        <input type="password" name="new_pass" minlength="6" required/>
      </div>
      <div class="form-group">
        <label>CONFIRM PASSWORD</label>
        <input type="password" name="confirm_pass" required/>
      </div>
      <button type="submit" class="btn-primary">RESET PASSWORD</button>
    </form>
    <?php endif; ?>

    <div style="margin-top:1rem;text-align:center">
      <a href="login.php" class="link-dim">← Back to Login</a>
    </div>
  </div>
</div>
</body>
</html>
