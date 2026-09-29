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
    $full_name = trim($_POST['full_name']);
    $department = trim($_POST['department']);
    $phone = trim($_POST['phone']);

    if (empty($full_name)) {
        $error = "Full Name is required.";
    } else {
        $chk = $conn->prepare("SELECT id FROM students WHERE full_name = ? AND phone = ?");
        $chk->bind_param("ss", $full_name, $phone);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $error = "A student with the same name and phone already exists.";
        } else {
            $plates = $_POST['plate_number'];
            $models = $_POST['car_model'];
            $colors = $_POST['color'];
            $errors = [];
            $plateOK = true;

            foreach ($plates as $i => $plate) {
                $p = strtoupper(trim($plate));
                if (empty($p)) continue;
                $chk2 = $conn->prepare("SELECT id FROM cars WHERE plate_number = ?");
                $chk2->bind_param("s", $p);
                $chk2->execute();
                if ($chk2->get_result()->num_rows > 0) {
                    $errors[] = "Plate " . htmlspecialchars($p) . " is already registered.";
                    $plateOK = false;
                }
            }

            if (!$plateOK) {
                $error = implode(" ", $errors);
            } else {
                $student_id = $next_id;

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
                    $cars_added = 0;

                    foreach ($plates as $i => $plate) {
                        $p = strtoupper(trim($plate));
                        if (empty($p)) continue;
                        $model = isset($models[$i]) ? trim($models[$i]) : "";
                        $color = isset($colors[$i]) ? trim($colors[$i]) : "";

                        $stmt2 = $conn->prepare("INSERT INTO cars (student_id, plate_number, car_model, color) VALUES (?, ?, ?, ?)");
                        $stmt2->bind_param("isss", $new_student_id, $p, $model, $color);
                        $stmt2->execute();
                        $cars_added++;
                    }

                    $success = "Student " . htmlspecialchars($student_id) . " (" . htmlspecialchars($full_name) . ") saved with $cars_added car(s)!";
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
        .card { background: #fff; padding: 30px; border-radius: 12px; border: 1px solid #e2e8f0; max-width: 850px; }
        .section-head { font-size: 14px; font-weight: 700; color: #0f172a; margin-bottom: 14px; padding-bottom: 10px; border-bottom: 1px solid #f1f5f9; }
        .form-group { margin-bottom: 18px; }
        label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 6px; color: #334155; }
        input[type=text], input[type=file] { width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; font-family: inherit; }
        input:focus { outline: none; border-color: #0f172a; }
        input[readonly] { background: #f1f5f9; color: #64748b; }
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .row3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }
        .btn { background: #0f172a; color: #fff; padding: 14px 28px; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
        .btn:hover { background: #1e293b; }
        .btn-back { background: #f1f5f9; color: #475569; text-decoration: none; display: inline-block; padding: 14px 28px; border-radius: 8px; margin-left: 10px; font-size: 14px; font-weight: 500; }
        .btn-add-car { background: #2563eb; color: #fff; padding: 8px 16px; border: none; border-radius: 8px; font-size: 13px; font-weight: 500; cursor: pointer; margin-top: 10px; }
        .alert { padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .optional { font-size: 11px; color: #94a3b8; font-weight: 400; }
        .auto-badge { display: inline-block; background: #dbeafe; color: #1e40af; font-size: 11px; padding: 2px 8px; border-radius: 4px; margin-left: 6px; font-weight: 600; }
        .car-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; margin-bottom: 12px; position: relative; }
        .car-box-header { font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
        .car-box-remove { position: absolute; top: 10px; right: 12px; background: #fee2e2; color: #dc2626; border: none; padding: 4px 10px; border-radius: 6px; font-size: 12px; cursor: pointer; font-weight: 500; }
        .car-box-remove:hover { background: #fecaca; }
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
            <a href="bulk_import.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg> Bulk Import</a>
            <a href="view_students.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/></svg> All Students</a>
            <a href="bulk_delete.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg> Bulk Delete</a>
            <div class="nav-label">Operations</div>
            <a href="search_plate.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg> Search</a>
            <a href="blacklist.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M4.93 4.93l14.14 14.14"/></svg> Blacklist</a>
        </nav>
    </aside>
    <main class="main">
        <h1 class="page-title">Register Student + Cars</h1>
        <p class="page-sub">Add a student and one or more cars — all in one form.</p>

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

                <div class="section-head" style="margin-top: 30px;">Cars <span class="optional">(optional — add one or more)</span></div>
                <div id="cars-container">
                    <div class="car-box">
                        <div class="car-box-header">Car 1</div>
                        <div class="row3">
                            <div class="form-group">
                                <label>Plate Number</label>
                                <input type="text" name="plate_number[]" placeholder="e.g. 12345 ABC">
                            </div>
                            <div class="form-group">
                                <label>Car Model</label>
                                <input type="text" name="car_model[]" placeholder="e.g. Toyota Corolla">
                            </div>
                            <div class="form-group">
                                <label>Color</label>
                                <input type="text" name="color[]" placeholder="e.g. White">
                            </div>
                        </div>
                    </div>
                </div>

                <button type="button" class="btn-add-car" onclick="addCar()">+ Add Another Car</button>

                <div style="margin-top: 30px;">
                    <button type="submit" class="btn">Save Student + Cars</button>
                    <a href="dashboard.php" class="btn-back">Cancel</a>
                </div>
            </form>
        </div>
    </main>

    <script>
    let carCount = 1;
    function addCar() {
        carCount++;
        const container = document.getElementById('cars-container');
        const div = document.createElement('div');
        div.className = 'car-box';
        div.innerHTML = `
            <div class="car-box-header">Car ${carCount}</div>
            <button type="button" class="car-box-remove" onclick="this.parentElement.remove()">Remove</button>
            <div class="row3">
                <div class="form-group">
                    <label>Plate Number</label>
                    <input type="text" name="plate_number[]" placeholder="e.g. 12345 ABC">
                </div>
                <div class="form-group">
                    <label>Car Model</label>
                    <input type="text" name="car_model[]" placeholder="e.g. Toyota Corolla">
                </div>
                <div class="form-group">
                    <label>Color</label>
                    <input type="text" name="color[]" placeholder="e.g. White">
                </div>
            </div>
        `;
        container.appendChild(div);
    }
    </script>
</body>
</html>