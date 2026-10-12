<?php
// ============================================
// AUTO-SETUP — Runs once, then never needed again
// ============================================

mysqli_report(MYSQLI_REPORT_OFF);

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "campus_security";

// Connect to MySQL (no database yet)
$conn = @new mysqli($host, $user, $pass);
if ($conn->connect_error) {
    die("❌ MySQL is not running. Start XAMPP first (Apache + MySQL green).");
}

$results = [];

// 1. Create database
$conn->query("CREATE DATABASE IF NOT EXISTS $dbname");
$conn->select_db($dbname);
$results[] = "✅ Database 'campus_security' ready";

// 2. Create users table
$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','guard') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
$results[] = "✅ Table 'users' ready";

// 3. Create students table
$conn->query("CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(20) NOT NULL UNIQUE,
    full_name VARCHAR(100) NOT NULL,
    stage INT NULL,
    class CHAR(1) NULL,
    track VARCHAR(30) NULL,
    phone VARCHAR(20),
    photo VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
$results[] = "✅ Table 'students' ready";

// 4. Create cars table
$conn->query("CREATE TABLE IF NOT EXISTS cars (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    plate_number VARCHAR(20) NOT NULL UNIQUE,
    car_model VARCHAR(50),
    color VARCHAR(30),
    car_photo VARCHAR(255),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
)");
$results[] = "✅ Table 'cars' ready";

// 5. Create blacklist table
$conn->query("CREATE TABLE IF NOT EXISTS blacklist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plate_number VARCHAR(20) NOT NULL,
    reason TEXT,
    date_added TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NULL DEFAULT NULL,
    added_by INT NULL DEFAULT NULL,
    duration_label VARCHAR(50) NULL DEFAULT NULL
)");
$results[] = "✅ Table 'blacklist' ready";

// 6. Create logs table
$conn->query("CREATE TABLE IF NOT EXISTS logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    car_id INT NOT NULL,
    guard_id INT NOT NULL,
    entry_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    exit_time DATETIME,
    status ENUM('in','out') DEFAULT 'in',
    FOREIGN KEY (car_id) REFERENCES cars(id) ON DELETE CASCADE,
    FOREIGN KEY (guard_id) REFERENCES users(id)
)");
$results[] = "✅ Table 'logs' ready";

// 7. Add missing columns to students
$cols = ['stage' => "INT NULL AFTER full_name", 'class' => "CHAR(1) NULL AFTER stage", 'track' => "VARCHAR(30) NULL AFTER class"];
foreach ($cols as $name => $def) {
    $chk = $conn->query("SHOW COLUMNS FROM students LIKE '$name'");
    if ($chk && $chk->num_rows == 0) {
        $conn->query("ALTER TABLE students ADD COLUMN $name $def");
        $results[] = "✅ Added 'students.$name'";
    }
}

// 8. Add missing columns to blacklist
$bcols = ['expires_at' => "DATETIME NULL", 'added_by' => "INT NULL", 'duration_label' => "VARCHAR(50) NULL"];
foreach ($bcols as $name => $def) {
    $chk = $conn->query("SHOW COLUMNS FROM blacklist LIKE '$name'");
    if ($chk && $chk->num_rows == 0) {
        $conn->query("ALTER TABLE blacklist ADD COLUMN $name $def");
        $results[] = "✅ Added 'blacklist.$name'";
    }
}

// 9. Fix old data
$conn->query("UPDATE students SET stage = 1 WHERE stage IS NULL");
$conn->query("UPDATE students SET class = 'A' WHERE class IS NULL");
$conn->query("UPDATE students SET track = NULL WHERE stage IN (1, 2, 3)");
$conn->query("UPDATE students SET track = 'Network' WHERE track = 'Networking'");
$results[] = "✅ Cleaned up old data";

// 10. Create admin user if missing
$chk = $conn->query("SELECT id FROM users WHERE username = 'admin'");
if ($chk->num_rows == 0) {
    $conn->query("INSERT INTO users (username, password, role) VALUES ('admin', 'admin123', 'admin')");
    $results[] = "✅ Admin created: admin / admin123";
} else {
    $results[] = "ℹ️ Admin already exists";
}

// 11. Create guard user if missing
$chk = $conn->query("SELECT id FROM users WHERE username = 'guard1'");
if ($chk->num_rows == 0) {
    $conn->query("INSERT INTO users (username, password, role) VALUES ('guard1', 'guard123', 'guard')");
    $results[] = "✅ Guard created: guard1 / guard123";
} else {
    $results[] = "ℹ️ Guard already exists";
}

// 12. Create uploads folder
if (!is_dir(__DIR__ . '/uploads')) {
    @mkdir(__DIR__ . '/uploads', 0755, true);
    $results[] = "✅ uploads folder created";
} else {
    $results[] = "ℹ️ uploads folder exists";
}

$conn->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Setup - Sardam Institute</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .box { background: #fff; padding: 40px; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.2); width: 100%; max-width: 560px; }
        h1 { font-size: 22px; color: #0f172a; margin-bottom: 8px; }
        .sub { color: #64748b; font-size: 14px; margin-bottom: 24px; }
        ul { list-style: none; padding: 0; margin: 20px 0; max-height: 300px; overflow-y: auto; }
        ul li { padding: 10px 14px; background: #f8fafc; border-left: 3px solid #3b82f6; border-radius: 6px; font-size: 13.5px; margin-bottom: 6px; font-family: monospace; }
        .success-box { background: #dcfce7; color: #166534; padding: 16px 20px; border-radius: 10px; font-size: 14px; margin: 20px 0; border: 1px solid #bbf7d0; line-height: 1.6; }
        .warning-box { background: #fef3c7; color: #92400e; padding: 16px 20px; border-radius: 10px; font-size: 13.5px; margin: 20px 0; border: 1px solid #fde68a; }
        .btn { display: inline-block; background: #0f172a; color: #fff; padding: 14px 28px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 600; }
        .btn:hover { background: #1e293b; }
        code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-family: monospace; color: #dc2626; }
    </style>
</head>
<body>
    <div class="box">
        <h1>🛠️ Setup Complete</h1>
        <p class="sub">Your database is fully configured</p>

        <ul>
            <?php foreach ($results as $line): ?>
                <li><?php echo htmlspecialchars($line); ?></li>
            <?php endforeach; ?>
        </ul>

        <div class="success-box">
            ✅ <strong>Ready to use!</strong>
            <br><br>
            <strong>Login:</strong><br>
            Admin: <code>admin</code> / <code>admin123</code><br>
            Guard: <code>guard1</code> / <code>guard123</code>
        </div>

        <div class="warning-box">
            ⚠️ <strong>Optional:</strong> Delete this file (<code>setup.php</code>) after running it once for security.
        </div>

        <a href="login.php" class="btn">Go to Login →</a>
    </div>
</body>
</html>