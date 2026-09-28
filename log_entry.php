<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
include 'db.php';

if (!isset($_GET['car_id'])) {
    header("Location: search_plate.php");
    exit;
}

$car_id = intval($_GET['car_id']);
$guard_id = $_SESSION['user_id'];

$check = $conn->prepare("SELECT id FROM logs WHERE car_id = ? AND status = 'in'");
$check->bind_param("i", $car_id);
$check->execute();
$existing = $check->get_result();

if ($existing->num_rows > 0) {
    $log = $existing->fetch_assoc();
    $upd = $conn->prepare("UPDATE logs SET exit_time = NOW(), status = 'out' WHERE id = ?");
    $upd->bind_param("i", $log['id']);
    $upd->execute();
} else {
    $ins = $conn->prepare("INSERT INTO logs (car_id, guard_id, entry_time, status) VALUES (?, ?, NOW(), 'in')");
    $ins->bind_param("ii", $car_id, $guard_id);
    $ins->execute();
}

header("Location: search_plate.php");
exit;
?>