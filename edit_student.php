<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
if ($_SESSION['role'] != 'admin') { header("Location: guard_dashboard.php"); exit; }
include 'db.php';

$success = "";
$error = "";

if (!isset($_GET['id'])) {
    header("Location: view_students.php");
    exit;
}

$student_id = intval($_GET['id']);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = trim($_POST['full_name']);
    $department = trim($_POST['department']);
    $phone = trim($_POST['phone']);

    if (empty($full_name)) {
        $error = "Full Name is required.";
    } else {
        $photo_sql = "";
        if (!empty($_FILES['photo']['name'])) {
            if (!is_dir("uploads")) mkdir("uploads");
            $photo = "uploads/" . time() . "_" . basename($_FILES['photo']['name']);
            move_uploaded_file($_FILES['photo']['tmp_name'], $photo);
            $photo_sql = ", photo = '$photo'";
        }

        $stmt = $conn->prepare("UPDATE students SET full_name = ?, department = ?, phone = ? $photo_sql WHERE id = ?");
        $stmt->bind_param("sssi", $full_name, $department, $phone, $student_id);
        $stmt->execute();

        $plate = strtoupper(trim($_POST['plate_number']));
        $car_model = trim($_POST['car_model']);
        $color = trim($_POST['color']);

        if (!empty($plate)) {
            $chk = $conn->prepare("SELECT id FROM cars WHERE student_id = ?");
            $chk->bind_param("i", $student_id);
            $chk->execute();
            $has_car = $chk->get_result();

            if ($has_car->num_rows > 0) {
                $car = $has_car->fetch_assoc();
                $u = $conn->prepare("UPDATE cars SET plate_number = ?, car_model = ?, color = ? WHERE id = ?");
                $u->bind_param("sssi", $plate, $car_model, $color, $car['id']);
                $u->execute();
            } else {
                $i = $conn->prepare("INSERT INTO cars (student_id, plate_number, car_model, color) VALUES (?, ?, ?, ?)");
                $i->bind_param("isss", $student_id, $plate, $car_model, $color);
                $i->execute();
            }
        }

        $success = "Student updated successfully!";
    }
}

$stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student) {
    header("Location: view_students.php");
    exit;
}

$stmt2 = $conn->prepare("SELECT * FROM cars WHERE student_id = ?");
$stmt2->bind_param("i", $student_id);
$stmt2->execute();
$car = $stmt2->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Student - Sardam Institute</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8fafc; color: #0f172a; }
        .sidebar { position: fixed; left: 0; top: 0; width: 250px; height: 100vh; background: #fff; border-right: 1px solid #e2e8f0; }
        .sidebar-header { padding: 20px 18px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; gap: 12px; }
        .sidebar-header img { width: 40px; height: 40px; border-radius: 50%; }
        .sidebar-header .name { font-size: 13px; font-weight: 700; line-height: 1.3; }
        .sidebar-header .name span { display: block; font-weight: 400; color: #64748b; font-size: 10.5px; }
        .sidebar nav { padding: 16px 12px; }
        .nav-label { font-size: 11px; font-weight: 600; color: #94a3b8; text-transform: uppercase; padding: 12px 12px 8px; }
        .sidebar nav a { display: flex; align-items: center; gap: 10px; padding: 10px 12px; color: #475569; text-decoration: none; font-size: 13.5px; font-weight: 500; border-radius: 8px; margin-bottom: 2px; }
        .sidebar nav a:hover { background: #f1f5f9; color: #0f172a; }
        .sidebar nav a.active { background: #0f172a; color: #fff; }
        .sidebar nav a svg { width: 18px; height: 18px; }
        .main { margin-left: 250px; padding: 32px; }
        .page-title { font-size: 22px; font-weight: 700; margin-bottom: 24px; }
        .card { background: #fff; padding: 30px; border-radius: 12px; border: 1px solid #e2e8f0; max-width: 800px; }
        .section-head { font-size: 14px; font-weight: 700; margin-bottom: 14px; padding-bottom: 10px; border-bottom: 1px solid #f1f5f9; }
        .form-group { margin-bottom: 18px; }
        label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 6px; color: #334155; }
        input[type=text], input[type=file] { width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; font-family: inherit; }
        input:focus { outline: none; border-color: #0f172a; }
        input[readonly] { background: #f1f5f9; color: #64748b; }
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .btn { background: #0f172a; color: #fff; padding: 14px 28px; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
        .btn:hover { background: #1e293b; }
        .btn-danger { background: #dc2626; color: #fff; padding: 14px 28px; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-danger:hover { background: #b91c1c; }
        .btn-back { background: #f1f5f9; color: #475569; text-decoration: none; display: inline-block; padding: 14px 28px; border-radius: 8px; font-size: 14px; font-weight: 500; }
        .alert { padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .action-row { margin-top: 24px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="logo.png" alt="Logo">
            <div class="name">Sardam Institute<span>Computer Sciences</span></div>
        </div>
        <nav>
            <div class="nav-label">Main</div>
            <a href="dashboard.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12l9-9 9 9M5 10v10h14V10"/></svg> Dashboard</a>
            <div class="nav-label">Management</div>
            <a href="add_student.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg> Add Student</a>
            <a href="view_students.php" class="active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/></svg> All Students</a>
            <div class="nav-label">Operations</div>
            <a href="search_plate.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg> Search Plate</a>
            <a href="view_logs.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/></svg> Entry Logs</a>
            <a href="blacklist.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M4.93 4.93l14.14 14.14"/></svg> Blacklist</a>
        </nav>
    </aside>
    <main class="main">
        <h1 class="page-title">Edit Student</h1>
        <div class="card">
            <?php if ($success) echo "<div class='alert alert-success'>$success</div>"; ?>
            <?php if ($error) echo "<div class='alert alert-error'>$error</div>"; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="section-head">Student Information</div>
                <div class="row">
                    <div class="form-group">
                        <label>Student ID</label>
                        <input type="text" value="<?php echo htmlspecialchars($student['student_id']); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($student['full_name']); ?>" required>
                    </div>
                </div>
                <div class="row">
                    <div class="form-group">
                        <label>Department</label>
                        <input type="text" name="department" value="<?php echo htmlspecialchars($student['department']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone" value="<?php echo htmlspecialchars($student['phone']); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Change Photo (optional)</label>
                    <input type="file" name="photo" accept="image/*">
                </div>

                <div class="section-head" style="margin-top: 30px;">Car Information</div>
                <div class="row">
                    <div class="form-group">
                        <label>Plate Number</label>
                        <input type="text" name="plate_number" value="<?php echo $car ? htmlspecialchars($car['plate_number']) : ''; ?>" placeholder="e.g. 12345 ABC">
                    </div>
                    <div class="form-group">
                        <label>Car Model</label>
                        <input type="text" name="car_model" value="<?php echo $car ? htmlspecialchars($car['car_model']) : ''; ?>" placeholder="e.g. Toyota Corolla">
                    </div>
                </div>
                <div class="form-group">
                    <label>Color</label>
                    <input type="text" name="color" value="<?php echo $car ? htmlspecialchars($car['color']) : ''; ?>" placeholder="e.g. White">
                </div>

                <div class="action-row">
                    <button type="submit" class="btn">Save Changes</button>
                    <a href="view_students.php" class="btn-back">Cancel</a>
                    <a href="delete_student.php?id=<?php echo $student['id']; ?>" class="btn-danger" onclick="return confirm('Delete this student?')">Delete Student</a>
                </div>
            </form>
        </div>
    </main>
</body>
</html>