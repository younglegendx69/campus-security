<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
if ($_SESSION['role'] != 'admin') { header("Location: guard_dashboard.php"); exit; }
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
    if (!file_exists($file)) $error = "Please select a file to upload.";
    else {
        $handle = fopen($file, "r");
        if ($handle === false) $error = "Cannot open the file.";
        else {
            $imported = 0; $skipped = 0; $row_num = 0;
            fgetcsv($handle);
            while (($row = fgetcsv($handle)) !== false) {
                $row_num++;
                if (count($row) < 1 || empty(trim($row[0]))) { $skipped++; continue; }
                $full_name = trim($row[0]);
                $stage = intval(trim($row[1] ?? 0));
                $class = strtoupper(trim($row[2] ?? ''));
                $track = trim($row[3] ?? '');
                $phone = trim($row[4] ?? '');
                $plate1 = isset($row[5]) ? strtoupper(trim($row[5])) : "";
                $model1 = trim($row[6] ?? '');
                $color1 = trim($row[7] ?? '');
                if ($track == 'Networking') $track = 'Network';
                if ($stage < 1 || $stage > 5) { $skipped++; $report[] = "Row $row_num: invalid stage"; continue; }
                if (!in_array($class, ['A', 'B', 'C', 'D'])) { $skipped++; $report[] = "Row $row_num: invalid class"; continue; }
                if (($stage == 4 || $stage == 5) && !in_array($track, ['Programming', 'Network'])) { $skipped++; $report[] = "Row $row_num: missing field"; continue; }
                if ($stage != 4 && $stage != 5) $track = '';
                $chk = $conn->prepare("SELECT id FROM students WHERE full_name = ? AND phone = ?");
                $chk->bind_param("ss", $full_name, $phone);
                $chk->execute();
                if ($chk->get_result()->num_rows > 0) { $skipped++; $report[] = "Row $row_num: duplicate $full_name"; continue; }
                if (!empty($plate1)) {
                    $chk2 = $conn->prepare("SELECT id FROM cars WHERE plate_number = ?");
                    $chk2->bind_param("s", $plate1);
                    $chk2->execute();
                    if ($chk2->get_result()->num_rows > 0) { $skipped++; $report[] = "Row $row_num: duplicate plate"; continue; }
                }
                $student_id = generateStudentID($conn);
                if ($stage == 4 || $stage == 5) {
                    $stmt = $conn->prepare("INSERT INTO students (student_id, full_name, stage, class, track, phone) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssisss", $student_id, $full_name, $stage, $class, $track, $phone);
                } else {
                    $stmt = $conn->prepare("INSERT INTO students (student_id, full_name, stage, class, phone) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssiss", $student_id, $full_name, $stage, $class, $phone);
                }
                if ($stmt->execute()) {
                    $new_id = $conn->insert_id;
                    if (!empty($plate1)) {
                        $stmt2 = $conn->prepare("INSERT INTO cars (student_id, plate_number, car_model, color) VALUES (?, ?, ?, ?)");
                        $stmt2->bind_param("isss", $new_id, $plate1, $model1, $color1);
                        $stmt2->execute();
                    }
                    $imported++;
                } else $skipped++;
            }
            fclose($handle);
            if ($imported > 0) $success = "Import complete: $imported added, $skipped skipped.";
            else $error = "No students imported.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Bulk Import - Sardam Institute</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="theme.css?v=99">
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
        .lang-btn { background: #f1f5f9; border: 1px solid #e2e8f0; height: 28px; padding: 0 10px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 11.5px; color: #475569; font-family: inherit; }
        .lang-btn.active { background: #0f172a; color: #fff; border-color: #0f172a; }
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
        .alert { padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .report { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; max-width: 900px; margin-top: 20px; }
        .report li { font-size: 13px; padding: 6px 0; border-bottom: 1px solid #f1f5f9; font-family: monospace; }
        .btn-download { display: inline-block; background: #2563eb; color: #fff; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: 500; margin-top: 10px; cursor: pointer; }

        /* DARK MODE */
        html.dark-mode, html.dark-mode body { background: #0f172a !important; color: #f1f5f9 !important; }
        html.dark-mode .sidebar { background: #1e293b !important; border-right-color: #334155 !important; }
        html.dark-mode .sidebar-header { border-bottom-color: #334155 !important; }
        html.dark-mode .sidebar-header .name { color: #f1f5f9 !important; }
        html.dark-mode .sidebar-header .name span { color: #94a3b8 !important; }
        html.dark-mode .sidebar nav a { color: #cbd5e1 !important; }
        html.dark-mode .sidebar nav a:hover { background: #334155 !important; color: #fff !important; }
        html.dark-mode .sidebar nav a.active { background: #3b82f6 !important; color: #fff !important; }
        html.dark-mode .nav-label { color: #64748b !important; }
        html.dark-mode .main { background: #0f172a !important; }
        html.dark-mode .page-title { color: #f1f5f9 !important; }
        html.dark-mode .page-sub { color: #94a3b8 !important; }
        html.dark-mode .card { background: #1e293b !important; border-color: #334155 !important; color: #f1f5f9 !important; }
        html.dark-mode .card h3 { color: #f1f5f9 !important; }
        html.dark-mode .instructions { background: #0f172a !important; color: #cbd5e1 !important; }
        html.dark-mode .instructions code { background: #1e293b !important; color: #fca5a5 !important; }
        html.dark-mode label { color: #cbd5e1 !important; }
        html.dark-mode input[type=file] { background: #0f172a !important; border-color: #475569 !important; color: #f1f5f9 !important; }
        html.dark-mode .btn { background: #3b82f6 !important; color: #fff !important; }
        html.dark-mode .report { background: #1e293b !important; border-color: #334155 !important; color: #f1f5f9 !important; }
        html.dark-mode .report li { border-bottom-color: #334155 !important; color: #cbd5e1 !important; }
        html.dark-mode .alert-success { background: #14532d !important; color: #bbf7d0 !important; border-color: #166534 !important; }
        html.dark-mode .alert-error { background: #7f1d1d !important; color: #fecaca !important; border-color: #991b1b !important; }
        html.dark-mode .lang-btn { background: #334155 !important; border-color: #475569 !important; color: #e2e8f0 !important; }
        html.dark-mode .lang-btn.active { background: #3b82f6 !important; color: #fff !important; border-color: #3b82f6 !important; }
        html.dark-mode button[onclick*="toggleTheme"] { background: #334155 !important; border-color: #475569 !important; color: #e2e8f0 !important; }
    </style>
    <script>
    (function() {
        try {
            var saved = localStorage.getItem('theme') || 'light';
            if (saved === 'dark') document.documentElement.classList.add('dark-mode');
            var lang = localStorage.getItem('lang') || 'en';
            document.documentElement.dir = lang === 'ar' ? 'rtl' : 'ltr';
        } catch(e) {}
    })();
    </script>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="logo.png" alt="Logo">
            <div class="name">Sardam Institute<span data-en="Computer Sciences" data-ku="زانستەکانی کۆمپیوتەر" data-ar="علوم الحاسوب">Computer Sciences</span></div>
        </div>
        <div style="padding: 10px 16px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; gap: 8px; flex-wrap: wrap;">
            <span style="font-size: 10px; font-weight: 600; color: #94a3b8; text-transform: uppercase;" data-en="Lang" data-ku="زمان" data-ar="لغة">Lang</span>
            <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                <button onclick="setLanguage('en')" class="lang-btn">English</button>
                <button onclick="setLanguage('ku')" class="lang-btn">کوردی</button>
                <button onclick="setLanguage('ar')" class="lang-btn">عربي</button>
                <button onclick="toggleTheme()" title="Theme" style="background: #f1f5f9; border: 1px solid #e2e8f0; width: 28px; height: 28px; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; padding: 0;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                </button>
            </div>
        </div>
        <nav>
            <div class="nav-label" data-en="Main" data-ku="سەرەکی" data-ar="الرئيسية">Main</div>
            <a href="dashboard.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12l9-9 9 9M5 10v10h14V10"/></svg> <span data-en="Dashboard" data-ku="پەڕەی کۆنترۆڵ" data-ar="لوحة التحكم">Dashboard</span></a>
            <div class="nav-label" data-en="Management" data-ku="بەڕێوەبردن" data-ar="الإدارة">Management</div>
            <a href="add_student.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg> <span data-en="Add Student" data-ku="زیادکردنی قوتابی" data-ar="إضافة طالب">Add Student</span></a>
            <a href="bulk_import.php" class="active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg> <span data-en="Bulk Import" data-ku="هاوردەکردنی بەکۆمەڵ" data-ar="استيراد جماعي">Bulk Import</span></a>
            <a href="view_students.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/></svg> <span data-en="All Students" data-ku="هەموو قوتابیان" data-ar="جميع الطلاب">All Students</span></a>
            <a href="bulk_delete.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg> <span data-en="Bulk Delete" data-ku="سڕینەوەی بەکۆمەڵ" data-ar="حذف جماعي">Bulk Delete</span></a>
            <a href="promote.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19V5M5 12l7-7 7 7"/></svg> <span data-en="Promote Students" data-ku="بەرزکردنەوەی قوتابیان" data-ar="ترقية الطلاب">Promote Students</span></a>
            <div class="nav-label" data-en="Operations" data-ku="کارەکان" data-ar="العمليات">Operations</div>
            <a href="search_plate.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg> <span data-en="Search" data-ku="گەڕان" data-ar="البحث">Search</span></a>
            <a href="blacklist.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M4.93 4.93l14.14 14.14"/></svg> <span data-en="Blacklist" data-ku="لیستی قەدەغە کراوان" data-ar="القائمة السوداء">Blacklist</span></a>
            <div class="nav-label" data-en="Account" data-ku="هەژمار" data-ar="الحساب">Account</div>
            <a href="manage_users.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg> <span data-en="Manage Users" data-ku="بەڕێوەبردنی بەکارهێنەران" data-ar="إدارة المستخدمين">Manage Users</span></a>
            <a href="change_password.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg> <span data-en="Change Password" data-ku="گۆڕینی وشەی نهێنی" data-ar="تغيير كلمة المرور">Change Password</span></a>
            <a href="backup.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><path d="M7 10l5 5 5-5M12 15V3"/></svg> <span data-en="Backup" data-ku="پاشەکەوت" data-ar="نسخ احتياطي">Backup</span></a>
            <a href="logout.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg> <span data-en="Sign Out" data-ku="چوونەدەرەوە" data-ar="تسجيل الخروج">Sign Out</span></a>
        </nav>
    </aside>
    <main class="main">
        <h1 class="page-title" data-en="Bulk Import Students" data-ku="هاوردەکردنی بەکۆمەڵی قوتابیان" data-ar="استيراد الطلاب جماعياً">Bulk Import Students</h1>
        <p class="page-sub" data-en="Upload a CSV to add many students at once" data-ku="فایلی CSV بار بکە بۆ زیادکردنی چەندین قوتابی" data-ar="ارفع ملف CSV لإضافة العديد من الطلاب">Upload a CSV to add many students at once</p>

        <?php if ($success) echo "<div class='alert alert-success'>$success</div>"; ?>
        <?php if ($error) echo "<div class='alert alert-error'>$error</div>"; ?>

        <div class="card">
            <h3 data-en="CSV File Format" data-ku="فۆرماتی فایلی CSV" data-ar="تنسيق ملف CSV">CSV File Format</h3>
            <div class="instructions">
                <strong data-en="Columns:" data-ku="کۆلۆنەکان:" data-ar="الأعمدة:">Columns:</strong>
                <code>full_name,stage,class,track,phone,plate_number,car_model,color</code>
                <strong data-en="Example:" data-ku="نموونە:" data-ar="مثال:">Example:</strong>
                <code>Ahmed Ali,1,A,,07701234567,21 S 52520,Toyota Corolla,White</code>
                <code>Sara Ahmed,4,B,Programming,07701111111,11 B 12345,Honda Civic,Red</code>
                <br>
                <span data-en="First row = header. Stage 4-5 need a Field." data-ku="ڕیزی یەکەم = سەرپەڕە. قۆناغ 4-5 بەش پێویستە." data-ar="الصف الأول = رأس. المرحلة 4-5 تحتاج تخصصاً.">First row = header. Stage 4-5 need a Field.</span>
                <br><br>
                <a class="btn-download" onclick="downloadTemplate()" data-en="Download Template" data-ku="داگرتنی نموونە" data-ar="تحميل النموذج">Download Template</a>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label data-en="Select CSV File" data-ku="فایلی CSV هەڵبژێرە" data-ar="اختر ملف CSV">Select CSV File</label>
                    <input type="file" name="csv_file" accept=".csv" required>
                </div>
                <button type="submit" class="btn" data-en="Upload & Import" data-ku="بارکردن و هاوردەکردن" data-ar="رفع واستيراد">Upload & Import</button>
            </form>
        </div>

        <?php if (!empty($report)): ?>
            <div class="report">
                <h3 data-en="Import Details" data-ku="وردەکاری هاوردەکردن" data-ar="تفاصيل الاستيراد">Import Details</h3>
                <ul>
                    <?php foreach ($report as $line): ?>
                        <li><?php echo htmlspecialchars($line); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </main>

    <script src="language.js?v=99"></script>
    <script>
    function downloadTemplate() {
        var csv = "full_name,stage,class,track,phone,plate_number,car_model,color\n";
        csv += "Ahmed Ali,1,A,,07701234567,21 S 52520,Toyota Corolla,White\n";
        csv += "Sara Ahmed,3,B,,07701111111,11 B 12345,Honda Civic,Red\n";
        csv += "Kardo Omar,4,A,Programming,07701902222,55 C 55555,Nissan Patrol,Black\n";
        csv += "Lana Rebaz,5,D,Network,07701333333,77 Z 99999,Mazda 6,Silver\n";
        var blob = new Blob([csv], { type: 'text/csv' });
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'student_import_template.csv';
        a.click();
    }
    function toggleTheme() {
        var isDark = document.documentElement.classList.toggle('dark-mode');
        try { localStorage.setItem('theme', isDark ? 'dark' : 'light'); } catch(e) {}
    }
    </script>
</body>
</html>