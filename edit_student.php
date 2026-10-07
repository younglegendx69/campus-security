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
    $stage = intval($_POST['stage'] ?? 0);
    $class = strtoupper(trim($_POST['class'] ?? ''));
    $track = trim($_POST['track'] ?? '');
    $phone = trim($_POST['phone']);

    if (empty($full_name)) {
        $error = "Full Name is required.";
    } elseif ($stage < 1 || $stage > 5) {
        $error = "Please select a valid stage (1-5).";
    } elseif (!in_array($class, ['A', 'B', 'C', 'D'])) {
        $error = "Please select a class (A, B, C, or D).";
    } elseif (($stage == 4 || $stage == 5) && empty($track)) {
        $error = "Please select a field (Programming or Network) for Stage $stage.";
    } else {
        $photo_sql = "";
        if (!empty($_FILES['photo']['name'])) {
            if (!is_dir("uploads")) mkdir("uploads");
            $photo = "uploads/" . time() . "_" . basename($_FILES['photo']['name']);
            move_uploaded_file($_FILES['photo']['tmp_name'], $photo);
            $photo_sql = ", photo = '$photo'";
        }

        // If stage 1-3, clear the field
        if ($stage != 4 && $stage != 5) {
            $track = '';
        }

        $stmt = $conn->prepare("UPDATE students SET full_name = ?, stage = ?, class = ?, track = ?, phone = ? $photo_sql WHERE id = ?");
        $stmt->bind_param("sisssi", $full_name, $stage, $class, $track, $phone, $student_id);
        $stmt->execute();

        $existing_ids = $_POST['existing_car_id'] ?? [];
        $existing_plates = $_POST['existing_plate'] ?? [];
        $existing_models = $_POST['existing_model'] ?? [];
        $existing_colors = $_POST['existing_color'] ?? [];

        $plate_errors = [];
        $all_plates_to_check = [];

        foreach ($existing_plates as $i => $plate) {
            $p = strtoupper(trim($plate));
            if (empty($p)) continue;
            $all_plates_to_check[] = ['plate' => $p, 'car_id' => intval($existing_ids[$i])];
        }

        if (!empty($_POST['new_plate'])) {
            foreach ($_POST['new_plate'] as $plate) {
                $p = strtoupper(trim($plate));
                if (empty($p)) continue;
                $all_plates_to_check[] = ['plate' => $p, 'car_id' => null];
            }
        }

        foreach ($all_plates_to_check as $entry) {
            $p = $entry['plate'];
            $exclude_id = $entry['car_id'];
            if ($exclude_id) {
                $chk = $conn->prepare("SELECT id FROM cars WHERE plate_number = ? AND id != ?");
                $chk->bind_param("si", $p, $exclude_id);
            } else {
                $chk = $conn->prepare("SELECT id FROM cars WHERE plate_number = ?");
                $chk->bind_param("s", $p);
            }
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $plate_errors[] = "Plate $p is already registered.";
            }
        }

        if (!empty($plate_errors)) {
            $error = implode(" ", array_unique($plate_errors));
        } else {
            foreach ($existing_ids as $i => $cid) {
                $cid = intval($cid);
                $p = strtoupper(trim($existing_plates[$i] ?? ''));
                $m = trim($existing_models[$i] ?? '');
                $c = trim($existing_colors[$i] ?? '');

                if (empty($p)) {
                    $del = $conn->prepare("DELETE FROM cars WHERE id = ?");
                    $del->bind_param("i", $cid);
                    $del->execute();
                } else {
                    $u = $conn->prepare("UPDATE cars SET plate_number = ?, car_model = ?, color = ? WHERE id = ?");
                    $u->bind_param("sssi", $p, $m, $c, $cid);
                    $u->execute();
                }
            }

            if (!empty($_POST['new_plate'])) {
                foreach ($_POST['new_plate'] as $i => $plate) {
                    $p = strtoupper(trim($plate));
                    if (empty($p)) continue;
                    $m = trim($_POST['new_model'][$i] ?? '');
                    $c = trim($_POST['new_color'][$i] ?? '');
                    $ins = $conn->prepare("INSERT INTO cars (student_id, plate_number, car_model, color) VALUES (?, ?, ?, ?)");
                    $ins->bind_param("isss", $student_id, $p, $m, $c);
                    $ins->execute();
                }
            }

            $success = "Student updated successfully!";
        }
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

$stmt2 = $conn->prepare("SELECT * FROM cars WHERE student_id = ? ORDER BY id ASC");
$stmt2->bind_param("i", $student_id);
$stmt2->execute();
$cars_res = $stmt2->get_result();
$cars = [];
while ($c = $cars_res->fetch_assoc()) $cars[] = $c;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Student - Sardam Institute</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="theme.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8fafc; color: #0f172a; }
        .sidebar { position: fixed; left: 0; top: 0; width: 250px; height: 100vh; background: #fff; border-right: 1px solid #e2e8f0; display: flex; flex-direction: column; }
        .sidebar-header { padding: 20px 18px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; gap: 12px; }
        .sidebar-header img { width: 40px; height: 40px; border-radius: 50%; }
        .sidebar-header .name { font-size: 13px; font-weight: 700; line-height: 1.3; }
        .sidebar-header .name span { display: block; font-weight: 400; color: #64748b; font-size: 10.5px; }
        .sidebar nav { flex: 1; padding: 16px 12px; overflow-y: auto; }
        .nav-label { font-size: 11px; font-weight: 600; color: #94a3b8; text-transform: uppercase; padding: 12px 12px 8px; }
        .sidebar nav a { display: flex; align-items: center; gap: 10px; padding: 10px 12px; color: #475569; text-decoration: none; font-size: 13.5px; font-weight: 500; border-radius: 8px; margin-bottom: 2px; }
        .sidebar nav a:hover { background: #f1f5f9; color: #0f172a; }
        .sidebar nav a.active { background: #0f172a; color: #fff; }
        .sidebar nav a svg { width: 18px; height: 18px; }
        .main { margin-left: 250px; padding: 32px; }
        .page-title { font-size: 22px; font-weight: 700; margin-bottom: 24px; }
        .card { background: #fff; padding: 30px; border-radius: 12px; border: 1px solid #e2e8f0; max-width: 900px; }
        .section-head { font-size: 14px; font-weight: 700; margin-bottom: 14px; padding-bottom: 10px; border-bottom: 1px solid #f1f5f9; }
        .form-group { margin-bottom: 18px; }
        label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 6px; color: #334155; }
        input[type=text], input[type=file], select { width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; font-family: inherit; background: #fff; }
        input:focus, select:focus { outline: none; border-color: #0f172a; }
        input[readonly] { background: #f1f5f9; color: #64748b; }
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .row3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }
        .btn { background: #0f172a; color: #fff; padding: 14px 28px; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
        .btn:hover { background: #1e293b; }
        .btn-danger { background: #dc2626; color: #fff; padding: 14px 28px; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-back { background: #f1f5f9; color: #475569; text-decoration: none; display: inline-block; padding: 14px 28px; border-radius: 8px; font-size: 14px; font-weight: 500; }
        .btn-add-car { background: #2563eb; color: #fff; padding: 10px 18px; border: none; border-radius: 8px; font-size: 13px; font-weight: 500; cursor: pointer; }
        .alert { padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .action-row { margin-top: 24px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
        .car-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; margin-bottom: 12px; position: relative; }
        .car-box-header { font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px; }
        .car-box-remove { position: absolute; top: 10px; right: 12px; background: #fee2e2; color: #dc2626; border: none; padding: 4px 10px; border-radius: 6px; font-size: 12px; cursor: pointer; font-weight: 500; }
        .empty-cars-msg { text-align: center; padding: 20px; color: #94a3b8; font-size: 13.5px; }
    </style>
    <script>
    (function() {
        try {
            var saved = localStorage.getItem('theme') || 'light';
            if (saved === 'dark') document.documentElement.classList.add('dark-mode');
        } catch(e) {}
    })();
    </script>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="logo.png" alt="Logo">
            <div class="name">Sardam Institute<span>Computer Sciences</span></div>
        </div>
        <div style="padding: 12px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
            <span style="font-size: 11px; font-weight: 600; color: #94a3b8; text-transform: uppercase;">Theme</span>
            <button onclick="toggleTheme()" style="background: #f1f5f9; border: 1px solid #e2e8f0; width: 34px; height: 34px; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            </button>
        </div>
        <nav>
            <div class="nav-label">Main</div>
            <a href="dashboard.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12l9-9 9 9M5 10v10h14V10"/></svg> Dashboard</a>
            <div class="nav-label">Management</div>
            <a href="add_student.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg> Add Student</a>
            <a href="bulk_import.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg> Bulk Import</a>
            <a href="view_students.php" class="active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/></svg> All Students</a>
            <a href="bulk_delete.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg> Bulk Delete</a>
<a href="promote.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19V5M5 12l7-7 7 7"/></svg> Promote Students</a>
            <div class="nav-label">Operations</div>
            <a href="search_plate.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg> Search</a>
            <a href="blacklist.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M4.93 4.93l14.14 14.14"/></svg> Blacklist</a>
            <div class="nav-label">Account</div>
            <a href="manage_users.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg> Manage Users</a>
            <a href="change_password.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg> Change Password</a>
            <a href="logout.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg> Sign Out</a>
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
                        <label>Stage *</label>
                        <select name="stage" id="stageSelect" required onchange="toggleTrack()">
                            <option value="">-- Select Stage --</option>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php if ($student['stage'] == $i) echo 'selected'; ?>>Stage <?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Class *</label>
                        <select name="class" required>
                            <option value="">-- Select Class --</option>
                            <?php foreach (['A', 'B', 'C', 'D'] as $cl): ?>
                                <option value="<?php echo $cl; ?>" <?php if ($student['class'] == $cl) echo 'selected'; ?>>Class <?php echo $cl; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="form-group" id="trackGroup" style="<?php echo ($student['stage'] == 4 || $student['stage'] == 5) ? '' : 'display:none;'; ?>">
                        <label>Field *</label>
                        <select name="track" id="trackSelect">
                            <option value="">-- Select Field --</option>
                            <option value="Programming" <?php if ($student['track'] == 'Programming') echo 'selected'; ?>>Programming</option>
                            <option value="Network" <?php if ($student['track'] == 'Network') echo 'selected'; ?>>Network</option>
                        </select>
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

                <div class="section-head" style="margin-top: 30px;">Existing Cars (<?php echo count($cars); ?>)</div>
                <?php if (count($cars) === 0): ?>
                    <div class="empty-cars-msg">No cars. Click "Add a New Car" below to add one.</div>
                <?php else: ?>
                    <?php foreach ($cars as $i => $car): ?>
                        <div class="car-box">
                            <div class="car-box-header">Car <?php echo $i + 1; ?></div>
                            <button type="button" class="car-box-remove" onclick="removeExisting(this)">Remove</button>
                            <input type="hidden" name="existing_car_id[]" value="<?php echo $car['id']; ?>">
                            <div class="row3">
                                <div class="form-group">
                                    <label>Plate Number</label>
                                    <input type="text" name="existing_plate[]" value="<?php echo htmlspecialchars($car['plate_number']); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Car Model</label>
                                    <input type="text" name="existing_model[]" value="<?php echo htmlspecialchars($car['car_model']); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Color</label>
                                    <input type="text" name="existing_color[]" value="<?php echo htmlspecialchars($car['color']); ?>">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <div class="section-head" style="margin-top: 30px;">Add a New Car <span style="font-size: 11px; color: #94a3b8; font-weight: 400;">(optional)</span></div>
                <div id="new-cars-container"></div>
                <button type="button" class="btn-add-car" onclick="addNewCar()">+ Add a New Car</button>

                <div class="action-row">
                    <button type="submit" class="btn">Save Changes</button>
                    <a href="view_students.php" class="btn-back">Cancel</a>
                    <a href="delete_student.php?id=<?php echo $student['id']; ?>" class="btn-danger" onclick="return confirm('Delete this student?')">Delete Student</a>
                </div>
            </form>
        </div>
    </main>
    <script>
    let newCarCount = 0;
    function removeExisting(btn) {
        const box = btn.closest('.car-box');
        const plateInput = box.querySelector('input[name="existing_plate[]"]');
        if (plateInput) plateInput.value = '';
        box.style.display = 'none';
    }
    function addNewCar() {
        newCarCount++;
        const container = document.getElementById('new-cars-container');
        const div = document.createElement('div');
        div.className = 'car-box';
        div.innerHTML = `
            <div class="car-box-header">New Car ${newCarCount}</div>
            <button type="button" class="car-box-remove" onclick="this.parentElement.remove()">Remove</button>
            <div class="row3">
                <div class="form-group">
                    <label>Plate Number</label>
                    <input type="text" name="new_plate[]" placeholder="e.g. 22 C 79770">
                </div>
                <div class="form-group">
                    <label>Car Model</label>
                    <input type="text" name="new_model[]" placeholder="e.g. Toyota Corolla">
                </div>
                <div class="form-group">
                    <label>Color</label>
                    <input type="text" name="new_color[]" placeholder="e.g. White">
                </div>
            </div>
        `;
        container.appendChild(div);
    }
    function toggleTrack() {
        const stage = document.getElementById('stageSelect').value;
        const trackGroup = document.getElementById('trackGroup');
        const trackSelect = document.getElementById('trackSelect');

        if (stage === '4' || stage === '5') {
            trackGroup.style.display = 'block';
            trackSelect.required = true;
        } else {
            trackGroup.style.display = 'none';
            trackSelect.required = false;
            trackSelect.value = '';
        }
    }
    function toggleTheme() {
        var isDark = document.documentElement.classList.toggle('dark-mode');
        try { localStorage.setItem('theme', isDark ? 'dark' : 'light'); } catch(e) {}
    }
    </script>
</body>
</html>