<?php
// views/animal_classification.php
$page = "farm";
include '../config/Connection.php';

include '../security/checkAccess.php';
checkAccess('animal_class');
include '../common/navbar.php'; 
include '../common/chat_support.php';

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Classification Rules | FarmPro</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />

    <style>
        /* ─── CSS VARIABLES ─── */
        :root {
            --bg-base:        #080f1a;
            --bg-surface:     #0d1829;
            --bg-elevated:    #111f35;
            --bg-hover:       #162540;
            --border:         rgba(255,255,255,0.07);
            --border-active:  rgba(59,130,246,0.5); /* Blue Accent */
            --primary:        #3b82f6;
            --primary-dim:    rgba(59,130,246,0.12);
            --primary-glow:   rgba(59,130,246,0.25);
            --pink:           #f472b6;
            --pink-dim:       rgba(244,114,182,0.12);
            --text-primary:   #f1f5f9;
            --text-secondary: #94a3b8;
            --text-muted:     #475569;
            --radius-md:      10px;
            --radius-lg:      14px;
            --radius-xl:      20px;
            --font:           'DM Sans', system-ui, sans-serif;
            --font-mono:      'DM Mono', monospace;
            --transition:     0.18s cubic-bezier(0.4,0,0.2,1);
        }

        /* ─── RESET & BASE ─── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: var(--font);
            background: var(--bg-base);
            color: var(--text-primary);
            min-height: 100vh;
            padding-bottom: 60px;
            background-image: radial-gradient(ellipse 80% 50% at 50% -20%, rgba(59,130,246,0.06) 0%, transparent 60%);
        }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem 1.5rem; }

        /* ─── TOP BAR ─── */
        .top-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 2rem; }
        .back-link {
            display: inline-flex; align-items: center; gap: 8px; text-decoration: none;
            color: var(--text-secondary); font-size: 0.875rem; font-weight: 500;
            padding: 8px 14px; background: var(--bg-elevated); border: 1px solid var(--border);
            border-radius: var(--radius-md); transition: all var(--transition);
        }
        .back-link:hover { color: var(--text-primary); border-color: var(--border-active); background: var(--bg-hover); }

        .page-badge {
            display: inline-flex; align-items: center; gap: 6px; font-size: 0.75rem;
            font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase;
            color: var(--primary); background: var(--primary-dim); border: 1px solid rgba(59,130,246,0.2);
            padding: 6px 12px; border-radius: 99px;
        }

        /* ─── HEADER ─── */
        .page-header { margin-bottom: 2.5rem; }
        .page-title {
            font-size: clamp(1.6rem, 3vw, 2.2rem); font-weight: 700;
            color: var(--text-primary); letter-spacing: -0.03em; line-height: 1.1; margin-bottom: 0.25rem;
        }
        .page-title span {
            background: linear-gradient(135deg, var(--primary), #2563eb);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .page-subtitle { color: var(--text-secondary); font-size: 0.95rem; }

        /* ─── INFO BANNER ─── */
        .info-banner {
            background: var(--primary-dim); border: 1px solid rgba(59,130,246,0.2);
            border-radius: var(--radius-lg); padding: 1.25rem; margin-bottom: 2rem;
            display: flex; align-items: center; gap: 1rem;
        }
        .info-icon { color: var(--primary); font-size: 1.2rem; }
        .info-text { color: var(--text-primary); font-size: 0.9rem; line-height: 1.5; font-weight: 500; }

        /* ─── TABLE ─── */
        .table-card {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); overflow: hidden;
        }
        .table-wrap { overflow-x: auto; }
        .table { width: 100%; border-collapse: collapse; min-width: 900px; }
        .table thead th {
            background: var(--bg-elevated); color: var(--text-muted);
            font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.07em; padding: 14px 16px; text-align: left;
            border-bottom: 1px solid var(--border);
        }
        .table tbody tr { border-bottom: 1px solid var(--border); transition: background var(--transition); }
        .table tbody tr:last-child { border-bottom: none; }
        .table tbody tr:hover { background: rgba(255,255,255,0.02); }
        .table td { padding: 14px 16px; font-size: 0.9rem; color: var(--text-primary); vertical-align: middle; }

        /* Badges & Ranges */
        .stage-badge {
            display: inline-flex; align-items: center; gap: 8px; font-weight: 700;
            color: #fff; background: var(--bg-elevated); padding: 6px 12px;
            border-radius: 8px; border: 1px solid var(--border);
        }
        .stage-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--primary); box-shadow: 0 0 8px var(--primary-glow); }
        
        .days-range { 
            font-family: var(--font-mono); font-weight: 600; font-size: 0.85rem;
            color: var(--primary); background: var(--primary-dim); 
            padding: 4px 10px; border-radius: 6px; 
        }

        .sex-badge {
            padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;
        }
        .sex-badge.male   { background: var(--primary-dim); color: var(--primary); }
        .sex-badge.female { background: var(--pink-dim); color: var(--pink); }
        .sex-badge.any    { background: rgba(255,255,255,0.05); color: var(--text-muted); }

        /* Actions */
        .action-btn {
            background: var(--bg-elevated); border: 1px solid var(--border);
            color: var(--primary); padding: 8px 16px; border-radius: 8px;
            font-size: 0.85rem; font-weight: 600; cursor: pointer;
            transition: all var(--transition);
        }
        .action-btn:hover { background: var(--primary); color: #000; box-shadow: 0 0 16px var(--primary-glow); transform: translateY(-1px); }

        /* ─── MODALS ─── */
        .modal {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85);
            backdrop-filter: blur(5px); z-index: 1000; align-items: center; justify-content: center;
            padding: 1rem;
        }
        .modal.show { display: flex; }
        .modal-content {
            background: var(--bg-surface); border: 1px solid var(--border);
            border-radius: var(--radius-xl); width: 100%; max-width: 480px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); overflow: hidden;
        }
        .modal-header { padding: 1.5rem; border-bottom: 1px solid var(--border); }
        .modal-header h2 { margin: 0; font-size: 1.25rem; font-weight: 700; color: #fff; }
        .modal-body { padding: 1.5rem; }
        .modal-footer { padding: 1.25rem 1.5rem; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 10px; background: var(--bg-elevated); }

        .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 1.25rem; }
        .form-label { font-size: 0.72rem; font-weight: 600; text-transform: uppercase; color: var(--text-secondary); letter-spacing: 0.05em; }
        .form-control {
            width: 100%; padding: 10px 12px; background: var(--bg-elevated); border: 1px solid var(--border);
            color: var(--text-primary); border-radius: 8px; font-size: 0.95rem; font-family: var(--font);
            outline: none; transition: all var(--transition);
        }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-glow); }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }

        .alert { 
            display: none; padding: 12px 16px; border-radius: 8px; margin-bottom: 1.5rem; 
            font-size: 0.85rem; font-weight: 600; border: 1px solid rgba(239, 68, 68, 0.3);
            background: rgba(239, 68, 68, 0.1); color: #fca5a5;
        }

        .btn-modal {
            padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s;
        }
        .btn-save { background: var(--primary); color: #000; border: none; }
        .btn-save:hover { background: #60a5fa; transform: translateY(-1px); }
        .btn-cancel { background: transparent; color: var(--text-secondary); border: 1px solid var(--border); }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 768px) {
            .page-header { text-align: left; }
            .info-banner { flex-direction: column; align-items: flex-start; }
            .table-wrap { border: none; background: transparent; }
            .table, .table thead, .table tbody, .table th, .table td, .table tr { display: block; }
            .table thead { display: none; }
            .table tbody tr { 
                background: var(--bg-surface); border: 1px solid var(--border); 
                border-radius: var(--radius-xl); margin-bottom: 1rem; padding: 1.25rem;
            }
            .table td { 
                display: flex; justify-content: space-between; align-items: center; 
                padding: 0.75rem 0; border-bottom: 1px solid rgba(255,255,255,0.05); text-align: right;
            }
            .table td:last-child { border-bottom: none; justify-content: flex-end; padding-top: 1rem; }
            .table td::before { 
                content: attr(data-label); font-weight: 700; color: var(--text-muted); 
                font-size: 0.75rem; text-transform: uppercase; text-align: left;
            }
        }
    </style>
</head>
<body>

<div class="container">
    
    <div class="top-bar">
        <a href="farm_dashboard.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Back to Farm Dashboard
        </a>
        <span class="page-badge"><i class="fa-solid fa-gears"></i> Operational Rules</span>
    </div>

    <header class="page-header">
        <h1 class="page-title">Animal <span>Classification</span> Rules</h1>
        <p class="page-subtitle">Configure non-overlapping age ranges for automated life-stage monitoring.</p>
    </header>

    <div class="info-banner">
        <i class="fa-solid fa-circle-info info-icon"></i>
        <div class="info-text">
            These parameters govern the <strong>Auto-Classifier</strong> engine. Changes will instantly trigger a re-scan of the active herd to update stage assignments based on current age.
        </div>
    </div>

    <div class="table-card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Life Stage Name</th>
                        <th>Min Age (Days)</th>
                        <th>Max Age (Days)</th>
                        <th>Target FCR</th>
                        <th>Eligibility</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($classes as $row): ?>
                    <tr>
                        <td data-label="Stage">
                            <div class="stage-badge">
                                <span class="stage-dot"></span>
                                <?php echo htmlspecialchars($row['STAGE_NAME']); ?>
                            </div>
                        </td>
                        <td data-label="Min Age"><span class="days-range"><?php echo $row['MIN_DAYS']; ?></span></td>
                        <td data-label="Max Age"><span class="days-range"><?php echo $row['MAX_DAYS']; ?></span></td>
                        <td data-label="Target FCR" class="val-mono"><?php echo number_format($row['FCR'], 2); ?></td>
                        <td data-label="Eligibility">
                            <?php 
                            $sexClass = !$row['REQUIRED_SEX'] ? 'any' : ($row['REQUIRED_SEX']=='M' ? 'male' : 'female');
                            $sexLabel = !$row['REQUIRED_SEX'] ? 'Unrestricted' : ($row['REQUIRED_SEX']=='M' ? 'Males Only' : 'Females Only');
                            ?>
                            <span class="sex-badge <?php echo $sexClass; ?>"><?php echo $sexLabel; ?></span>
                        </td>
                        <td style="text-align: right;">
                            <button class="action-btn" onclick="openEditModal(
                                <?php echo $row['CLASS_ID']; ?>, 
                                '<?php echo addslashes($row['STAGE_NAME']); ?>', 
                                <?php echo $row['MIN_DAYS']; ?>, 
                                <?php echo $row['MAX_DAYS']; ?>,
                                <?php echo $row['FCR']; ?>
                            )"><i class="fa-solid fa-pen-to-square me-1"></i> Edit Rule</button>
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
        <div class="modal-header">
            <h2>Adjust Classification</h2>
        </div>
        <div class="modal-body">
            <div id="modalAlert" class="alert"></div>
            
            <form id="editForm">
                <input type="hidden" id="class_id" name="class_id">
                
                <div class="form-group">
                    <label class="form-label">Stage Identity</label>
                    <input type="text" id="stage_name" class="form-control" readonly>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Starting Day</label>
                        <input type="number" id="min_days" name="min_days" class="form-control" required min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Final Day</label>
                        <input type="number" id="max_days" name="max_days" class="form-control" required min="1">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Feed Conversion Ratio (FCR)</label>
                    <input type="number" id="fcr" name="fcr" class="form-control" step="0.01" min="0">
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-modal btn-cancel" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-modal btn-save" id="saveButton">Update logic</button>
                </div>
            </form>
        </div>
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
            showAlert("Start Day must be less than End Day.");
            return;
        }

        let hasConflict = false;
        for (const cls of existingClasses) {
            if (parseInt(cls.CLASS_ID) === currentId) continue;
            const existingMin = parseInt(cls.MIN_DAYS);
            const existingMax = parseInt(cls.MAX_DAYS);

            if (newMin <= existingMax && newMax >= existingMin) {
                showAlert(`Overlap Error: This range conflicts with "${cls.STAGE_NAME}" (${existingMin}-${existingMax} days).`);
                hasConflict = true;
                break;
            }
        }

        if (hasConflict) return;

        const formData = new FormData(this);
        const btn = document.getElementById('saveButton');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        btn.disabled = true;

        fetch('../process/updateAnimalClass.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                showAlert("Update failed: " + data.message);
                btn.innerHTML = "Update logic";
                btn.disabled = false;
            }
        })
        .catch(() => {
            showAlert("Connection error occurred.");
            btn.innerHTML = "Update logic";
            btn.disabled = false;
        });
    });

    function showAlert(msg) {
        alertBox.textContent = msg;
        alertBox.style.display = 'block';
    }

    window.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });
</script>

</body>
</html>