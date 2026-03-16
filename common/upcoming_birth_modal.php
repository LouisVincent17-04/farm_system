<?php
// Ensure this is included after your database Connection.php
// upcoming_birth_modal.php
$upcoming_births = [];

try {
    // Apply user location restriction if the user is not a Super Admin (1000)
    $locRestriction = "";
    if (isset($USER_LOCATION_) && $USER_LOCATION_ != 1000) {
        $locRestriction = " AND ar.LOCATION_ID = " . (int)$USER_LOCATION_;
    }

    // Standard swine gestation is 114 days.
    // We check for active PREGNANT statuses where expected date is within 2 days, or up to 15 days overdue (-15).
    $sql = "SELECT 
                ssh.ANIMAL_ID, 
                ar.TAG_NO, 
                ar.LOCATION_ID,
                ar.BUILDING_ID,
                ssh.STATUS_START_DATE,
                DATE_ADD(ssh.STATUS_START_DATE, INTERVAL 114 DAY) AS EXPECTED_DATE,
                DATEDIFF(DATE_ADD(ssh.STATUS_START_DATE, INTERVAL 114 DAY), CURDATE()) AS DAYS_LEFT
            FROM sow_status_history ssh
            JOIN animal_records ar ON ssh.ANIMAL_ID = ar.ANIMAL_ID
            WHERE ssh.STATUS_NAME = 'PREGNANT' 
              AND ssh.IS_ACTIVE = 1
              $locRestriction
              AND DATEDIFF(DATE_ADD(ssh.STATUS_START_DATE, INTERVAL 114 DAY), CURDATE()) <= 2
              AND DATEDIFF(DATE_ADD(ssh.STATUS_START_DATE, INTERVAL 114 DAY), CURDATE()) >= -15
            ORDER BY DAYS_LEFT ASC";
            
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $upcoming_births = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    // Fail silently so it doesn't break the UI if there's a DB issue
    error_log("Birthing Alert Error: " . $e->getMessage());
}
?>

<?php if (!empty($upcoming_births)): ?>
    <style>
        /* --- Alert Modal Styles --- */
        .ub-modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(8px);
            z-index: 9999; display: flex; align-items: center; justify-content: center;
            animation: ubFadeIn 0.3s ease;
        }
        .ub-modal {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border: 1px solid #f59e0b; border-radius: 16px;
            width: 90%; max-width: 450px; padding: 2rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
            position: relative;
            transform: translateY(0);
            animation: ubSlideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }
        
        @keyframes ubFadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes ubSlideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }

        .ub-header { display: flex; align-items: center; gap: 15px; margin-bottom: 1rem; color: #f59e0b; }
        .ub-icon { font-size: 2.5rem; animation: ubPulse 2s infinite; }
        @keyframes ubPulse { 0% { transform: scale(1); } 50% { transform: scale(1.1); } 100% { transform: scale(1); } }
        
        .ub-title { font-size: 1.4rem; font-weight: 800; margin: 0; color: #fff; }
        .ub-desc { color: #94a3b8; margin-bottom: 1.5rem; font-size: 0.95rem; line-height: 1.5; }
        
        .ub-list { max-height: 280px; overflow-y: auto; margin-bottom: 1.5rem; padding-right: 5px; }
        .ub-list::-webkit-scrollbar { width: 6px; }
        .ub-list::-webkit-scrollbar-track { background: #0f172a; border-radius: 10px; }
        .ub-list::-webkit-scrollbar-thumb { background: #475569; border-radius: 10px; }

        /* Clickable Item Links */
        .ub-link {
            text-decoration: none; display: block; margin-bottom: 0.5rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border-radius: 10px;
        }
        .ub-link:hover {
            transform: translateX(5px);
            box-shadow: -4px 4px 10px rgba(0,0,0,0.3);
        }

        .ub-item { 
            background: rgba(255, 255, 255, 0.03); padding: 1rem; border-radius: 10px; 
            display: flex; justify-content: space-between; align-items: center;
            border-left: 4px solid #f59e0b; transition: background 0.2s;
        }
        .ub-link:hover .ub-item { background: rgba(245, 158, 11, 0.08); }
        
        .ub-item.urgent { border-left-color: #ef4444; background: rgba(239, 68, 68, 0.05); }
        .ub-link:hover .ub-item.urgent { background: rgba(239, 68, 68, 0.1); }

        .ub-tag { font-weight: 800; color: #60a5fa; font-size: 1.1rem; margin-bottom: 2px; }
        .ub-link:hover .ub-tag { color: #93c5fd; text-decoration: underline; text-underline-offset: 3px; }
        
        .ub-date { font-size: 0.8rem; color: #cbd5e1; }
        
        .ub-days { font-size: 0.9rem; font-weight: 600; color: #fbbf24; background: rgba(245, 158, 11, 0.1); padding: 4px 10px; border-radius: 20px;}
        .ub-days.urgent { color: #ef4444; background: rgba(239, 68, 68, 0.1); }

        .ub-btn {
            background: linear-gradient(135deg, #f59e0b, #d97706); color: #000; border: none; 
            padding: 1rem; border-radius: 10px; font-weight: 800; font-size: 1rem;
            cursor: pointer; width: 100%; transition: transform 0.2s, box-shadow 0.2s;
            text-transform: uppercase; letter-spacing: 1px;
        }
        .ub-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(245, 158, 11, 0.3); }
    </style>

    <div id="upcomingBirthsModal" class="ub-modal-overlay">
        <div class="ub-modal">
            <div class="ub-header">
                <div class="ub-icon">🚨</div>
                <h2 class="ub-title">Farrowing Alert!</h2>
            </div>
            <p class="ub-desc">
                The following <strong><?= count($upcoming_births) ?></strong> sow(s) are expected to give birth within the next 48 hours (or are currently overdue). Please prepare the farrowing pens.
            </p>
            
            <div class="ub-list">
                <?php foreach($upcoming_births as $b): ?>
                    <?php 
                        $days = (int)$b['DAYS_LEFT'];
                        $isUrgent = ($days <= 0);
                        
                        if ($days == 0) {
                            $dayText = "Today!";
                        } elseif ($days < 0) {
                            $dayText = abs($days) . " Days Overdue";
                        } else {
                            $dayText = "In $days Day(s)";
                        }
                    ?>
                    <a href="animal_sow_status.php?location_id=<?= $b['LOCATION_ID'] ?>&building_id=<?= $b['BUILDING_ID'] ?>&animal_id=<?= $b['ANIMAL_ID'] ?>" class="ub-link" title="Click to view/update Sow Status">
                        <div class="ub-item <?= $isUrgent ? 'urgent' : '' ?>">
                            <div>
                                <div class="ub-tag">Tag: <?= htmlspecialchars($b['TAG_NO']) ?> <span style="font-size: 0.8rem; margin-left: 4px;">🔗</span></div>
                                <div class="ub-date">Expected: <?= date('M d, Y', strtotime($b['EXPECTED_DATE'])) ?></div>
                            </div>
                            <div class="ub-days <?= $isUrgent ? 'urgent' : '' ?>"><?= $dayText ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
            
            <button class="ub-btn" onclick="closeBirthingAlert()">Acknowledge</button>
        </div>
    </div>

    <script>
        // Check session storage to see if the user already dismissed this alert today
        // const alertKey = 'birthingAlertAck_' + new Date().toISOString().slice(0, 10); 
        
        // if(sessionStorage.getItem(alertKey) === 'true') {
        //     console.log("Farrowing alert hidden: User already acknowledged it today.");
        //     document.getElementById('upcomingBirthsModal').style.display = 'none';
        // }

        function closeBirthingAlert() {
            document.getElementById('upcomingBirthsModal').style.opacity = '0';
            setTimeout(() => {
                document.getElementById('upcomingBirthsModal').style.display = 'none';
            }, 300);
            // Save to session storage so it doesn't bother them again today
            sessionStorage.setItem(alertKey, 'true');
        }
    </script>
<?php endif; ?>