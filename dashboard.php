<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include 'db.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard - Sardam Institute</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8fafc; color: #0f172a; font-size: 14px; }
        .sidebar { position: fixed; left: 0; top: 0; width: 250px; height: 100vh; background: #fff; border-right: 1px solid #e2e8f0; display: flex; flex-direction: column; }
        .sidebar-header { padding: 20px 18px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; gap: 12px; }
        .sidebar-header img { width: 40px; height: 40px; border-radius: 50%; }
        .sidebar-header .name { font-size: 13px; font-weight: 700; line-height: 1.3; color: #0f172a; }
        .sidebar-header .name span { display: block; font-weight: 400; color: #64748b; font-size: 10.5px; }
        .sidebar nav { flex: 1; padding: 16px 12px; overflow-y: auto; }
        .nav-label { font-size: 11px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; padding: 12px 12px 8px; }
        .sidebar nav a { display: flex; align-items: center; gap: 10px; padding: 10px 12px; color: #475569; text-decoration: none; font-size: 13.5px; font-weight: 500; border-radius: 8px; margin-bottom: 2px; transition: 0.15s; }
        .sidebar nav a:hover { background: #f1f5f9; color: #0f172a; }
        .sidebar nav a.active { background: #0f172a; color: #fff; }
        .sidebar nav a svg { width: 18px; height: 18px; flex-shrink: 0; }
        .main { margin-left: 250px; min-height: 100vh; }
        .topbar { background: #fff; border-bottom: 1px solid #e2e8f0; padding: 16px 32px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 10; }
        .topbar h1 { font-size: 18px; font-weight: 600; }
        .topbar .user { display: flex; align-items: center; gap: 14px; }
        .avatar { width: 36px; height: 36px; border-radius: 50%; background: #0f172a; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 14px; }
        .user-info .name { font-size: 13px; font-weight: 600; line-height: 1.2; }
        .user-info .role { font-size: 11.5px; color: #64748b; text-transform: capitalize; }
        .logout-btn { padding: 8px 14px; background: #fff; color: #475569; border: 1px solid #e2e8f0; text-decoration: none; border-radius: 8px; font-size: 13px; font-weight: 500; }
        .logout-btn:hover { background: #f1f5f9; color: #dc2626; border-color: #fecaca; }
        .content { padding: 32px; }
        .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 32px; }
        .stat { background: #fff; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0; }
        .stat-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
        .stat-label { font-size: 12.5px; color: #64748b; font-weight: 500; }
        .stat-icon { width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: #f1f5f9; }
        .stat-value { font-size: 26px; font-weight: 700; color: #0f172a; line-height: 1; }
        .stat-sub { font-size: 12px; color: #94a3b8; margin-top: 6px; }
        .section-title { font-size: 14px; font-weight: 600; margin-bottom: 14px; }
        .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
        .card { background: #fff; padding: 22px; border-radius: 12px; border: 1px solid #e2e8f0; text-decoration: none; color: inherit; transition: 0.15s; display: block; }
        .card:hover { border-color: #94a3b8; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06); }
        .card .icon-box { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-bottom: 14px; background: #f1f5f9; }
        .card h3 { font-size: 14px; font-weight: 600; margin-bottom: 4px; }
        .card p { font-size: 12.5px; color: #64748b; line-height: 1.5; }
        svg { stroke-linecap: round; stroke-linejoin: round; }
        .ic-dark { stroke: #0f172a; } .ic-blue { stroke: #2563eb; } .ic-green { stroke: #16a34a; }
        .ic-purple { stroke: #7c3aed; } .ic-orange { stroke: #ea580c; } .ic-red { stroke: #dc2626; } .ic-cyan { stroke: #0891b2; }
        @media (max-width: 1100px) { .stats { grid-template-columns: repeat(2, 1fr); } .grid { grid-template-columns: repeat(2, 1fr); } }
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
            <a href="dashboard.php" class="active">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12l9-9 9 9M5 10v10h14V10"/></svg>
                Dashboard
            </a>
            <div class="nav-label">Management</div>
            <a href="add_student.php">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 00-3-3.87"/></svg>
                Add Student
            </a>
            <a href="view_students.php">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>
                All Students
            </a>
            <div class="nav-label">Operations</div>
            <a href="search_plate.php">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg>
                Search Plate
            </a>
            <a href="view_logs.php">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h5"/></svg>
                Entry Logs
            </a>
            <a href="blacklist.php">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M4.93 4.93l14.14 14.14"/></svg>
                Blacklist
            </a>
        </nav>
    </aside>

    <main class="main">
        <div class="topbar">
            <h1>Dashboard</h1>
            <div class="user">
                <div class="avatar"><?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?></div>
                <div class="user-info">
                    <div class="name"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                    <div class="role"><?php echo htmlspecialchars($_SESSION['role']); ?></div>
                </div>
                <a href="logout.php" class="logout-btn">Sign out</a>
            </div>
        </div>

        <div class="content">
            <div class="stats">
                <div class="stat">
                    <div class="stat-top">
                        <div class="stat-label">Total Students</div>
                        <div class="stat-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" class="ic-blue" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
                    </div>
                    <div class="stat-value"><?php $r = $conn->query("SELECT COUNT(*) AS c FROM students"); echo $r->fetch_assoc()['c']; ?></div>
                    <div class="stat-sub">Registered in system</div>
                </div>
                <div class="stat">
                    <div class="stat-top">
                        <div class="stat-label">Registered Cars</div>
                        <div class="stat-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" class="ic-green" stroke-width="2"><path d="M5 17h14M5 17v-6l2-5h10l2 5v6"/><circle cx="7.5" cy="14.5" r="1.5"/><circle cx="16.5" cy="14.5" r="1.5"/></svg></div>
                    </div>
                    <div class="stat-value"><?php $r = $conn->query("SELECT COUNT(*) AS c FROM cars"); echo $r->fetch_assoc()['c']; ?></div>
                    <div class="stat-sub">Active vehicles</div>
                </div>
                <div class="stat">
                    <div class="stat-top">
                        <div class="stat-label">Entries Today</div>
                        <div class="stat-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" class="ic-purple" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></div>
                    </div>
                    <div class="stat-value"><?php $r = $conn->query("SELECT COUNT(*) AS c FROM logs WHERE DATE(entry_time) = CURDATE()"); echo $r->fetch_assoc()['c']; ?></div>
                    <div class="stat-sub">Since midnight</div>
                </div>
                <div class="stat">
                    <div class="stat-top">
                        <div class="stat-label">Blacklisted</div>
                        <div class="stat-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" class="ic-red" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M4.93 4.93l14.14 14.14"/></svg></div>
                    </div>
                    <div class="stat-value"><?php $r = $conn->query("SELECT COUNT(*) AS c FROM blacklist"); echo $r->fetch_assoc()['c']; ?></div>
                    <div class="stat-sub">Banned vehicles</div>
                </div>
            </div>

            <div class="section-title">Quick Actions</div>
            <div class="grid">
                <a href="add_student.php" class="card">
                    <div class="icon-box"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" class="ic-blue" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg></div>
                    <h3>Add Student</h3>
                    <p>Register a new student with their car</p>
                </a>
                <a href="search_plate.php" class="card">
                    <div class="icon-box"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" class="ic-purple" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/></svg></div>
                    <h3>Search Plate</h3>
                    <p>Identify a student from a car plate</p>
                </a>
                <a href="view_logs.php" class="card">
                    <div class="icon-box"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" class="ic-orange" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h5"/></svg></div>
                    <h3>Entry Logs</h3>
                    <p>Review all entry and exit records</p>
                </a>
                <a href="view_students.php" class="card">
                    <div class="icon-box"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" class="ic-cyan" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg></div>
                    <h3>All Students</h3>
                    <p>Browse and manage the student list</p>
                </a>
                <a href="blacklist.php" class="card">
                    <div class="icon-box"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" class="ic-red" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M4.93 4.93l14.14 14.14"/></svg></div>
                    <h3>Blacklist</h3>
                    <p>Manage banned or blocked vehicles</p>
                </a>
            </div>
        </div>
    </main>

</body>
</html>