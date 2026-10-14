<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guard') {
    header("Location: login.php");
    exit;
}
include 'db.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Guard Dashboard - Sardam Institute</title>
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
        .main { margin-left: 250px; min-height: 100vh; }
        .topbar { background: #fff; border-bottom: 1px solid #e2e8f0; padding: 16px 32px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 10; }
        .topbar h1 { font-size: 18px; font-weight: 600; }
        .topbar .user { display: flex; align-items: center; gap: 12px; }
        .avatar { width: 36px; height: 36px; border-radius: 50%; background: #0f172a; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 14px; }
        .user-info .name { font-size: 13px; font-weight: 600; line-height: 1.2; }
        .user-info .role { font-size: 11.5px; color: #64748b; text-transform: capitalize; }
        .logout-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; background: #fff; color: #dc2626; border: 1px solid #fecaca; text-decoration: none; border-radius: 8px; font-size: 13px; font-weight: 600; }
        .logout-btn:hover { background: #fef2f2; }
        .content { padding: 32px; }
        .welcome-box { background: linear-gradient(135deg, #0f172a 0%, #334155 100%); color: #fff; padding: 28px 32px; border-radius: 14px; margin-bottom: 30px; max-width: 800px; }
        .welcome-box h2 { font-size: 22px; margin-bottom: 8px; }
        .welcome-box p { font-size: 14px; color: #cbd5e1; }
        .page-title { font-size: 20px; font-weight: 700; margin-bottom: 6px; }
        .page-sub { color: #64748b; font-size: 14px; margin-bottom: 24px; }
        .grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; max-width: 700px; }
        .big-card { background: #fff; padding: 26px; border-radius: 14px; border: 1px solid #e2e8f0; text-decoration: none; color: inherit; transition: 0.15s; display: block; }
        .big-card:hover { border-color: #0f172a; transform: translateY(-2px); box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08); }
        .big-card .icon { width: 52px; height: 52px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 14px; background: #f1f5f9; }
        .big-card h2 { font-size: 15px; font-weight: 700; margin-bottom: 5px; }
        .big-card p { font-size: 12.5px; color: #64748b; line-height: 1.5; }
        .logout-card { background: #fff; padding: 26px; border-radius: 14px; border: 1px solid #fecaca; text-decoration: none; color: inherit; transition: 0.15s; display: block; }
        .logout-card:hover { border-color: #dc2626; background: #fef2f2; }
        .logout-card .icon { width: 52px; height: 52px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 14px; background: #fee2e2; }
        .logout-card h2 { font-size: 15px; font-weight: 700; margin-bottom: 5px; color: #dc2626; }
        .logout-card p { font-size: 12.5px; color: #64748b; line-height: 1.5; }
        svg { stroke-linecap: round; stroke-linejoin: round; }
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
            <div class="name">Sardam Institute<span data-en="Guard Panel" data-ku="پانێلی پاسەوان" data-ar="لوحة الحارس">Guard Panel</span></div>
        </div>
        <div style="padding: 10px 16px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; gap: 8px; flex-wrap: wrap;">
            <span style="font-size: 10px; font-weight: 600; color: #94a3b8; text-transform: uppercase;" data-en="Lang" data-ku="زمان" data-ar="لغة">Lang</span>
            <div style="display: flex; gap: 4px;">
                <button onclick="setLanguage('en')" style="background: #f1f5f9; border: 1px solid #e2e8f0; height: 26px; padding: 0 8px; border-radius: 5px; cursor: pointer; font-weight: 700; font-size: 10.5px; color: #475569; font-family: inherit;">EN</button>
                <button onclick="setLanguage('ku')" style="background: #f1f5f9; border: 1px solid #e2e8f0; height: 26px; padding: 0 8px; border-radius: 5px; cursor: pointer; font-weight: 700; font-size: 10.5px; color: #475569; font-family: inherit;">کو</button>
                <button onclick="setLanguage('ar')" style="background: #f1f5f9; border: 1px solid #e2e8f0; height: 26px; padding: 0 8px; border-radius: 5px; cursor: pointer; font-weight: 700; font-size: 10.5px; color: #475569; font-family: inherit;">ع</button>
                <button onclick="toggleTheme()" title="Theme" style="background: #f1f5f9; border: 1px solid #e2e8f0; width: 26px; height: 26px; border-radius: 5px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; padding: 0;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                </button>
            </div>
        </div>
        <nav>
            <div class="nav-label" data-en="Main" data-ku="سەرەکی" data-ar="الرئيسية">Main</div>
            <a href="guard_dashboard.php" class="active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12l9-9 9 9M5 10v10h14V10"/></svg> <span data-en="Dashboard" data-ku="پەڕەی کۆنترۆڵ" data-ar="لوحة التحكم">Dashboard</span></a>
            <div class="nav-label" data-en="Operations" data-ku="کارەکان" data-ar="العمليات">Operations</div>
            <a href="search_plate.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg> <span data-en="Search" data-ku="گەڕان" data-ar="البحث">Search</span></a>
            <a href="blacklist.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M4.93 4.93l14.14 14.14"/></svg> <span data-en="Blacklist" data-ku="لیستی قەدەغە کراوان" data-ar="القائمة السوداء">Blacklist</span></a>
            <div class="nav-label" data-en="Account" data-ku="هەژمار" data-ar="الحساب">Account</div>
            <a href="backup.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><path d="M7 10l5 5 5-5M12 15V3"/></svg> <span data-en="Backup" data-ku="پاشەکەوت" data-ar="نسخ احتياطي">Backup</span></a>
            <a href="logout.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg> <span data-en="Sign Out" data-ku="چوونەدەرەوە" data-ar="تسجيل الخروج">Sign Out</span></a>
        </nav>
    </aside>
    <main class="main">
        <div class="topbar">
            <h1 data-en="Guard Dashboard" data-ku="پەڕەی کۆنترۆڵی پاسەوان" data-ar="لوحة الحارس">Guard Dashboard</h1>
            <div class="user">
                <div class="avatar"><?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?></div>
                <div class="user-info">
                    <div class="name"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                    <div class="role"><?php echo htmlspecialchars($_SESSION['role']); ?></div>
                </div>
                <a href="logout.php" class="logout-btn">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                    <span data-en="Sign out" data-ku="چوونەدەرەوە" data-ar="خروج">Sign out</span>
                </a>
            </div>
        </div>
        <div class="content">
            <div class="welcome-box">
                <h2><span data-en="Welcome, Guard" data-ku="بەخێربێیت، پاسەوان" data-ar="مرحباً، حارس">Welcome, Guard</span> <?php echo htmlspecialchars($_SESSION['username']); ?></h2>
                <p data-en="Search plates and verify students." data-ku="گەڕان بۆ پلاک و پشتڕاستکردنەوەی قوتابیان." data-ar="ابحث عن اللوحات وتحقق من الطلاب.">Search plates and verify students.</p>
            </div>
            <h2 class="page-title" data-en="Quick Actions" data-ku="کارە خێراکان" data-ar="إجراءات سريعة">Quick Actions</h2>
            <p class="page-sub" data-en="Choose what to do" data-ku="هەڵبژێرە چی بکەیت" data-ar="اختر ما تريد القيام به">Choose what to do</p>
            <div class="grid">
                <a href="search_plate.php" class="big-card">
                    <div class="icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg></div>
                    <h2 data-en="Search Plate" data-ku="گەڕان بە پلاک" data-ar="البحث باللوحة">Search Plate</h2>
                    <p data-en="Identify a student by car plate." data-ku="قوتابی بناسەوە بە پلاکی ئۆتۆمبێل." data-ar="تعرف على الطالب من لوحة السيارة.">Identify a student by car plate.</p>
                </a>
                <a href="blacklist.php" class="big-card">
                    <div class="icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M4.93 4.93l14.14 14.14"/></svg></div>
                    <h2 data-en="View Blacklist" data-ku="بینینی لیستی قەدەغە کراوان" data-ar="عرض القائمة السوداء">View Blacklist</h2>
                    <p data-en="Check banned vehicles." data-ku="پشکنین بۆ ئۆتۆمبێلە قەدەغەکراوەکان." data-ar="تحقق من المركبات المحظورة.">Check banned vehicles.</p>
                </a>
                <a href="logout.php" class="logout-card">
                    <div class="icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg></div>
                    <h2 data-en="Sign Out" data-ku="چوونەدەرەوە" data-ar="تسجيل الخروج">Sign Out</h2>
                    <p data-en="Log out of your account." data-ku="لە هەژمارەکەت بچۆ دەرەوە." data-ar="اخرج من حسابك.">Log out of your account.</p>
                </a>
            </div>
        </div>
    </main>
    <script src="language.js"></script>
    <script>
    function toggleTheme() {
        var isDark = document.documentElement.classList.toggle('dark-mode');
        try { localStorage.setItem('theme', isDark ? 'dark' : 'light'); } catch(e) {}
    }
    </script>
</body>
</html>