<?php

$host = "localhost";
$dbname = "smart_wardrobe";
$username = "root";
$password = "";

try {

    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname",
        $username,
        $password
    );

    echo "Database Connected Successfully";

} catch(PDOException $e){

    echo "Connection Failed: " . $e->getMessage();
}

?>