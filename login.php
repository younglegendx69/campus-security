<?php
session_start();
include 'db.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // 1. Get the user by username only (Secure from SQL Injection)
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        $password_matches = false;
        
        // 2. Check the password (works for hashes AND plain text)
        if (password_verify($password, $user['password'])) {
            $password_matches = true; // It's a hash
        } elseif ($password === $user['password']) {
            $password_matches = true; // It's plain text (like admin)
        }

        // 3. If password is correct, log them in
        if ($password_matches) {
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
    <link rel="stylesheet" href="theme.css?v=99">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); display: flex; justify-content: center; align-items: center; height: 100vh; }
        .box { background: white; padding: 40px; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.2); width: 360px; position: relative; }
        .lang-bar { position: absolute; top: 12px; right: 12px; display: flex; gap: 4px; }
        .lang-bar button { background: #f1f5f9; border: 1px solid #e2e8f0; height: 26px; padding: 0 8px; border-radius: 5px; cursor: pointer; font-weight: 700; font-size: 10.5px; color: #475569; font-family: inherit; }
        .lang-bar button:hover { background: #e2e8f0; }
        .logo { width: 100px; height: 100px; display: block; margin: 0 auto 20px; border-radius: 50%; }
        h2 { text-align: center; color: #0f172a; font-size: 20px; margin-bottom: 6px; }
        .subtitle { text-align: center; color: #64748b; font-size: 12px; margin-bottom: 30px; }
        label { display: block; font-size: 13px; font-weight: 500; color: #334155; margin-bottom: 6px; }
        input { width: 100%; padding: 12px 14px; margin-bottom: 18px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 14px; font-family: inherit; }
        input:focus { outline: none; border-color: #0f172a; }
        button.submit { width: 100%; padding: 14px; background: #0f172a; color: white; border: none; border-radius: 10px; cursor: pointer; font-size: 15px; font-weight: 600; }
        button.submit:hover { background: #1e293b; }
        .error { color: #dc2626; text-align: center; margin-bottom: 15px; font-size: 13px; padding: 10px; background: #fee2e2; border-radius: 8px; border: 1px solid #fecaca; }

        /* Dark Mode fixes for login */
        html.dark-mode body { background: #0f172a !important; }
        html.dark-mode .box { background: #1e293b !important; }
        html.dark-mode h2 { color: #f1f5f9 !important; }
        html.dark-mode label { color: #cbd5e1 !important; }
        html.dark-mode input { background: #0f172a !important; border-color: #475569 !important; color: #f1f5f9 !important; }
        html.dark-mode .lang-bar button { background: #334155 !important; border-color: #475569 !important; color: #e2e8f0 !important; }
        
        /* THIS IS THE FIX FOR THE ERROR MESSAGE IN DARK MODE */
        html.dark-mode .error { 
            background: #7f1d1d !important; 
            color: #fecaca !important; 
            border: 1px solid #991b1b !important;
        }
    </style>
    <script>
    (function() {
        try {
            var saved = localStorage.getItem('theme') || 'light';
            if (saved === 'dark') document.documentElement.classList.add('dark-mode');
            var lang = localStorage.getItem('lang') || 'en';
            document.documentElement.dir = lang === 'ar' ? 'rtl' : 'ltr';
        } catch(e) {}
    })();
    </script>
</head>
<body>
    <div class="box">
        <div class="lang-bar">
            <button onclick="setLanguage('en')">EN</button>
            <button onclick="setLanguage('ku')">کو</button>
            <button onclick="setLanguage('ar')">ع</button>
        </div>
        <img src="logo.png" alt="Logo" class="logo">
        <h2 data-en="Sardam Institute" data-ku="پەیمانگەی سەردەم" data-ar="معهد سردم">Sardam Institute</h2>
        <p class="subtitle" data-en="Computer Sciences" data-ku="زانستەکانی کۆمپیوتەر" data-ar="علوم الحاسوب">Computer Sciences</p>
        
        <?php if ($error) echo "<p class='error'>$error</p>"; ?>
        
        <form method="POST">
            <label data-en="Username" data-ku="ناوی بەکارهێنەر" data-ar="اسم المستخدم">Username</label>
            <input type="text" name="username" required autocomplete="username">
            
            <label data-en="Password" data-ku="وشەی نهێنی" data-ar="كلمة المرور">Password</label>
            <input type="password" name="password" required autocomplete="current-password">
            
            <button type="submit" class="submit" data-en="Sign In" data-ku="بچۆ ژوورەوە" data-ar="دخول">Sign In</button>
        </form>
    </div>
    <script src="language.js?v=99"></script>
</body>
</html>