<?php
require_once 'config.php';
requireLogin();

// System info
$os      = php_uname();
$phpVer  = phpversion();
$uptime  = @file_get_contents('/proc/uptime') ?: null;
$uptimeStr = '';
if ($uptime) {
    $secs = (int)explode(' ', $uptime)[0];
    $uptimeStr = sprintf('%dd %02dh %02dm', $secs/86400, ($secs%86400)/3600, ($secs%3600)/60);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Dashboard — ESP32 SecurePanel</title>
  <link rel="stylesheet" href="style.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Rajdhani:wght@400;600;700&display=swap" rel="stylesheet"/>
</head>
<body>
<div class="scanline"></div>
<div class="noise"></div>

<?php include 'navbar.php'; ?>

<main class="dashboard">
  <header class="dash-header">
    <div>
      <h1 class="dash-title">SECURITY DASHBOARD</h1>
      <p class="dash-sub">Real-time monitoring — ESP32 Node Alpha</p>
    </div>
    <div class="dash-time" id="clock">--:--:--</div>
  </header>

  <!-- Live Sensor Cards -->
  <section class="cards-grid">
    <div class="card card--status">
      <div class="card-label">DOOR STATUS</div>
      <div class="card-value status-locked" id="door-status">LOCKED</div>
      <div class="card-icon">🔒</div>
    </div>
    <div class="card card--temp">
      <div class="card-label">TEMPERATURE</div>
      <div class="card-value" id="temperature">--<span class="unit">°C</span></div>
      <div class="card-bar"><div class="bar-fill" id="temp-bar" style="width:0%"></div></div>
    </div>
    <div class="card card--humidity">
      <div class="card-label">HUMIDITY</div>
      <div class="card-value" id="humidity">--<span class="unit">%</span></div>
      <div class="card-bar"><div class="bar-fill blue" id="hum-bar" style="width:0%"></div></div>
    </div>
    <div class="card card--voltage">
      <div class="card-label">VOLTAGE</div>
      <div class="card-value" id="voltage">--<span class="unit">V</span></div>
      <div class="card-icon">⚡</div>
    </div>
    <div class="card card--attempts">
      <div class="card-label">FAILED ATTEMPTS</div>
      <div class="card-value danger" id="attempts">0</div>
      <div class="card-icon">⚠️</div>
    </div>
    <div class="card card--lock">
      <div class="card-label">LOCKOUT</div>
      <div class="card-value" id="lockout-status">NONE</div>
      <div class="card-icon">🛡️</div>
    </div>
  </section>


  <!-- Recent Logs -->
  <section class="section-block">
    <div class="section-header">
      <h2>RECENT ACTIVITY</h2>
      <a href="logs.php" class="btn-ghost">View All →</a>
    </div>
    <table class="data-table">
      <thead>
        <tr><th>TIME</th><th>EVENT</th><th>CODE</th><th>STATUS</th></tr>
      </thead>
      <tbody id="logs-body">
        <tr><td colspan="4" class="loading">Loading...</td></tr>
      </tbody>
    </table>
  </section>

  <!-- Temp Chart -->
  <section class="section-block">
    <div class="section-header"><h2>TEMPERATURE HISTORY (Last 10)</h2></div>
    <div class="chart-wrap" id="temp-chart">
      <div class="no-data">Loading...</div>
    </div>
  </section>
</main>

<footer class="footer">
  <span><?= SITE_NAME ?></span>
  <span id="footer-time"></span>
</footer>

<script src="dashboard.js"></script>
</body>
</html>
