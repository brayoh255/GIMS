<?php
include '../../config/database.php';
include '../../config/constants.php';
session_start();

// Only admin allowed
if (!isset($_SESSION['role']) || $_SESSION['role'] !== ROLE_ADMIN) {
    header("Location: ../../unauthorized.php");
    exit();
}

$errors = [];
$success = "";

// Handle user deletion
if (isset($_GET['delete_user_id'])) {
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$_GET['delete_user_id']]);
    $success = "User deleted successfully.";
}

// Handle Add User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];

    if (empty($full_name) || empty($email) || empty($password) || empty($role)) {
        $errors[] = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } elseif ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    } elseif (!in_array($role, [ROLE_ADMIN, ROLE_MANAGER, ROLE_SALES])) {
        $errors[] = "Invalid role selected.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = "Email already registered.";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$full_name, $email, $hashed, $role]);
            $success = "User added successfully.";
        }
    }
}

// Handle Add Cylinder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_cylinder'])) {
    $brand = $_POST['brand'];
    $size = $_POST['size'];
    $quantity = (int)$_POST['quantity'];

    if (empty($brand) || empty($size) || $quantity <= 0) {
        $errors[] = "All cylinder fields are required and quantity must be > 0.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO cylinders (brand, size, status, created_at) VALUES (?, ?, 'empty', NOW())");
        for ($i = 0; $i < $quantity; $i++) {
            $stmt->execute([$brand, $size]);
        }
        $success = "$quantity empty cylinder(s) added.";
    }
}

// Fetch users & cylinders
$users = $pdo->query("SELECT * FROM users ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$cylinders = $pdo->query("SELECT * FROM cylinders WHERE status = 'empty' ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users & Cylinders | GIMS</title>
    <link rel="stylesheet" href="../../assets/styles.css">
    <style>
        body { font-family: Arial; padding: 20px; background: #f4f4f4; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; background: #fff; }
        table, th, td { border: 1px solid #ccc; }
        th, td { padding: 10px; text-align: left; }
        .form-box { background: #fff; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .form-box h3 { margin-top: 0; }
        input, select { width: 100%; padding: 8px; margin-top: 5px; margin-bottom: 10px; }
        .btn { background: green; color: white; padding: 10px 15px; border: none; cursor: pointer; }
        .btn:hover { background: darkgreen; }
        .error-message { color: red; margin-bottom: 10px; }
        .success-message { color: green; margin-bottom: 10px; }
    </style>
</head>
<body>

    <h2>Manage Users</h2>

    <?php if (!empty($errors)) foreach ($errors as $e): ?>
        <div class="error-message"><?= $e ?></div>
    <?php endforeach; ?>

    <?php if ($success): ?>
        <div class="success-message"><?= $success ?></div>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>Full Name</th><th>Email</th><th>Role</th><th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= htmlspecialchars($u['full_name']) ?></td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td><?= htmlspecialchars($u['role']) ?></td>
                <td><a href="?delete_user_id=<?= $u['id'] ?>" onclick="return confirm('Delete user?')">Delete</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="form-box">
        <h3>Add New User</h3>
        <form method="POST">
            <input type="hidden" name="add_user" value="1">
            <label>Full Name:</label>
            <input type="text" name="full_name" required>

            <label>Email:</label>
            <input type="email" name="email" required>

            <label>Password:</label>
            <input type="password" name="password" required>

            <label>Confirm Password:</label>
            <input type="password" name="confirm_password" required>

            <label>Role:</label>
            <select name="role" required>
                <option value="">Select Role</option>
                <option value="<?= ROLE_ADMIN ?>">Admin</option>
                <option value="<?= ROLE_MANAGER ?>">Manager</option>
                <option value="<?= ROLE_SALES ?>">Sales</option>
            </select>

            <button class="btn" type="submit">Add User</button>
        </form>
    </div>

    <h2>Empty Gas Cylinders</h2>
    <table>
        <thead>
            <tr><th>Size</th><th>Brand</th><th>Status</th><th>Created At</th></tr>
        </thead>
        <tbody>
            <?php foreach ($cylinders as $c): ?>
            <tr>
                <td><?= htmlspecialchars($c['size']) ?></td>
                <td><?= htmlspecialchars($c['brand']) ?></td>
                <td><?= htmlspecialchars($c['status']) ?></td>
                <td><?= htmlspecialchars($c['created_at']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="form-box">
        <h3>Add Empty Cylinder</h3>
        <form method="POST">
            <input type="hidden" name="add_cylinder" value="1">

            <label>Brand:</label>
            <select name="brand" required>
                <option value="">Select Brand</option>
                <option value="O Gas">O Gas</option>
                <option value="Oryx">Oryx</option>
                <option value="Puma">Puma</option>
                <option value="TaifaGas">TaifaGas</option>
                <option value="Lake Gas">Lake Gas</option>
                <option value="Manjis">Manjis</option>
            </select>

            <label>Size (e.g. 6kg, 15kg):</label>
            <input type="text" name="size" required>

            <label>Quantity:</label>
            <input type="number" name="quantity" min="1" required>

            <button class="btn" type="submit">Add Cylinder</button>
        </form>
    </div>

</body>
</html>
