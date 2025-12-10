<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

include 'api.php';

$search = $_GET['search'] ?? '';

// Get products with average ratings
$query = "
    SELECT p.*, 
           COALESCE(AVG(pr.rating), 0) as avg_rating,
           COUNT(pr.id) as rating_count
    FROM products p 
    LEFT JOIN product_ratings pr ON p.id = pr.product_id 
    WHERE p.quantity > 0 
";

if (!empty($search)) {
    $query .= " AND (p.product_name LIKE ? OR p.category LIKE ? OR p.description LIKE ?)";
}

$query .= " GROUP BY p.id ORDER BY p.id DESC LIMIT 12";

$stmt = $conn->prepare($query);

if (!empty($search)) {
    $searchTerm = "%$search%";
    $stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
}

$stmt->execute();
$products = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leshan OMS - Online Book Store</title>
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
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo i {
            font-size: 28px;
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
            position: relative;
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
            position: relative;
            overflow: hidden;
        }

        .hero-banner::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none"><path d="M0,0 L100,0 L100,100 Z" fill="rgba(255,255,255,0.1)"/></svg>');
            background-size: cover;
        }

        .hero-banner h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            position: relative;
        }

        .hero-banner p {
            font-size: 1.2rem;
            opacity: 0.9;
            position: relative;
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
            display: flex;
            align-items: center;
            gap: 10px;
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
            display: flex;
            flex-direction: column;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
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
            flex: 1;
        }

        .product-author {
            font-size: 0.9rem;
            color: var(--text-light);
            margin-bottom: 8px;
            font-style: italic;
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
            margin-top: auto;
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

        .btn-primary:hover {
            background: #e68900;
        }

        .btn-secondary {
            background: var(--secondary);
            color: white;
            flex: 1;
        }

        .btn-secondary:hover {
            background: #1a2530;
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
            box-shadow: 0 5px 25px rgba(0,0,0,0.3);
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
            padding-bottom: 5px;
            border-bottom: 1px solid #ddd;
        }

        .help-section ul {
            padding-left: 20px;
        }

        .help-section li {
            margin-bottom: 8px;
            line-height: 1.5;
        }

        .help-step {
            display: flex;
            align-items: flex-start;
            margin-bottom: 15px;
            padding: 15px;
            background: rgba(255, 153, 0, 0.05);
            border-radius: 8px;
        }

        .step-number {
            background: var(--primary);
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
            flex-shrink: 0;
        }

        .step-content {
            flex: 1;
        }

        .step-content h5 {
            margin-bottom: 5px;
            color: var(--secondary);
        }

        /* Login Prompt */
        .login-prompt {
            text-align: center;
            padding: 40px 20px;
        }

        .login-prompt i {
            font-size: 4rem;
            color: var(--primary);
            margin-bottom: 20px;
        }

        .login-prompt h3 {
            margin-bottom: 15px;
            color: var(--secondary);
        }

        .login-prompt p {
            color: var(--text-light);
            margin-bottom: 25px;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.6;
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
<body data-theme="light">
    <!-- Top Navigation -->
    <div class="top-nav">
        <div class="nav-container">
            <button class="nav-toggle" onclick="toggleSideNav()">
                <i class="fas fa-bars"></i>
            </button>
           
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
                                G
                            </div>
                            <div class="profile-name">Guest User</div>
                            <div class="profile-email">guest@bookhaven.com</div>
                        </div>
                        <div class="profile-links">
                            <a href="#" class="profile-link" onclick="showLoginPrompt('Edit Profile')">
                                <i class="fas fa-user-edit"></i> Edit Profile
                            </a>
                            <a href="#" class="profile-link" onclick="showLoginPrompt('My Orders')">
                                <i class="fas fa-clipboard-list"></i> My Orders
                            </a>
                            <a href="#" class="profile-link" onclick="showLoginPrompt('Payment History')">
                                <i class="fas fa-receipt"></i> Payment History
                            </a>
                            <a href="#" class="profile-link" onclick="showLoginPrompt('Settings')">
                                <i class="fas fa-cog"></i> Settings
                            </a>
                            <a href="customer_login.php" class="profile-link">
                                <i class="fas fa-sign-in-alt"></i> Login
                            </a>
                        </div>
                    </div>
                </div>
                <a href="#" class="nav-icon" onclick="openModal('help')">
                    <i class="fas fa-question-circle"></i>
                    <span>Help</span>
                </a>
                <a href="#" class="nav-icon" onclick="showLoginPrompt('Shopping Cart')" style="position: relative;">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Cart</span>
                    <span class="cart-badge">0</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Categories Navigation -->
    <div class="categories-nav">
        <div class="categories-container">
            <a href="#" class="category-link" onclick="showLoginPrompt('Official Stores')">Official Stores</a>
            <a href="#" class="category-link" onclick="openWhatsApp()">WhatsApp</a>
            <a href="#" class="category-link" onclick="showLoginPrompt('Chat To Order')">Chat To Order</a>
            <a href="#" class="category-link" onclick="showLoginPrompt('Nairobi Town')">NAIROBI TOWN</a>
            <a href="#" class="category-link" onclick="showLoginPrompt('Now on Jumia')">NOW ON JUMIA</a>
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
            <a href="#" class="nav-item" onclick="showLoginPrompt('Dashboard')">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="#" class="nav-item" onclick="showLoginPrompt('All Products')">
                <i class="fas fa-box"></i> All Products
            </a>
            <a href="#" class="nav-item" onclick="showLoginPrompt('Shopping Cart')">
                <i class="fas fa-shopping-cart"></i> Shopping Cart
            </a>
            <a href="#" class="nav-item" onclick="showLoginPrompt('My Orders')">
                <i class="fas fa-clipboard-list"></i> My Orders
            </a>
            <a href="#" class="nav-item" onclick="showLoginPrompt('Payment History')">
                <i class="fas fa-receipt"></i> Payment History
            </a>
            <a href="#" class="nav-item" onclick="showLoginPrompt('Profile')">
                <i class="fas fa-user"></i> Profile
            </a>
            <a href="#" class="nav-item" onclick="showLoginPrompt('Settings')">
                <i class="fas fa-cog"></i> Settings
            </a>
            <a href="customer_login.php" class="nav-item">
                <i class="fas fa-sign-in-alt"></i> Login
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-container">
        <!-- Hero Banner -->
        <div class="hero-banner">
            <h1>Welcome to Leshan OMS</h1>
            <p>Your one-stop online market store</p>
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
                    <a href="#" class="view-all" onclick="showLoginPrompt('All Products')">View All</a>
                <?php else: ?>
                    <a href="?" class="view-all">Clear Search</a>
                <?php endif; ?>
            </div>
            <div class="products-grid" id="productsGrid">
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
                                <i class="fas fa-box"></i>
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
                            <button class="btn btn-secondary" onclick="showLoginPrompt('Add to Cart')">
                                <i class="fas fa-cart-plus"></i>
                            </button>
                            <button class="btn btn-primary" onclick="showLoginPrompt('Product Details')">
                                View Details
                            </button>
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
                <a href="#" class="category-link" style="color: var(--secondary);" onclick="showLoginPrompt('Health & Beauty')">Health & Beauty</a>
                <a href="#" class="category-link" style="color: var(--secondary);" onclick="showLoginPrompt('Home & Office')">Home & Office</a>
                <a href="#" class="category-link" style="color: var(--secondary);" onclick="showLoginPrompt('Fashion')">Fashion</a>
                <a href="#" class="category-link" style="color: var(--secondary);" onclick="showLoginPrompt('Computing')">Computing</a>
                <a href="#" class="category-link" style="color: var(--secondary);" onclick="showLoginPrompt('Gaming')">Gaming</a>
                <a href="#" class="category-link" style="color: var(--secondary);" onclick="showLoginPrompt('Supermarket')">Supermarket</a>
                <a href="#" class="category-link" style="color: var(--secondary);" onclick="showLoginPrompt('Baby Products')">Baby Products</a>
                <a href="#" class="category-link" style="color: var(--secondary);" onclick="showLoginPrompt('Other')">Other categories</a>
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
            
            if (type === 'help') {
                modalTitle.textContent = 'Leshan OMS Help Guide';
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
                    </div>
                `;
            }
            
            modalOverlay.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            closeProfilePopup();
        }

        function showLoginPrompt(feature) {
            const modalOverlay = document.getElementById('modalOverlay');
            const modalTitle = document.getElementById('modalTitle');
            const modalBody = document.getElementById('modalBody');
            
            modalTitle.textContent = feature;
            modalBody.innerHTML = `
                <div class="login-prompt">
                    <i class="fas fa-lock"></i>
                    <h3>Login Required</h3>
                    <p>To access ${feature}, you need to be logged in. Please sign in to your account to continue shopping and enjoy all our features.</p>
                    <a href="customer_login.php" class="btn btn-primary" style="padding: 12px 30px; font-size: 16px; text-decoration:none; font-weight:bold;">
                        Login Now
                    </a>
                </div>
            `;
            
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

        // Add enter key functionality to search
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('searchForm').submit();
            }
        });

        <?php
        // Close database connections
        $stmt->close();
        $conn->close();
        ?>
    </script>
</body>
</html>