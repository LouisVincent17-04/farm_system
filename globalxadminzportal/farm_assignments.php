<?php
// globalxadminzportal/farm_assignments.php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

require_once '../config/SadminConnection.php';

// ========================================================================
// INTERNAL AJAX HANDLERS
// ========================================================================
if (isset($_GET['action']) && $_GET['action'] === 'search_admin') {
    @ob_end_clean();
    header('Content-Type: application/json');
    $term = '%' . trim($_GET['term']) . '%';
    try {
        $stmt = $conn->prepare("SELECT user_id, full_name, email, phone_no FROM users WHERE full_name LIKE ? OR email LIKE ? OR phone_no LIKE ? LIMIT 5");
        $stmt->execute([$term, $term, $term]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch(Exception $e) { echo json_encode([]); }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    @ob_end_clean();
    header('Content-Type: application/json');

    try {
        if ($_POST['action'] === 'assign_user') {
            $farm_id = (int)$_POST['farm_id'];
            $name    = trim($_POST['full_name']);
            $email   = trim($_POST['email']);
            $phone   = trim($_POST['phone_no']);

            if (!$farm_id || empty($name) || empty($email)) {
                throw new Exception("Farm, Name, and Email are required.");
            }

            $conn->beginTransaction();

            $user_id = null;
            $new_password_plain = null;

            // Check if user already exists
            $check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
            $check->execute([$email]);
            $existing = $check->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $user_id = $existing['user_id'];
            } else {
                // Create new user
                $new_password_plain = substr(str_shuffle('abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789'), 0, 8);
                $hashed = password_hash($new_password_plain, PASSWORD_DEFAULT);

                $stmtNew = $conn->prepare("INSERT INTO users (full_name, email, phone_no, password, role, status) VALUES (?, ?, ?, ?, 'employee', 1)");
                $stmtNew->execute([$name, $email, $phone, $hashed]);
                $user_id = $conn->lastInsertId();
            }

            // Assign to Farm
            try {
                $stmtAssign = $conn->prepare("INSERT INTO assigned_farms (user_id, farm_id) VALUES (?, ?)");
                $stmtAssign->execute([$user_id, $farm_id]);
            } catch (PDOException $e) {
                if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    throw new Exception("This user is already assigned to this farm.");
                }
                throw $e;
            }

            $conn->commit();

            echo json_encode([
                'success'  => true,
                'message'  => 'User assigned successfully!',
                'new_user' => $new_password_plain !== null,
                'password' => $new_password_plain
            ]);
            exit;
        }

        if ($_POST['action'] === 'remove_assignment') {
            $assignment_id = (int)$_POST['assignment_id'];
            $stmt = $conn->prepare("DELETE FROM assigned_farms WHERE assignment_id = ?");
            $stmt->execute([$assignment_id]);
            echo json_encode(['success' => true, 'message' => 'Assignment removed.']);
            exit;
        }
    } catch (Exception $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}
// ========================================================================

// Fetch active farms for dropdown
$farms = $conn->query("SELECT farm_id, farm_name FROM farms WHERE farm_status = 1 ORDER BY farm_name")->fetchAll(PDO::FETCH_ASSOC);

$full_name = $_SESSION['full_name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farm Assignments | FarmPro Admin</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@400;500;600;700&family=DM+Mono&display=swap');

        :root {
            --bg: #07090f; --surface: #0d1117; --card: #111720;
            --border: #1c2535; --border2: #243045; --text: #c8d8ec;
            --muted: #455870; --accent: #3dd68c; --accent2: #07955a;
            --blue: #3b82f6; --red: #f05252; --nav-h: 64px;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; overflow-x: hidden; }

        body::before { content:''; position:fixed; inset:0; z-index:0; pointer-events:none; background: radial-gradient(ellipse 70% 50% at 15% 0%, rgba(61,214,140,.055) 0%, transparent 60%); }
        body::after { content:''; position:fixed; inset:0; z-index:0; pointer-events:none; background-image: linear-gradient(rgba(61,214,140,.018) 1px, transparent 1px), linear-gradient(90deg, rgba(61,214,140,.018) 1px, transparent 1px); background-size: 48px 48px; }

        .topnav { position: sticky; top: 0; z-index: 100; height: var(--nav-h); display: flex; align-items: center; justify-content: space-between; padding: 0 2rem; background: rgba(7,9,15,.85); backdrop-filter: blur(16px); border-bottom: 1px solid var(--border); }
        .nav-brand { font-family: 'Bebas Neue', sans-serif; font-size: 1.55rem; color: var(--accent); text-decoration: none; display: flex; align-items: center; gap: 10px; }
        .nav-links { display: flex; align-items: center; gap: 15px; }
        .nav-link { color: var(--muted); text-decoration: none; font-size: 0.85rem; font-weight: 600; padding: 6px 12px; border-radius: 8px; transition: 0.2s; }
        .nav-link:hover, .nav-link.active { color: var(--accent); background: rgba(61,214,140,.08); }

        .page-wrap { position: relative; z-index: 1; padding: calc(var(--nav-h) + 2rem) 2rem 3rem; max-width: 1200px; margin: 0 auto; }
        .page-title { font-family: 'Bebas Neue', sans-serif; font-size: 2.6rem; color: #fff; margin-bottom: 0.2rem; }
        .page-sub { color: var(--muted); margin-bottom: 2rem; }

        .table-wrap { background: var(--card); border: 1px solid var(--border2); border-radius: 16px; overflow: hidden; }
        .table-top { display: flex; justify-content: space-between; padding: 1.2rem 1.5rem; border-bottom: 1px solid var(--border); }
        .btn-new { background: linear-gradient(135deg, var(--accent), var(--accent2)); border: none; color: #0f172a; padding: 8px 16px; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; gap: 6px;}
        .btn-new:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(61,214,140,.2); }

        table { width: 100%; border-collapse: collapse; }
        th { padding: 1rem 1.5rem; text-align: left; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: var(--muted); border-bottom: 1px solid var(--border); background: rgba(255,255,255,.015); }
        td { padding: 1rem 1.5rem; font-size: 0.9rem; color: var(--text); border-bottom: 1px solid var(--border); }
        tr:hover { background: rgba(255,255,255,.02); }
        
        .farm-badge { background: rgba(61,214,140,.1); color: var(--accent); padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: bold; border: 1px solid rgba(61,214,140,.2); }
        .btn-remove { background: rgba(240,82,82,.1); color: var(--red); border: 1px solid rgba(240,82,82,.3); padding: 4px 10px; border-radius: 6px; cursor: pointer; font-size: 0.75rem; font-weight: bold; transition: 0.2s;}
        .btn-remove:hover { background: var(--red); color: #fff; }

        /* Modal & Form */
        .modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); backdrop-filter:blur(4px); z-index:1000; align-items:center; justify-content:center; padding:1rem; }
        .modal.show { display:flex; }
        .modal-content { background:var(--card); border-radius:16px; width:100%; max-width:450px; border:1px solid var(--border2); box-shadow:0 25px 50px -12px rgba(0,0,0,0.5); }
        .modal-header { padding:1.5rem; border-bottom:1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .modal-header h2 { margin:0; font-size:1.2rem; color:#fff; }
        .modal-close { background: none; border: none; color: var(--muted); cursor: pointer; font-size: 1.2rem; }
        .modal-body { padding:1.5rem; position: relative; }
        .modal-footer { padding:1.5rem; border-top:1px solid var(--border); display:flex; justify-content:flex-end; gap:10px; background: rgba(0,0,0,0.2); }
        
        .form-group { margin-bottom: 1.2rem; position: relative;}
        .form-label { display: block; font-size: .8rem; color: var(--muted); margin-bottom: .4rem; font-weight: 600;}
        .form-control { width: 100%; padding: 10px 12px; background: #0f172a; border: 1px solid var(--border); border-radius: 8px; color: #fff; font-size: .95rem; outline: none; transition: 0.2s;}
        .form-control:focus { border-color: var(--accent); }

        .ac-dropdown { position: absolute; top: 100%; left: 0; width: 100%; background: #0f172a; border: 1px solid var(--accent); border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); z-index: 1000; display: none; overflow: hidden; margin-top: 4px; }
        .ac-item { padding: 10px 15px; cursor: pointer; border-bottom: 1px solid var(--border); transition: 0.2s; }
        .ac-item:hover { background: rgba(52, 211, 153, 0.1); }
        .ac-name { font-weight: bold; color: #fff; font-size: 0.9rem; }
        .ac-email { font-size: 0.75rem; color: var(--muted); }

        .success-box { display: none; background: rgba(59, 130, 246, 0.1); border-left: 4px solid var(--blue); padding: 1rem; border-radius: 8px; margin-top: 1rem; }
        .pass-text { font-family: 'DM Mono', monospace; font-size: 1.1rem; color: #fff; background: var(--bg); padding: 4px 10px; border-radius: 4px; font-weight: bold; letter-spacing: 1px;}
    </style>
</head>
<body>

<nav class="topnav">
    <a href="farm_page.php" class="nav-brand">Farm<span>Pro</span> Admin</a>
    <div class="nav-links">
        <a href="farm_page.php" class="nav-link">Dashboard</a>
        <a href="create_client_farm.php" class="nav-link">Create Farm</a>
        <a href="farm_assignments.php" class="nav-link active">Assignments</a>
        <a href="logout.php" class="nav-link" style="color:var(--red);">Logout</a>
    </div>
</nav>

<main class="page-wrap">
    <h1 class="page-title">Farm Assignments</h1>
    <p class="page-sub">Manage which admins and users have access to which farms.</p>

    <div class="table-wrap">
        <div class="table-top">
            <h3 style="color:#fff; font-size:1rem; margin:0;">Assigned Users</h3>
            <button class="btn-new" onclick="openModal()">+ Assign User</button>
        </div>

        <table>
            <thead>
                <tr>
                    <th>User / Email</th>
                    <th>Assigned Farm</th>
                    <th>Assigned Date</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $assignments = $conn->query("
                SELECT af.assignment_id, af.assigned_at, a.full_name, a.email, f.farm_name
                FROM assigned_farms af
                JOIN admin_users a ON a.admin_id = af.admin_id
                JOIN farms f ON f.farm_id = af.farm_id
                ORDER BY af.assigned_at DESC
            ")->fetchAll();

            if(empty($assignments)): ?>
                <tr><td colspan="4" style="text-align:center; padding: 2rem; color:var(--muted);">No assignments found.</td></tr>
            <?php else: foreach($assignments as $row): ?>
                <tr>
                    <td>
                        <div style="font-weight:bold; color:#fff;"><?= htmlspecialchars($row['full_name']) ?></div>
                        <div style="font-size:0.75rem; color:var(--muted);"><?= htmlspecialchars($row['email']) ?></div>
                    </td>
                    <td><span class="farm-badge"><?= htmlspecialchars($row['farm_name']) ?></span></td>
                    <td><?= date('M j, Y h:i A', strtotime($row['assigned_at'])) ?></td>
                    <td style="text-align: right;">
                        <button class="btn-remove" onclick="removeAssignment(<?= $row['assignment_id'] ?>)">Remove</button>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</main>

<div id="assignModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Assign User to Farm</h2>
            <button class="modal-close" onclick="closeModal()">✕</button>
        </div>
        <div class="modal-body">
            <form id="assignForm" onsubmit="submitAssignment(event)">
                <input type="hidden" name="action" value="assign_user">
                
                <div class="form-group">
                    <label class="form-label">Select Farm</label>
                    <select name="farm_id" class="form-control" required>
                        <option value="">-- Choose Farm --</option>
                        <?php foreach($farms as $f): ?>
                            <option value="<?= $f['farm_id'] ?>"><?= htmlspecialchars($f['farm_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="margin: 1.5rem 0 1rem; border-top: 1px solid var(--border2); padding-top: 1rem;">
                    <span style="font-size: 0.75rem; color: var(--muted); text-transform: uppercase; letter-spacing: 1px; font-weight: bold;">User Details (Search or Create)</span>
                </div>

                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" id="assign_name" name="full_name" class="form-control" placeholder="Search or type new..." required oninput="triggerAutocomplete(this)">
                    <div class="ac-dropdown"></div>
                </div>

                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" id="assign_email" name="email" class="form-control" required oninput="triggerAutocomplete(this)">
                    <div class="ac-dropdown"></div>
                </div>

                <div class="form-group">
                    <label class="form-label">Phone No (Optional)</label>
                    <input type="text" id="assign_phone" name="phone_no" class="form-control" oninput="triggerAutocomplete(this)">
                    <div class="ac-dropdown"></div>
                </div>

                <div id="newPasswordBox" class="success-box">
                    <div style="font-size:0.8rem; font-weight:bold; color:var(--blue); margin-bottom:5px;">New User Created! App Password:</div>
                    <span id="gen_password" class="pass-text"></span>
                    <div style="font-size:0.7rem; color:var(--muted); margin-top:5px;">Copy this now. It will not be shown again.</div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 1.5rem;">
                    <button type="button" class="btn-new" style="background:transparent; border:1px solid var(--border); color:var(--text);" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-new" id="btnAssign">Assign User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // --- Autocomplete ---
    let acTimer = null;
    function triggerAutocomplete(inputEl) {
        clearTimeout(acTimer);
        const val = inputEl.value.trim();
        const dropdown = inputEl.nextElementSibling;
        document.querySelectorAll('.ac-dropdown').forEach(d => d.style.display = 'none');
        if (val.length < 2) return;

        acTimer = setTimeout(async () => {
            try {
                const res = await fetch(`?action=search_admin&term=${encodeURIComponent(val)}`);
                const data = await res.json();
                if (data.length > 0) {
                    let html = '';
                    data.forEach(item => {
                        const safeName = item.full_name ? item.full_name.replace(/'/g, "\\'") : '';
                        const safeEmail = item.email ? item.email.replace(/'/g, "\\'") : '';
                        const safePhone = item.phone_no ? item.phone_no.replace(/'/g, "\\'") : '';
                        html += `
                        <div class="ac-item" onclick="fillAdmin('${safeName}', '${safeEmail}', '${safePhone}')">
                            <div class="ac-name">${item.full_name}</div>
                            <div class="ac-email">${item.email}</div>
                        </div>`;
                    });
                    dropdown.innerHTML = html;
                    dropdown.style.display = 'block';
                }
            } catch(e) {}
        }, 300);
    }

    function fillAdmin(name, email, phone) {
        document.getElementById('assign_name').value = name;
        document.getElementById('assign_email').value = email;
        document.getElementById('assign_phone').value = phone;
        document.querySelectorAll('.ac-dropdown').forEach(d => d.style.display = 'none');
    }

    document.addEventListener('click', (e) => {
        if (!e.target.closest('.ac-dropdown') && !e.target.closest('.form-control')) {
            document.querySelectorAll('.ac-dropdown').forEach(d => d.style.display = 'none');
        }
    });

    // --- Modal & Submit ---
    function openModal() {
        document.getElementById('assignForm').reset();
        document.getElementById('newPasswordBox').style.display = 'none';
        document.getElementById('btnAssign').style.display = 'inline-flex';
        document.getElementById('assignModal').classList.add('show');
    }

    function closeModal() {
        document.getElementById('assignModal').classList.remove('show');
        if(document.getElementById('newPasswordBox').style.display === 'block') {
            window.location.reload(); // Reload if we successfully saved earlier but didn't refresh
        }
    }

    function submitAssignment(e) {
        e.preventDefault();
        const form = document.getElementById('assignForm');
        const fd = new FormData(form);
        const btn = document.getElementById('btnAssign');
        btn.disabled = true; btn.textContent = 'Saving...';

        fetch(window.location.href, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if(data.success) {
                    if(data.new_user) {
                        // Keep modal open to show password
                        document.getElementById('gen_password').textContent = data.password;
                        document.getElementById('newPasswordBox').style.display = 'block';
                        btn.style.display = 'none'; // hide assign button so they can't double click
                    } else {
                        window.location.reload();
                    }
                } else {
                    alert("Error: " + data.message);
                    btn.disabled = false; btn.textContent = 'Assign User';
                }
            }).catch(err => {
                alert("System Error Occurred.");
                btn.disabled = false; btn.textContent = 'Assign User';
            });
    }

    // --- Remove Assignment ---
    function removeAssignment(id) {
        if(!confirm("Remove this user from the farm?")) return;
        const fd = new FormData();
        fd.append('action', 'remove_assignment');
        fd.append('assignment_id', id);

        fetch(window.location.href, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if(data.success) window.location.reload();
                else alert("Error: " + data.message);
            });
    }
</script>
</body>
</html>