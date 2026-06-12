<?php
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (isLoggedIn()) { header('Location: index.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username && $password) {
        try {
            $db   = getDB();
            $stmt = $db->prepare('SELECT id,username,password,role FROM users WHERE username=? LIMIT 1');
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            // Comparaison en CLAIR (pas de hash)
            if ($user && $password === $user['password']) {
                session_regenerate_id(true);
                $_SESSION['user_id']       = $user['id'];
                $_SESSION['username']      = $user['username'];
                $_SESSION['role']          = $user['role'];
                $_SESSION['last_activity'] = time();
                header('Location: index.php'); exit;
            } else {
                $error = 'Identifiant ou mot de passe incorrect.';
            }
        } catch (Exception $e) {
            $error = 'Erreur système : '.$e->getMessage();
        }
    } else {
        $error = 'Veuillez remplir tous les champs.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Login — ESP32 SecurePanel</title>
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
    <h1 class="form-title">SECURE <span>ACCESS</span></h1>
    <p class="form-sub">Authentification requise // v2.0</p>
    <?php if ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['timeout'])): ?>
      <div class="alert alert-error">Session expirée. Reconnectez-vous.</div>
    <?php endif; ?>
    <?php if (isset($_GET['reset']) && $_GET['reset']==='done'): ?>
      <div class="alert alert-success">Mot de passe réinitialisé avec succès.</div>
    <?php endif; ?>
    <form method="POST">
      <div class="form-group">
        <label>IDENTIFIANT</label>
        <input type="text" name="username" autocomplete="username" required
               value="<?= htmlspecialchars($_POST['username']??'') ?>"/>
      </div>
      <div class="form-group">
        <label>MOT DE PASSE</label>
        <input type="password" name="password" autocomplete="current-password" required/>
      </div>
      <button type="submit" class="btn-primary">SE CONNECTER</button>
    </form>
    <div style="margin-top:1rem;font-family:var(--font-mono);font-size:0.7rem;color:var(--text-dim);text-align:center">
      Par défaut : admin / admin123
    </div>
    <div style="margin-top:0.8rem;display:flex;gap:1rem;justify-content:center">
      <a href="forgot_email.php" class="link-dim">Mot de passe oublié (Email)</a>
      <span style="color:var(--text-dim)">|</span>
      <a href="forgot_sms.php" class="link-dim">Reset via SMS</a>
    </div>
  </div>
</div>
</body>
</html>
