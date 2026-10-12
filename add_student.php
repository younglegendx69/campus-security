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
    $stage = intval($_POST['stage'] ?? 0);
    $class = strtoupper(trim($_POST['class'] ?? ''));
    $track = trim($_POST['track'] ?? '');
    $phone = trim($_POST['phone']);

    if (empty($full_name)) $error = "Full Name is required.";
    elseif ($stage < 1 || $stage > 5) $error = "Please select a valid stage (1-5).";
    elseif (!in_array($class, ['A', 'B', 'C', 'D'])) $error = "Please select a class (A, B, C, or D).";
    elseif (($stage == 4 || $stage == 5) && empty($track)) $error = "Please select a field for Stage $stage.";
    else {
        $chk = $conn->prepare("SELECT id FROM students WHERE full_name = ? AND phone = ?");
        $chk->bind_param("ss", $full_name, $phone);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) $error = "A student with the same name and phone already exists.";
        else {
            $cars_data = [];
            if (!empty($_POST['plate_p1'])) {
                foreach ($_POST['plate_p1'] as $i => $p1) {
                    $p1 = trim($p1);
                    $letter = strtoupper(trim($_POST['plate_letter'][$i] ?? ''));
                    $p2 = trim($_POST['plate_p2'][$i] ?? '');
                    $model = trim($_POST['car_model'][$i] ?? '');
                    $color = trim($_POST['color'][$i] ?? '');
                    if ($p1 === '' && $letter === '' && $p2 === '' && $model === '' && $color === '') continue;
                    $plate = '';
                    if ($p1 !== '' || $letter !== '' || $p2 !== '') {
                        if (!preg_match('/^\d{1,3}$/', $p1) || !preg_match('/^[A-Z]{1,2}$/', $letter) || !preg_match('/^\d{1,6}$/', $p2)) {
                            $error = "Invalid plate. Use: 1-3 digits + 1-2 letters + 1-6 digits.";
                            break;
                        }
                        $plate = "$p1 $letter $p2";
                    }
                    $cars_data[] = ['plate' => $plate, 'model' => $model, 'color' => $color];
                }
            }
            if (!$error) {
                foreach ($cars_data as $c) {
                    if (empty($c['plate'])) continue;
                    $chk2 = $conn->prepare("SELECT id FROM cars WHERE plate_number = ?");
                    $chk2->bind_param("s", $c['plate']);
                    $chk2->execute();
                    if ($chk2->get_result()->num_rows > 0) { $error = "Plate " . htmlspecialchars($c['plate']) . " is already registered."; break; }
                }
            }
            if (!$error) {
                $student_id = $next_id;
                $photo = "";
                if (!empty($_FILES['photo']['name'])) {
                    if (!is_dir("uploads")) mkdir("uploads");
                    $photo = "uploads/" . time() . "_" . basename($_FILES['photo']['name']);
                    move_uploaded_file($_FILES['photo']['tmp_name'], $photo);
                }
                if ($stage == 4 || $stage == 5) {
                    $stmt = $conn->prepare("INSERT INTO students (student_id, full_name, stage, class, track, phone, photo) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssissss", $student_id, $full_name, $stage, $class, $track, $phone, $photo);
                } else {
                    $stmt = $conn->prepare("INSERT INTO students (student_id, full_name, stage, class, phone, photo) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssisss", $student_id, $full_name, $stage, $class, $phone, $photo);
                }
                if ($stmt->execute()) {
                    $new_student_id = $conn->insert_id;
                    $cars_added = 0;
                    foreach ($cars_data as $c) {
                        $stmt2 = $conn->prepare("INSERT INTO cars (student_id, plate_number, car_model, color) VALUES (?, ?, ?, ?)");
                        $stmt2->bind_param("isss", $new_student_id, $c['plate'], $c['model'], $c['color']);
                        $stmt2->execute();
                        $cars_added++;
                    }
                    $success = "Student $student_id ($full_name) saved with $cars_added car(s)!";
                    $next_id = generateStudentID($conn);
                } else $error = "Error: " . $conn->error;
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
        .card { background: #fff; padding: 30px; border-radius: 12px; border: 1px solid #e2e8f0; max-width: 900px; }
        .section-head { font-size: 14px; font-weight: 700; color: #0f172a; margin-bottom: 14px; padding-bottom: 10px; border-bottom: 1px solid #f1f5f9; }
        .form-group { margin-bottom: 18px; }
        label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 6px; color: #334155; }
        input[type=text], input[type=file], select { width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; font-family: inherit; background: #fff; }
        input:focus, select:focus { outline: none; border-color: #0f172a; }
        input[readonly] { background: #f1f5f9; color: #64748b; }
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .btn { background: #0f172a; color: #fff; padding: 14px 28px; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
        .btn:hover { background: #1e293b; }
        .btn-back { background: #f1f5f9; color: #475569; text-decoration: none; display: inline-block; padding: 14px 28px; border-radius: 8px; margin-left: 10px; font-size: 14px; font-weight: 500; }
        .btn-add-car { background: #2563eb; color: #fff; padding: 10px 18px; border: none; border-radius: 8px; font-size: 13px; font-weight: 500; cursor: pointer; }
        .alert { padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .optional { font-size: 11px; color: #94a3b8; font-weight: 400; }
        .auto-badge { display: inline-block; background: #dbeafe; color: #1e40af; font-size: 11px; padding: 2px 8px; border-radius: 4px; margin-left: 6px; font-weight: 600; }
        .car-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; margin-bottom: 14px; position: relative; }
        .car-box-header { font-size: 13px; font-weight: 700; color: #0f172a; margin-bottom: 14px; text-transform: uppercase; letter-spacing: 0.5px; }
        .car-box-remove { position: absolute; top: 12px; right: 12px; background: #fee2e2; color: #dc2626; border: none; padding: 5px 12px; border-radius: 6px; font-size: 12px; cursor: pointer; font-weight: 600; }
        .plate-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-bottom: 8px; }
        .plate-field input { text-align: center; font-family: monospace; font-size: 22px; font-weight: 700; letter-spacing: 3px; text-transform: uppercase; padding: 14px 8px; }
        .plate-field .hint { text-align: center; font-size: 10.5px; color: #94a3b8; margin-top: 4px; font-weight: 500; }
        .car-details-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 16px; }

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
        html.dark-mode .section-head { color: #f1f5f9 !important; border-bottom-color: #334155 !important; }
        html.dark-mode label { color: #cbd5e1 !important; }
        html.dark-mode input[type=text], html.dark-mode input[type=file], html.dark-mode select { background: #0f172a !important; border-color: #475569 !important; color: #f1f5f9 !important; }
        html.dark-mode input[readonly] { background: #334155 !important; color: #94a3b8 !important; }
        html.dark-mode select option { background: #0f172a !important; color: #f1f5f9 !important; }
        html.dark-mode .car-box { background: #0f172a !important; border-color: #334155 !important; }
        html.dark-mode .car-box-header { color: #f1f5f9 !important; }
        html.dark-mode .optional { color: #94a3b8 !important; }
        html.dark-mode .btn { background: #3b82f6 !important; color: #fff !important; }
        html.dark-mode .btn-back { background: #334155 !important; color: #e2e8f0 !important; }
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
            <a href="dashboard.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12l9-9 9 9M5 10v10h14V10"/></svg> <span data-en="Dashboard" data-ku="داشبۆرد" data-ar="لوحة التحكم">Dashboard</span></a>
            <div class="nav-label" data-en="Management" data-ku="بەڕێوەبردن" data-ar="الإدارة">Management</div>
            <a href="add_student.php" class="active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg> <span data-en="Add Student" data-ku="زیادکردنی قوتابی" data-ar="إضافة طالب">Add Student</span></a>
            <a href="bulk_import.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg> <span data-en="Bulk Import" data-ku="هاوردەکردنی بەکۆمەڵ" data-ar="استيراد جماعي">Bulk Import</span></a>
            <a href="view_students.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/></svg> <span data-en="All Students" data-ku="هەموو قوتابیان" data-ar="جميع الطلاب">All Students</span></a>
            <a href="bulk_delete.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg> <span data-en="Bulk Delete" data-ku="سڕینەوەی بەکۆمەڵ" data-ar="حذف جماعي">Bulk Delete</span></a>
            <a href="promote.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19V5M5 12l7-7 7 7"/></svg> <span data-en="Promote Students" data-ku="بەرزکردنەوەی قوتابیان" data-ar="ترقية الطلاب">Promote Students</span></a>
            <div class="nav-label" data-en="Operations" data-ku="کارەکان" data-ar="العمليات">Operations</div>
            <a href="search_plate.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg> <span data-en="Search" data-ku="گەڕان" data-ar="البحث">Search</span></a>
            <a href="blacklist.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M4.93 4.93l14.14 14.14"/></svg> <span data-en="Blacklist" data-ku="ڕەشلیست" data-ar="القائمة السوداء">Blacklist</span></a>
            <div class="nav-label" data-en="Account" data-ku="هەژمار" data-ar="الحساب">Account</div>
            <a href="manage_users.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg> <span data-en="Manage Users" data-ku="بەڕێوەبردنی بەکارهێنەران" data-ar="إدارة المستخدمين">Manage Users</span></a>
            <a href="change_password.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg> <span data-en="Change Password" data-ku="گۆڕینی وشەی نهێنی" data-ar="تغيير كلمة المرور">Change Password</span></a>
            <a href="logout.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg> <span data-en="Sign Out" data-ku="چوونەدەرەوە" data-ar="تسجيل الخروج">Sign Out</span></a>
        </nav>
    </aside>
    <main class="main">
        <h1 class="page-title" data-en="Register Student + Cars" data-ku="تۆمارکردنی قوتابی + ئۆتۆمبێل" data-ar="تسجيل طالب + سيارة">Register Student + Cars</h1>
        <p class="page-sub" data-en="Every student: Stage + Class. Stage 4-5 also choose Field." data-ku="هەموو قوتابی: قۆناغ + پۆل. قۆناغی 4-5 بەش هەڵدەبژێرن." data-ar="كل طالب: المرحلة + الصف. المرحلة 4-5 يختارون التخصص.">Every student: Stage + Class. Stage 4-5 also choose Field.</p>

        <div class="card">
            <?php if ($success) echo "<div class='alert alert-success'>$success</div>"; ?>
            <?php if ($error) echo "<div class='alert alert-error'>$error</div>"; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="section-head" data-en="Student Information" data-ku="زانیاری قوتابی" data-ar="معلومات الطالب">Student Information</div>
                <div class="row">
                    <div class="form-group">
                        <label><span data-en="Student ID" data-ku="ژمارەی قوتابی" data-ar="رقم الطالب">Student ID</span> <span class="auto-badge">AUTO</span></label>
                        <input type="text" value="<?php echo $next_id; ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label><span data-en="Full Name *" data-ku="ناوی تەواو *" data-ar="الاسم الكامل *">Full Name *</span></label>
                        <input type="text" name="full_name" placeholder="Ahmed Ali" required>
                    </div>
                </div>
                <div class="row">
                    <div class="form-group">
                        <label data-en="Stage *" data-ku="قۆناغ *" data-ar="المرحلة *">Stage *</label>
                        <select name="stage" id="stageSelect" required onchange="toggleTrack()">
                            <option value="" data-en="-- Select Stage --" data-ku="-- قۆناغ هەڵبژێرە --" data-ar="-- اختر المرحلة --">-- Select Stage --</option>
                            <option value="1">Stage 1</option>
                            <option value="2">Stage 2</option>
                            <option value="3">Stage 3</option>
                            <option value="4">Stage 4</option>
                            <option value="5">Stage 5</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label data-en="Class *" data-ku="پۆل *" data-ar="الصف *">Class *</label>
                        <select name="class" required>
                            <option value="" data-en="-- Select Class --" data-ku="-- پۆل هەڵبژێرە --" data-ar="-- اختر الصف --">-- Select Class --</option>
                            <option value="A">A</option>
                            <option value="B">B</option>
                            <option value="C">C</option>
                            <option value="D">D</option>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="form-group" id="trackGroup" style="display:none;">
                        <label data-en="Field *" data-ku="بەش *" data-ar="التخصص *">Field *</label>
                        <select name="track" id="trackSelect">
                            <option value="" data-en="-- Select Field --" data-ku="-- بەش هەڵبژێرە --" data-ar="-- اختر التخصص --">-- Select Field --</option>
                            <option value="Programming" data-en="Programming" data-ku="بەرنامەسازی" data-ar="البرمجة">Programming</option>
                            <option value="Network" data-en="Network" data-ku="تۆڕ" data-ar="الشبكات">Network</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label data-en="Phone" data-ku="تەلەفۆن" data-ar="الهاتف">Phone</label>
                        <input type="text" name="phone" placeholder="07701234567">
                    </div>
                </div>
                <div class="form-group">
                    <label data-en="Student Photo" data-ku="وێنەی قوتابی" data-ar="صورة الطالب">Student Photo</label>
                    <input type="file" name="photo" accept="image/*">
                </div>

                <div class="section-head" style="margin-top: 30px;"><span data-en="Cars" data-ku="ئۆتۆمبێلەکان" data-ar="السيارات">Cars</span> <span class="optional" data-en="(optional)" data-ku="(ئارەزوومەندانە)" data-ar="(اختياري)">(optional)</span></div>
                <div id="cars-container">
                    <div class="car-box">
                        <div class="car-box-header">Car 1</div>
                        <label data-en="Plate Number" data-ku="ژمارەی پلاک" data-ar="رقم اللوحة">Plate Number</label>
                        <div class="plate-row">
                            <div class="plate-field">
                                <input type="text" name="plate_p1[]" maxlength="3" placeholder="22" oninput="this.value=this.value.replace(/\D/g,'');">
                                <div class="hint" data-en="1-3 digits" data-ku="1-3 ژمارە" data-ar="1-3 أرقام">1-3 digits</div>
                            </div>
                            <div class="plate-field">
                                <input type="text" name="plate_letter[]" maxlength="2" placeholder="C" oninput="this.value=this.value.toUpperCase().replace(/[^A-Z]/g,'');">
                                <div class="hint" data-en="1-2 letters" data-ku="1-2 پیت" data-ar="1-2 حروف">1-2 letters</div>
                            </div>
                            <div class="plate-field">
                                <input type="text" name="plate_p2[]" maxlength="6" placeholder="79770" oninput="this.value=this.value.replace(/\D/g,'');">
                                <div class="hint" data-en="1-6 digits" data-ku="1-6 ژمارە" data-ar="1-6 أرقام">1-6 digits</div>
                            </div>
                        </div>
                        <div class="car-details-row">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label data-en="Car Model" data-ku="مۆدێلی ئۆتۆمبێل" data-ar="موديل السيارة">Car Model</label>
                                <input type="text" name="car_model[]" placeholder="Toyota Corolla">
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label data-en="Color" data-ku="ڕەنگ" data-ar="اللون">Color</label>
                                <input type="text" name="color[]" placeholder="White">
                            </div>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-add-car" onclick="addCar()" style="margin-top: 16px;" data-en="+ Add Another Car" data-ku="+ زیادکردنی ئۆتۆمبێلی تر" data-ar="+ إضافة سيارة أخرى">+ Add Another Car</button>
                <div style="margin-top: 30px;">
                    <button type="submit" class="btn" data-en="Save Student + Cars" data-ku="پاشەکەوتکردن" data-ar="حفظ">Save Student + Cars</button>
                    <a href="dashboard.php" class="btn-back" data-en="Cancel" data-ku="پاشگەزبوونەوە" data-ar="إلغاء">Cancel</a>
                </div>
            </form>
        </div>
    </main>
    <script src="language.js?v=99"></script>
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
            <label>Plate Number</label>
            <div class="plate-row">
                <div class="plate-field">
                    <input type="text" name="plate_p1[]" maxlength="3" placeholder="22" oninput="this.value=this.value.replace(/\\D/g,'');">
                    <div class="hint">1-3 digits</div>
                </div>
                <div class="plate-field">
                    <input type="text" name="plate_letter[]" maxlength="2" placeholder="C" oninput="this.value=this.value.toUpperCase().replace(/[^A-Z]/g,'');">
                    <div class="hint">1-2 letters</div>
                </div>
                <div class="plate-field">
                    <input type="text" name="plate_p2[]" maxlength="6" placeholder="79770" oninput="this.value=this.value.replace(/\\D/g,'');">
                    <div class="hint">1-6 digits</div>
                </div>
            </div>
            <div class="car-details-row">
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Car Model</label>
                    <input type="text" name="car_model[]" placeholder="Toyota Corolla">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Color</label>
                    <input type="text" name="color[]" placeholder="White">
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