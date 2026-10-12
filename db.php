<?php
// ============================================
// DATABASE CONNECTION
// Auto-detects missing database and redirects to setup
// ============================================

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "campus_security";

// Suppress warnings — we'll handle them ourselves
mysqli_report(MYSQLI_REPORT_OFF);

// Connect to MySQL
$conn = @new mysqli($host, $user, $pass);

// If connection fails completely
if ($conn->connect_error) {
    die("MySQL is not running. Please start XAMPP (Apache + MySQL) and refresh this page.");
}

// Check if database exists
$dbExists = $conn->query("SHOW DATABASES LIKE '$dbname'");
if (!$dbExists || $dbExists->num_rows == 0) {
    // Database doesn't exist → redirect to setup
    $currentFile = basename($_SERVER['PHP_SELF']);
    if ($currentFile != 'setup.php') {
        header("Location: setup.php");
        exit;
    }
}

// Select the database
if (!$conn->select_db($dbname)) {
    $currentFile = basename($_SERVER['PHP_SELF']);
    if ($currentFile != 'setup.php') {
        header("Location: setup.php");
        exit;
    }
}

// Check if required tables exist
$requiredTables = ['users', 'students', 'cars', 'blacklist'];
$missingTables = [];
foreach ($requiredTables as $table) {
    $check = $conn->query("SHOW TABLES LIKE '$table'");
    if (!$check || $check->num_rows == 0) {
        $missingTables[] = $table;
    }
}

// Check if students table has required columns
$missingColumns = [];
if (!in_array('students', $missingTables)) {
    $requiredCols = ['stage', 'class', 'track'];
    foreach ($requiredCols as $col) {
        $check = $conn->query("SHOW COLUMNS FROM students LIKE '$col'");
        if (!$check || $check->num_rows == 0) {
            $missingColumns[] = $col;
        }
    }
}

// Check blacklist columns
$blacklistCols = ['expires_at', 'added_by', 'duration_label'];
$missingBlacklistCols = [];
if (!in_array('blacklist', $missingTables)) {
    foreach ($blacklistCols as $col) {
        $check = $conn->query("SHOW COLUMNS FROM blacklist LIKE '$col'");
        if (!$check || $check->num_rows == 0) {
            $missingBlacklistCols[] = $col;
        }
    }
}

// If anything is missing → redirect to setup
if (!empty($missingTables) || !empty($missingColumns) || !empty($missingBlacklistCols)) {
    $currentFile = basename($_SERVER['PHP_SELF']);
    if ($currentFile != 'setup.php') {
        header("Location: setup.php");
        exit;
    }
}

// Enable error reporting (after all checks pass)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
?>