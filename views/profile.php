<?php
ob_start(); // Start output buffering

$page   = 'profile';
include '../config/Connection.php';
include '../common/navbar.php';
include '../common/chat_support.php';
include '../common/upcoming_birth_modal.php';

// Redirect to login if user not logged in
if(!isset($_SESSION['user'])){
    header("Location: ../views/login.php");
    exit;
}

// Safely retrieve user data
$fullName = $_SESSION['user']['FULL_NAME'] ?? '';
$email = $_SESSION['user']['EMAIL'] ?? '';
$contact_info = $_SESSION['user']['CONTACT_INFO'] ?? '';
$userType = $_SESSION['user']['USER_TYPE_NAME'] ?? 'Staff'; // Assuming you have a user type string, defaulting to Staff if not

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>My Profile | FarmPro</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

    <style>
        /* ─── CSS VARIABLES ─── */
        :root {
            --profile-bg-base:        #080f1a;
            --profile-bg-surface:     #0d1829;
            --profile-bg-elevated:    #111f35;
            --profile-bg-hover:       #162540;
            --profile-border:         rgba(255,255,255,0.07);
            
            --profile-emerald:        #10b981;
            --profile-emerald-glow:   rgba(16,185,129,0.25);
            --profile-red:            #ef4444;
            --profile-red-dim:        rgba(239,68,68,0.12);
            
            --profile-text-primary:   #f1f5f9;
            --profile-text-secondary: #94a3b8;
            --profile-text-muted:     #475569;
            
            --profile-radius-md:      10px;
            --profile-radius-xl:      20px;
            --profile-shadow-md:      0 4px 16px rgba(0,0,0,0.4);
            --profile-font:           'DM Sans', system-ui, sans-serif;
            --profile-transition:     0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* ─── RESET & BASE ─── */
        body {
            font-family: var(--profile-font);
            background: var(--profile-bg-base);
            color: var(--profile-text-primary);
            min-height: 100vh;
            padding-bottom: 60px;
            background-image: radial-gradient(circle at top right, rgba(16, 185, 129, 0.05), transparent 40%);
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .profile-page-container {
            width: 100%;
            max-width: 550px;
            padding: 2rem 1.5rem;
            margin-top: 2rem;
            box-sizing: border-box;
        }

        /* ─── PROFILE CARD ─── */
        .profile-main-card {
            background: var(--profile-bg-surface);
            border: 1px solid var(--profile-border);
            border-radius: var(--profile-radius-xl);
            padding: 2.5rem;
            box-shadow: var(--profile-shadow-md);
            position: relative;
            overflow: hidden;
            box-sizing: border-box;
        }
        
        /* Decorative Top Accent */
        .profile-main-card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
            background: linear-gradient(90deg, var(--profile-emerald), #047857);
        }

        /* ─── HEADER & AVATAR ─── */
        .profile-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .profile-avatar-wrapper {
            position: relative;
            margin-bottom: 1rem;
        }

        .profile-avatar {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: var(--profile-bg-elevated);
            border: 2px solid var(--profile-emerald);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: var(--profile-emerald);
            box-shadow: 0 0 20px var(--profile-emerald-glow);
        }

        .profile-role-badge {
            position: absolute;
            bottom: -5px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--profile-emerald);
            color: #000;
            padding: 4px 12px;
            border-radius: 99px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            box-shadow: 0 2px 5px rgba(0,0,0,0.5);
            white-space: nowrap;
        }

        .profile-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--profile-text-primary);
            margin-bottom: 0.25rem;
            margin-top: 0.5rem;
        }
        
        .profile-header p {
            color: var(--profile-text-secondary);
            font-size: 0.9rem;
            margin: 0;
        }

        /* ─── FORM ─── */
        .profile-form-group {
            margin-bottom: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .profile-form-label {
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--profile-text-secondary);
            letter-spacing: 0.05em;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .profile-form-label i { color: var(--profile-emerald); font-size: 0.9rem; }

        .profile-form-input {
            width: 100%;
            padding: 14px 16px;
            background: var(--profile-bg-elevated);
            border: 1px solid var(--profile-border);
            border-radius: var(--profile-radius-md);
            color: var(--profile-text-primary);
            font-size: 0.95rem;
            font-family: var(--profile-font);
            transition: all var(--profile-transition);
            outline: none;
            box-sizing: border-box;
        }

        .profile-form-input:focus {
            border-color: var(--profile-emerald);
            background: var(--profile-bg-hover);
            box-shadow: 0 0 0 3px var(--profile-emerald-glow);
        }

        /* Disabled Input Styling */
        .profile-form-input:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            background: rgba(255,255,255,0.02);
            color: var(--profile-text-muted);
        }

        /* ─── BUTTONS ─── */
        .profile-action-group {
            margin-top: 2.5rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--profile-border);
        }

        .profile-btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            padding: 14px 24px; border-radius: var(--profile-radius-md); font-size: 0.95rem;
            font-weight: 700; font-family: var(--profile-font); cursor: pointer;
            transition: all var(--profile-transition); text-decoration: none; border: none;
            box-sizing: border-box;
        }

        .profile-btn-save {
            background: var(--profile-emerald);
            color: #000;
        }
        .profile-btn-save:hover {
            background: #34d399;
            box-shadow: 0 4px 15px var(--profile-emerald-glow);
            transform: translateY(-1px);
        }

        .profile-btn-logout {
            background: transparent;
            color: var(--profile-red);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
        .profile-btn-logout:hover {
            background: var(--profile-red-dim);
            border-color: var(--profile-red);
            transform: translateY(-1px);
        }

        @media (min-width: 480px) {
            .profile-action-group {
                flex-direction: row;
                justify-content: space-between;
            }
            .profile-btn { flex: 1; }
        }
        
        @media (max-width: 480px) {
            .profile-main-card { padding: 1.5rem; }
        }
    </style>
</head>
<body>

    <div class="profile-page-container">
        <div class="profile-main-card">
            
            <div class="profile-header">
                <div class="profile-avatar-wrapper">
                    <div class="profile-avatar"><i class="fa-solid fa-user-astronaut"></i></div>
                    <div class="profile-role-badge"><?= htmlspecialchars($userType) ?></div>
                </div>
                <h1><?= htmlspecialchars($fullName ?: 'FarmPro User') ?></h1>
                <p>Manage your account details and contact information</p>
            </div>

            <form id="profileForm" method="POST" action="../process/updateProfile.php">
                
                <div class="profile-form-group">
                    <label class="profile-form-label" for="fullName"><i class="fa-solid fa-id-card"></i> Full Name</label>
                    <input type="text" class="profile-form-input" id="fullName" name="fullName" 
                        value="<?php echo htmlspecialchars($fullName); ?>" 
                        placeholder="Enter your full name" required>
                </div>

                <div class="profile-form-group">
                    <label class="profile-form-label" for="email"><i class="fa-solid fa-envelope"></i> Email Address</label>
                    <input type="email" class="profile-form-input" id="email" 
                        value="<?php echo htmlspecialchars($email); ?>" 
                        placeholder="your.email@example.com" disabled>
                </div>
                
                <div class="profile-form-group">
                    <label class="profile-form-label" for="contactInfo"><i class="fa-solid fa-phone"></i> Contact Number</label>
                    <input type="tel" class="profile-form-input" id="contactInfo" name="contactInfo" 
                        value="<?php echo htmlspecialchars($contact_info); ?>" 
                        placeholder="Enter contact number">
                </div>

                <div class="profile-action-group">
                    <button type="submit" class="profile-btn profile-btn-save">
                        <i class="fa-solid fa-floppy-disk"></i> Save Changes
                    </button>

                    <a href="../process/logout.php" class="profile-btn profile-btn-logout">
                        <i class="fa-solid fa-arrow-right-from-bracket"></i> Sign Out
                    </a>
                </div>
                
            </form>
            
        </div>
    </div>

</body>
</html>
<?php ob_end_flush(); ?>