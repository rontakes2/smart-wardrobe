<?php
$host = "127.0.0.1";
$dbname = "smart_wardrobe";
$username = "root";
$password = "";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4", #fixed broken dns string
        $username,
        $password
    );
    $pdo->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );
} catch (PDOException $e) {
    die("Connection Failed: " . $e->getMessage());
}
?>