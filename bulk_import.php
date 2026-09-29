<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
if ($_SESSION['role'] != 'admin') { header("Location: dashboard.php"); exit; }
include 'db.php';

$success = "";
$error = "";
$report = [];

function generateStudentID($conn) {
    $result = $conn->query("SELECT student_id FROM students ORDER BY id DESC LIMIT 1");
    if ($result->num_rows == 0) return "STU001";
    $last = $result->fetch_assoc()['student_id'];
    $num = intval(preg_replace('/[^0-9]/', '', $last));
    $num++;
    return "STU" . str_pad($num, 3, "0", STR_PAD_LEFT);
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];

    if (!file_exists($file)) {
        $error = "Please select a file to upload.";
    } else {
        $handle = fopen($file, "r");
        if ($handle === false) {
            $error = "Cannot open the file.";
        } else {
            $imported = 0;
            $skipped = 0;
            $row_num = 0;
            fgetcsv($handle);

            while (($row = fgetcsv($handle)) !== false) {
                $row_num++;
                if (count($row) < 1 || empty(trim($row[0]))) {
                    $skipped++;
                    $report[] = "Row $row_num: skipped (empty name)";
                    continue;
                }

                $full_name = trim($row[0]);
                $department = isset($row[1]) ? trim($row[1]) : "";
                $phone = isset($row[2]) ? trim($row[2]) : "";

                $plate1 = isset($row[3]) ? strtoupper(trim($row[3])) : "";
                $model1 = isset($row[4]) ? trim($row[4]) : "";
                $color1 = isset($row[5]) ? trim($row[5]) : "";

                $plate2 = isset($row[6]) ? strtoupper(trim($row[6])) : "";
                $model2 = isset($row[7]) ? trim($row[7]) : "";
                $color2 = isset($row[8]) ? trim($row[8]) : "";

                $chk = $conn->prepare("SELECT id FROM students WHERE full_name = ? AND phone = ?");
                $chk->bind_param("ss", $full_name, $phone);
                $chk->execute();
                if ($chk->get_result()->num_rows > 0) {
                    $skipped++;
                    $report[] = "Row $row_num: skipped (duplicate: $full_name)";
                    continue;
                }

                $plate_error = false;
                foreach ([$plate1, $plate2] as $p) {
                    if (empty($p)) continue;
                    $chk2 = $conn->prepare("SELECT id FROM cars WHERE plate_number = ?");
                    $chk2->bind_param("s", $p);
                    $chk2->execute();
                    if ($chk2->get_result()->num_rows > 0) {
                        $skipped++;
                        $report[] = "Row $row_num: skipped (duplicate plate: $p)";
                        $plate_error = true;
                        break;
                    }
                }
                if ($plate_error) continue;

                $student_id = generateStudentID($conn);

                $stmt = $conn->prepare("INSERT INTO students (student_id, full_name, department, phone) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $student_id, $full_name, $department, $phone);

                if ($stmt->execute()) {
                    $new_id = $conn->insert_id;
                    $cars_added = 0;

                    if (!empty($plate1)) {
                        $stmt2 = $conn->prepare("INSERT INTO cars (student_id, plate_number, car_model, color) VALUES (?, ?, ?, ?)");
                        $stmt2->bind_param("isss", $new_id, $plate1, $model1, $color1);
                        $stmt2->execute();
                        $cars_added++;
                    }

                    if (!empty($plate2)) {
                        $stmt2 = $conn->prepare("INSERT INTO cars (student_id, plate_number, car_model, color) VALUES (?, ?, ?, ?)");
                        $stmt2->bind_param("isss", $new_id, $plate2, $model2, $color2);
                        $stmt2->execute();
                        $cars_added++;
                    }

                    $imported++;
                    $report[] = "Row $row_num: OK - $full_name ($student_id) with $cars_added car(s)";
                } else {
                    $skipped++;
                    $report[] = "Row $row_num: error inserting $full_name";
                }
            }
            fclose($handle);

            if ($imported > 0) {
                $success = "Import complete: $imported students added, $skipped skipped.";
            } else {
                $error = "No students imported. $skipped rows skipped.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Bulk Import - Sardam Institute</title>
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
        .card { background: #fff; padding: 30px; border-radius: 12px; border: 1px solid #e2e8f0; max-width: 900px; margin-bottom: 20px; }
        .card h3 { font-size: 15px; font-weight: 600; margin-bottom: 14px; }
        .instructions { background: #f1f5f9; padding: 16px 20px; border-radius: 8px; font-size: 13px; color: #475569; line-height: 1.7; margin-bottom: 20px; }
        .instructions code { background: #fff; padding: 6px 8px; border-radius: 4px; font-family: monospace; font-size: 12px; color: #dc2626; display: block; margin: 8px 0; overflow-x: auto; }
        .form-group { margin-bottom: 18px; }
        input[type=file] { width: 100%; padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit; background: #fff; }
        .btn { background: #0f172a; color: #fff; padding: 14px 28px; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
        .btn:hover { background: #1e293b; }
        .alert { padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .report { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; max-width: 900px; margin-top: 20px; }
        .report h3 { font-size: 14px; margin-bottom: 12px; }
        .report ul { list-style: none; padding: 0; }
        .report li { font-size: 13px; padding: 6px 0; border-bottom: 1px solid #f1f5f9; font-family: monospace; }
        .btn-download { display: inline-block; background: #2563eb; color: #fff; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: 500; margin-top: 10px; cursor: pointer; }
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
            <a href="bulk_import.php" class="active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg> Bulk Import</a>
            <a href="view_students.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/></svg> All Students</a>
            <a href="bulk_delete.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg> Bulk Delete</a>
            <div class="nav-label">Operations</div>
            <a href="search_plate.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg> Search</a>
            <a href="blacklist.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M4.93 4.93l14.14 14.14"/></svg> Blacklist</a>
        </nav>
    </aside>
    <main class="main">
        <h1 class="page-title">Bulk Import Students</h1>
        <p class="page-sub">Upload a CSV file to add multiple students at once</p>

        <?php if ($success) echo "<div class='alert alert-success'>$success</div>"; ?>
        <?php if ($error) echo "<div class='alert alert-error'>$error</div>"; ?>

        <div class="card">
            <h3>CSV File Format</h3>
            <div class="instructions">
                CSV columns in this exact order:<br>
                <code>full_name,department,phone,plate1,model1,color1,plate2,model2,color2</code>
                The last 3 columns (plate2, model2, color2) are OPTIONAL for a second car.
                <br>
                <strong>Example (student with 1 car):</strong>
                <code>Ahmed Ali,Computer Science,07701234567,12345 ABC,Toyota Corolla,White,,,</code>
                <strong>Example (student with 2 cars):</strong>
                <code>Sara Ahmed,IT,07701111111,55555 XYZ,Honda Civic,Red,77777 DEF,Nissan Patrol,Black</code>
                <br>
                - First row = header (skipped)<br>
                - Student IDs are auto-generated<br>
                - Duplicates will be skipped
                <br><br>
                <a class="btn-download" onclick="downloadTemplate()">Download Template CSV</a>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Select CSV File</label>
                    <input type="file" name="csv_file" accept=".csv" required>
                </div>
                <button type="submit" class="btn">Upload & Import</button>
            </form>
        </div>

        <?php if (!empty($report)): ?>
            <div class="report">
                <h3>Import Details</h3>
                <ul>
                    <?php foreach ($report as $line): ?>
                        <li><?php echo htmlspecialchars($line); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </main>

    <script>
    function downloadTemplate() {
        const csv = "full_name,department,phone,plate1,model1,color1,plate2,model2,color2\nAhmed Ali,Computer Science,07701234567,12345 ABC,Toyota Corolla,White,,,\nSara Ahmed,IT,07701111111,55555 XYZ,Honda Civic,Red,77777 DEF,Nissan Patrol,Black\n";
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'student_template.csv';
        a.click();
    }
    </script>
</body>
</html>