<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();
if (!isset($_SESSION['customer_loggedin']) || $_SESSION['customer_loggedin'] !== true) {
    header("Location: customer_login.php");
    exit();
}

include 'api.php';

$customer_email = $_SESSION['customer_email'];
$customer_name = $_SESSION['customer_name'];
$customer_id = $_SESSION['customer_id'];

// Handle search
$search = $_GET['search'] ?? '';

// Handle theme preference
if (isset($_POST['set_theme'])) {
    $theme = $_POST['theme'];
    $_SESSION['theme'] = $theme;
    
    // Update in database
    $theme_stmt = $conn->prepare("UPDATE customers SET theme_preference = ? WHERE id = ?");
    $theme_stmt->bind_param("si", $theme, $customer_id);
    $theme_stmt->execute();
}

// Get current theme
$current_theme = $_SESSION['theme'] ?? 'light';

// Handle cart operations
if (isset($_POST['add_to_cart'])) {
    $product_id = $_POST['product_id'];
    $quantity = $_POST['quantity'] ?? 1;
    
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    if (isset($_SESSION['cart'][$product_id])) {
        $_SESSION['cart'][$product_id] += $quantity;
    } else {
        $_SESSION['cart'][$product_id] = $quantity;
    }
    
    $message = "Product added to cart!";
}

// Handle order placement
if (isset($_POST['place_order'])) {
    if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
        $total_amount = 0;
        
        // Calculate total amount
        foreach ($_SESSION['cart'] as $product_id => $quantity) {
            $product_stmt = $conn->prepare("SELECT price FROM products WHERE id = ?");
            $product_stmt->bind_param("i", $product_id);
            $product_stmt->execute();
            $product_result = $product_stmt->get_result();
            $product = $product_result->fetch_assoc();
            $total_amount += $product['price'] * $quantity;
        }
        
        // Create order
        $order_stmt = $conn->prepare("INSERT INTO orders (customer_id, total_amount, status) VALUES (?, ?, 'pending')");
        $order_stmt->bind_param("id", $customer_id, $total_amount);
        
        if ($order_stmt->execute()) {
            $order_id = $conn->insert_id;
            
            // Add order items
            foreach ($_SESSION['cart'] as $product_id => $quantity) {
                $product_stmt = $conn->prepare("SELECT price FROM products WHERE id = ?");
                $product_stmt->bind_param("i", $product_id);
                $product_stmt->execute();
                $product_result = $product_stmt->get_result();
                $product = $product_result->fetch_assoc();
                
                $item_stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                $item_stmt->bind_param("iiid", $order_id, $product_id, $quantity, $product['price']);
                $item_stmt->execute();
                
                // Update product quantity
                $update_stmt = $conn->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?");
                $update_stmt->bind_param("ii", $quantity, $product_id);
                $update_stmt->execute();
            }
            
            // Create initial order progress
            $progress_stmt = $conn->prepare("INSERT INTO order_progress (order_id, status, notes) VALUES (?, 'pending', 'Order received and awaiting processing')");
            $progress_stmt->bind_param("i", $order_id);
            $progress_stmt->execute();
            
            $success_message = "Order placed successfully! It is now pending admin confirmation.";
        } else {
            $error_message = "Error placing order. Please try again.";
        }
    }
}

// Handle cart item removal
if (isset($_POST['remove_from_cart'])) {
    $product_id = $_POST['product_id'];
    if (isset($_SESSION['cart'][$product_id])) {
        unset($_SESSION['cart'][$product_id]);
    }
}

// Handle cart quantity update
if (isset($_POST['update_cart'])) {
    $product_id = $_POST['product_id'];
    $quantity = $_POST['quantity'];
    
    if ($quantity <= 0) {
        unset($_SESSION['cart'][$product_id]);
    } else {
        $_SESSION['cart'][$product_id] = $quantity;
    }
}

// Handle rating submission
if (isset($_POST['submit_rating'])) {
    $product_id = $_POST['product_id'];
    $rating = $_POST['rating'];
    $comment = $_POST['comment'];
    
    // Check if customer already rated this product
    $check_stmt = $conn->prepare("SELECT id FROM product_ratings WHERE customer_id = ? AND product_id = ?");
    $check_stmt->bind_param("ii", $customer_id, $product_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Update existing rating
        $rating_stmt = $conn->prepare("UPDATE product_ratings SET rating = ?, comment = ? WHERE customer_id = ? AND product_id = ?");
        $rating_stmt->bind_param("isii", $rating, $comment, $customer_id, $product_id);
    } else {
        // Insert new rating
        $rating_stmt = $conn->prepare("INSERT INTO product_ratings (customer_id, product_id, rating, comment) VALUES (?, ?, ?, ?)");
        $rating_stmt->bind_param("iiis", $customer_id, $product_id, $rating, $comment);
    }
    
    if ($rating_stmt->execute()) {
        $rating_message = "Thank you for your rating!";
    } else {
        $rating_error = "Error submitting rating. Please try again.";
    }
}

// Handle support ticket submission
if (isset($_POST['submit_support'])) {
    $issue = $_POST['issue'];
    $priority = $_POST['priority'];
    
    $support_stmt = $conn->prepare("INSERT INTO support_tickets (customer_id, issue, priority, status) VALUES (?, ?, ?, 'open')");
    $support_stmt->bind_param("iss", $customer_id, $issue, $priority);
    
    if ($support_stmt->execute()) {
        $support_message = "Your support ticket has been submitted. We'll get back to you soon!";
    } else {
        $support_error = "Error submitting support ticket. Please try again.";
    }
}

// Enhanced M-Pesa payment handling
if (isset($_POST['process_mpesa_payment'])) {
    $product_id = $_POST['product_id'];
    $amount = $_POST['amount'];
    $pin = $_POST['mpesa_pin'];
    
    // Validate PIN
    if (strlen($pin) === 4 && is_numeric($pin)) {
        // Store payment information
        $transaction_id = 'MP' . time() . rand(100, 999);
        $payment_stmt = $conn->prepare("INSERT INTO mpesa_payments (customer_id, product_id, amount, status, transaction_id, payment_type) VALUES (?, ?, ?, 'completed', ?, ?)");
        
        $payment_type = ($product_id == 'full') ? 'cart' : 'single';
        $payment_stmt->bind_param("iidss", $customer_id, $product_id, $amount, $transaction_id, $payment_type);
        
        if ($payment_stmt->execute()) {
            $mpesa_success = "Payment of KSh " . number_format($amount, 2) . " processed successfully! Transaction ID: " . $transaction_id;
            
            // Mark item as paid in session
            if (!isset($_SESSION['paid_items'])) {
                $_SESSION['paid_items'] = [];
            }
            
            if ($product_id == 'full') {
                // Mark all cart items as paid
                foreach ($_SESSION['cart'] as $pid => $quantity) {
                    $_SESSION['paid_items'][$pid] = true;
                }
            } else {
                $_SESSION['paid_items'][$product_id] = true;
            }
            
            // Update product quantity for single product payments
            if ($product_id != 'full' && $product_id != 'cart') {
                $quantity_in_cart = $_SESSION['cart'][$product_id] ?? 1;
                $update_stmt = $conn->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?");
                $update_stmt->bind_param("ii", $quantity_in_cart, $product_id);
                $update_stmt->execute();
            }
        } else {
            $mpesa_error = "Error processing payment. Please try again.";
        }
    } else {
        $mpesa_error = "Invalid PIN. Please enter a valid 4-digit M-Pesa PIN.";
    }
}

// Get products with average ratings based on search
$products_query = "
    SELECT p.*, 
           COALESCE(AVG(pr.rating), 0) as avg_rating,
           COUNT(pr.id) as rating_count
    FROM products p 
    LEFT JOIN product_ratings pr ON p.id = pr.product_id 
    WHERE p.quantity > 0 
";

if (!empty($search)) {
    $products_query .= " AND (p.product_name LIKE ? OR p.category LIKE ? OR p.description LIKE ?)";
}

$products_query .= " GROUP BY p.id ORDER BY p.id DESC LIMIT 12";

$products_stmt = $conn->prepare($products_query);

if (!empty($search)) {
    $searchTerm = "%$search%";
    $products_stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
}

$products_stmt->execute();
$products = $products_stmt->get_result();

// Get cart count
$cart_count = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;

// Get customer orders
$orders = $conn->query("SELECT o.*, COUNT(oi.id) as item_count 
                       FROM orders o 
                       LEFT JOIN order_items oi ON o.id = oi.order_id 
                       WHERE o.customer_id = $customer_id 
                       GROUP BY o.id 
                       ORDER BY o.created_at DESC");

// Get order progress for chat to order
$order_progress = $conn->query("SELECT o.*, op.status, op.notes, op.updated_at 
                               FROM orders o 
                               LEFT JOIN order_progress op ON o.id = op.order_id 
                               WHERE o.customer_id = $customer_id 
                               ORDER BY op.updated_at DESC");

// Get recent ratings for display
$recent_ratings = $conn->query("SELECT pr.*, c.name as customer_name, p.product_name 
                        FROM product_ratings pr 
                        JOIN customers c ON pr.customer_id = c.id 
                        JOIN products p ON pr.product_id = p.id 
                        ORDER BY pr.created_at DESC 
                        LIMIT 5");

// Get support tickets
$support_tickets = $conn->query("SELECT * FROM support_tickets 
                                WHERE customer_id = $customer_id 
                                ORDER BY created_at DESC");

// Get customer payment history
$payment_history = $conn->query("SELECT mp.*, p.product_name 
                                FROM mpesa_payments mp 
                                JOIN products p ON mp.product_id = p.id 
                                WHERE mp.customer_id = $customer_id 
                                ORDER BY mp.created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leshan OMS - Online Market</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        :root {
            --primary: #ff9900;
            --secondary: #232f3e;
            --accent: #146eb4;
            --light: #f5f5f5;
            --dark: #131a22;
            --text: #333;
            --text-light: #666;
        }

        :root[data-theme="dark"] {
            --primary: #ff9900;
            --secondary: #1a1a1a;
            --accent: #4a9fe3;
            --light: #2d2d2d;
            --dark: #1a1a1a;
            --text: #e0e0e0;
            --text-light: #a0a0a0;
        }

        body {
            background: var(--light);
            color: var(--text);
            transition: background-color 0.3s, color 0.3s;
        }

        /* Top Navigation Bar */
        .top-nav {
            background: var(--secondary);
            color: white;
            padding: 10px 0;
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
        }

        .logo {
            font-size: 24px;
            font-weight: bold;
            color: var(--primary);
        }

        .search-bar {
            flex: 1;
            max-width: 600px;
            margin: 0 20px;
            display: flex;
        }

        .search-bar input {
            flex: 1;
            padding: 10px 15px;
            border: none;
            border-radius: 4px 0 0 4px;
            font-size: 14px;
            background: white;
            color: #333;
        }

        .search-bar button {
            background: var(--primary);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 0 4px 4px 0;
            cursor: pointer;
        }

        .nav-icons {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .nav-icon {
            color: white;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            font-size: 12px;
            cursor: pointer;
            transition: color 0.3s;
        }

        .nav-icon:hover {
            color: var(--primary);
        }

        .nav-icon i {
            font-size: 20px;
            margin-bottom: 4px;
        }

        .cart-badge {
            background: var(--primary);
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 10px;
            position: absolute;
            top: -5px;
            right: -5px;
        }

        /* Categories Bar */
        .categories-nav {
            background: var(--dark);
            padding: 10px 0;
        }

        .categories-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            padding: 0 20px;
        }

        .category-link {
            color: white;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s;
            cursor: pointer;
        }

        .category-link:hover {
            color: var(--primary);
        }

        /* Main Content */
        .main-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
        }

        /* Hero Banner */
        .hero-banner {
            background: linear-gradient(135deg, var(--primary), #ff6600);
            color: white;
            padding: 40px;
            border-radius: 8px;
            margin-bottom: 30px;
            text-align: center;
        }

        .hero-banner h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .hero-banner p {
            font-size: 1.2rem;
            opacity: 0.9;
        }

        /* Products Grid */
        .products-section {
            margin-bottom: 40px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 1.5rem;
            color: var(--secondary);
        }

        .view-all {
            color: var(--accent);
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }

        .product-card {
            background: var(--light);
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
            cursor: pointer;
        }

        .product-card:hover {
            transform: translateY(-5px);
        }

        .product-image {
            height: 200px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            margin-bottom: 15px;
            overflow: hidden;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .product-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--secondary);
        }

        .product-price {
            font-size: 1.3rem;
            font-weight: bold;
            color: var(--primary);
            margin-bottom: 10px;
        }

        .product-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            flex: 2;
        }

        .btn-secondary {
            background: var(--secondary);
            color: white;
            flex: 1;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        /* Modal Overlay */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal-content {
            background: var(--light);
            color: var(--text);
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            border-radius: 8px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .modal-header {
            background: var(--secondary);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
        }

        .modal-body {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
        }

        /* Side Navigation */
        .side-nav {
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            height: 100vh;
            background: var(--secondary);
            color: white;
            transform: translateX(-100%);
            transition: transform 0.3s;
            z-index: 1001;
        }

        .side-nav.active {
            transform: translateX(0);
        }

        .nav-toggle {
            background: none;
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
        }

        .side-nav-header {
            padding: 20px;
            background: var(--dark);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .side-nav-menu {
            padding: 20px 0;
        }

        .nav-item {
            padding: 15px 20px;
            color: white;
            text-decoration: none;
            display: block;
            transition: background 0.3s;
            border-left: 4px solid transparent;
            cursor: pointer;
        }

        .nav-item:hover, .nav-item.active {
            background: rgba(255,255,255,0.1);
            border-left-color: var(--primary);
        }

        .nav-item i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        /* Profile Popup */
        .profile-popup {
            position: absolute;
            top: 100%;
            right: 0;
            background: var(--light);
            color: var(--text);
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            padding: 20px;
            min-width: 250px;
            display: none;
            z-index: 1002;
            margin-top: 10px;
        }

        .profile-popup.active {
            display: block;
        }

        .profile-header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .profile-avatar {
            width: 60px;
            height: 60px;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            margin: 0 auto 10px;
        }

        .profile-name {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .profile-email {
            color: var(--text-light);
            font-size: 0.9rem;
        }

        .profile-links {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .profile-link {
            padding: 10px 15px;
            background: #f8f9fa;
            border-radius: 5px;
            text-decoration: none;
            color: var(--text);
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .profile-link:hover {
            background: var(--primary);
            color: white;
        }

        /* Cart Styles */
        .cart-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #eee;
            gap: 15px;
        }

        .cart-item-image {
            width: 80px;
            height: 80px;
            background: #f5f5f5;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .cart-item-details {
            flex: 1;
        }

        .cart-item-name {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .cart-item-price {
            color: var(--primary);
            font-weight: bold;
        }

        .cart-item-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .quantity-input {
            width: 60px;
            padding: 5px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        /* Order Styles */
        .order-card {
            background: var(--light);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .order-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-processing { background: #cce7ff; color: #004085; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }

        /* Payment History Styles */
        .payment-item {
            background: var(--light);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            border-left: 4px solid #28a745;
        }

        .payment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .payment-amount {
            font-size: 1.2rem;
            font-weight: bold;
            color: #28a745;
        }

        .payment-details {
            color: var(--text-light);
            font-size: 0.9rem;
        }

        /* Store Info Styles */
        .store-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            align-items: center;
        }

        .store-image {
            border-radius: 8px;
            overflow: hidden;
        }

        .store-image img {
            width: 100%;
            height: auto;
            display: block;
        }

        /* Help Guide Styles */
        .help-section {
            margin-bottom: 25px;
        }

        .help-section h4 {
            color: var(--secondary);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .help-section ul {
            padding-left: 20px;
        }

        .help-section li {
            margin-bottom: 8px;
            line-height: 1.5;
        }

        /* Progress Bar */
        .progress-container {
            margin: 20px 0;
        }

        .progress-bar {
            height: 10px;
            background: #e0e0e0;
            border-radius: 5px;
            overflow: hidden;
            margin-bottom: 10px;
        }

        .progress-fill {
            height: 100%;
            background: var(--primary);
            transition: width 0.3s;
        }

        .progress-steps {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
        }

        .progress-step {
            text-align: center;
            flex: 1;
            position: relative;
        }

        .progress-step.active {
            color: var(--primary);
            font-weight: bold;
        }

        /* Rating Styles */
        .rating-stars {
            display: flex;
            gap: 5px;
            margin: 10px 0;
        }

        .rating-star {
            color: #ddd;
            cursor: pointer;
            font-size: 1.5rem;
            transition: color 0.2s;
        }

        .rating-star.active {
            color: #ffc107;
        }

        .rating-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .rating-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .rating-customer {
            font-weight: bold;
        }

        .rating-product {
            color: var(--text-light);
            font-style: italic;
        }

        .rating-comment {
            margin-top: 10px;
            line-height: 1.5;
        }

        /* Settings Styles */
        .setting-option {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }

        .setting-label {
            font-weight: 500;
        }

        .theme-toggle {
            display: flex;
            gap: 10px;
        }

        .theme-option {
            padding: 8px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .theme-option.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        /* Support Form */
        .support-form {
            margin-top: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
            color: #333;
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        /* M-Pesa Payment Styles */
        .mpesa-payment {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }

        .mpesa-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .mpesa-logo {
            color: #00a650;
            font-size: 24px;
        }

        .mpesa-title {
            font-weight: bold;
            color: #00a650;
        }

        .payment-total {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            font-weight: bold;
            font-size: 1.2rem;
            border-top: 2px solid #eee;
            margin-top: 10px;
        }

        .mpesa-pin-form {
            margin-top: 20px;
        }

        .pin-input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            margin-bottom: 15px;
            text-align: center;
            letter-spacing: 5px;
        }

        .amount-input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            margin-bottom: 15px;
        }

        .paid-badge {
            background: #28a745;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        /* Messages */
        .message {
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* No Products Found */
        .no-products {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 20px;
            color: var(--text-light);
        }

        .no-products i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #ccc;
        }

        .no-products h3 {
            margin-bottom: 10px;
            color: var(--secondary);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .nav-container {
                flex-wrap: wrap;
            }
            
            .search-bar {
                order: 3;
                margin: 10px 0 0 0;
                max-width: 100%;
            }
            
            .categories-container {
                flex-wrap: wrap;
                gap: 10px;
            }
            
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
            
            .modal-content {
                width: 95%;
                height: 90%;
            }
            
            .cart-item {
                flex-direction: column;
                text-align: center;
            }
            
            .store-info {
                grid-template-columns: 1fr;
            }
            
            .profile-popup {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                width: 90%;
                max-width: 300px;
            }
        }
    </style>
</head>
<body data-theme="<?php echo $current_theme; ?>">
    <!-- Top Navigation -->
    <div class="top-nav">
        <div class="nav-container">
            <button class="nav-toggle" onclick="toggleSideNav()">
                <i class="fas fa-bars"></i>
            </button>
            <div class="logo">LESHAN OMS</div>
            <form method="GET" action="" class="search-bar" id="searchForm">
                <input type="text" name="search" id="searchInput" placeholder="Search products, brands and categories" value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit"><i class="fas fa-search"></i></button>
            </form>
            <div class="nav-icons">
                <div class="nav-icon" onclick="toggleProfilePopup()" style="position: relative;">
                    <i class="fas fa-user"></i>
                    <span>Account</span>
                    <div class="profile-popup" id="profilePopup">
                        <div class="profile-header">
                            <div class="profile-avatar">
                                <?php echo strtoupper(substr($customer_name, 0, 1)); ?>
                            </div>
                            <div class="profile-name"><?php echo htmlspecialchars($customer_name); ?></div>
                            <div class="profile-email"><?php echo htmlspecialchars($customer_email); ?></div>
                        </div>
                        <div class="profile-links">
                            <a href="#" class="profile-link" onclick="openModal('profile')">
                                <i class="fas fa-user-edit"></i> Edit Profile
                            </a>
                            <a href="#" class="profile-link" onclick="openModal('orders')">
                                <i class="fas fa-clipboard-list"></i> My Orders
                            </a>
                            <a href="#" class="profile-link" onclick="openModal('payments')">
                                <i class="fas fa-receipt"></i> Payment History
                            </a>
                            <a href="#" class="profile-link" onclick="openModal('settings')">
                                <i class="fas fa-cog"></i> Settings
                            </a>
                            <a href="customer_logout.php" class="profile-link">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
                <a href="#" class="nav-icon" onclick="openModal('help')">
                    <i class="fas fa-question-circle"></i>
                    <span>Help</span>
                </a>
                <a href="#" class="nav-icon" onclick="openModal('cart')" style="position: relative;">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Cart</span>
                    <?php if ($cart_count > 0): ?>
                        <span class="cart-badge"><?php echo $cart_count; ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
    </div>

    <!-- Categories Navigation -->
    <div class="categories-nav">
        <div class="categories-container">
            <a href="#" class="category-link" onclick="openModal('official-stores')">Official Stores</a>
            <a href="#" class="category-link" onclick="openWhatsApp()">WhatsApp</a>
            <a href="#" class="category-link" onclick="openModal('chat-order')">Chat To Order</a>
            <a href="#" class="category-link" onclick="openModal('nairobi-town')">NAIROBI TOWN</a>
            <a href="#" class="category-link" onclick="openModal('now-on')">NOW ON LESHAN OMS</a>
        </div>
    </div>

    <!-- Side Navigation -->
    <div class="side-nav" id="sideNav">
        <div class="side-nav-header">
            <h3>Navigation Menu</h3>
            <button class="nav-toggle" onclick="toggleSideNav()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="side-nav-menu">
            <a href="#" class="nav-item" onclick="openModal('dashboard')">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="#" class="nav-item" onclick="openModal('products')">
                <i class="fas fa-box"></i> All Products
            </a>
            <a href="#" class="nav-item" onclick="openModal('cart')">
                <i class="fas fa-shopping-cart"></i> Shopping Cart
            </a>
            <a href="#" class="nav-item" onclick="openModal('orders')">
                <i class="fas fa-clipboard-list"></i> My Orders
            </a>
            <a href="#" class="nav-item" onclick="openModal('payments')">
                <i class="fas fa-receipt"></i> Payment History
            </a>
            <a href="#" class="nav-item" onclick="openModal('profile')">
                <i class="fas fa-user"></i> Profile
            </a>
            <a href="#" class="nav-item" onclick="openModal('settings')">
                <i class="fas fa-cog"></i> Settings
            </a>
            <a href="customer_logout.php" class="nav-item">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-container">
        <!-- Hero Banner -->
        <div class="hero-banner">
            <h1>Welcome to Leshan OMS</h1>
            <p>Your one-stop online market solution</p>
        </div>

        <!-- Featured Products -->
        <div class="products-section">
            <div class="section-header">
                <h2 class="section-title">
                    <?php echo !empty($search) ? 'Search Results' : 'Featured Products'; ?>
                    <?php if (!empty($search)): ?>
                        <span style="font-size: 1rem; color: var(--text-light);">for "<?php echo htmlspecialchars($search); ?>"</span>
                    <?php endif; ?>
                </h2>
                <?php if (empty($search)): ?>
                    <a href="#" class="view-all" onclick="openModal('products')">View All</a>
                <?php else: ?>
                    <a href="?" class="view-all">Clear Search</a>
                <?php endif; ?>
            </div>
            <div class="products-grid">
                <?php if ($products->num_rows > 0): ?>
                    <?php while($product = $products->fetch_assoc()): 
                        $avg_rating = round($product['avg_rating'], 1);
                        $rating_count = $product['rating_count'];
                    ?>
                    <div class="product-card">
                        <div class="product-image">
                            <?php if (!empty($product['image_path'])): ?>
                                <img src="<?php echo $product['image_path']; ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                            <?php else: ?>
                                📦
                            <?php endif; ?>
                        </div>
                        <h3 class="product-title"><?php echo htmlspecialchars($product['product_name']); ?></h3>
                        <div class="product-price">KSh <?php echo number_format($product['price'], 2); ?></div>
                        <?php if ($avg_rating > 0): ?>
                            <div style="display: flex; align-items: center; gap: 5px; margin-bottom: 10px;">
                                <div style="color: #ffc107;">
                                    <?php 
                                    $full_stars = floor($avg_rating);
                                    $half_star = ($avg_rating - $full_stars) >= 0.5;
                                    for ($i = 1; $i <= 5; $i++): 
                                        if ($i <= $full_stars): ?>
                                            ★
                                        <?php elseif ($i == $full_stars + 1 && $half_star): ?>
                                            ★
                                        <?php else: ?>
                                            ☆
                                        <?php endif;
                                    endfor; ?>
                                </div>
                                <span style="color: #666; font-size: 0.9rem;">(<?php echo $avg_rating; ?>)</span>
                            </div>
                        <?php endif; ?>
                        <p style="color: #666; font-size: 0.9rem; margin-bottom: 10px;">In stock: <?php echo $product['quantity']; ?></p>
                        <div class="product-actions">
                            <form method="POST" style="display: flex; gap: 10px; width: 100%;">
                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit" name="add_to_cart" class="btn btn-secondary">
                                    <i class="fas fa-cart-plus"></i>
                                </button>
                                <button type="button" class="btn btn-primary" onclick="openProductModal(<?php echo $product['id']; ?>)">
                                    View Details
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="no-products">
                        <i class="fas fa-search"></i>
                        <h3>No products found</h3>
                        <p>Try adjusting your search terms or browse different categories.</p>
                        <?php if (!empty($search)): ?>
                            <a href="?" class="btn btn-primary" style="margin-top: 15px; display: inline-block;">Show All Products</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- More Categories -->
        <div class="products-section">
            <div class="section-header">
                <h2 class="section-title">More Categories</h2>
            </div>
            <div class="categories-container" style="background: none; padding: 0;">
                <a href="#" class="category-link" style="color: var(--secondary);" onclick="openModal('health-beauty')">Health & Beauty</a>
                <a href="#" class="category-link" style="color: var(--secondary);" onclick="openModal('home-office')">Home & Office</a>
                <a href="#" class="category-link" style="color: var(--secondary);" onclick="openModal('fashion')">Fashion</a>
                <a href="#" class="category-link" style="color: var(--secondary);" onclick="openModal('computing')">Computing</a>
                <a href="#" class="category-link" style="color: var(--secondary);" onclick="openModal('gaming')">Gaming</a>
                <a href="#" class="category-link" style="color: var(--secondary);" onclick="openModal('supermarket')">Supermarket</a>
                <a href="#" class="category-link" style="color: var(--secondary);" onclick="openModal('baby')">Baby Products</a>
                <a href="#" class="category-link" style="color: var(--secondary);" onclick="openModal('other')">Other categories</a>
            </div>
        </div>
    </div>

    <!-- Modal Overlay -->
    <div class="modal-overlay" id="modalOverlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Modal Title</h3>
                <button class="modal-close" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        // Modal functionality
        function openModal(type) {
            const modalOverlay = document.getElementById('modalOverlay');
            const modalTitle = document.getElementById('modalTitle');
            const modalBody = document.getElementById('modalBody');
            
            // Set modal title and content based on type
            switch(type) {
                case 'dashboard':
                    modalTitle.textContent = 'Dashboard Overview';
                    modalBody.innerHTML = `
                        <div style="padding: 20px;">
                            <h3>Welcome, <?php echo htmlspecialchars($customer_name); ?>!</h3>
                            <p>Email: <?php echo htmlspecialchars($customer_email); ?></p>
                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-top: 20px;">
                                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center;">
                                    <h4>Cart Items</h4>
                                    <p style="font-size: 2rem; color: var(--primary);"><?php echo $cart_count; ?></p>
                                </div>
                                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center;">
                                    <h4>Total Orders</h4>
                                    <p style="font-size: 2rem; color: var(--primary);"><?php echo $orders->num_rows; ?></p>
                                </div>
                            </div>
                        </div>
                    `;
                    break;
                    
                case 'products':
                    modalTitle.textContent = 'All Products';
                    modalBody.innerHTML = `
                        <div style="padding: 20px;">
                            <h3>Available Products</h3>
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; margin-top: 20px;">
                                <?php 
                                $all_products = $conn->query("
                                    SELECT p.*, 
                                           COALESCE(AVG(pr.rating), 0) as avg_rating,
                                           COUNT(pr.id) as rating_count
                                    FROM products p 
                                    LEFT JOIN product_ratings pr ON p.id = pr.product_id 
                                    WHERE p.quantity > 0 
                                    GROUP BY p.id
                                ");
                                while($product = $all_products->fetch_assoc()): 
                                    $avg_rating = round($product['avg_rating'], 1);
                                ?>
                                <div style="background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                                    <div style="height: 120px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 6px; display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem; margin-bottom: 10px; overflow: hidden;">
                                        <?php if (!empty($product['image_path'])): ?>
                                            <img src="<?php echo $product['image_path']; ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                        <?php else: ?>
                                            📦
                                        <?php endif; ?>
                                    </div>
                                    <h4 style="margin-bottom: 5px;"><?php echo htmlspecialchars($product['product_name']); ?></h4>
                                    <p style="color: #666; font-size: 0.9rem; margin-bottom: 8px;"><?php echo htmlspecialchars($product['category']); ?></p>
                                    <p style="font-weight: bold; color: var(--primary); margin-bottom: 5px;">KSh <?php echo number_format($product['price'], 2); ?></p>
                                    <?php if ($avg_rating > 0): ?>
                                        <div style="display: flex; align-items: center; gap: 5px; margin-bottom: 5px;">
                                            <div style="color: #ffc107;">
                                                <?php 
                                                $full_stars = floor($avg_rating);
                                                $half_star = ($avg_rating - $full_stars) >= 0.5;
                                                for ($i = 1; $i <= 5; $i++): 
                                                    if ($i <= $full_stars): ?>
                                                        ★
                                                    <?php elseif ($i == $full_stars + 1 && $half_star): ?>
                                                        ★
                                                    <?php else: ?>
                                                        ☆
                                                    <?php endif;
                                                endfor; ?>
                                            </div>
                                            <span style="color: #666; font-size: 0.8rem;">(<?php echo $avg_rating; ?>)</span>
                                        </div>
                                    <?php endif; ?>
                                    <p style="color: #666; font-size: 0.8rem; margin-bottom: 10px;">Stock: <?php echo $product['quantity']; ?></p>
                                    <form method="POST" style="display: flex; gap: 5px;">
                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                        <input type="hidden" name="quantity" value="1">
                                        <button type="submit" name="add_to_cart" style="background: var(--secondary); color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; flex: 1;">Add to Cart</button>
                                    </form>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    `;
                    break;
                    
                case 'cart':
                    modalTitle.textContent = 'Shopping Cart';
                    modalBody.innerHTML = `
                        <div style="padding: 20px;">
                            <h3>Your Cart (<?php echo $cart_count; ?> items)</h3>
                            <?php if (isset($success_message)): ?>
                                <div class="message success"><?php echo $success_message; ?></div>
                            <?php endif; ?>
                            <?php if (isset($error_message)): ?>
                                <div class="message error"><?php echo $error_message; ?></div>
                            <?php endif; ?>
                            
                            <?php if ($cart_count > 0): ?>
                                <div style="margin-bottom: 20px;">
                                    <?php 
                                    $cart_total = 0;
                                    $payment_items = [];
                                    if (isset($_SESSION['cart'])) {
                                        foreach ($_SESSION['cart'] as $product_id => $quantity) {
                                            $product_stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
                                            $product_stmt->bind_param("i", $product_id);
                                            $product_stmt->execute();
                                            $product_result = $product_stmt->get_result();
                                            $product = $product_result->fetch_assoc();
                                            $item_total = $product['price'] * $quantity;
                                            $cart_total += $item_total;
                                            
                                            // Check if item is paid
                                            $is_paid = isset($_SESSION['paid_items']) && isset($_SESSION['paid_items'][$product_id]) ? $_SESSION['paid_items'][$product_id] : false;
                                            
                                            if (!$is_paid) {
                                                $payment_items[] = [
                                                    'id' => $product_id,
                                                    'name' => $product['product_name'],
                                                    'quantity' => $quantity,
                                                    'price' => $product['price'],
                                                    'total' => $item_total
                                                ];
                                            }
                                    ?>
                                    <div class="cart-item">
                                        <div class="cart-item-image">
                                            <?php if (!empty($product['image_path'])): ?>
                                                <img src="<?php echo $product['image_path']; ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 6px;">
                                            <?php else: ?>
                                                📦
                                            <?php endif; ?>
                                        </div>
                                        <div class="cart-item-details">
                                            <div class="cart-item-name"><?php echo htmlspecialchars($product['product_name']); ?></div>
                                            <div class="cart-item-price">KSh <?php echo number_format($product['price'], 2); ?> each</div>
                                            <div>Quantity: <?php echo $quantity; ?></div>
                                            <div class="cart-item-total">Total: KSh <?php echo number_format($item_total, 2); ?></div>
                                        </div>
                                        <div class="cart-item-actions">
                                            <?php if ($is_paid): ?>
                                                <span class="paid-badge">PAID</span>
                                            <?php else: ?>
                                                <form method="POST" style="display: flex; align-items: center; gap: 10px;">
                                                    <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                                                    <input type="number" name="quantity" value="<?php echo $quantity; ?>" min="1" max="<?php echo $product['quantity']; ?>" class="quantity-input">
                                                    <button type="submit" name="update_cart" class="btn btn-primary">Update</button>
                                                </form>
                                                <button class="btn btn-success" onclick="initiateMpesaPayment(<?php echo $product_id; ?>, <?php echo $item_total; ?>, '<?php echo htmlspecialchars($product['product_name']); ?>')">
                                                    Pay with M-Pesa
                                                </button>
                                            <?php endif; ?>
                                            <form method="POST">
                                                <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                                                <button type="submit" name="remove_from_cart" class="btn btn-danger">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    <?php }} ?>
                                </div>
                                
                                <?php if (count($payment_items) > 0): ?>
                                <div class="mpesa-payment">
                                    <div class="mpesa-header">
                                        <div class="mpesa-logo">
                                            <i class="fas fa-money-bill-wave"></i>
                                        </div>
                                        <div class="mpesa-title">M-Pesa Payment Options</div>
                                    </div>
                                    
                                    <div class="payment-total">
                                        <span>Total Cart Amount:</span>
                                        <span>KSh <?php echo number_format($cart_total, 2); ?></span>
                                    </div>
                                    
                                    <div style="text-align: center; margin-top: 20px;">
                                        <!-- Individual item payments -->
                                        <?php foreach($payment_items as $item): ?>
                                        <div style="margin-bottom: 10px;">
                                            <button class="btn btn-success" onclick="initiateMpesaPayment(<?php echo $item['id']; ?>, <?php echo $item['total']; ?>, '<?php echo htmlspecialchars($item['name']); ?>')" style="margin: 5px; width: 100%;">
                                                <i class="fas fa-money-check-alt"></i> Pay <?php echo htmlspecialchars($item['name']); ?> - KSh <?php echo number_format($item['total'], 2); ?>
                                            </button>
                                        </div>
                                        <?php endforeach; ?>
                                        
                                        <!-- Full cart payment -->
                                        <?php 
                                        $unpaid_total = array_sum(array_column($payment_items, 'total'));
                                        if ($unpaid_total > 0): 
                                        ?>
                                        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">
                                           
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <div style="border-top: 2px solid #eee; padding-top: 20px; margin-top: 20px;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                        <h4>Order Total: KSh <?php echo number_format($cart_total, 2); ?></h4>
                                        <form method="POST">
                                            <button type="submit" name="place_order" class="btn btn-success" style="padding: 10px 20px;">
                                                <i class="fas fa-shopping-bag"></i> Place Order
                                            </button>
                                        </form>
                                    </div>
                                    <p style="color: #666; font-size: 0.9rem; text-align: center;">
                                        <i class="fas fa-info-circle"></i> Note: Items will remain in your cart after ordering for easy reordering.
                                    </p>
                                </div>
                            <?php else: ?>
                                <p style="text-align: center; color: #666; margin-top: 50px;">Your cart is empty</p>
                            <?php endif; ?>
                        </div>
                    `;
                    break;
                    
                case 'orders':
                    modalTitle.textContent = 'My Orders';
                    modalBody.innerHTML = `
                        <div style="padding: 20px;">
                            <h3>Order History</h3>
                            <?php if ($orders->num_rows > 0): ?>
                                <?php while($order = $orders->fetch_assoc()): 
                                    $status_class = 'status-' . $order['status'];
                                    $status_text = ucfirst($order['status']);
                                ?>
                                <div class="order-card">
                                    <div class="order-header">
                                        <div>
                                            <strong>Order #<?php echo $order['id']; ?></strong>
                                            <div style="color: #666; font-size: 0.9rem;">
                                                <?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?>
                                            </div>
                                        </div>
                                        <div class="order-status <?php echo $status_class; ?>">
                                            <?php echo $status_text; ?>
                                        </div>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <div>
                                            <strong>KSh <?php echo number_format($order['total_amount'], 2); ?></strong>
                                            <div style="color: #666; font-size: 0.9rem;">
                                                <?php echo $order['item_count']; ?> item(s)
                                            </div>
                                        </div>
                                        <?php if ($order['status'] == 'pending'): ?>
                                            <span style="color: #856404; font-size: 0.9rem;">
                                                <i class="fas fa-clock"></i> Waiting for admin confirmation
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p style="text-align: center; color: #666; margin-top: 50px;">You haven't placed any orders yet</p>
                            <?php endif; ?>
                        </div>
                    `;
                    break;
                    
                case 'payments':
                    modalTitle.textContent = 'Payment History';
                    modalBody.innerHTML = `
                        <div style="padding: 20px;">
                            <h3>Your Payment History</h3>
                            <?php if ($payment_history->num_rows > 0): ?>
                                <?php while($payment = $payment_history->fetch_assoc()): ?>
                                <div class="payment-item">
                                    <div class="payment-header">
                                        <div>
                                            <strong><?php echo htmlspecialchars($payment['product_name']); ?></strong>
                                            <div class="payment-details">
                                                Transaction ID: <?php echo $payment['transaction_id']; ?>
                                            </div>
                                        </div>
                                        <div class="payment-amount">
                                            KSh <?php echo number_format($payment['amount'], 2); ?>
                                        </div>
                                    </div>
                                    <div class="payment-details">
                                        <div>Date: <?php echo date('M j, Y g:i A', strtotime($payment['created_at'])); ?></div>
                                        <div>Status: <span style="color: #28a745;">Completed</span></div>
                                        <div>Payment Type: <?php echo ucfirst($payment['payment_type'] ?? 'single'); ?></div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p style="text-align: center; color: #666; margin-top: 50px;">
                                    No payment history found. Make a purchase to see your payment history here.
                                </p>
                            <?php endif; ?>
                        </div>
                    `;
                    break;
                    
                case 'profile':
                    modalTitle.textContent = 'Profile Settings';
                    modalBody.innerHTML = `
                        <div style="padding: 20px;">
                            <h3>Profile Information</h3>
                            <form style="max-width: 400px;">
                                <div style="margin-bottom: 15px;">
                                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Full Name</label>
                                    <input type="text" value="<?php echo htmlspecialchars($customer_name); ?>" style="width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">
                                </div>
                                <div style="margin-bottom: 15px;">
                                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Email</label>
                                    <input type="email" value="<?php echo htmlspecialchars($customer_email); ?>" style="width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">
                                </div>
                                <button type="submit" style="background: var(--primary); color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">Update Profile</button>
                            </form>
                        </div>
                    `;
                    break;

                case 'settings':
                    modalTitle.textContent = 'Settings';
                    modalBody.innerHTML = `
                        <div style="padding: 20px;">
                            <h3>Application Settings</h3>
                            
                            <div class="setting-option">
                                <div class="setting-label">
                                    <i class="fas fa-palette"></i> Theme Preference
                                </div>
                                <form method="POST" class="theme-toggle">
                                    <input type="hidden" name="set_theme" value="1">
                                    <div class="theme-option ${'<?php echo $current_theme; ?>' === 'light' ? 'active' : ''}" onclick="setTheme('light')">
                                        Light Mode
                                    </div>
                                    <div class="theme-option ${'<?php echo $current_theme; ?>' === 'dark' ? 'active' : ''}" onclick="setTheme('dark')">
                                        Dark Mode
                                    </div>
                                </form>
                            </div>
                            
                            <div class="setting-option">
                                <div class="setting-label">
                                    <i class="fas fa-bell"></i> Notifications
                                </div>
                                <div>
                                    <label style="display: flex; align-items: center; gap: 10px;">
                                        <input type="checkbox" checked> Order Updates
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 10px;">
                                        <input type="checkbox" checked> Promotional Offers
                                    </label>
                                </div>
                            </div>
                            
                            <div class="setting-option">
                                <div class="setting-label">
                                    <i class="fas fa-language"></i> Language
                                </div>
                                <select style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">
                                    <option>English</option>
                                    <option>Swahili</option>
                                </select>
                            </div>
                            
                            <div class="setting-option">
                                <div class="setting-label">
                                    <i class="fas fa-shield-alt"></i> Privacy & Security
                                </div>
                                <div>
                                    <button style="background: #f8f9fa; border: 1px solid #ddd; padding: 8px 15px; border-radius: 4px; cursor: pointer;">
                                        Change Password
                                    </button>
                                </div>
                            </div>
                            
                            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
                                <h4>About Leshan OMS</h4>
                                <p style="color: #666; margin-top: 10px;">
                                    Version 2.0.0<br>
                                    &copy; 2023 Leshan OMS. All rights reserved.
                                </p>
                            </div>
                        </div>
                    `;
                    break;

                case 'chat-order':
                    modalTitle.textContent = 'Chat To Order - Order Progress';
                    modalBody.innerHTML = `
                        <div style="padding: 20px;">
                            <h3>Your Order Progress</h3>
                            <p style="color: #666; margin-bottom: 20px;">
                                Track the progress of your orders here. Our team will update the status as your order moves through processing.
                            </p>
                            
                            <?php if ($order_progress->num_rows > 0): ?>
                                <?php while($progress = $order_progress->fetch_assoc()): ?>
                                <div class="order-card">
                                    <div class="order-header">
                                        <div>
                                            <strong>Order #<?php echo $progress['id']; ?></strong>
                                            <div style="color: #666; font-size: 0.9rem;">
                                                Placed on: <?php echo date('M j, Y g:i A', strtotime($progress['created_at'])); ?>
                                            </div>
                                        </div>
                                        <div class="order-status status-<?php echo $progress['status']; ?>">
                                            <?php echo ucfirst($progress['status']); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="progress-container">
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: ${getProgressWidth('<?php echo $progress['status']; ?>')}%"></div>
                                        </div>
                                        <div class="progress-steps">
                                            <div class="progress-step ${'<?php echo $progress['status']; ?>' === 'pending' ? 'active' : ''}">Pending</div>
                                            <div class="progress-step ${'<?php echo $progress['status']; ?>' === 'processing' ? 'active' : ''}">Processing</div>
                                            <div class="progress-step ${'<?php echo $progress['status']; ?>' === 'shipped' ? 'active' : ''}">Shipped</div>
                                            <div class="progress-step ${'<?php echo $progress['status']; ?>' === 'delivered' ? 'active' : ''}">Delivered</div>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($progress['notes'])): ?>
                                        <div style="background: #f8f9fa; padding: 10px; border-radius: 4px; margin-top: 10px;">
                                            <strong>Admin Note:</strong> <?php echo htmlspecialchars($progress['notes']); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div style="color: #666; font-size: 0.9rem; margin-top: 10px;">
                                        Last updated: <?php echo date('M j, Y g:i A', strtotime($progress['updated_at'])); ?>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p style="text-align: center; color: #666; margin-top: 50px;">
                                    No orders found. Place an order to track its progress here.
                                </p>
                            <?php endif; ?>
                        </div>
                    `;
                    break;

                case 'help':
                    modalTitle.textContent = 'Help & Support';
                    modalBody.innerHTML = `
                        <div style="padding: 20px;">
                            <h3 style="color: var(--primary); margin-bottom: 20px;">Leshan OMS User Guide</h3>
                            
                            <div class="help-section">
                                <h4><i class="fas fa-shopping-cart"></i> Shopping Guide</h4>
                                <ul>
                                    <li><strong>Browse Products:</strong> Use the search bar or browse categories to find products</li>
                                    <li><strong>Add to Cart:</strong> Click the cart icon or "Add to Cart" button on any product</li>
                                    <li><strong>View Cart:</strong> Click the cart icon in the top navigation to see your items</li>
                                    <li><strong>Place Order:</strong> Go to your cart and click "Place Order" to complete purchase</li>
                                </ul>
                            </div>

                            <div class="help-section">
                                <h4><i class="fas fa-clipboard-list"></i> Order Management</h4>
                                <ul>
                                    <li><strong>Track Orders:</strong> View your order history and status in "My Orders"</li>
                                    <li><strong>Order Status:</strong> Orders show as Pending until admin confirmation</li>
                                    <li><strong>Multiple Orders:</strong> You can place multiple orders from the same cart</li>
                                </ul>
                            </div>

                            <div class="help-section">
                                <h4><i class="fas fa-user"></i> Account Management</h4>
                                <ul>
                                    <li><strong>Profile:</strong> Update your personal information in the profile section</li>
                                    <li><strong>Account Access:</strong> Click the account icon for quick access to your profile</li>
                                </ul>
                            </div>

                            <div class="help-section">
                                <h4><i class="fas fa-question-circle"></i> Need More Help?</h4>
                                <ul>
                                    <li><strong>WhatsApp Support:</strong> Click the WhatsApp link for direct messaging</li>
                                    <li><strong>Email Support:</strong> Contact us at support@leshanoms.com</li>
                                    <li><strong>Phone Support:</strong> Call us at +254 792 470 595 during business hours</li>
                                </ul>
                            </div>

                            <div style="background: #e7f3ff; padding: 15px; border-radius: 8px; margin-top: 20px;">
                                <h4 style="color: var(--accent); margin-bottom: 10px;"><i class="fas fa-lightbulb"></i> Quick Tips</h4>
                                <ul style="padding-left: 20px;">
                                    <li>Keep items in your cart for easy reordering</li>
                                    <li>Check product stock levels before ordering</li>
                                    <li>Review your orders regularly for status updates</li>
                                    <li>Use the search function to quickly find products</li>
                                </ul>
                            </div>

                            <div class="support-form">
                                <h4 style="margin-top: 30px; margin-bottom: 15px;">Submit a Support Request</h4>
                                <?php if (isset($support_message)): ?>
                                    <div class="message success"><?php echo $support_message; ?></div>
                                <?php endif; ?>
                                <?php if (isset($support_error)): ?>
                                    <div class="message error"><?php echo $support_error; ?></div>
                                <?php endif; ?>
                                
                                <form method="POST">
                                    <div class="form-group">
                                        <label class="form-label">Describe your issue</label>
                                        <textarea name="issue" class="form-control" placeholder="Please describe the issue you're experiencing..." required></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Priority</label>
                                        <select name="priority" class="form-control">
                                            <option value="low">Low</option>
                                            <option value="medium" selected>Medium</option>
                                            <option value="high">High</option>
                                            <option value="urgent">Urgent</option>
                                        </select>
                                    </div>
                                    <button type="submit" name="submit_support" class="btn btn-primary">
                                        <i class="fas fa-paper-plane"></i> Submit Request
                                    </button>
                                </form>
                            </div>

                            <div style="margin-top: 30px;">
                                <h4>Your Support Tickets</h4>
                                <?php if ($support_tickets->num_rows > 0): ?>
                                    <?php while($ticket = $support_tickets->fetch_assoc()): ?>
                                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 10px;">
                                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                            <strong>Ticket #<?php echo $ticket['id']; ?></strong>
                                            <span style="background: #e9ecef; padding: 3px 8px; border-radius: 4px; font-size: 0.8rem;">
                                                <?php echo ucfirst($ticket['status']); ?>
                                            </span>
                                        </div>
                                        <p style="margin-bottom: 10px;"><?php echo htmlspecialchars($ticket['issue']); ?></p>
                                        <div style="color: #666; font-size: 0.8rem;">
                                            Submitted: <?php echo date('M j, Y g:i A', strtotime($ticket['created_at'])); ?>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <p style="color: #666; text-align: center; margin-top: 20px;">No support tickets submitted yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    `;
                    break;

                case 'now-on':
                    modalTitle.textContent = 'NOW ON LESHAN OMS- Hot Deals';
                    modalBody.innerHTML = `
                        <div style="padding: 20px;">
                            <h3 style="color: var(--primary); margin-bottom: 20px;">Exclusive Deals</h3>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
                                <div>
                                    <h4 style="margin-bottom: 15px;">🔥 Flash Sales</h4>
                                    <ul style="padding-left: 20px;">
                                        <li>Up to 70% off on Electronics</li>
                                        <li>Buy 1 Get 1 Free on Fashion Items</li>
                                        <li>Limited Time Home & Kitchen Deals</li>
                                        <li>Exclusive Mobile Phone Bundles</li>
                                    </ul>
                                </div>
                                <div>
                                    <h4 style="margin-bottom: 15px;">📦 Free Delivery</h4>
                                    <ul style="padding-left: 20px;">
                                        <li>Free delivery on orders over KSh 2,000</li>
                                        <li>Same-day delivery in Nairobi</li>
                                        <li>Next-day delivery nationwide</li>
                                        <li>Pickup stations available</li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div style="background: linear-gradient(135deg, #ff9900, #ff6600); color: white; padding: 20px; border-radius: 8px; text-align: center; margin-bottom: 20px;">
                                <h4>JUMIA PRIME</h4>
                                <p>Unlimited free delivery, exclusive deals, and early access to sales</p>
                                <button style="background: white; color: #ff9900; border: none; padding: 10px 20px; border-radius: 4px; font-weight: bold; margin-top: 10px; cursor: pointer;">
                                    Try 30 Days Free
                                </button>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px; margin-top: 20px;">
                                <div style="text-align: center;">
                                    <div style="height: 100px; background: #f5f5f5; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 2rem; margin-bottom: 10px;">
                                        📱
                                    </div>
                                    <p>Smartphones</p>
                                </div>
                                <div style="text-align: center;">
                                    <div style="height: 100px; background: #f5f5f5; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 2rem; margin-bottom: 10px;">
                                        👕
                                    </div>
                                    <p>Fashion</p>
                                </div>
                                <div style="text-align: center;">
                                    <div style="height: 100px; background: #f5f5f5; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 2rem; margin-bottom: 10px;">
                                        🏠
                                    </div>
                                    <p>Home & Living</p>
                                </div>
                                <div style="text-align: center;">
                                    <div style="height: 100px; background: #f5f5f5; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 2rem; margin-bottom: 10px;">
                                        🎮
                                    </div>
                                    <p>Electronics</p>
                                </div>
                            </div>
                            
                            <div style="margin-top: 30px; text-align: center;">
                                <p style="color: #666;">
                                    <i class="fas fa-info-circle"></i> These deals are exclusive to our JUMIA partnership. Prices and availability may vary.
                                </p>
                            </div>
                        </div>
                    `;
                    break;

                case 'official-stores':
                    modalTitle.textContent = 'Official Stores';
                    modalBody.innerHTML = `
                        <div style="padding: 20px;">
                            <div class="store-info">
                                <div>
                                    <h3 style="color: var(--primary); margin-bottom: 15px;">Welcome to Our Official Stores</h3>
                                    <p style="line-height: 1.6; margin-bottom: 15px;">
                                        Discover the best deals from our trusted official stores. We partner with leading brands 
                                        to bring you authentic products with guaranteed quality and manufacturer warranties.
                                    </p>
                                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                                        <h4 style="color: var(--secondary); margin-bottom: 10px;">🏪 Store Features:</h4>
                                        <ul style="padding-left: 20px;">
                                            <li>100% Authentic Products</li>
                                            <li>Manufacturer Warranty</li>
                                            <li>Direct Brand Partnerships</li>
                                            <li>Exclusive Deals & Offers</li>
                                            <li>Fast & Secure Delivery</li>
                                        </ul>
                                    </div>
                                    <p style="line-height: 1.6;">
                                        Shop with confidence from our verified official stores. Each store is carefully selected 
                                        to ensure you get the best shopping experience with genuine products and excellent customer service.
                                    </p>
                                </div>
                                <div class="store-image">
                                    <img src="https://images.unsplash.com/photo-1563013541-2d0e975f5d44?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80" alt="Official Store">
                                </div>
                            </div>
                        </div>
                    `;
                    break;

                case 'nairobi-town':
                    modalTitle.textContent = 'NAIROBI TOWN';
                    modalBody.innerHTML = `
                        <div style="padding: 20px;">
                            <div class="store-info">
                                <div class="store-image">
                                    <img src="https://images.unsplash.com/photo-1551698618-1dfe5d97d256?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80" alt="Nairobi Town">
                                </div>
                                <div>
                                    <h3 style="color: var(--primary); margin-bottom: 15px;">Welcome to Nairobi Town</h3>
                                    <p style="line-height: 1.6; margin-bottom: 15px;">
                                        Experience the vibrant heart of Kenya's capital city through our Nairobi Town section. 
                                        Discover local products, cultural items, and authentic Kenyan goods sourced directly 
                                        from Nairobi's bustling markets and talented artisans.
                                    </p>
                                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                                        <h4 style="color: var(--secondary); margin-bottom: 10px;">📍 What's Available:</h4>
                                        <ul style="padding-left: 20px;">
                                            <li>Local Artisan Crafts</li>
                                            <li>Traditional Kenyan Products</li>
                                            <li>Nairobi Souvenirs</li>
                                            <li>Local Fashion & Accessories</li>
                                            <li>Cultural Art Pieces</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    break;

                case 'health-beauty':
                case 'home-office':
                case 'fashion':
                case 'computing':
                case 'gaming':
                case 'supermarket':
                case 'baby':
                case 'other':
                    modalTitle.textContent = type.charAt(0).toUpperCase() + type.slice(1).replace('-', ' ');
                    modalBody.innerHTML = `
                        <div style="padding: 20px;">
                            <h3 style="color: var(--primary); margin-bottom: 20px;">${modalTitle.textContent}</h3>
                            <p style="color: #666; margin-bottom: 20px;">
                                Explore our wide range of ${modalTitle.textContent.toLowerCase()} products. We offer competitive prices and fast delivery.
                            </p>
                            <div style="text-align: center; margin: 40px 0;">
                                <div style="font-size: 4rem; color: var(--primary); margin-bottom: 20px;">
                                    🛍️
                                </div>
                                <p style="color: #666;">
                                    This category is coming soon! We're working hard to bring you the best ${modalTitle.textContent.toLowerCase()} products.
                                </p>
                            </div>
                        </div>
                    `;
                    break;
                    
                default:
                    modalTitle.textContent = type.charAt(0).toUpperCase() + type.slice(1).replace('-', ' ');
                    modalBody.innerHTML = `
                        <div style="padding: 20px;">
                            <h3>${modalTitle.textContent}</h3>
                            <p>This section is under development. Content for ${type} will be displayed here.</p>
                        </div>
                    `;
            }
            
            modalOverlay.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            closeProfilePopup();
        }

        function closeModal() {
            const modalOverlay = document.getElementById('modalOverlay');
            modalOverlay.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function toggleSideNav() {
            const sideNav = document.getElementById('sideNav');
            sideNav.classList.toggle('active');
            closeProfilePopup();
        }

        function toggleProfilePopup() {
            const profilePopup = document.getElementById('profilePopup');
            profilePopup.classList.toggle('active');
        }

        function closeProfilePopup() {
            const profilePopup = document.getElementById('profilePopup');
            profilePopup.classList.remove('active');
        }

        function openWhatsApp() {
            const phoneNumber = '254792470595';
            const message = 'Hello! I need assistance with Leshan OMS.';
            const whatsappUrl = `https://wa.me/${phoneNumber}?text=${encodeURIComponent(message)}`;
            window.open(whatsappUrl, '_blank');
        }

        function openProductModal(productId) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            form.style.display = 'none';
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'view_product';
            input.value = productId;
            
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        }

        function getProgressWidth(status) {
            switch(status) {
                case 'pending': return 25;
                case 'processing': return 50;
                case 'shipped': return 75;
                case 'delivered': return 100;
                default: return 0;
            }
        }

        function setTheme(theme) {
            document.body.setAttribute('data-theme', theme);
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const themeInput = document.createElement('input');
            themeInput.type = 'hidden';
            themeInput.name = 'theme';
            themeInput.value = theme;
            
            const submitInput = document.createElement('input');
            submitInput.type = 'hidden';
            submitInput.name = 'set_theme';
            submitInput.value = '1';
            
            form.appendChild(themeInput);
            form.appendChild(submitInput);
            document.body.appendChild(form);
            form.submit();
        }

        function initiateMpesaPayment(productId, amount, description) {
            const modalOverlay = document.getElementById('modalOverlay');
            const modalTitle = document.getElementById('modalTitle');
            const modalBody = document.getElementById('modalBody');
            
            modalTitle.textContent = 'M-Pesa Payment';
            modalBody.innerHTML = `
                <div style="padding: 20px;">
                    <div class="mpesa-header">
                        <div class="mpesa-logo">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="mpesa-title">M-Pesa Payment</div>
                    </div>
                    
                    <div style="text-align: center; margin: 20px 0;">
                        <p>You are about to pay for:</p>
                        <h3 style="color: var(--primary);">${description}</h3>
                        <p style="font-size: 1.5rem; font-weight: bold; margin: 10px 0;">KSh ${amount.toLocaleString()}</p>
                    </div>
                    
                    <?php if (isset($mpesa_success)): ?>
                        <div class="message success"><?php echo $mpesa_success; ?></div>
                    <?php endif; ?>
                    <?php if (isset($mpesa_error)): ?>
                        <div class="message error"><?php echo $mpesa_error; ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" class="mpesa-pin-form">
                        <input type="hidden" name="product_id" value="${productId}">
                        <input type="hidden" name="amount" value="${amount}">
                        
                        <div class="form-group">
                            <label class="form-label">Enter M-Pesa PIN</label>
                            <input type="password" class="pin-input" name="mpesa_pin" placeholder="Enter your M-Pesa PIN" maxlength="4" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Amount to Pay (KSh)</label>
                            <input type="number" class="amount-input" name="payment_amount" value="${amount}" min="1" max="${amount}" required>
                        </div>
                        
                        <button type="submit" name="process_mpesa_payment" class="btn btn-success" style="width: 100%; padding: 12px;">
                            <i class="fas fa-paper-plane"></i> Send Payment
                        </button>
                    </form>
                    
                    <div style="margin-top: 20px; text-align: center; color: #666; font-size: 0.9rem;">
                        <p><i class="fas fa-shield-alt"></i> Your PIN is secure and encrypted</p>
                    </div>
                </div>
            `;
            
            modalOverlay.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        // Close modal when clicking outside
        document.getElementById('modalOverlay').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Close profile popup when clicking outside
        document.addEventListener('click', function(e) {
            const profilePopup = document.getElementById('profilePopup');
            const accountIcon = document.querySelector('.nav-icon[onclick="toggleProfilePopup()"]');
            
            if (profilePopup && accountIcon && !profilePopup.contains(e.target) && !accountIcon.contains(e.target)) {
                closeProfilePopup();
            }
        });

        // Close side nav when clicking on a link
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', () => {
                const sideNav = document.getElementById('sideNav');
                if (sideNav) {
                    sideNav.classList.remove('active');
                }
            });
        });

        // Auto-hide messages after 5 seconds
        setTimeout(() => {
            const messages = document.querySelectorAll('.message');
            messages.forEach(message => {
                message.style.display = 'none';
            });
        }, 5000);

        // Add enter key functionality to search
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('searchForm').submit();
            }
        });
    </script>

    <?php
    // Handle product view with ratings
    if (isset($_POST['view_product'])) {
        $product_id = $_POST['view_product'];
        $product_stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
        $product_stmt->bind_param("i", $product_id);
        $product_stmt->execute();
        $product_result = $product_stmt->get_result();
        $product = $product_result->fetch_assoc();
        
        if ($product) {
            // Get ratings for this product
            $ratings_stmt = $conn->prepare("SELECT pr.*, c.name as customer_name FROM product_ratings pr JOIN customers c ON pr.customer_id = c.id WHERE pr.product_id = ? ORDER BY pr.created_at DESC");
            $ratings_stmt->bind_param("i", $product_id);
            $ratings_stmt->execute();
            $ratings_result = $ratings_stmt->get_result();
            
            // Get average rating
            $avg_rating_stmt = $conn->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as rating_count FROM product_ratings WHERE product_id = ?");
            $avg_rating_stmt->bind_param("i", $product_id);
            $avg_rating_stmt->execute();
            $avg_rating_result = $avg_rating_stmt->get_result();
            $avg_rating_data = $avg_rating_result->fetch_assoc();
            $avg_rating = $avg_rating_data['avg_rating'] ? round($avg_rating_data['avg_rating'], 1) : 0;
            $rating_count = $avg_rating_data['rating_count'];
            
            echo "
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const modalOverlay = document.getElementById('modalOverlay');
                const modalTitle = document.getElementById('modalTitle');
                const modalBody = document.getElementById('modalBody');
                
                modalTitle.textContent = '" . htmlspecialchars($product['product_name']) . "';
                modalBody.innerHTML = `
                    <div style='padding: 20px;'>
                        <div style='display: grid; grid-template-columns: 1fr 1fr; gap: 30px;'>
                            <div>
                                <div style='height: 300px; background: #f5f5f5; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 4rem; margin-bottom: 20px;'>
                                    " . (!empty($product['image_path']) ? "<img src='" . $product['image_path'] . "' alt='" . htmlspecialchars($product['product_name']) . "' style='width: 100%; height: 100%; object-fit: cover; border-radius: 8px;'>" : "📦") . "
                                </div>
                            </div>
                            <div>
                                <h2 style='margin-bottom: 10px;'>" . htmlspecialchars($product['product_name']) . "</h2>
                                <p style='color: #666; margin-bottom: 15px;'>" . htmlspecialchars($product['category']) . "</p>
                                <div style='font-size: 2rem; font-weight: bold; color: var(--primary); margin-bottom: 20px;'>
                                    KSh " . number_format($product['price'], 2) . "
                                </div>
                                <p style='color: #666; margin-bottom: 20px;'>" . ($product['description'] ? htmlspecialchars($product['description']) : 'No description available.') . "</p>
                                <div style='margin-bottom: 20px;'>
                                    <strong>In Stock:</strong> " . $product['quantity'] . " units
                                </div>
                                <form method='POST' style='display: flex; gap: 10px;'>
                                    <input type='hidden' name='product_id' value='" . $product['id'] . "'>
                                    <input type='number' name='quantity' value='1' min='1' max='" . $product['quantity'] . "' style='padding: 10px; border: 1px solid #ddd; border-radius: 4px; width: 80px;'>
                                    <button type='submit' name='add_to_cart' class='btn btn-primary' style='flex: 1;'>
                                        <i class='fas fa-cart-plus'></i> Add to Cart
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Ratings Section -->
                        <div style='margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px;'>
                            <h3 style='margin-bottom: 15px;'>Product Ratings & Reviews</h3>
                            
                            " . (isset($rating_message) ? "<div class='message success'>" . $rating_message . "</div>" : "") . "
                            " . (isset($rating_error) ? "<div class='message error'>" . $rating_error . "</div>" : "") . "
                            
                            <div style='display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;'>
                                <div>
                                    <h4>Customer Reviews</h4>
                                    " . ($avg_rating > 0 ? "
                                    <div style='display: flex; align-items: center; gap: 10px; margin-top: 5px;'>
                                        <div style='color: #ffc107; font-size: 1.5rem;'>
                                            " . str_repeat('★', floor($avg_rating)) . ($avg_rating - floor($avg_rating) >= 0.5 ? '★' : '') . str_repeat('☆', 5 - ceil($avg_rating)) . "
                                        </div>
                                        <span style='font-size: 1.2rem; font-weight: bold;'>" . $avg_rating . "</span>
                                        <span style='color: #666;'>based on " . $rating_count . " review(s)</span>
                                    </div>
                                    " : "<p>No reviews yet. Be the first to review this product!</p>") . "
                                </div>
                            </div>
                            
                            <!-- Rating Form -->
                            <div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>
                                <h4 style='margin-bottom: 10px;'>Rate this Product</h4>
                                <form method='POST'>
                                    <input type='hidden' name='product_id' value='" . $product['id'] . "'>
                                    <div style='margin-bottom: 10px;'>
                                        <label style='display: block; margin-bottom: 5px;'>Your Rating</label>
                                        <div class='rating-stars' id='ratingStars'>
                                            <span class='rating-star' data-rating='1'>★</span>
                                            <span class='rating-star' data-rating='2'>★</span>
                                            <span class='rating-star' data-rating='3'>★</span>
                                            <span class='rating-star' data-rating='4'>★</span>
                                            <span class='rating-star' data-rating='5'>★</span>
                                        </div>
                                        <input type='hidden' name='rating' id='selectedRating' value='0' required>
                                    </div>
                                    <div style='margin-bottom: 10px;'>
                                        <label style='display: block; margin-bottom: 5px;'>Your Comment</label>
                                        <textarea name='comment' style='width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; min-height: 80px;' placeholder='Share your experience with this product...'></textarea>
                                    </div>
                                    <button type='submit' name='submit_rating' class='btn btn-primary'>
                                        Submit Review
                                    </button>
                                </form>
                            </div>
                            
                            <!-- Existing Ratings -->
                            <div>
            ";
            
            if ($ratings_result->num_rows > 0) {
                while($rating = $ratings_result->fetch_assoc()) {
                    echo "
                    <div class='rating-item'>
                        <div class='rating-header'>
                            <div class='rating-customer'>" . htmlspecialchars($rating['customer_name']) . "</div>
                            <div class='rating-stars'>
                                " . str_repeat('★', $rating['rating']) . str_repeat('☆', 5 - $rating['rating']) . "
                            </div>
                        </div>
                        <div class='rating-comment'>
                            " . htmlspecialchars($rating['comment']) . "
                        </div>
                        <div style='color: #666; font-size: 0.8rem; margin-top: 10px;'>
                            " . date('M j, Y', strtotime($rating['created_at'])) . "
                        </div>
                    </div>
                    ";
                }
            } else {
                echo "
                <p style='color: #666; text-align: center; padding: 20px;'>
                    No reviews yet. Be the first to review this product!
                </p>
                ";
            }
            
            echo "
                            </div>
                        </div>
                    </div>
                `;
                
                // Add event listeners for rating stars
                const stars = modalBody.querySelectorAll('.rating-star');
                stars.forEach(star => {
                    star.addEventListener('click', function() {
                        const rating = parseInt(this.getAttribute('data-rating'));
                        document.getElementById('selectedRating').value = rating;
                        
                        // Update star display
                        stars.forEach(s => {
                            if (parseInt(s.getAttribute('data-rating')) <= rating) {
                                s.classList.add('active');
                            } else {
                                s.classList.remove('active');
                            }
                        });
                    });
                });
                
                modalOverlay.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            });
            </script>
            ";
        }
    }

    // Close database connections
    $products_stmt->close();
    $conn->close();
    ?>
</body>
<footer style="background:black;color: white; text-align:center; padding:12px 16px; font-size:14px;">
  © 2025 Leshan Oms — All rights reserved. | Terms | Privacy
</footer>
</html>