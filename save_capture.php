<?php
session_start();
if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['error' => 'Not logged in']); exit; }
include 'db.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['image'])) {
    echo json_encode(['error' => 'No image provided']);
    exit;
}

$plate = $data['plate'] ?? '';
$student_id = intval($data['student_id'] ?? 0);
$image = $data['image'];

// Parse base64 data URL
if (!preg_match('/^data:image\/(\w+);base64,/', $image, $m)) {
    echo json_encode(['error' => 'Invalid image data']);
    exit;
}

$ext = $m[1] === 'jpeg' ? 'jpg' : $m[1];
$image = substr($image, strpos($image, ',') + 1);
$image = base64_decode($image);

if ($image === false) {
    echo json_encode(['error' => 'Base64 decode failed']);
    exit;
}

// Ensure uploads/captures folder
$dir = __DIR__ . '/uploads/captures';
if (!is_dir($dir)) mkdir($dir, 0755, true);

$filename = 'capture_' . date('Ymd_His') . '_' . uniqid() . '.' . $ext;
$path = $dir . '/' . $filename;
$relPath = 'uploads/captures/' . $filename;

if (!file_put_contents($path, $image)) {
    echo json_encode(['error' => 'Failed to write file']);
    exit;
}

// Save to database
$captured_by = $_SESSION['user_id'];
$student_id_val = $student_id > 0 ? $student_id : null;

$stmt = $conn->prepare("INSERT INTO search_logs (plate_number, student_id, captured_by, photo_path) VALUES (?, ?, ?, ?)");
$stmt->bind_param("siis", $plate, $student_id_val, $captured_by, $relPath);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'path' => $relPath]);
} else {
    echo json_encode(['error' => 'DB error: ' . $conn->error]);
}
?>