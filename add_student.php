<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
if ($_SESSION['role'] != 'admin') { header("Location: guard_dashboard.php"); exit; }
include 'db.php';

$success = "";
$error = "";

function generateStudentID($conn) {
    $result = $conn->query("SELECT student_id FROM students ORDER BY id DESC LIMIT 1");
    if ($result->num_rows == 0) return "STU001";
    $last = $result->fetch_assoc()['student_id'];
    $num = intval(preg_replace('/[^0-9]/', '', $last));
    $num++;
    return "STU" . str_pad($num, 3, "0", STR_PAD_LEFT);
}

$next_id = generateStudentID($conn);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $student_id = $next_id;
    $full_name = trim($_POST['full_name']);
    $department = trim($_POST['department']);
    $phone = trim($_POST['phone']);
    $plate_number = strtoupper(trim($_POST['plate_number']));
    $car_model = trim($_POST['car_model']);
    $color = trim($_POST['color']);

    if (empty($full_name)) {
        $error = "Full Name is required.";
    } else {
        $chk = $conn->prepare("SELECT id FROM students WHERE full_name = ? AND phone = ?");
        $chk->bind_param("ss", $full_name, $phone);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $error = "A student with the same name and phone already exists.";
        } else {
            $plateOK = true;
            if (!empty($plate_number)) {
                $chk2 = $conn->prepare("SELECT id FROM cars WHERE plate_number = ?");
                $chk2->bind_param("s", $plate_number);
                $chk2->execute();
                if ($chk2->get_result()->num_rows > 0) {
                    $error = "This plate number is already registered.";
                    $plateOK = false;
                }
            }

            if ($plateOK) {
                $photo = "";
                if (!empty($_FILES['photo']['name'])) {
                    if (!is_dir("uploads")) mkdir("uploads");
                    $photo = "uploads/" . time() . "_" . basename($_FILES['photo']['name']);
                    move_uploaded_file($_FILES['photo']['tmp_name'], $photo);
                }

                $stmt = $conn->prepare("INSERT INTO students (student_id, full_name, department, phone, photo) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $student_id, $full_name, $department, $phone, $photo);

                if ($stmt->execute()) {
                    $new_student_id = $conn->insert_id;

                    if (!empty($plate_number)) {
                        $car_photo = "";
                        if (!empty($_FILES['car_photo']['name'])) {
                            $car_photo = "uploads/" . time() . "_car_" . basename($_FILES['car_photo']['name']);
                            move_uploaded_file($_FILES['car_photo']['tmp_name'], $car_photo);
                        }
                        $stmt2 = $conn->prepare("INSERT INTO cars (student_id, plate_number, car_model, color, car_photo) VALUES (?, ?, ?, ?, ?)");
                        $stmt2->bind_param("issss", $new_student_id, $plate_number, $car_model, $color, $car_photo);
                        $stmt2->execute();
                    }

                    $success = "Student " . htmlspecialchars($student_id) . " (" . htmlspecialchars($full_name) . ") saved" . (!empty($plate_number) ? " with car " . htmlspecialchars($plate_number) : "") . "!";
                    $next_id = generateStudentID($conn);
                } else {
                    $error = "Error: " . $conn->error;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Student - Sardam Institute</title>
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
        .page-title { font-size: 22px; font-weight: 700; margin-bottom: 6px; }
        .page-sub { color: #64748b; font-size: 14px; margin-bottom: 24px; }
        .card { background: #fff; padding: 30px; border-radius: 12px; border: 1px solid #e2e8f0; max-width: 800px; }
        .section-head { font-size: 14px; font-weight: 700; color: #0f172a; margin-bottom: 14px; padding-bottom: 10px; border-bottom: 1px solid #f1f5f9; }
        .form-group { margin-bottom: 18px; }
        label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 6px; color: #334155; }
        input[type=text], input[type=file] { width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; font-family: inherit; }
        input:focus { outline: none; border-color: #0f172a; }
        input[readonly] { background: #f1f5f9; color: #64748b; cursor: not-allowed; }
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .btn { background: #0f172a; color: #fff; padding: 14px 28px; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
        .btn:hover { background: #1e293b; }
        .btn-back { background: #f1f5f9; color: #475569; text-decoration: none; display: inline-block; padding: 14px 28px; border-radius: 8px; margin-left: 10px; font-size: 14px; font-weight: 500; }
        .alert { padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .optional { font-size: 11px; color: #94a3b8; font-weight: 400; }
        .auto-badge { display: inline-block; background: #dbeafe; color: #1e40af; font-size: 11px; padding: 2px 8px; border-radius: 4px; margin-left: 6px; font-weight: 600; }
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
            <a href="add_student.php" class="active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg> Add Student</a>
            <a href="view_students.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/></svg> All Students</a>
            <div class="nav-label">Operations</div>
            <a href="search_plate.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg> Search Plate</a>
            <a href="view_logs.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/></svg> Entry Logs</a>
            <a href="blacklist.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M4.93 4.93l14.14 14.14"/></svg> Blacklist</a>
        </nav>
    </aside>
    <main class="main">
        <h1 class="page-title">Register Student + Car</h1>
        <p class="page-sub">Just fill in the info — Student ID is generated automatically.</p>

        <div class="card">
            <?php if ($success) echo "<div class='alert alert-success'>$success</div>"; ?>
            <?php if ($error) echo "<div class='alert alert-error'>$error</div>"; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="section-head">Student Information</div>
                <div class="row">
                    <div class="form-group">
                        <label>Student ID <span class="auto-badge">AUTO</span></label>
                        <input type="text" value="<?php echo $next_id; ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" name="full_name" placeholder="e.g. Ahmed Ali" required>
                    </div>
                </div>
                <div class="row">
                    <div class="form-group">
                        <label>Department</label>
                        <input type="text" name="department" placeholder="e.g. Computer Science">
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone" placeholder="e.g. 07701234567">
                    </div>
                </div>
                <div class="form-group">
                    <label>Student Photo</label>
                    <input type="file" name="photo" accept="image/*">
                </div>

                <div class="section-head" style="margin-top: 30px;">Car Information <span class="optional">(optional)</span></div>
                <div class="row">
                    <div class="form-group">
                        <label>Plate Number</label>
                        <input type="text" name="plate_number" placeholder="e.g. 12345 ABC">
                    </div>
                    <div class="form-group">
                        <label>Car Model</label>
                        <input type="text" name="car_model" placeholder="e.g. Toyota Corolla">
                    </div>
                </div>
                <div class="row">
                    <div class="form-group">
                        <label>Color</label>
                        <input type="text" name="color" placeholder="e.g. White">
                    </div>
                    <div class="form-group">
                        <label>Car Photo</label>
                        <input type="file" name="car_photo" accept="image/*">
                    </div>
                </div>

                <div style="margin-top: 24px;">
                    <button type="submit" class="btn">Save Student + Car</button>
                    <a href="dashboard.php" class="btn-back">Cancel</a>
                </div>
            </form>
        </div>
    </main>
</body>
</html>