<?php
// ============================================
// DATABASE CONNECTION
// Auto-detects missing database and redirects to setup
// ============================================

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "campus_security";

mysqli_report(MYSQLI_REPORT_OFF);

$conn = @new mysqli($host, $user, $pass);

if ($conn->connect_error) {
    die("MySQL is not running. Please start XAMPP (Apache + MySQL) and refresh this page.");
}

$dbExists = $conn->query("SHOW DATABASES LIKE '$dbname'");
if (!$dbExists || $dbExists->num_rows == 0) {
    $currentFile = basename($_SERVER['PHP_SELF']);
    if ($currentFile != 'setup.php') {
        header("Location: setup.php");
        exit;
    }
}

if (!$conn->select_db($dbname)) {
    $currentFile = basename($_SERVER['PHP_SELF']);
    if ($currentFile != 'setup.php') {
        header("Location: setup.php");
        exit;
    }
}

$requiredTables = ['users', 'students', 'cars', 'blacklist'];
$missingTables = [];
foreach ($requiredTables as $table) {
    $check = $conn->query("SHOW TABLES LIKE '$table'");
    if (!$check || $check->num_rows == 0) {
        $missingTables[] = $table;
    }
}

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

if (!empty($missingTables) || !empty($missingColumns) || !empty($missingBlacklistCols)) {
    $currentFile = basename($_SERVER['PHP_SELF']);
    if ($currentFile != 'setup.php') {
        header("Location: setup.php");
        exit;
    }
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
?>