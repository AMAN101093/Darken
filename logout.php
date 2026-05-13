<?php
// logout.php
session_start();

require_once 'config/db.php';

// Delete remember-me token from DB if it exists
if (isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    $stmt = $pdo->prepare("DELETE FROM remember_tokens WHERE token = ?");
    $stmt->execute([$token]);
    setcookie('remember_token', '', time() - 3600, '/', '', true, true);
}

// Destroy session
$_SESSION = [];
session_destroy();

header('Location: login.php');
exit;