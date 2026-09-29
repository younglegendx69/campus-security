<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
if ($_SESSION['role'] != 'admin') { header("Location: guard_dashboard.php"); exit; }
include 'db.php';

$success = "";
$error = "";
$preview = [];
$csv_processed = false;
$csv_ids = [];

// Manual delete
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ids']) && !isset($_POST['from_csv'])) {
    $ids = $_POST['ids'];
    $deleted = 0;
    foreach ($ids as $id) {
        $id = intval($id);
        $stmt = $conn->prepare("DELETE FROM students WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) $deleted++;
    }
    $success = "$deleted student(s) deleted successfully.";
}

// CSV delete
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['from_csv']) && isset($_POST['csv_ids'])) {
    $ids = $_POST['csv_ids'];
    $deleted = 0;
    foreach ($ids as $id) {
        $id = intval($id);
        $stmt = $conn->prepare("DELETE FROM students WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) $deleted++;
    }
    $success = "$deleted student(s) deleted from CSV import.";
}

// CSV upload preview
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['csv_file']) && $_FILES['csv_file']['size'] > 0) {
    $file = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($file, "r");

    if ($handle === false) {
        $error = "Cannot open the file.";
    } else {
        $csv_processed = true;
        $row_num = 0;
        $header = fgetcsv($handle);

        if ($header && (
            strtolower(trim($header[0])) == 'student_id' || 
            strtolower(trim($header[0])) == 'id' ||
            strtolower(trim($header[0])) == 'full_name' ||
            strtolower(trim($header[0])) == 'name'
        )) {
            $mode = 'id';
            if (strtolower(trim($header[0])) == 'full_name' || strtolower(trim($header[0])) == 'name') {
                $mode = 'name';
            }
        } else {
            rewind($file);
            $handle = fopen($file, "r");
            $mode = 'id';
        }

        while (($row = fgetcsv($handle)) !== false) {
            $row_num++;
            if (empty(trim($row[0]))) continue;
            $value = trim($row[0]);

            if ($mode === 'id') {
                $stmt = $conn->prepare("SELECT id, student_id, full_name, department FROM students WHERE student_id = ?");
            } else {
                $stmt = $conn->prepare("SELECT id, student_id, full_name, department FROM students WHERE full_name = ?");
            }
            $stmt->bind_param("s", $value);
            $stmt->execute();
            $r = $stmt->get_result();

            if ($r->num_rows > 0) {
                while ($s = $r->fetch_assoc()) {
                    $preview[] = $s;
                    $csv_ids[] = $s['id'];
                }
            } else {
                $preview[] = ['not_found' => $value];
            }
        }
        fclose($handle);

        if (empty($preview)) {
            $error = "No valid data in the CSV file.";
            $csv_processed = false;
        }
    }
}

$search = "";
$where = "";
if (!empty($_GET['q'])) {
    $search = trim($_GET['q']);
    $where = "WHERE s.full_name LIKE '%$search%' OR s.student_id LIKE '%$search%'";
}

$students = $conn->query("
    SELECT s.*, 
           (SELECT COUNT(*) FROM cars WHERE student_id = s.id) AS car_count
    FROM students s
    $where
    ORDER BY s.full_name ASC
");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Bulk Delete - Sardam Institute</title>
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
        .page-title { font-size: 22px; font-weight: 700; margin-bottom: 6px; }
        .page-sub { color: #64748b; font-size: 14px; margin-bottom: 24px; }
        .alert { padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .warning-box { background: #fef3c7; color: #92400e; padding: 14px 18px; border-radius: 8px; font-size: 13.5px; margin-bottom: 20px; border: 1px solid #fde68a; }
        .tabs { display: flex; gap: 4px; margin-bottom: 20px; background: #f1f5f9; padding: 4px; border-radius: 10px; width: fit-content; }
        .tab { padding: 10px 22px; border-radius: 8px; font-size: 14px; font-weight: 500; color: #64748b; cursor: pointer; text-decoration: none; }
        .tab.active { background: #fff; color: #0f172a; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .csv-card { background: #fff; padding: 30px; border-radius: 12px; border: 1px solid #e2e8f0; max-width: 800px; margin-bottom: 20px; }
        .csv-card h3 { font-size: 15px; font-weight: 600; margin-bottom: 14px; }
        .instructions { background: #f1f5f9; padding: 16px 20px; border-radius: 8px; font-size: 13px; color: #475569; line-height: 1.7; margin-bottom: 20px; }
        .instructions code { background: #fff; padding: 6px 8px; border-radius: 4px; font-family: monospace; font-size: 12px; color: #dc2626; display: block; margin: 8px 0; overflow-x: auto; }
        .form-group { margin-bottom: 18px; }
        input[type=file] { width: 100%; padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit; background: #fff; }
        .btn { background: #0f172a; color: #fff; padding: 14px 28px; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
        .btn-download { display: inline-block; background: #2563eb; color: #fff; padding: 8px 16px; border-radius: 8px; text-decoration: none; font-size: 12.5px; font-weight: 500; cursor: pointer; margin-top: 8px; }
        .preview-card { background: #fff; border: 2px solid #dc2626; border-radius: 12px; padding: 24px; max-width: 800px; margin-bottom: 20px; }
        .preview-card h3 { font-size: 15px; font-weight: 700; margin-bottom: 14px; color: #dc2626; }
        .preview-list { max-height: 300px; overflow-y: auto; border: 1px solid #f1f5f9; border-radius: 8px; margin-bottom: 16px; }
        .preview-row { padding: 12px 16px; border-bottom: 1px solid #f1f5f9; font-size: 13.5px; display: flex; justify-content: space-between; align-items: center; }
        .preview-row.matched { background: #fef2f2; }
        .preview-row.not-found { background: #f8fafc; color: #94a3b8; }
        .preview-summary { font-size: 14px; margin-bottom: 16px; color: #334155; }
        .preview-summary strong { color: #dc2626; }
        .btn-delete-all { background: #dc2626; color: #fff; padding: 14px 28px; border: none; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; }
        .btn-cancel { background: #f1f5f9; color: #475569; text-decoration: none; display: inline-block; padding: 14px 28px; border-radius: 8px; margin-left: 10px; font-size: 14px; font-weight: 500; }
        .toolbar { display: flex; gap: 12px; margin-bottom: 20px; align-items: center; }
        .toolbar form.search-form { display: flex; flex: 1; gap: 12px; }
        .toolbar input { flex: 1; padding: 12px 16px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 14px; font-family: inherit; }
        .toolbar button { background: #0f172a; color: #fff; padding: 12px 24px; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; }
        .btn-delete-selected { background: #dc2626; color: #fff; padding: 12px 24px; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; white-space: nowrap; }
        .btn-delete-selected:disabled { background: #94a3b8; cursor: not-allowed; }
        .table-card { background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8fafc; padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #e2e8f0; }
        td { padding: 16px 20px; font-size: 14px; border-bottom: 1px solid #f1f5f9; }
        tr:hover td { background: #fef2f2; }
        .avatar-sm { width: 36px; height: 36px; border-radius: 50%; background: #0f172a; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-weight: 600; font-size: 13px; vertical-align: middle; margin-right: 10px; }
        .student-id { font-family: monospace; background: #f1f5f9; padding: 3px 8px; border-radius: 4px; font-size: 12px; }
        .car-badge { display: inline-block; background: #dbeafe; color: #1e40af; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; }
        .checkbox-cell { width: 50px; text-align: center; }
        input[type="checkbox"] { width: 20px; height: 20px; cursor: pointer; accent-color: #dc2626; }
        .empty { text-align: center; padding: 60px; color: #64748b; }
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
            <a href="view_students.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/></svg> All Students</a>
            <a href="bulk_delete.php" class="active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg> Bulk Delete</a>
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
        <h1 class="page-title">Bulk Delete Students</h1>
        <p class="page-sub">Delete students via CSV upload or manual selection</p>

        <?php if ($success) echo "<div class='alert alert-success'>$success</div>"; ?>
        <?php if ($error) echo "<div class='alert alert-error'>$error</div>"; ?>

        <div class="warning-box">
            <strong>Warning:</strong> Deleting a student also deletes their cars. This cannot be undone.
        </div>

        <div class="tabs">
            <a href="?tab=csv" class="tab <?php echo (!isset($_GET['tab']) || $_GET['tab'] == 'csv') ? 'active' : ''; ?>">CSV Upload</a>
            <a href="?tab=manual" class="tab <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'manual') ? 'active' : ''; ?>">Manual Selection</a>
        </div>

        <?php if (!isset($_GET['tab']) || $_GET['tab'] == 'csv'): ?>

            <?php if ($csv_processed && !empty($preview)): ?>
                <div class="preview-card">
                    <h3>Confirm Deletion</h3>
                    <div class="preview-summary">
                        Found <strong><?php echo count($csv_ids); ?></strong> matching student(s).
                        Click <strong>Delete All</strong> to proceed.
                    </div>
                    <div class="preview-list">
                        <?php foreach ($preview as $p): ?>
                            <?php if (isset($p['not_found'])): ?>
                                <div class="preview-row not-found">
                                    <span><?php echo htmlspecialchars($p['not_found']); ?></span>
                                    <span>Not found</span>
                                </div>
                            <?php else: ?>
                                <div class="preview-row matched">
                                    <span><strong><?php echo htmlspecialchars($p['full_name']); ?></strong> — <?php echo htmlspecialchars($p['student_id']); ?></span>
                                    <span><?php echo htmlspecialchars($p['department'] ?: '—'); ?></span>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <form method="POST" onsubmit="return confirm('Delete <?php echo count($csv_ids); ?> student(s)?')">
                        <input type="hidden" name="from_csv" value="1">
                        <?php foreach ($csv_ids as $id): ?>
                            <input type="hidden" name="csv_ids[]" value="<?php echo $id; ?>">
                        <?php endforeach; ?>
                        <button type="submit" class="btn-delete-all">Delete All (<?php echo count($csv_ids); ?>)</button>
                        <a href="bulk_delete.php?tab=csv" class="btn-cancel">Cancel</a>
                    </form>
                </div>
            <?php else: ?>
                <div class="csv-card">
                    <h3>Upload CSV File</h3>
                    <div class="instructions">
                        <strong>CSV Format:</strong> One student per row.
                        <br><br>
                        <strong>By Student ID:</strong>
                        <code>student_id<br>STU001<br>STU002<br>STU003</code>
                        <strong>By Full Name:</strong>
                        <code>full_name<br>Ahmed Ali Hassan<br>Sara Mohammed</code>
                        - First row can be a header<br>
                        - Only exact matches will be deleted<br>
                        - You'll see a preview before confirming
                        <br><br>
                        <a class="btn-download" onclick="downloadTemplate()">Download Template</a>
                    </div>

                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label>Select CSV File</label>
                            <input type="file" name="csv_file" accept=".csv" required>
                        </div>
                        <button type="submit" class="btn">Upload & Preview</button>
                    </form>
                </div>
            <?php endif; ?>

        <?php else: ?>

            <div class="toolbar">
                <form method="GET" class="search-form">
                    <input type="hidden" name="tab" value="manual">
                    <input type="text" name="q" placeholder="Search by name or student ID..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit">Search</button>
                </form>
                <form method="POST" id="deleteForm" onsubmit="return confirmDelete()">
                    <div id="hidden-ids"></div>
                    <button type="submit" class="btn-delete-selected" id="deleteBtn" disabled>Delete Selected (0)</button>
                </form>
            </div>

            <?php if ($students->num_rows === 0): ?>
                <div class="empty">No students found.</div>
            <?php else: ?>
                <div class="table-card">
                    <table>
                        <thead>
                            <tr>
                                <th class="checkbox-cell"><input type="checkbox" id="selectAll"></th>
                                <th>Student</th>
                                <th>Student ID</th>
                                <th>Department</th>
                                <th>Cars</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($s = $students->fetch_assoc()): ?>
                                <tr>
                                    <td class="checkbox-cell">
                                        <input type="checkbox" class="student-checkbox" value="<?php echo $s['id']; ?>">
                                    </td>
                                    <td>
                                        <span class="avatar-sm"><?php echo strtoupper(substr($s['full_name'], 0, 1)); ?></span>
                                        <strong><?php echo htmlspecialchars($s['full_name']); ?></strong>
                                    </td>
                                    <td><span class="student-id"><?php echo htmlspecialchars($s['student_id']); ?></span></td>
                                    <td><?php echo htmlspecialchars($s['department'] ?: '—'); ?></td>
                                    <td>
                                        <?php if ($s['car_count'] > 0): ?>
                                            <span class="car-badge"><?php echo $s['car_count']; ?> car<?php echo $s['car_count'] > 1 ? 's' : ''; ?></span>
                                        <?php else: ?>
                                            <span style="color: #94a3b8; font-size: 12px;">No cars</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </main>

    <script>
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.student-checkbox');
    const deleteBtn = document.getElementById('deleteBtn');

    function updateButton() {
        if (!deleteBtn) return;
        const checked = document.querySelectorAll('.student-checkbox:checked').length;
        deleteBtn.textContent = 'Delete Selected (' + checked + ')';
        deleteBtn.disabled = checked === 0;
    }

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
            updateButton();
        });
    }

    checkboxes.forEach(cb => cb.addEventListener('change', updateButton));

    function confirmDelete() {
        const checked = document.querySelectorAll('.student-checkbox:checked').length;
        if (checked === 0) return false;
        if (!confirm('Delete ' + checked + ' student(s)? This cannot be undone.')) return false;

        const hidden = document.getElementById('hidden-ids');
        hidden.innerHTML = '';
        document.querySelectorAll('.student-checkbox:checked').forEach(cb => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = cb.value;
            hidden.appendChild(input);
        });
        return true;
    }

    function downloadTemplate() {
        var csv = "student_id\nSTU001\nSTU002\nSTU003\n";
        var blob = new Blob([csv], { type: 'text/csv' });
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'delete_students_template.csv';
        a.click();
    }
    function toggleTheme() {
        var isDark = document.documentElement.classList.toggle('dark-mode');
        try { localStorage.setItem('theme', isDark ? 'dark' : 'light'); } catch(e) {}
    }
    </script>
</body>
</html>