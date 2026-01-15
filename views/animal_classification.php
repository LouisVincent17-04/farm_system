<?php
// views/manage_animal_classes.php
$page = "farm";
include '../common/navbar.php';
include '../config/Connection.php';
include '../security/checkRole.php';    
checkRole(2);

try {
    $sql = "SELECT * FROM animal_classifications ORDER BY MIN_DAYS ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Encode for JS to use in validation
    $jsonClasses = json_encode($classes);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Animal Class Management</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            color: #e2e8f0;
            min-height: 100vh;
            padding-bottom: 3rem;
            position: relative;
            overflow-x: hidden;
        }
        
        /* Animated background effects */
        body::before {
            content: '';
            position: fixed;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.05) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: backgroundScroll 20s linear infinite;
            pointer-events: none;
        }
        
        @keyframes backgroundScroll {
            0% { transform: translate(0, 0); }
            100% { transform: translate(50px, 50px); }
        }
        
        .container { 
            max-width: 1400px; 
            margin: 0 auto; 
            padding: 2rem 1.5rem;
            position: relative;
            z-index: 1;
        }
        
        /* Header with enhanced styling */
        .page-header { 
            text-align: center; 
            margin-bottom: 3rem;
            animation: fadeInDown 0.6s ease-out;
        }
        
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .page-title {
            font-size: 3rem; 
            font-weight: 800; 
            margin-bottom: 0.75rem;
            background: linear-gradient(135deg, #60a5fa, #3b82f6, #2563eb);
            -webkit-background-clip: text; 
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.02em;
            position: relative;
            display: inline-block;
        }
        
        .page-title::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 60%;
            height: 3px;
            background: linear-gradient(90deg, transparent, #3b82f6, transparent);
            border-radius: 2px;
        }
        
        .page-subtitle { 
            color: #94a3b8; 
            font-size: 1.15rem;
            font-weight: 400;
            max-width: 600px;
            margin: 0 auto;
        }
        
        /* Info banner */
        .info-banner {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(37, 99, 235, 0.05));
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            animation: fadeIn 0.8s ease-out 0.2s both;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .info-icon {
            width: 24px;
            height: 24px;
            background: #3b82f6;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-weight: bold;
            color: white;
        }
        
        .info-text {
            color: #cbd5e1;
            font-size: 0.95rem;
            line-height: 1.6;
        }

        /* Table Container with enhanced glass effect and scroll */
        .table-container {
            background: rgba(30, 41, 59, 0.4);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            border: 1px solid rgba(59, 130, 246, 0.2);
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3),
                        0 0 0 1px rgba(255, 255, 255, 0.05) inset;
            animation: fadeIn 0.8s ease-out 0.4s both;
        }

        /* SCROLL WRAPPER FOR MOBILE */
        .table-scroll-wrapper {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch; /* smooth scrolling iOS */
        }
        
        .table { 
            width: 100%; 
            border-collapse: collapse;
            min-width: 800px; /* Force table width so it scrolls on small screens */
        }
        
        .table thead {
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.8), rgba(30, 41, 59, 0.6));
            border-bottom: 2px solid rgba(59, 130, 246, 0.3);
        }
        
        .table th { 
            padding: 1.5rem 1.75rem;
            text-align: left;
            color: #93c5fd;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap; /* Prevent header wrapping */
        }
        
        .table tbody tr {
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            transition: all 0.3s ease;
        }
        
        .table tbody tr:hover {
            background: rgba(59, 130, 246, 0.05);
            transform: scale(1.01);
        }
        
        .table tbody tr:last-child {
            border-bottom: none;
        }
        
        .table td { 
            padding: 1.5rem 1.75rem;
            color: #cbd5e1;
            font-size: 0.95rem;
        }
        
        /* Stage name badge */
        .stage-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.15), rgba(37, 99, 235, 0.1));
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 8px;
            font-weight: 600;
            color: #fff;
            white-space: nowrap;
        }
        
        .stage-icon {
            width: 8px;
            height: 8px;
            background: #3b82f6;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        /* Days range with enhanced styling */
        .days-range {
            font-family: 'Monaco', 'Courier New', monospace;
            font-weight: 700;
            color: #fff;
            background: rgba(59, 130, 246, 0.1);
            padding: 0.35rem 0.75rem;
            border-radius: 6px;
            border: 1px solid rgba(59, 130, 246, 0.2);
            display: inline-block;
        }
        
        /* Sex badge */
        .sex-badge {
            padding: 0.35rem 0.85rem;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .sex-badge.male {
            background: rgba(59, 130, 246, 0.15);
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }
        
        .sex-badge.female {
            background: rgba(236, 72, 153, 0.15);
            color: #f9a8d4;
            border: 1px solid rgba(236, 72, 153, 0.3);
        }
        
        .sex-badge.any {
            background: rgba(148, 163, 184, 0.15);
            color: #94a3b8;
            border: 1px solid rgba(148, 163, 184, 0.3);
        }
        
        /* Enhanced button */
        .btn-edit {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            border: none;
            padding: 0.65rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
            position: relative;
            overflow: hidden;
            white-space: nowrap;
        }
        
        .btn-edit::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn-edit:hover::before {
            left: 100%;
        }
        
        .btn-edit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
        }
        
        .btn-edit:active {
            transform: translateY(0);
        }
        
        /* Modal with enhanced backdrop */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(8px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            animation: modalFadeIn 0.3s ease-out;
            padding: 1rem; /* Padding for mobile */
        }
        
        @keyframes modalFadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .modal.show { display: flex; }
        
        .modal-content {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 16px;
            width: 100%;
            max-width: 550px;
            border: 1px solid rgba(59, 130, 246, 0.3);
            padding: 2.5rem;
            box-shadow: 0 25px 75px rgba(0, 0, 0, 0.5);
            animation: modalSlideIn 0.4s ease-out;
            position: relative;
            max-height: 90vh; /* Prevent modal from going off screen */
            overflow-y: auto; /* Scroll inside modal if too tall */
        }
        
        @keyframes modalSlideIn {
            from { opacity: 0; transform: translateY(-30px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        
        .modal-content h2 {
            margin-bottom: 1.5rem;
            color: #fff;
            font-size: 1.75rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .modal-content h2::before {
            content: '';
            width: 4px;
            height: 28px;
            background: linear-gradient(180deg, #3b82f6, #2563eb);
            border-radius: 2px;
        }
        
        /* Alert box */
        #modalAlert {
            display: none;
            padding: 1rem 1.25rem;
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid #ef4444;
            color: #fca5a5;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            animation: shake 0.5s;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        /* Form styling */
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            color: #94a3b8;
            margin-bottom: 0.5rem;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .form-group input {
            width: 100%;
            padding: 0.875rem 1rem;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(51, 65, 85, 0.8);
            border-radius: 8px;
            color: white;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #3b82f6;
            background: rgba(15, 23, 42, 0.8);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .form-group input:read-only {
            background: rgba(30, 41, 59, 0.4);
            color: #64748b;
            cursor: not-allowed;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        /* Modal footer buttons */
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            margin-top: 2rem;
        }
        
        .btn-save {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            border: none;
            color: white;
            padding: 0.75rem 1.75rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }
        
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.4);
        }
        
        .btn-save:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-cancel {
            background: transparent;
            border: 1px solid #475569;
            color: #cbd5e1;
            padding: 0.75rem 1.75rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-cancel:hover {
            background: rgba(71, 85, 105, 0.2);
            border-color: #64748b;
        }
        
        /* Responsive Design Adjustments */
        @media (max-width: 768px) {
            .page-title { font-size: 2rem; }
            .page-subtitle { font-size: 1rem; }
            .container { padding: 1.5rem 1rem; }
            
            /* Stack form grid on mobile */
            .form-grid { grid-template-columns: 1fr; }
            
            .modal-content { padding: 1.5rem; }
            .modal-footer { flex-direction: column-reverse; } /* Stack buttons, cancel at bottom */
            .btn-save, .btn-cancel { width: 100%; text-align: center; }
            
            /* Table adjustments handled by scroll wrapper */
            .table th, .table td { padding: 1rem 1.25rem; font-size: 0.9rem; }
        }
    </style>
</head>
<body>

<div class="container">
    <header class="page-header">
        <h1 class="page-title">Animal Classification Rules</h1>
        <p class="page-subtitle">Define non-overlapping age ranges for automatic classification and monitoring.</p>
    </header>

    <div class="info-banner">
        <div class="info-icon">ℹ</div>
        <div class="info-text">
            Classification rules automatically categorize animals based on their age in days. Ensure ranges don't overlap to maintain data integrity.
        </div>
    </div>

    <div class="table-container">
        <div class="table-scroll-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Stage Name</th>
                        <th>Start Day</th>
                        <th>End Day</th>
                        <th>Required Sex</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($classes as $row): ?>
                    <tr>
                        <td>
                            <div class="stage-badge">
                                <span class="stage-icon"></span>
                                <?php echo htmlspecialchars($row['STAGE_NAME']); ?>
                            </div>
                        </td>
                        <td><span class="days-range"><?php echo $row['MIN_DAYS']; ?></span></td>
                        <td><span class="days-range"><?php echo $row['MAX_DAYS']; ?></span></td>
                        <td>
                            <?php 
                            $sexClass = !$row['REQUIRED_SEX'] ? 'any' : ($row['REQUIRED_SEX']=='M' ? 'male' : 'female');
                            $sexLabel = !$row['REQUIRED_SEX'] ? 'Any' : ($row['REQUIRED_SEX']=='M' ? 'Male' : 'Female');
                            ?>
                            <span class="sex-badge <?php echo $sexClass; ?>"><?php echo $sexLabel; ?></span>
                        </td>
                        <td style="text-align: right;">
                            <button class="btn-edit" onclick="openEditModal(
                                <?php echo $row['CLASS_ID']; ?>, 
                                '<?php echo addslashes($row['STAGE_NAME']); ?>', 
                                <?php echo $row['MIN_DAYS']; ?>, 
                                <?php echo $row['MAX_DAYS']; ?>,
                                <?php echo $row['FCR']; ?>
                            )">✏ Edit</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="editModal" class="modal">
    <div class="modal-content">
        <h2>Edit Classification</h2>
        <div id="modalAlert"></div>
        
        <form id="editForm">
            <input type="hidden" id="class_id" name="class_id">
            
            <div class="form-group">
                <label>Stage Name</label>
                <input type="text" id="stage_name" readonly>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Start Day (Min)</label>
                    <input type="number" id="min_days" name="min_days" required min="0">
                </div>
                <div class="form-group">
                    <label>End Day (Max)</label>
                    <input type="number" id="max_days" name="max_days" required min="1">
                </div>
            </div>

            <div class="form-group">
                <label>Target FCR</label>
                <input type="number" id="fcr" name="fcr" step="0.01" min="0">
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn-save">💾 Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
    const existingClasses = <?php echo $jsonClasses; ?>;
    const modal = document.getElementById('editModal');
    const alertBox = document.getElementById('modalAlert');

    function openEditModal(id, name, min, max, fcr) {
        document.getElementById('class_id').value = id;
        document.getElementById('stage_name').value = name;
        document.getElementById('min_days').value = min;
        document.getElementById('max_days').value = max;
        document.getElementById('fcr').value = fcr;
        alertBox.style.display = 'none';
        modal.classList.add('show');
    }

    function closeModal() {
        modal.classList.remove('show');
    }

    document.getElementById('editForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const currentId = parseInt(document.getElementById('class_id').value);
        const newMin = parseInt(document.getElementById('min_days').value);
        const newMax = parseInt(document.getElementById('max_days').value);

        if (newMin >= newMax) {
            showAlert("⚠️ Start Day must be less than End Day.");
            return;
        }

        let hasConflict = false;
        
        for (const cls of existingClasses) {
            if (parseInt(cls.CLASS_ID) === currentId) continue;

            const existingMin = parseInt(cls.MIN_DAYS);
            const existingMax = parseInt(cls.MAX_DAYS);

            if (newMin <= existingMax && newMax >= existingMin) {
                showAlert(`⚠️ Conflict! This range overlaps with "${cls.STAGE_NAME}" (${existingMin}-${existingMax} days).`);
                hasConflict = true;
                break;
            }
        }

        if (hasConflict) return;

        const formData = new FormData(this);
        const btn = document.querySelector('.btn-save');
        const originalText = btn.innerHTML;
        
        btn.innerHTML = "⏳ Updating...";
        btn.disabled = true;

        fetch('../process/updateAnimalClass.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert("✅ Rules updated and animals re-classified!");
                window.location.reload();
            } else {
                showAlert("❌ Server Error: " + data.message);
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        })
        .catch(err => {
            showAlert("❌ System Error occurred.");
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    });

    function showAlert(msg) {
        alertBox.textContent = msg;
        alertBox.style.display = 'block';
    }

    modal.addEventListener('click', (e) => { 
        if (e.target === modal) closeModal(); 
    });
</script>

</body>
</html>