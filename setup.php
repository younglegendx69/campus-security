<?php
// ============================================
// AUTO-SETUP SCRIPT
// Run this once after downloading the project
// Visit: http://localhost/campus-security/setup.php
// ============================================

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "campus_security";

// Connect to MySQL (without database)
$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) {
    die("MySQL connection failed: " . $conn->connect_error);
}

$results = [];

// 1. Create database if not exists
$conn->query("CREATE DATABASE IF NOT EXISTS campus_security");
$results[] = "✅ Database 'campus_security' ready";
$conn->select_db($dbname);

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

// ========== FIX MISSING COLUMNS ON EXISTING TABLES ==========

// Check if 'stage' column exists in students
$check = $conn->query("SHOW COLUMNS FROM students LIKE 'stage'");
if ($check->num_rows == 0) {
    $conn->query("ALTER TABLE students ADD COLUMN stage INT NULL AFTER full_name");
    $results[] = "✅ Added column 'students.stage'";
} else {
    $results[] = "ℹ️ Column 'students.stage' already exists";
}

// Check 'class'
$check = $conn->query("SHOW COLUMNS FROM students LIKE 'class'");
if ($check->num_rows == 0) {
    $conn->query("ALTER TABLE students ADD COLUMN class CHAR(1) NULL AFTER stage");
    $results[] = "✅ Added column 'students.class'";
} else {
    $results[] = "ℹ️ Column 'students.class' already exists";
}

// Check 'track'
$check = $conn->query("SHOW COLUMNS FROM students LIKE 'track'");
if ($check->num_rows == 0) {
    $conn->query("ALTER TABLE students ADD COLUMN track VARCHAR(30) NULL AFTER class");
    $results[] = "✅ Added column 'students.track'";
} else {
    $results[] = "ℹ️ Column 'students.track' already exists";
}

// Check 'expires_at' in blacklist
$check = $conn->query("SHOW COLUMNS FROM blacklist LIKE 'expires_at'");
if ($check->num_rows == 0) {
    $conn->query("ALTER TABLE blacklist ADD COLUMN expires_at DATETIME NULL DEFAULT NULL");
    $results[] = "✅ Added column 'blacklist.expires_at'";
} else {
    $results[] = "ℹ️ Column 'blacklist.expires_at' already exists";
}

// Check 'added_by' in blacklist
$check = $conn->query("SHOW COLUMNS FROM blacklist LIKE 'added_by'");
if ($check->num_rows == 0) {
    $conn->query("ALTER TABLE blacklist ADD COLUMN added_by INT NULL DEFAULT NULL");
    $results[] = "✅ Added column 'blacklist.added_by'";
} else {
    $results[] = "ℹ️ Column 'blacklist.added_by' already exists";
}

// Check 'duration_label' in blacklist
$check = $conn->query("SHOW COLUMNS FROM blacklist LIKE 'duration_label'");
if ($check->num_rows == 0) {
    $conn->query("ALTER TABLE blacklist ADD COLUMN duration_label VARCHAR(50) NULL DEFAULT NULL");
    $results[] = "✅ Added column 'blacklist.duration_label'";
} else {
    $results[] = "ℹ️ Column 'blacklist.duration_label' already exists";
}

// ========== FILL DEFAULTS FOR EXISTING DATA ==========

// Fix students with NULL stage
$conn->query("UPDATE students SET stage = 1 WHERE stage IS NULL");
$conn->query("UPDATE students SET class = 'A' WHERE class IS NULL");
$results[] = "✅ Default stage/class filled for old students";

// Clear track from stages 1-3 (shouldn't have one)
$conn->query("UPDATE students SET track = NULL WHERE stage IN (1, 2, 3)");
$results[] = "✅ Cleared track from stages 1-3";

// Fix "Networking" → "Network"
$conn->query("UPDATE students SET track = 'Network' WHERE track = 'Networking'");
$results[] = "✅ Normalized 'Networking' to 'Network'";

// ========== CREATE DEFAULT ADMIN IF MISSING ==========

$check = $conn->query("SELECT id FROM users WHERE username = 'admin'");
if ($check->num_rows == 0) {
    $conn->query("INSERT INTO users (username, password, role) VALUES ('admin', 'admin123', 'admin')");
    $results[] = "✅ Default admin created (admin / admin123)";
} else {
    $results[] = "ℹ️ Admin user already exists";
}

$check = $conn->query("SELECT id FROM users WHERE username = 'guard1'");
if ($check->num_rows == 0) {
    $conn->query("INSERT INTO users (username, password, role) VALUES ('guard1', 'guard123', 'guard')");
    $results[] = "✅ Default guard created (guard1 / guard123)";
} else {
    $results[] = "ℹ️ Guard user already exists";
}

// ========== CREATE UPLOADS FOLDER ==========
$uploadsDir = __DIR__ . '/uploads';
if (!is_dir($uploadsDir)) {
    @mkdir($uploadsDir, 0755, true);
    $results[] = "✅ uploads folder created";
} else {
    $results[] = "ℹ️ uploads folder already exists";
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
        ul { list-style: none; padding: 0; margin: 20px 0; }
        ul li { padding: 10px 14px; background: #f8fafc; border-left: 3px solid #3b82f6; border-radius: 6px; font-size: 13.5px; margin-bottom: 6px; font-family: monospace; }
        .success-box { background: #dcfce7; color: #166534; padding: 16px 20px; border-radius: 10px; font-size: 14px; margin: 20px 0; border: 1px solid #bbf7d0; line-height: 1.6; }
        .warning-box { background: #fef3c7; color: #92400e; padding: 16px 20px; border-radius: 10px; font-size: 13.5px; margin: 20px 0; border: 1px solid #fde68a; }
        .btn { display: inline-block; background: #0f172a; color: #fff; padding: 14px 28px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 600; margin-right: 10px; }
        .btn:hover { background: #1e293b; }
        .btn-danger { background: #dc2626; }
        .btn-danger:hover { background: #b91c1c; }
    </style>
</head>
<body>
    <div class="box">
        <h1>🛠️ Setup Complete</h1>
        <p class="sub">Your database has been set up successfully</p>

        <ul>
            <?php foreach ($results as $line): ?>
                <li><?php echo htmlspecialchars($line); ?></li>
            <?php endforeach; ?>
        </ul>

        <div class="success-box">
            ✅ <strong>Everything is ready!</strong>
            <br><br>
            <strong>Login:</strong><br>
            Admin: <code>admin</code> / <code>admin123</code><br>
            Guard: <code>guard1</code> / <code>guard123</code>
        </div>

        <div class="warning-box">
            ⚠️ <strong>Important:</strong> Delete the file <code>setup.php</code> from your server after running this. Otherwise anyone could reset your database by visiting this URL.
        </div>

        <a href="login.php" class="btn">Go to Login →</a>
    </div>
</body>
</html>