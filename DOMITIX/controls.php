<?php
require_once 'config.php';
requireLogin();

$db  = getDB();
$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'alarm_off') {
        $db->exec("UPDATE system_state SET alarm_active=0 WHERE id=1");
        $db->prepare("INSERT INTO access_logs (event_type,status) VALUES (?,?)")
           ->execute(['Alarm Silenced by '.$_SESSION['username'],'success']);
        $msg = 'Alarme désactivée.';
    }
    if ($action === 'alarm_on') {
        $db->exec("UPDATE system_state SET alarm_active=1 WHERE id=1");
        $db->prepare("INSERT INTO access_logs (event_type,status) VALUES (?,?)")
           ->execute(['Alarm Triggered by '.$_SESSION['username'],'success']);
        $msg = 'Alarme déclenchée — le buzzer sonnera dans les 5 secondes.';
    }
    if ($action === 'led_blue') {
        $level = $_POST['level'] ?? 'off';
        if (!in_array($level,['off','low','medium','high'])) $level='off';
        $db->prepare("UPDATE system_state SET led_blue_level=? WHERE id=1")->execute([$level]);
        $msg = 'LED bleue : '.strtoupper($level);
    }
    if ($action === 'led_green') {
        $val = (int)($_POST['val'] ?? 0);
        $db->prepare("UPDATE system_state SET led_green=? WHERE id=1")->execute([$val]);
        $msg = 'LED verte : '.($val?'ON':'OFF');
    }
    if ($action === 'led_red') {
        $val = (int)($_POST['val'] ?? 0);
        $db->prepare("UPDATE system_state SET led_red=? WHERE id=1")->execute([$val]);
        $msg = 'LED rouge : '.($val?'ON':'OFF');
    }
}

$state = $db->query("SELECT * FROM system_state WHERE id=1")->fetch();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Controls — ESP32 SecurePanel</title>
  <link rel="stylesheet" href="style.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Rajdhani:wght@400;600;700&display=swap" rel="stylesheet"/>
</head>
<body>
<div class="scanline"></div><div class="noise"></div>
<?php include 'navbar.php'; ?>
<div class="page-wrap">
  <h1 class="page-title">SYSTEM CONTROLS</h1>
  <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>

  <div class="controls-grid">

    <!-- ALARME / BUZZER -->
    <div class="ctrl-card <?= $state['alarm_active']?'ctrl-card--danger':'' ?>">
      <div class="ctrl-card-header">
        <span class="ctrl-icon">🔔</span>
        <h3>BUZZER / ALARME</h3>
        <span class="ctrl-badge <?= $state['alarm_active']?'badge-fail':'badge-success' ?> badge">
          <?= $state['alarm_active']?'ACTIVE':'SILENT' ?>
        </span>
      </div>
      <p class="ctrl-desc">
        <?= $state['alarm_active']
          ? 'Alarme active. Le buzzer sonne sur l\'ESP32.'
          : 'Alarme silencieuse. Cliquer pour déclencher le buzzer.' ?>
      </p>
      <div class="ctrl-actions">
        <?php if ($state['alarm_active']): ?>
          <form method="POST">
            <input type="hidden" name="action" value="alarm_off"/>
            <button type="submit" class="btn-primary btn-danger ctrl-btn">🔕 DÉSACTIVER BUZZER</button>
          </form>
        <?php else: ?>
          <form method="POST">
            <input type="hidden" name="action" value="alarm_on"/>
            <button type="submit" class="btn-primary ctrl-btn" style="background:var(--accent2)"
                    onclick="return confirm('Déclencher le buzzer sur l\'ESP32 ?')">
              🔔 DÉCLENCHER BUZZER
            </button>
          </form>
        <?php endif; ?>
      </div>
      <div style="margin-top:0.8rem;font-family:var(--font-mono);font-size:0.7rem;color:var(--text-dim)">
        ⏱ Le buzzer sonne 2 secondes puis s'arrête automatiquement.
      </div>
    </div>

    <!-- LED BLEUE (OFF / LOW / MEDIUM / HIGH) -->
    <div class="ctrl-card">
      <div class="ctrl-card-header">
        <span class="ctrl-icon" style="color:#4fc3f7">💡</span>
        <h3>LED BLEUE</h3>
        <span class="ctrl-badge badge" style="background:rgba(79,195,247,0.15);color:#4fc3f7;border:1px solid rgba(79,195,247,0.3)">
          <?= strtoupper($state['led_blue_level']) ?>
        </span>
      </div>
      <p class="ctrl-desc">Contrôle l'intensité de la LED bleue via PWM sur l'ESP32.</p>
      <div class="led-level-wrap">
        <?php foreach (['off','low','medium','high'] as $lvl): ?>
          <form method="POST" style="flex:1">
            <input type="hidden" name="action" value="led_blue"/>
            <input type="hidden" name="level" value="<?= $lvl ?>"/>
            <button type="submit"
              class="led-level-btn <?= $state['led_blue_level']===$lvl?'active':'' ?>"
              style="--led-color:#4fc3f7">
              <?= strtoupper($lvl) ?>
            </button>
          </form>
        <?php endforeach; ?>
      </div>
      <div class="led-preview">
        <div class="led-dot" style="
          background:<?= $state['led_blue_level']==='off'?'#1e2a3a':'#4fc3f7' ?>;
          opacity:<?= ['off'=>0.1,'low'=>0.4,'medium'=>0.7,'high'=>1.0][$state['led_blue_level']] ?>;
          box-shadow:<?= $state['led_blue_level']!=='off'?'0 0 20px #4fc3f7':'none' ?>;
        "></div>
        <span class="led-preview-label">
          PWM : <?= ['off'=>'0%','low'=>'33%','medium'=>'66%','high'=>'100%'][$state['led_blue_level']] ?>
        </span>
      </div>
    </div>


  </div>

  <div class="section-block" style="margin-top:1.5rem">
    <div class="section-header"><h2>COMMENT ÇA MARCHE</h2></div>
    <div style="padding:1.2rem 1.5rem;font-family:var(--font-mono);font-size:0.78rem;color:var(--text-dim);line-height:2">
      L'ESP32 interroge <span style="color:var(--accent)">fetch_data.php?action=get_commands</span> toutes les <strong style="color:var(--text)">5 secondes</strong>.<br>
      Les commandes définies ici sont envoyées à la prochaine interrogation.<br>
      LED bleue LOW = 33% PWM | MEDIUM = 66% PWM | HIGH = 100% PWM<br>
      <span style="color:var(--yellow)">⚠ Assure-toi que l'ESP32 est connecté au WiFi.</span>
    </div>
  </div>
</div>
<footer class="footer">
  <span><?= SITE_NAME ?></span>
  <span><?= date('d/m/Y H:i') ?></span>
</footer>
</body>
</html>
