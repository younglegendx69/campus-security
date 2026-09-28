<?php
session_start();
include 'db.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE username = '$username' AND password = '$password'";
    $result = $conn->query($sql);

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        if ($user['role'] == 'guard') {
            header("Location: guard_dashboard.php");
        } else {
            header("Location: dashboard.php");
        }
        exit;
    } else {
        $error = "Wrong username or password";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login - Sardam Institute</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); display: flex; justify-content: center; align-items: center; height: 100vh; }
        .box { background: white; padding: 40px; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.2); width: 360px; }
        .logo { width: 100px; height: 100px; display: block; margin: 0 auto 20px; border-radius: 50%; }
        h2 { text-align: center; color: #0f172a; font-size: 20px; margin-bottom: 6px; }
        .subtitle { text-align: center; color: #64748b; font-size: 12px; margin-bottom: 30px; }
        label { display: block; font-size: 13px; font-weight: 500; color: #334155; margin-bottom: 6px; }
        input { width: 100%; padding: 12px 14px; margin-bottom: 18px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 14px; font-family: inherit; }
        input:focus { outline: none; border-color: #0f172a; }
        button { width: 100%; padding: 14px; background: #0f172a; color: white; border: none; border-radius: 10px; cursor: pointer; font-size: 15px; font-weight: 600; }
        button:hover { background: #1e293b; }
        .error { color: #dc2626; text-align: center; margin-bottom: 15px; font-size: 13px; padding: 10px; background: #fee2e2; border-radius: 8px; }
    </style>
</head>
<body>
    <div class="box">
        <img src="logo.png" alt="Logo" class="logo">
        <h2>Sardam Institute</h2>
        <p class="subtitle">Non-Governmental Institute for Computer Sciences</p>
        <?php if ($error) echo "<p class='error'>$error</p>"; ?>
        <form method="POST">
            <label>Username</label>
            <input type="text" name="username" placeholder="Enter username" required>
            <label>Password</label>
            <input type="password" name="password" placeholder="Enter password" required>
            <button type="submit">Sign In</button>
        </form>
    </div>
</body>
</html>