<?php
$DB_HOST = 'localhost';
$DB_NAME = 'booktrack_db';
$DB_USER = 'root';
$DB_PASS = '';

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage() .
        "<br>Make sure XAMPP MySQL is running and 'booktrack_db' has been imported.");
}

define('LOAN_DAYS', 2);
define('FINE_PER_DAY', 1.00);
define('TEST_MODE_MINUTES', true);

