<?php
include 'api.php';
$message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $check = $conn->prepare("SELECT * FROM customers WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $res = $check->get_result();

    if ($res->num_rows > 0) {
        $message = "⚠️ Email already registered!";
    } else {
        $stmt = $conn->prepare("INSERT INTO customers (name,email,phone,created_at,password) VALUES (?,?,?,NOW(),?)");
        $stmt->bind_param("ssss", $name, $email, $phone, $password);
        $stmt->execute();
        $message = "✅ Registration successful! You can now log in.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Customer Registration - Leshan OMS</title>
    <style>
        body {font-family: 'Segoe UI'; background: #f4f7fb; display: flex; justify-content: center; align-items: center; height: 100vh;}
        form {background: white; padding: 30px; border-radius: 10px; width: 350px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);}
        input {width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ccc; border-radius: 5px;}
        button {width: 100%; background: #3498db; color: white; border: none; padding: 12px; border-radius: 5px; cursor: pointer;}
        .msg {text-align: center; color: #2c3e50;}
        a {display: block; text-align: center; margin-top: 10px; color: #3498db;}
    </style>
</head>
<body>
<form method="post">
    <h2>Customer Registration</h2>
    <input type="text" name="name" placeholder="Full Name" required />
    <input type="email" name="email" placeholder="Email Address" required />
    <input type="text" name="phone" placeholder="Phone Number" required />
    <input type="password" name="password" placeholder="Create Password" required />
    <button type="submit">Register</button>
    <p class="msg"><?= $message ?></p>
    <a href="customer_login.php">Already registered? Login here</a>
</form>
</body>
</html>
