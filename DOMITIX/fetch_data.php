<?php
require_once 'config.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
if ($_GET['action'] === 'reset_led_green') {
    $db->exec("UPDATE system_state SET led_green=0 WHERE id=1");
    echo "OK";
    exit;
}
// ── ESP32 polls for pending commands ─────────────────────────────
if ($action === 'get_commands') {
    $secret = $_GET['key'] ?? $_POST['key'] ?? '';
    if ($secret !== 'ESP32SECRET') {
        http_response_code(403); echo json_encode(['error'=>'Forbidden']); exit;
    }
    $db    = getDB();
    $state = $db->query("SELECT * FROM system_state WHERE id=1")->fetch();

    // Reset alarme après envoi (évite boucle infinie sur le buzzer)
    if ($state['alarm_active']) {
        $db->exec("UPDATE system_state SET alarm_active=0 WHERE id=1");
    }

    // Envoie le code porte en clair + niveau LED bleue pour PWM
    echo json_encode([
        'alarm_active'   => (int)$state['alarm_active'],
        'led_blue_level' => $state['led_blue_level'],
        'led_green'      => (int)$state['led_green'],
        'led_red'        => (int)$state['led_red'],
        'door_code'      => $state['door_code'] ?? '1234',
    ]);
    exit;
}

// ── ESP32 pushes sensor data ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'push') {
    $secret = $_POST['key'] ?? '';
    if ($secret !== 'ESP32SECRET') {
        http_response_code(403); echo json_encode(['error'=>'Forbidden']); exit;
    }

    $db   = getDB();
    $temp = isset($_POST['temperature']) && $_POST['temperature'] !== '' ? (float)$_POST['temperature'] : null;
    $hum  = isset($_POST['humidity'])    && $_POST['humidity']    !== '' ? (float)$_POST['humidity']    : null;
    $volt = isset($_POST['voltage'])     && $_POST['voltage']     !== '' ? (float)$_POST['voltage']     : null;

    // ── CORRECTIF : toujours insérer même si valeurs nulles ──────
    $db->prepare('INSERT INTO sensor_data (temperature,humidity,voltage) VALUES (?,?,?)')->execute([$temp,$hum,$volt]);

    if (!empty($_POST['event'])) {
        $status = in_array($_POST['status'],['success','fail','lock']) ? $_POST['status'] : 'fail';
        $code   = $_POST['code'] ?? null;
        $db->prepare('INSERT INTO access_logs (event_type,code_used,status,ip_address) VALUES (?,?,?,?)')
           ->execute([$_POST['event'],$code,$status,$_SERVER['REMOTE_ADDR']]);
    }

    if (isset($_POST['is_locked'])) {
        $lockUntil = null;
        if ((int)$_POST['is_locked'] && !empty($_POST['lock_remain'])) {
            $lockUntil = date('Y-m-d H:i:s', time()+(int)$_POST['lock_remain']);
        }
        $db->prepare('UPDATE system_state SET is_locked=?,lock_until=?,failed_attempts=? WHERE id=1')
           ->execute([(int)$_POST['is_locked'],$lockUntil,(int)($_POST['attempts']??0)]);
    }

    echo json_encode(['ok'=>true]);
    exit;
}

// ── Dashboard AJAX ────────────────────────────────────────────────
if ($action === 'dashboard') {
    requireLogin();
    $db = getDB();

    // Dernière lecture capteur
    $sensor = $db->query('SELECT * FROM sensor_data ORDER BY id DESC LIMIT 1')->fetch();
    $state  = $db->query('SELECT * FROM system_state WHERE id=1')->fetch();

    $lockRemain = 0;
    if ($state['is_locked'] && $state['lock_until']) {
        $lockRemain = max(0, strtotime($state['lock_until'])-time());
    }

    $logs    = $db->query('SELECT * FROM access_logs ORDER BY id DESC LIMIT 10')->fetchAll();
    $history = $db->query('SELECT temperature,created_at FROM sensor_data ORDER BY id DESC LIMIT 10')->fetchAll();
    $history = array_reverse($history);

    echo json_encode([
        'temperature'  => $sensor['temperature']  ?? null,
        'humidity'     => $sensor['humidity']      ?? null,
        'voltage'      => $sensor['voltage']       ?? null,
        'is_locked'    => $state['is_locked']      ?? 0,
        'lock_remain'  => $lockRemain,
        'door_open'    => $state['door_open']      ?? 0,
        'attempts'     => $state['failed_attempts'] ?? 0,
        'alarm_active' => $state['alarm_active']   ?? 0,
        'recent_logs'  => $logs,
        'temp_history' => $history,
    ]);
    exit;
}

echo json_encode(['error'=>'Unknown action']);
