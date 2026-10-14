<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
include 'db.php';

$is_admin = ($_SESSION['role'] == 'admin');
$is_guard = ($_SESSION['role'] == 'guard');

$search = "";
$where = "";

if (!empty($_GET['q'])) {
    $search = trim($_GET['q']);
    $like = "%$search%";
    $where = "WHERE s.full_name LIKE ? OR s.student_id LIKE ? OR c.plate_number LIKE ?";
}

if ($where) {
    $stmt = $conn->prepare("
        SELECT s.id, s.student_id, s.full_name, s.stage, s.class, s.track, s.phone, s.photo,
               GROUP_CONCAT(c.plate_number SEPARATOR ', ') AS plates,
               COUNT(c.id) AS car_count
        FROM students s
        LEFT JOIN cars c ON c.student_id = s.id
        $where
        GROUP BY s.id
        ORDER BY s.full_name ASC
        LIMIT 200
    ");
    $stmt->bind_param("sss", $like, $like, $like);
    $stmt->execute();
    $students = $stmt->get_result();
} else {
    $students = $conn->query("
        SELECT s.id, s.student_id, s.full_name, s.stage, s.class, s.track, s.phone, s.photo,
               GROUP_CONCAT(c.plate_number SEPARATOR ', ') AS plates,
               COUNT(c.id) AS car_count
        FROM students s
        LEFT JOIN cars c ON c.student_id = s.id
        GROUP BY s.id
        ORDER BY s.full_name ASC
        LIMIT 200
    ");
}

$total = $students->num_rows;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Search - Sardam Institute</title>
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
        .search-card { background: #fff; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 20px; }
        .search-form { display: flex; gap: 12px; }
        .search-form input { flex: 1; padding: 14px 18px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 15px; font-family: inherit; }
        .search-form input:focus { outline: none; border-color: #0f172a; }
        .search-form button { background: #0f172a; color: #fff; padding: 14px 28px; border: none; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; }
        .search-form .btn-clear { background: #f1f5f9; color: #475569; padding: 14px 20px; border-radius: 10px; text-decoration: none; font-size: 14px; font-weight: 500; }
        .result-info { font-size: 13.5px; color: #64748b; margin-bottom: 14px; }
        .result-info strong { color: #0f172a; }
        .table-card { background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8fafc; padding: 14px 20px; text-align: left; font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #e2e8f0; }
        td { padding: 16px 20px; font-size: 14px; border-bottom: 1px solid #f1f5f9; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f8fafc; }
        .avatar-sm { width: 36px; height: 36px; border-radius: 50%; background: #0f172a; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-weight: 600; font-size: 13px; vertical-align: middle; margin-right: 10px; }
        .student-link { color: inherit; text-decoration: none; display: inline-flex; align-items: center; }
        .student-link:hover .student-name { color: #2563eb; text-decoration: underline; }
        .student-name { font-weight: 600; color: #0f172a; }
        .student-id { font-family: monospace; background: #f1f5f9; padding: 3px 8px; border-radius: 4px; font-size: 12px; }
        .stage-badge { display: inline-block; background: #f1f5f9; color: #0f172a; padding: 3px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; }
        .class-badge { display: inline-block; background: #fef3c7; color: #92400e; padding: 3px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; }
        .track-badge { display: inline-block; padding: 3px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; }
        .track-programming { background: #dbeafe; color: #1e40af; }
        .track-network { background: #dcfce7; color: #166534; }
        .plate-tag { display: inline-block; font-family: monospace; background: #0f172a; color: #fff; padding: 3px 8px; border-radius: 4px; font-size: 11.5px; letter-spacing: 1px; margin-right: 4px; margin-bottom: 3px; }
        .plate-blocked { background: #dc2626; }
        .no-cars { color: #94a3b8; font-size: 12.5px; font-style: italic; }
        .empty { text-align: center; padding: 60px; color: #64748b; }
        .empty h3 { font-size: 16px; font-weight: 500; margin-bottom: 6px; }
        .empty p { font-size: 13.5px; color: #94a3b8; }

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
        html.dark-mode .search-card { background: #1e293b !important; border-color: #334155 !important; }
        html.dark-mode .search-form input { background: #0f172a !important; border-color: #475569 !important; color: #f1f5f9 !important; }
        html.dark-mode .search-form button { background: #3b82f6 !important; }
        html.dark-mode .search-form .btn-clear { background: #334155 !important; color: #e2e8f0 !important; }
        html.dark-mode .result-info { color: #94a3b8 !important; }
        html.dark-mode .result-info strong { color: #f1f5f9 !important; }
        html.dark-mode .table-card { background: #1e293b !important; border-color: #334155 !important; }
        html.dark-mode th { background: #0f172a !important; color: #94a3b8 !important; border-bottom-color: #334155 !important; }
        html.dark-mode td { color: #f1f5f9 !important; border-bottom-color: #334155 !important; }
        html.dark-mode tr:hover td { background: #334155 !important; }
        html.dark-mode .student-name { color: #f1f5f9 !important; }
        html.dark-mode .student-id { background: #334155 !important; color: #e2e8f0 !important; }
        html.dark-mode .stage-badge { background: #1e40af !important; color: #dbeafe !important; }
        html.dark-mode .class-badge { background: #78350f !important; color: #fef3c7 !important; }
        html.dark-mode .avatar-sm { background: #0f172a !important; color: #f1f5f9 !important; }
        html.dark-mode .empty { color: #94a3b8 !important; }
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
            <a href="<?php echo $is_admin ? 'dashboard.php' : 'guard_dashboard.php'; ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12l9-9 9 9M5 10v10h14V10"/></svg> <span data-en="Dashboard" data-ku="پەڕەی کۆنترۆڵ" data-ar="لوحة التحكم">Dashboard</span></a>
            <?php if ($is_admin): ?>
                <div class="nav-label" data-en="Management" data-ku="بەڕێوەبردن" data-ar="الإدارة">Management</div>
                <a href="add_student.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg> <span data-en="Add Student" data-ku="زیادکردنی قوتابی" data-ar="إضافة طالب">Add Student</span></a>
                <a href="bulk_import.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg> <span data-en="Bulk Import" data-ku="هاوردەکردنی بەکۆمەڵ" data-ar="استيراد جماعي">Bulk Import</span></a>
                <a href="view_students.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/></svg> <span data-en="All Students" data-ku="هەموو قوتابیان" data-ar="جميع الطلاب">All Students</span></a>
                <a href="bulk_delete.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg> <span data-en="Bulk Delete" data-ku="سڕینەوەی بەکۆمەڵ" data-ar="حذف جماعي">Bulk Delete</span></a>
                <a href="promote.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19V5M5 12l7-7 7 7"/></svg> <span data-en="Promote Students" data-ku="بەرزکردنەوەی قوتابیان" data-ar="ترقية الطلاب">Promote Students</span></a>
            <?php endif; ?>
            <div class="nav-label" data-en="Operations" data-ku="کارەکان" data-ar="العمليات">Operations</div>
            <a href="search_plate.php" class="active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg> <span data-en="Search" data-ku="گەڕان" data-ar="البحث">Search</span></a>
            <a href="blacklist.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M4.93 4.93l14.14 14.14"/></svg> <span data-en="Blacklist" data-ku="لیستی قەدەغە کراوان" data-ar="القائمة السوداء">Blacklist</span></a>
            <?php if ($is_admin): ?>
                <div class="nav-label" data-en="Account" data-ku="هەژمار" data-ar="الحساب">Account</div>
                <a href="manage_users.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg> <span data-en="Manage Users" data-ku="بەڕێوەبردنی بەکارهێنەران" data-ar="إدارة المستخدمين">Manage Users</span></a>
                <a href="change_password.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg> <span data-en="Change Password" data-ku="گۆڕینی وشەی نهێنی" data-ar="تغيير كلمة المرور">Change Password</span></a>
            <?php endif; ?>
            <a href="backup.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><path d="M7 10l5 5 5-5M12 15V3"/></svg> <span data-en="Backup" data-ku="پاشەکەوت" data-ar="نسخ احتياطي">Backup</span></a>
            <a href="logout.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg> <span data-en="Sign Out" data-ku="چوونەدەرەوە" data-ar="تسجيل الخروج">Sign Out</span></a>
        </nav>
    </aside>
    <main class="main">
        <h1 class="page-title" data-en="Search" data-ku="گەڕان" data-ar="البحث">Search</h1>
        <p class="page-sub" data-en="Search by plate, name, or ID" data-ku="گەڕان بە پلاک، ناو، یان ژمارە" data-ar="البحث باللوحة أو الاسم أو الرقم">Search by plate, name, or ID</p>

        <div class="search-card">
            <form method="GET" class="search-form">
                <input type="text" name="q" placeholder="Plate, name, or ID..." value="<?php echo htmlspecialchars($search); ?>" autofocus data-en="Plate, name, or ID..." data-ku="پلاک، ناو، یان ژمارە..." data-ar="اللوحة، الاسم، أو الرقم...">
                <button type="submit" data-en="Search" data-ku="گەڕان" data-ar="بحث">Search</button>
                <?php if ($search): ?>
                    <a href="search_plate.php" class="btn-clear" data-en="Clear" data-ku="سڕینەوە" data-ar="مسح">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <p class="result-info">
            <span data-en="Showing" data-ku="پیشاندانی" data-ar="عرض">Showing</span>
            <strong><?php echo $total; ?></strong>
            <span data-en="students" data-ku="قوتابی" data-ar="طالب">students</span>
        </p>

        <?php if ($total === 0): ?>
            <div class="empty">
                <h3 data-en="No students found" data-ku="هیچ قوتابییەک نەدۆزرایەوە" data-ar="لا يوجد طلاب">No students found</h3>
            </div>
        <?php else: ?>
            <div class="table-card">
                <table>
                    <thead>
                        <tr>
                            <th data-en="Student" data-ku="قوتابی" data-ar="الطالب">Student</th>
                            <th data-en="Student ID" data-ku="ژمارەی قوتابی" data-ar="رقم الطالب">Student ID</th>
                            <th data-en="Stage" data-ku="قۆناغ" data-ar="المرحلة">Stage</th>
                            <th data-en="Class" data-ku="پۆل" data-ar="الصف">Class</th>
                            <th data-en="Field" data-ku="بەش" data-ar="التخصص">Field</th>
                            <th data-en="Plate Numbers" data-ku="ژمارەی ئۆتۆمبێل" data-ar="أرقام اللوحات">Plate Numbers</th>
                            <th data-en="Cars" data-ku="ئۆتۆمبێل" data-ar="سيارات">Cars</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($s = $students->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <a href="student_profile.php?id=<?php echo $s['id']; ?>" class="student-link">
                                        <span class="avatar-sm"><?php echo strtoupper(substr($s['full_name'], 0, 1)); ?></span>
                                        <span class="student-name"><?php echo htmlspecialchars($s['full_name']); ?></span>
                                    </a>
                                </td>
                                <td><span class="student-id"><?php echo htmlspecialchars($s['student_id']); ?></span></td>
                                <td><span class="stage-badge">Stage <?php echo intval($s['stage']); ?></span></td>
                                <td><span class="class-badge">Class <?php echo htmlspecialchars($s['class']); ?></span></td>
                                <td>
                                    <?php if (($s['stage'] == 4 || $s['stage'] == 5) && $s['track']): ?>
                                        <span class="track-badge track-<?php echo strtolower($s['track']); ?>"><?php echo htmlspecialchars($s['track']); ?></span>
                                    <?php else: ?>
                                        <span style="color: #94a3b8;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($s['plates']): 
                                        $plates = explode(', ', $s['plates']);
                                        foreach ($plates as $plate):
                                            $stmt = $conn->prepare("SELECT reason FROM blacklist WHERE plate_number = ? AND (expires_at IS NULL OR expires_at > NOW())");
                                            $stmt->bind_param("s", $plate);
                                            $stmt->execute();
                                            $bl = $stmt->get_result()->num_rows > 0;
                                    ?>
                                        <span class="plate-tag <?php echo $bl ? 'plate-blocked' : ''; ?>"><?php echo htmlspecialchars($plate); ?></span>
                                    <?php endforeach; else: ?>
                                        <span class="no-cars" data-en="No cars" data-ku="ئۆتۆمبێل نییە" data-ar="لا سيارات">No cars</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo intval($s['car_count']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>
    <script src="language.js?v=99"></script>
    <script>
    function toggleTheme() {
        var isDark = document.documentElement.classList.toggle('dark-mode');
        try { localStorage.setItem('theme', isDark ? 'dark' : 'light'); } catch(e) {}
    }
    </script>
</body>
</html>