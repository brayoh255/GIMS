<?php
// database.php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=gims', 'root', ''); // Use your actual DB credentials
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    die();
}
?>
