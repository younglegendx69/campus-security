<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
if ($_SESSION['role'] != 'admin') { header("Location: guard_dashboard.php"); exit; }
include 'db.php';

$success = "";
$error = "";

function countByStage($conn, $stage) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM students WHERE stage = ?");
    $stmt->bind_param("i", $stage);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc()['c'];
}

$counts = [];
for ($i = 1; $i <= 5; $i++) $counts[$i] = countByStage($conn, $i);
$total = array_sum($counts);

// Fetch Stage 3 students (they need a field before Stage 4)
$stage3_students = [];
if ($counts[3] > 0) {
    $res = $conn->query("SELECT id, full_name, student_id, class FROM students WHERE stage = 3 ORDER BY class ASC, full_name ASC");
    while ($row = $res->fetch_assoc()) $stage3_students[] = $row;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_promote'])) {
    if ($total == 0) {
        $error = "No students to promote.";
    } else {
        $fields = $_POST['field'] ?? [];
        $missing = 0;
        foreach ($stage3_students as $s) {
            $f = $fields[$s['id']] ?? '';
            if (!in_array($f, ['Programming', 'Network'])) $missing++;
        }

        if ($missing > 0) {
            $error = "Please choose a Field for every Stage 3 student. Missing: $missing";
        } else {
            $conn->begin_transaction();
            try {
                // Step 1: Delete Stage 5 (graduates)
                $conn->query("DELETE FROM students WHERE stage = 5");

                // Step 2: Stage 4 -> Stage 5 (keep field)
                $conn->query("UPDATE students SET stage = 5 WHERE stage = 4");

                // Step 3: Stage 3 -> Stage 4 (with chosen field)
                $upd = $conn->prepare("UPDATE students SET stage = 4, track = ? WHERE id = ?");
                foreach ($stage3_students as $s) {
                    $field = $fields[$s['id']];
                    $upd->bind_param("si", $field, $s['id']);
                    $upd->execute();
                }

                // Step 4: Stage 2 -> Stage 3 (clear track)
                $conn->query("UPDATE students SET stage = 3, track = NULL WHERE stage = 2");

                // Step 5: Stage 1 -> Stage 2 (clear track)
                $conn->query("UPDATE students SET stage = 2, track = NULL WHERE stage = 1");

                $conn->commit();
                $success = "Promotion complete! Every student moved up one stage. Stage 5 graduates were deleted.";

                for ($i = 1; $i <= 5; $i++) $counts[$i] = countByStage($conn, $i);
                $total = array_sum($counts);
                $stage3_students = [];
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Promotion failed: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Promote Students - Sardam Institute</title>
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

        .warning-box { background: #fef3c7; color: #92400e; padding: 18px 22px; border-radius: 10px; font-size: 14px; margin-bottom: 24px; border: 1px solid #fde68a; line-height: 1.6; }
        .warning-box strong { color: #78350f; }

        .card { background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 28px; max-width: 1000px; margin-bottom: 20px; }
        .card h3 { font-size: 15px; font-weight: 700; margin-bottom: 18px; padding-bottom: 12px; border-bottom: 1px solid #f1f5f9; }

        .promo-list { list-style: none; padding: 0; }
        .promo-list li { display: flex; justify-content: space-between; align-items: center; padding: 14px 0; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        .promo-list li:last-child { border-bottom: none; }
        .promo-list .label { font-weight: 600; }
        .promo-from { color: #64748b; }
        .promo-to { color: #2563eb; font-weight: 600; }
        .promo-delete { color: #dc2626; font-weight: 600; }

        .total-row { display: flex; justify-content: space-between; font-size: 15px; padding-top: 16px; margin-top: 8px; border-top: 2px solid #f1f5f9; font-weight: 700; }

        .field-list { margin-top: 4px; }
        .field-item { display: grid; grid-template-columns: 50px 1fr 110px 90px 200px; gap: 12px; align-items: center; padding: 14px 0; border-bottom: 1px solid #f1f5f9; }
        .field-item:last-child { border-bottom: none; }
        .field-item .num { color: #94a3b8; font-size: 13px; font-weight: 600; }
        .field-item .name { font-weight: 600; color: #0f172a; }
        .field-item .sid {
            font-family: monospace;
            background: #f1f5f9;
            color: #0f172a;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11.5px;
            font-weight: 600;
            text-align: center;
            display: block;
        }
        .field-item .cl {
            background: #fef3c7;
            color: #92400e;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            text-align: center;
        }
        .field-item select {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-family: inherit;
            font-size: 13.5px;
            background: #fff;
            color: #0f172a;
        }
        .field-item select:focus { outline: none; border-color: #0f172a; }

        .helper-bar { display: flex; gap: 10px; margin-bottom: 12px; flex-wrap: wrap; }
        .helper-bar button {
            padding: 8px 16px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 12.5px;
            font-weight: 500;
            cursor: pointer;
            font-family: inherit;
            color: #475569;
        }
        .helper-bar button:hover { background: #e2e8f0; color: #0f172a; }

        .btn { background: #16a34a; color: #fff; padding: 15px 32px; border: none; border-radius: 10px; font-size: 15px; font-weight: 700; cursor: pointer; max-width: 1000px; width: 100%; }
        .btn:hover { background: #15803d; }

        .alert { padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .empty { text-align: center; padding: 40px; color: #94a3b8; font-size: 14px; }
        .empty-success { text-align: center; padding: 60px 20px; color: #166534; font-size: 15px; line-height: 1.8; }

        /* Dark mode fixes */
        html.dark-mode .field-item { border-bottom-color: #334155; }
        html.dark-mode .field-item .num { color: #94a3b8; }
        html.dark-mode .field-item .name { color: #e2e8f0; }
        html.dark-mode .field-item .sid { background: #334155 !important; color: #ffffff !important; }
        html.dark-mode .field-item .cl { background: #78350f; color: #fde68a; }
        html.dark-mode .field-item select { background: #0f172a; border-color: #475569; color: #ffffff; }
        html.dark-mode .helper-bar button { background: #334155; border-color: #475569; color: #e2e8f0; }
        html.dark-mode .helper-bar button:hover { background: #475569; color: #ffffff; }
        html.dark-mode .promo-list li { border-bottom-color: #334155; }
        html.dark-mode .total-row { border-top-color: #334155; }
        html.dark-mode .warning-box { background: #78350f !important; color: #fde68a !important; border-color: #92400e !important; }
        html.dark-mode .warning-box strong { color: #fcd34d !important; }
        html.dark-mode .empty-success { color: #86efac !important; }
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
            <a href="bulk_delete.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg> Bulk Delete</a>
            <a href="promote.php" class="active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19V5M5 12l7-7 7 7"/></svg> Promote Students</a>
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
        <h1 class="page-title">Promote Students</h1>
        <p class="page-sub">Advance every student exactly one stage. Stage 5 students graduate (deleted).</p>

        <?php if (!$success): ?>
        <div class="warning-box">
            ⚠️ <strong>Warning:</strong> This cannot be undone. Every student moves up <strong>exactly one stage</strong>:
            <br>• Stage 1 → 2
            <br>• Stage 2 → 3
            <br>• Stage 3 → 4 (assign Field below)
            <br>• Stage 4 → 5 (keep Field)
            <br>• Stage 5 → <strong>Deleted (Graduated)</strong>
            <br><br>
            <strong>Backup first!</strong> phpMyAdmin → campus_security → Export → Go.
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
            <div class="card" style="text-align: center;">
                <div class="empty-success">
                    ✅ <strong>Promotion Complete!</strong><br><br>
                    Every student moved up exactly ONE stage.<br>
                    Stage 5 graduates were deleted.<br><br>
                    <a href="promote.php" style="color: #2563eb;">Promote again</a>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($error) echo "<div class='alert alert-error'>$error</div>"; ?>

        <?php if (!$success && $total > 0): ?>
        <div class="card">
            <h3>📊 Preview — What Will Happen</h3>
            <ul class="promo-list">
                <li><span class="label promo-from">Stage 1</span><span><strong><?php echo $counts[1]; ?></strong> students</span><span class="promo-to">→ Stage 2</span></li>
                <li><span class="label promo-from">Stage 2</span><span><strong><?php echo $counts[2]; ?></strong> students</span><span class="promo-to">→ Stage 3</span></li>
                <li><span class="label promo-from">Stage 3</span><span><strong><?php echo $counts[3]; ?></strong> students</span><span class="promo-to">→ Stage 4 (assign Field below)</span></li>
                <li><span class="label promo-from">Stage 4</span><span><strong><?php echo $counts[4]; ?></strong> students</span><span class="promo-to">→ Stage 5 (keep Field)</span></li>
                <li><span class="label promo-from">Stage 5</span><span><strong><?php echo $counts[5]; ?></strong> students</span><span class="promo-delete">→ Graduate (Delete)</span></li>
            </ul>
            <div class="total-row"><span>Total students affected:</span><span><?php echo $total; ?></span></div>
        </div>
        <?php endif; ?>

        <?php if (!$success && count($stage3_students) > 0): ?>
        <form method="POST" onsubmit="return confirmPromote()">
            <div class="card">
                <h3>👥 Assign a Field to Each Stage 3 Student</h3>
                <p style="font-size: 13px; color: #64748b; margin-bottom: 16px;">
                    Every Stage 3 student moves to <strong>Stage 4</strong> and must have a Field. Choose each one individually.
                </p>

                <div class="helper-bar">
                    <button type="button" onclick="setAll('Programming')">Set All → Programming</button>
                    <button type="button" onclick="setAll('Network')">Set All → Network</button>
                    <button type="button" onclick="setAll('')">Clear All</button>
                </div>

                <div class="field-list">
                    <?php foreach ($stage3_students as $i => $s): ?>
                        <div class="field-item">
                            <div class="num">#<?php echo $i + 1; ?></div>
                            <div class="name"><?php echo htmlspecialchars($s['full_name']); ?></div>
                            <div class="sid"><?php echo htmlspecialchars($s['student_id']); ?></div>
                            <div class="cl">Class <?php echo htmlspecialchars($s['class']); ?></div>
                            <select name="field[<?php echo $s['id']; ?>]" class="field-select" required>
                                <option value="">-- Choose Field --</option>
                                <option value="Programming">Programming</option>
                                <option value="Network">Network</option>
                            </select>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <button type="submit" name="confirm_promote" class="btn">
                ✅ Confirm Promotion — Advance All Students
            </button>
        </form>
        <?php elseif (!$success && $total > 0): ?>
            <form method="POST" onsubmit="return confirmPromote()">
                <button type="submit" name="confirm_promote" class="btn">
                    ✅ Confirm Promotion — Advance All Students
                </button>
            </form>
        <?php elseif (!$success): ?>
            <div class="card">
                <div class="empty">No students in the system to promote.</div>
            </div>
        <?php endif; ?>
    </main>

    <script>
    function setAll(value) {
        document.querySelectorAll('.field-select').forEach(sel => sel.value = value);
    }
    function confirmPromote() {
        const selects = document.querySelectorAll('.field-select');
        let missing = 0;
        selects.forEach(s => { if (!s.value) missing++; });
        if (missing > 0) {
            alert('Please choose a Field for every Stage 3 student. Missing: ' + missing);
            return false;
        }
        return confirm('Are you SURE?\n\nEvery student moves up ONE stage.\nStage 5 students will be DELETED (graduated).\nThis cannot be undone.');
    }
    function toggleTheme() {
        var isDark = document.documentElement.classList.toggle('dark-mode');
        try { localStorage.setItem('theme', isDark ? 'dark' : 'light'); } catch(e) {}
    }
    </script>
</body>
</html>