<?php
require_once 'config.php';
requireLogin();

$db = getDB();
$status  = $_GET['status'] ?? '';
$date    = $_GET['date']   ?? '';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$where = []; $params = [];
if ($status) { $where[] = 'status = ?';           $params[] = $status; }
if ($date)   { $where[] = 'DATE(created_at) = ?'; $params[] = $date; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = $db->prepare("SELECT COUNT(*) FROM access_logs $whereSql");
$total->execute($params);
$totalRows  = (int)$total->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

$stmt = $db->prepare("SELECT * FROM access_logs $whereSql ORDER BY id DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$logs = $stmt->fetchAll();

$stats = $db->query("SELECT SUM(status='success') ok, SUM(status='fail') fail, SUM(status='lock') lck FROM access_logs")->fetch();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Logs — ESP32 SecurePanel</title>
  <link rel="stylesheet" href="style.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Rajdhani:wght@400;600;700&display=swap" rel="stylesheet"/>
</head>
<body>
<div class="scanline"></div><div class="noise"></div>

<?php include 'navbar.php'; ?>

<div class="page-wrap">
  <h1 class="page-title">ACCESS LOGS</h1>

  <div class="cards-grid" style="margin-bottom:1.5rem">
    <div class="card"><div class="card-label">TOTAL</div><div class="card-value"><?= $totalRows ?></div></div>
    <div class="card"><div class="card-label">GRANTED</div><div class="card-value" style="color:var(--accent3)"><?= $stats['ok']??0 ?></div></div>
    <div class="card"><div class="card-label">DENIED</div><div class="card-value danger"><?= $stats['fail']??0 ?></div></div>
    <div class="card"><div class="card-label">LOCKOUTS</div><div class="card-value" style="color:#b39ddb"><?= $stats['lck']??0 ?></div></div>
  </div>

  <form method="GET" class="filter-bar">
    <select name="status">
      <option value="">All</option>
      <option value="success" <?= $status==='success'?'selected':'' ?>>Granted</option>
      <option value="fail"    <?= $status==='fail'   ?'selected':'' ?>>Denied</option>
      <option value="lock"    <?= $status==='lock'   ?'selected':'' ?>>Locked</option>
    </select>
    <input type="date" name="date" value="<?= htmlspecialchars($date) ?>"/>
    <button type="submit" class="btn-sm">FILTER</button>
    <a href="logs.php" class="btn-sm">RESET</a>
  </form>

  <div class="section-block">
    <table class="data-table">
      <thead><tr><th>#</th><th>DATETIME</th><th>EVENT</th><th>CODE</th><th>STATUS</th><th>IP</th></tr></thead>
      <tbody>
        <?php if ($logs): foreach ($logs as $l): ?>
        <tr>
          <td><?= $l['id'] ?></td>
          <td><?= htmlspecialchars($l['created_at']) ?></td>
          <td><?= htmlspecialchars($l['event_type']) ?></td>
          <td><?= htmlspecialchars($l['code_used']??'—') ?></td>
          <td><?php
            $map = ['success'=>'badge-success','fail'=>'badge-fail','lock'=>'badge-lock'];
            $lbl = ['success'=>'ACCESS OK','fail'=>'DENIED','lock'=>'LOCKED'];
            echo '<span class="badge '.($map[$l['status']]??'').'">'.($lbl[$l['status']]??$l['status']).'</span>';
          ?></td>
          <td><?= htmlspecialchars($l['ip_address']??'—') ?></td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="6" class="loading">No logs found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
      <?php for ($i=1;$i<=$totalPages;$i++): ?>
        <a href="?page=<?=$i?>&status=<?=urlencode($status)?>&date=<?=urlencode($date)?>"
           class="<?=$i===$page?'current':''?>"><?=$i?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<footer class="footer"><span><?= SITE_NAME ?></span><span><?= date('d/m/Y H:i') ?></span></footer>
</body>
</html>
