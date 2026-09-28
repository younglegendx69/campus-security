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
        .page-sub { color: #64748b; font-size: 14px; margin-bottom: 30px; }
        .grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; max-width: 700px; }
        .big-card { background: #fff; padding: 32px; border-radius: 14px; border: 1px solid #e2e8f0; text-decoration: none; color: inherit; transition: 0.15s; }
        .big-card:hover { border-color: #0f172a; transform: translateY(-2px); box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08); }
        .big-card .icon { width: 56px; height: 56px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 16px; background: #f1f5f9; }
        .big-card h2 { font-size: 17px; font-weight: 700; margin-bottom: 6px; }
        .big-card p { font-size: 13px; color: #64748b; line-height: 1.5; }
        .welcome-box { background: linear-gradient(135deg, #0f172a 0%, #334155 100%); color: #fff; padding: 24px 28px; border-radius: 12px; margin-bottom: 30px; max-width: 700px; }
        .welcome-box h2 { font-size: 20px; margin-bottom: 6px; }
        .welcome-box p { font-size: 13.5px; color: #cbd5e1; }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="logo.png" alt="Logo">
            <div class="name">Sardam Institute<span>Guard Panel</span></div>
        </div>
        <nav>
            <div class="nav-label">Guard</div>
            <a href="guard_dashboard.php" class="active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12l9-9 9 9M5 10v10h14V10"/></svg> Dashboard</a>
            <a href="search_plate.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg> Search Plate</a>
            <a href="view_logs.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/></svg> Entry Logs</a>
            <a href="blacklist.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M4.93 4.93l14.14 14.14"/></svg> Blacklist (View)</a>
            <a href="logout.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg> Sign Out</a>
        </nav>
    </aside>
    <main class="main">
        <div class="welcome-box">
            <h2>Welcome, Guard <?php echo htmlspecialchars($_SESSION['username']); ?></h2>
            <p>Your job: search plates, verify students, and log entries at the gate.</p>
        </div>

        <h1 class="page-title">Quick Actions</h1>
        <p class="page-sub">Choose what you want to do</p>

        <div class="grid">
            <a href="search_plate.php" class="big-card">
                <div class="icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg></div>
                <h2>Search Plate</h2>
                <p>Enter a car plate number to identify the student and allow entry.</p>
            </a>
            <a href="view_logs.php" class="big-card">
                <div class="icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/></svg></div>
                <h2>Entry Logs</h2>
                <p>See all entry and exit records from today and past days.</p>
            </a>
            <a href="blacklist.php" class="big-card">
                <div class="icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M4.93 4.93l14.14 14.14"/></svg></div>
                <h2>View Blacklist</h2>
                <p>Check banned vehicles. Contact admin to add or remove entries.</p>
            </a>
            <a href="logout.php" class="big-card">
                <div class="icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg></div>
                <h2>Sign Out</h2>
                <p>Log out of your guard account.</p>
            </a>
        </div>
    </main>
</body>
</html>