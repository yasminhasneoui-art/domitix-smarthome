<?php
require_once 'config.php';
requireAdmin();

$db = getDB();
$msg = $err = '';

if (isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'change_code') {
        $newCode     = trim($_POST['new_code'] ?? '');
        $confirmCode = trim($_POST['confirm_code'] ?? '');
        if (!preg_match('/^\d{4}$/', $newCode))  { $err = 'Le code doit être exactement 4 chiffres.'; }
        elseif ($newCode !== $confirmCode)         { $err = 'Les codes ne correspondent pas.'; }
        else {
            $db->prepare('UPDATE system_state SET door_code=? WHERE id=1')->execute([$newCode]);
            $db->prepare('INSERT INTO access_logs (event_type,status) VALUES (?,?)')->execute(['Code Changed by Admin','success']);
            $msg = 'Code porte mis à jour : '.$newCode;
        }
    }

    if ($action === 'change_pass') {
        $cur  = $_POST['current_pass'] ?? '';
        $new  = $_POST['new_pass']     ?? '';
        $conf = $_POST['confirm_pass'] ?? '';
        $row  = $db->prepare('SELECT password FROM users WHERE id=?');
        $row->execute([$_SESSION['user_id']]);
        $userRow = $row->fetch();
        if ($cur !== $userRow['password'])  { $err = 'Mot de passe actuel incorrect.'; }
        elseif (strlen($new) < 4)           { $err = 'Minimum 4 caractères.'; }
        elseif ($new !== $conf)             { $err = 'Les mots de passe ne correspondent pas.'; }
        else {
            $db->prepare('UPDATE users SET password=? WHERE id=?')->execute([$new,$_SESSION['user_id']]);
            $msg = 'Mot de passe mis à jour.';
        }
    }

    if ($action === 'update_contact') {
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $db->prepare('UPDATE users SET email=?,phone=? WHERE id=?')->execute([$email,$phone,$_SESSION['user_id']]);
        $msg = 'Infos de contact sauvegardées.';
    }

    // ── FORCER DÉVERROUILLAGE ────────────────────────────────────
    // Met led_green=1 → l'ESP32 détecte au prochain poll (≤1s),
    // allume la LED verte pendant 5s, puis remet led_green=0 lui-même.
    if ($action === 'reset_state') {
        $db->exec('UPDATE system_state 
    SET is_locked       = 0,
        lock_until      = NULL,
        failed_attempts = 0,
        led_green       = 1
    WHERE id = 1');

// Remet à 0 après 5 secondes
sleep(5);

$db->exec('UPDATE system_state SET led_green = 0 WHERE id = 1');

        // Log l'action admin
        $db->prepare('INSERT INTO access_logs (event_type, status) VALUES (?, ?)')
           ->execute(['Force Unlock by Admin', 'success']);

        $msg = 'Déverrouillage forcé — LED verte active 5s sur l\'ESP32.';
    }
    // ─────────────────────────────────────────────────────────────

    if ($action === 'clear_logs') {
        $db->exec('DELETE FROM access_logs');
        $msg = 'Logs effacés.';
    }
}

$state = $db->query('SELECT * FROM system_state WHERE id=1')->fetch();
$adminUser = $db->prepare('SELECT email,phone FROM users WHERE id=?');
$adminUser->execute([$_SESSION['user_id']]);
$adminInfo = $adminUser->fetch();
$lockRemain = 0;
if ($state['is_locked'] && $state['lock_until']) {
    $lockRemain = max(0, strtotime($state['lock_until']) - time());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin — ESP32 SecurePanel</title>
  <link rel="stylesheet" href="style.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Rajdhani:wght@400;600;700&display=swap" rel="stylesheet"/>
</head>
<body>
<div class="scanline"></div><div class="noise"></div>
<?php include 'navbar.php'; ?>
<div class="page-wrap">
  <h1 class="page-title">ADMIN PANEL</h1>
  <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>

  <div class="cards-grid" style="margin-bottom:1.5rem">
    <div class="card">
      <div class="card-label">LOCK STATUS</div>
      <div class="card-value <?= $state['is_locked'] ? 'danger' : '' ?>">
        <?= $state['is_locked'] ? 'LOCKED ('.$lockRemain.'s)' : 'UNLOCKED' ?>
      </div>
    </div>
    <div class="card">
      <div class="card-label">FAILED ATTEMPTS</div>
      <div class="card-value danger"><?= $state['failed_attempts'] ?></div>
    </div>
    <div class="card">
      <div class="card-label">ALARM</div>
      <div class="card-value <?= $state['alarm_active'] ? 'danger' : '' ?>">
        <?= $state['alarm_active'] ? 'ACTIVE' : 'SILENT' ?>
      </div>
    </div>
    <div class="card">
      <div class="card-label">CODE PORTE ACTUEL</div>
      <div class="card-value" style="color:var(--accent)"><?= htmlspecialchars($state['door_code'] ?? '1234') ?></div>
    </div>
  </div>

  <div class="admin-grid">

    <!-- Change Door Code -->
    <div class="admin-card">
      <h3>CHANGER LE CODE PORTE</h3>
      <p style="font-family:var(--font-mono);font-size:0.72rem;color:var(--text-dim);margin-bottom:1rem">
        Code actuel : <span style="color:var(--accent)"><?= htmlspecialchars($state['door_code'] ?? '1234') ?></span><br>
        L'ESP32 recevra le nouveau code dans les 5 secondes.
      </p>
      <form method="POST">
        <input type="hidden" name="action" value="change_code"/>
        <div class="form-group">
          <label>NOUVEAU CODE (4 chiffres)</label>
          <input type="text" name="new_code" maxlength="4" pattern="\d{4}" placeholder="ex: 5678" required/>
        </div>
        <div class="form-group">
          <label>CONFIRMER LE CODE</label>
          <input type="text" name="confirm_code" maxlength="4" pattern="\d{4}" placeholder="ex: 5678" required/>
        </div>
        <button type="submit" class="btn-primary">METTRE À JOUR LE CODE</button>
      </form>
    </div>

    <!-- Change Admin Password -->
    <div class="admin-card">
      <h3>CHANGER LE MOT DE PASSE</h3>
      <p style="font-family:var(--font-mono);font-size:0.72rem;color:var(--text-dim);margin-bottom:1rem">
        Mot de passe stocké en clair (pas de hash).
      </p>
      <form method="POST">
        <input type="hidden" name="action" value="change_pass"/>
        <div class="form-group">
          <label>MOT DE PASSE ACTUEL</label>
          <input type="password" name="current_pass" required/>
        </div>
        <div class="form-group">
          <label>NOUVEAU MOT DE PASSE</label>
          <input type="password" name="new_pass" minlength="4" required/>
        </div>
        <div class="form-group">
          <label>CONFIRMER</label>
          <input type="password" name="confirm_pass" required/>
        </div>
        <button type="submit" class="btn-primary">METTRE À JOUR</button>
      </form>
    </div>

    <!-- Contact Info -->
    <div class="admin-card">
      <h3>INFOS DE CONTACT</h3>
      <form method="POST">
        <input type="hidden" name="action" value="update_contact"/>
        <div class="form-group">
          <label>EMAIL</label>
          <input type="email" name="email" value="<?= htmlspecialchars($adminInfo['email'] ?? '') ?>"/>
        </div>
        <div class="form-group">
          <label>TÉLÉPHONE</label>
          <input type="tel" name="phone" placeholder="+21612345678" value="<?= htmlspecialchars($adminInfo['phone'] ?? '') ?>"/>
        </div>
        <button type="submit" class="btn-primary">SAUVEGARDER</button>
      </form>
    </div>

    <!-- System Actions -->
    <div class="admin-card">
      <h3>ACTIONS SYSTÈME</h3>

      <!-- FORCER DÉVERROUILLAGE -->
      <form method="POST" style="margin-bottom:1rem"
            onsubmit="return confirm('Forcer le déverrouillage ? La LED verte s\'allumera 5s sur l\'ESP32.')">
        <input type="hidden" name="action" value="reset_state"/>
        <button type="submit" class="btn-primary">
          ⚡ FORCER DÉVERROUILLAGE
        </button>
        <p style="font-family:var(--font-mono);font-size:0.68rem;color:var(--text-dim);margin-top:0.5rem">
          Remet les tentatives à 0, déverrouille et déclenche<br>
          la LED verte pendant <strong>5 secondes</strong> sur l'ESP32.
        </p>
      </form>

      <form method="POST" onsubmit="return confirm('Effacer TOUS les logs ?')">
        <input type="hidden" name="action" value="clear_logs"/>
        <button type="submit" class="btn-primary btn-danger">EFFACER LES LOGS</button>
      </form>

      <div style="margin-top:1rem">
        <a href="controls.php" class="btn-primary" style="display:block;text-align:center;text-decoration:none">
          → CONTRÔLES
        </a>
      </div>
    </div>

  </div><!-- /.admin-grid -->
</div><!-- /.page-wrap -->

<footer class="footer">
  <span><?= SITE_NAME ?></span>
  <span><?= date('d/m/Y H:i') ?></span>
</footer>
</body>
</html>