<?php
// config.php — modifier DB_PASS si ton MySQL a un mot de passe
define('DB_HOST',    'localhost');
define('DB_PORT',    3306);
define('DB_NAME',    'domitix');
define('DB_USER',    'root');
define('DB_PASS',    '');        // ← mettre ton mot de passe MySQL si besoin
define('DB_CHARSET', 'utf8mb4');
define('SESSION_TIMEOUT', 1800);
define('SITE_NAME', 'smarthouse domitix');
define('MAIL_FROM', 'noreply@domitix.local');
define('MAIL_FROM_NAME', 'domitix');
define('TWILIO_SID',   '');
define('TWILIO_TOKEN', '');
define('TWILIO_FROM',  '');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=".DB_HOST.";port=".DB_PORT.";dbname=".DB_NAME.";charset=".DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            die('<pre style="color:red;padding:20px">DB ERROR: '.$e->getMessage().'
→ Vérifie que MySQL est démarré dans XAMPP
→ Importe database.sql dans phpMyAdmin
→ Vérifie DB_PASS dans config.php</pre>');
        }
    }
    return $pdo;
}

function requireLogin(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
    if (isset($_SESSION['last_activity']) && (time()-$_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_unset(); session_destroy();
        header('Location: login.php?timeout=1'); exit;
    }
    $_SESSION['last_activity'] = time();
}

function requireAdmin(): void {
    requireLogin();
    if (($_SESSION['role'] ?? '') !== 'admin') { header('Location: index.php'); exit; }
}

function isLoggedIn(): bool {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return !empty($_SESSION['user_id']);
}
