<?php
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];

    $stmt = $pdo->prepare("INSERT INTO customers (name, phone, address, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$name, $phone, $address]);

    header("Location: view_customers.php");
    exit;
}
?>

<h2>Add Customer</h2>
<form method="post">
    Name: <input type="text" name="name" required><br>
    Phone: <input type="text" name="phone" required><br>
    Address: <input type="text" name="address" required><br>
    <button type="submit">Save</button>
</form>
