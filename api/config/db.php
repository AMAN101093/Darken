<?php
// config/db.php

define('DB_HOST', 'localhost');
define('DB_NAME', 'darken_shadows');
define('DB_USER', 'root');
define('DB_PASS', '1234');
define('DB_CHARSET', 'utf8mb4');

$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {

    // Optional: log error internally
    error_log($e->getMessage());

    die("Database connection failed.");
}
?>