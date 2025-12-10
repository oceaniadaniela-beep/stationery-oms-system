<?php
session_start();
if (!isset($_SESSION['admin_email'])) {
    header("Location: index.php");
    exit();
}

include 'api.php';

// Fetch admin data
$email = $_SESSION['admin_email'];
$query = "SELECT name, email FROM admin_users WHERE email = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
if (!$user) {
    $user = ["name" => "Admin User", "email" => $email];
}

// --- FETCH DASHBOARD DATA ---

$totalProducts = $conn->query("SELECT COUNT(*) as total FROM products")->fetch_assoc()['total'] ?? 0;
$totalCustomers = $conn->query("SELECT COUNT(*) as total FROM customers")->fetch_assoc()['total'] ?? 0;
$totalDelivered = $conn->query("SELECT COUNT(*) as total FROM orders WHERE status='Delivered'")->fetch_assoc()['total'] ?? 0;
$totalPending = $conn->query("SELECT COUNT(*) as total FROM orders WHERE status='Pending'")->fetch_assoc()['total'] ?? 0;
$totalPaidGoods = $conn->query("SELECT COUNT(*) as total FROM paid_goods")->fetch_assoc()['total'] ?? 0;

// Fetch recent data
$recentProducts = $conn->query("SELECT * FROM products ORDER BY id DESC LIMIT 5");
$recentCustomers = $conn->query("SELECT * FROM customers ORDER BY id DESC LIMIT 5");
$recentOrders = $conn->query("SELECT * FROM orders ORDER BY order_date DESC LIMIT 5");
$recentPaidGoods = $conn->query("
    SELECT pg.id, p.product_name, pg.amount_paid, pg.payment_method, pg.payment_date 
    FROM paid_goods pg
    JOIN products p ON pg.product_id = p.id
    ORDER BY pg.payment_date DESC
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leshan OMS - Dashboard</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            margin: 0; padding: 0;
            background: #f4f7fb;
        }
        .container { display: flex; min-height: 100vh; }
        .sidebar {
            width: 250px; background: #2c3e50; color: white;
            padding: 20px 0; box-shadow: 2px 0 8px rgba(0,0,0,0.1);
        }
        .sidebar h2 { text-align: center; color: #3498db; margin-bottom: 30px; }
        .sidebar ul { list-style: none; padding: 0; }
        .sidebar li {
            padding: 12px 20px; cursor: pointer;
        }
        .sidebar li:hover, .active { background: #3498db; }
        .main { flex: 1; padding: 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header h1 { color: #2c3e50; }
        .user-info { display: flex; align-items: center; gap: 10px; }
        .user-info img { width: 40px; height: 40px; border-radius: 50%; }
        .cards { display: grid; grid-template-columns: repeat(auto-fit,minmax(200px,1fr)); gap: 20px; margin-bottom: 30px; }
        .card {
            background: white; border-radius: 10px; padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .card h3 { color: #7f8c8d; font-size: 16px; }
        .card p { font-size: 28px; font-weight: bold; margin: 5px 0 0; color: #2c3e50; }
        .section { background: white; border-radius: 10px; padding: 20px; margin-bottom: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .section h2 { color: #2c3e50; margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; border-bottom: 1px solid #eee; text-align: left; }
        th { background: #ecf0f1; color: #2c3e50; }
        tr:hover { background: #f8f8f8; }
    </style>
</head>
<body>
<div class="container">
    <div class="sidebar">
        <h2>Leshan OMS</h2>
        <ul>
            <li class="active">📊 Dashboard</li>
            <li>📦 Inventory</li>
            <li>👥 Customers</li>
            <li>🚚 Deliveries</li>
            <li>💳 Paid Goods</li>
            <li>⚙️ Settings</li>
        </ul>
    </div>
    <div class="main">
        <div class="header">
            <h1>Dashboard</h1>
            <div class="user-info">
                <div>
                    <h3><?php echo htmlspecialchars($user['name']); ?></h3>
                    <p><?php echo htmlspecialchars($user['email']); ?></p>
                </div>
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['name']); ?>&background=3498db&color=fff" alt="User">
            </div>
        </div>

        <!-- DASHBOARD CARDS -->
        <div class="cards">
            <div class="card"><h3>Inventory Items</h3><p><?php echo $totalProducts; ?></p></div>
            <div class="card"><h3>Customers</h3><p><?php echo $totalCustomers; ?></p></div>
            <div class="card"><h3>Delivered Orders</h3><p><?php echo $totalDelivered; ?></p></div>
            <div class="card"><h3>Pending Orders</h3><p><?php echo $totalPending; ?></p></div>
            <div class="card"><h3>Paid Goods</h3><p><?php echo $totalPaidGoods; ?></p></div>
        </div>

        <!-- RECENT PRODUCTS -->
        <div class="section">
            <h2>Recent Inventory</h2>
            <table>
                <tr><th>ID</th><th>Product Name</th><th>Category</th><th>Stock</th><th>Price</th></tr>
                <?php while($row = $recentProducts->fetch_assoc()): ?>
                <tr>
                    <td>#<?php echo $row['id']; ?></td>
                    <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['category']); ?></td>
                    <td><?php echo $row['quantity']; ?></td>
                    <td>KSh <?php echo number_format($row['price'], 2); ?></td>
                </tr>
                <?php endwhile; ?>
            </table>
        </div>

        <!-- RECENT CUSTOMERS -->
        <div class="section">
            <h2>Recent Customers</h2>
            <table>
                <tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Joined</th></tr>
                <?php while($c = $recentCustomers->fetch_assoc()): ?>
                <tr>
                    <td>#<?php echo $c['id']; ?></td>
                    <td><?php echo htmlspecialchars($c['name']); ?></td>
                    <td><?php echo htmlspecialchars($c['email']); ?></td>
                    <td><?php echo htmlspecialchars($c['phone']); ?></td>
                    <td><?php echo date('M d, Y', strtotime($c['created_at'])); ?></td>
                </tr>
                <?php endwhile; ?>
            </table>
        </div>

        <!-- RECENT ORDERS -->
        <div class="section">
            <h2>Recent Orders</h2>
            <table>
                <tr><th>ID</th><th>Customer</th><th>Product</th><th>Status</th><th>Date</th></tr>
                <?php while($o = $recentOrders->fetch_assoc()): ?>
                <tr>
                    <td>#<?php echo $o['id']; ?></td>
                    <td><?php echo htmlspecialchars($o['customer_name']); ?></td>
                    <td><?php echo htmlspecialchars($o['product']); ?></td>
                    <td><?php echo htmlspecialchars($o['status']); ?></td>
                    <td><?php echo date('M d, Y', strtotime($o['order_date'])); ?></td>
                </tr>
                <?php endwhile; ?>
            </table>
        </div>

        <!-- RECENT PAID GOODS -->
        <div class="section">
            <h2>Recent Paid Goods</h2>
            <table>
                <tr><th>ID</th><th>Product</th><th>Amount Paid</th><th>Payment Method</th><th>Date</th></tr>
                <?php while($pg = $recentPaidGoods->fetch_assoc()): ?>
                <tr>
                    <td>#<?php echo $pg['id']; ?></td>
                    <td><?php echo htmlspecialchars($pg['product_name']); ?></td>
                    <td>KSh <?php echo number_format($pg['amount_paid'], 2); ?></td>
                    <td><?php echo htmlspecialchars($pg['payment_method']); ?></td>
                    <td><?php echo date('M d, Y', strtotime($pg['payment_date'])); ?></td>
                </tr>
                <?php endwhile; ?>
            </table>
        </div>
    </div>
</div>
</body>
</html>
