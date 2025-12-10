<?php
session_start();
if (!isset($_SESSION['customer_email'])) {
    header("Location: customer_login.php");
    exit();
}
include 'api.php';

$customer = $_SESSION['customer_name'];
$email = $_SESSION['customer_email'];

// Handle new order
if (isset($_POST['product_id'])) {
    $pid = $_POST['product_id'];
    $product = $conn->query("SELECT product_name FROM products WHERE id=$pid")->fetch_assoc()['product_name'];
    $stmt = $conn->prepare("INSERT INTO orders (customer_name, product, status, order_date) VALUES (?, ?, 'Pending', NOW())");
    $stmt->bind_param("ss", $customer, $product);
    $stmt->execute();
    $message = "✅ Your order for '$product' has been placed successfully!";
}

// Fetch products
$products = $conn->query("SELECT * FROM products ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Leshan OMS - Customer Dashboard</title>
    <style>
        body {font-family: 'Segoe UI'; background: #f4f7fb; margin: 0; padding: 0;}
        header {background: #3498db; color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center;}
        .container {padding: 30px;}
        .products {display: grid; grid-template-columns: repeat(auto-fit,minmax(250px,1fr)); gap: 20px;}
        .card {background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);}
        .card h3 {margin: 0 0 10px;}
        .card p {margin: 5px 0;}
        button {background: #3498db; color: white; border: none; padding: 10px; border-radius: 5px; cursor: pointer;}
        .msg {background: #dff0d8; color: #2c662d; padding: 10px; border-radius: 5px; margin-bottom: 20px;}
    </style>
</head>
<body>
<header>
    <h2>Welcome, <?= htmlspecialchars($customer) ?></h2>
    <div>
        <a href="customer_logout.php" style="color:white; text-decoration:none;">Logout</a>
    </div>
</header>

<div class="container">
    <?php if (!empty($message)) echo "<div class='msg'>$message</div>"; ?>

    <h2>Available Products</h2>
    <div class="products">
        <?php while($row = $products->fetch_assoc()): ?>
        <div class="card">
            <h3><?= htmlspecialchars($row['product_name']) ?></h3>
            <p>Category: <?= htmlspecialchars($row['category']) ?></p>
            <p>Stock: <?= $row['quantity'] ?></p>
            <p>Price: KSh <?= number_format($row['price'],2) ?></p>
            <form method="post">
                <input type="hidden" name="product_id" value="<?= $row['id'] ?>">
                <button type="submit">🛒 Order Now</button>
            </form>
        </div>
        <?php endwhile; ?>
    </div>
</div>
</body>
</html>
