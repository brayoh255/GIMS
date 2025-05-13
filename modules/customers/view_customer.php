<?php
require_once '../../config/db.php';

$stmt = $pdo->query("SELECT * FROM customers ORDER BY created_at DESC");
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>Customer List</h2>
<table border="1">
    <tr>
        <th>ID</th><th>Name</th><th>Phone</th><th>Address</th><th>Created At</th>
    </tr>
    <?php foreach ($customers as $customer): ?>
    <tr>
        <td><?= $customer['id'] ?></td>
        <td><?= $customer['name'] ?></td>
        <td><?= $customer['phone'] ?></td>
        <td><?= $customer['address'] ?></td>
        <td><?= $customer['created_at'] ?></td>
    </tr>
    <?php endforeach; ?>
</table>
