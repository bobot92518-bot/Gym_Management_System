<?php
$host = 'localhost';
$db = 'gym_management';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "Database connected successfully!";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}