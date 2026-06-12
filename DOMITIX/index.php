<?php
// index.php — replaces index.html, enforces login
require_once 'config.php';
requireLogin();
// Redirect to dashboard
header('Location: dashboard.php');
exit;
