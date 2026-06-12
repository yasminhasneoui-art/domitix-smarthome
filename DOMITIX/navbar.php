<?php
// navbar.php — include in every page
$current = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar">
  <div class="logo">
    <span class="logo-bracket">[</span>ESP32<span class="logo-accent">SEC</span><span class="logo-bracket">]</span>
  </div>
  <ul class="nav-links">
    <li><a href="dashboard.php"  <?= $current==='dashboard.php' ?'class="active"':'' ?>>Dashboard</a></li>
    <li><a href="logs.php"       <?= $current==='logs.php'      ?'class="active"':'' ?>>Logs</a></li>
    <li><a href="controls.php"   <?= $current==='controls.php'  ?'class="active"':'' ?>>Controls</a></li>
    <?php if (($_SESSION['role']??'') === 'admin'): ?>
    <li><a href="admin.php"      <?= $current==='admin.php'     ?'class="active"':'' ?>>Admin</a></li>
    <?php endif; ?>
    <li><a href="logout.php" class="nav-logout">Logout</a></li>
  </ul>
  <div class="nav-status">
    <span class="status-dot online"></span>
    <?= htmlspecialchars($_SESSION['username'] ?? '') ?>
    <?php if(($_SESSION['role']??'')==='admin'): ?><span style="color:var(--accent2)">[ADMIN]</span><?php endif; ?>
  </div>
</nav>
