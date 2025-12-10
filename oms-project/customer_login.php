<?php
session_start();
include 'api.php';

$message = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM customers WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['customer_email'] = $user['email'];
        $_SESSION['customer_name'] = $user['name'];
        header("Location: customer_dashboard.php");
        exit();
    } else {
        $message = "❌ Invalid email or password!";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Customer Login - Leshan OMS</title>
    <style>
        body {font-family: 'Segoe UI'; background: #f4f7fb; display: flex; justify-content: center; align-items: center; height: 100vh;}
        form {background: white; padding: 30px; border-radius: 10px; width: 350px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);}
        input {width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ccc; border-radius: 5px;}
        button {width: 100%; background: #3498db; color: white; border: none; padding: 12px; border-radius: 5px; cursor: pointer;}
        .msg {text-align: center; color: red;}
        a {display: block; text-align: center; margin-top: 10px; color: #3498db;}
    </style>
</head>
<body>
<form method="post">
    <h2>Customer Login</h2>
    <input type="email" name="email" placeholder="Email Address" required />
    <input type="password" name="password" placeholder="Password" required />
    <button type="submit">Login</button>
    <p class="msg"><?= $message ?></p>
    <a href="customer_register.php">Create a new account</a>
</form>
</body>
</html>
