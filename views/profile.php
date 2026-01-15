<?php
ob_start(); // Start output buffering

$page 	= 'profile';
include '../common/navbar.php';

// Redirect to login if user not logged in
if(!isset($_SESSION['user'])){
    header("Location: ../views/login.php");
    exit;
}

include '../process/autoUpdateAnimalClasses.php';

// Safely retrieve user data
$fullName = $_SESSION['user']['FULL_NAME'] ?? '';
$email = $_SESSION['user']['EMAIL'] ?? '';
$contact_info = $_SESSION['user']['CONTACT_INFO'] ?? '';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FarmPro - Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    
    <style>
        /* Base Reset */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* Body and Layout */
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #1e293b; /* Dark background from images */
            color: #e2e8f0;
            min-height: 100vh;
 
            justify-content: center;
        }

        .container {
            max-width: 500px; /* Constrain width for profile form */
            width: 100%;
            margin: 0 auto;
            padding: 1rem;
        }

        /* Profile Card Styling */
        .profile-container {
            background: linear-gradient(145deg, #2d4059, #1c2838); /* Subtle gradient for depth */
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(34, 197, 94, 0.1);
            padding: 2rem;
            margin-top: 2rem; /* Added margin-top for breathing room */
        }

        /* Header Styling */
        .profile-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .brand {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.5rem;
        }

        .brand-icon {
            font-size: 1.5rem;
            margin-right: 0.5rem;
            color: #22c55e; /* Theme color */
        }

        .brand-text {
            font-size: 1.5rem;
            font-weight: 700;
            color: #22c55e; /* Theme color */
        }

        .tagline {
            color: #94a3b8;
            font-size: 0.9rem;
        }

        /* Avatar Styling */
        .profile-avatar {
            text-align: center;
            margin-bottom: 2rem;
        }

        .avatar {
            display: inline-flex;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(45deg, #10b981, #22c55e);
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: #fff;
            box-shadow: 0 0 0 5px rgba(34, 197, 94, 0.3);
        }

        /* Form Group Styling */
        .form-group {
            margin-bottom: 1.5rem; /* Increased vertical spacing */
            display: flex;
            flex-direction: column;
            gap: 8px; /* Spacing between label and input */
        }

        .form-label {
            display: block;
            font-weight: 500;
            color: #94a3b8;
            font-size: 0.95rem;
        }
        
        .form-label i {
            margin-right: 6px;
            color: #22c55e;
        }

        .form-input {
            width: 100%;
            padding: 1rem; /* Generous padding for input height */
            background-color: #1e293b;
            border: 1px solid #334155;
            border-radius: 10px;
            color: #e2e8f0;
            font-size: 1rem;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        .form-input:focus {
            outline: none;
            border-color: #22c55e;
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.3);
        }
        
        /* Disabled Input Styling */
        input:disabled {
            background-color: #1a2430 !important;
            cursor: not-allowed !important;
            border-style: dashed;
        }

        /* Button Group */
        .button-group {
            margin-top: 2.5rem; /* More space above buttons */
            display: flex;
            flex-direction: column; /* Stack buttons vertically by default */
            gap: 1rem; /* Spacing between buttons */
        }

        .save-btn {
            padding: 1rem 1.5rem;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s, transform 0.2s;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        /* Save Button (Primary) */
        button.save-btn {
            background: linear-gradient(135deg, #22c55e, #16a34a);
        }

        button.save-btn:hover {
            background: linear-gradient(135deg, #16a34a, #22c55e);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(34, 197, 94, 0.4);
        }

        /* Logout Button (Secondary/Accent) */
        .logout-btn {
            background-color: #ef4444; /* Red for logout/danger */
        }

        .logout-btn:hover {
            background-color: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(239, 68, 68, 0.4);
        }

        /* Media Queries for Responsiveness */
        @media (min-width: 600px) {
            .button-group {
                flex-direction: row; /* Layout buttons horizontally on wider screens */
                justify-content: space-between;
            }
            .save-btn {
                flex-grow: 1; /* Allow buttons to grow and fill space */
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="profile-container">
            <div class="profile-header">
                <div class="brand">
                    <div class="brand-icon">🌱</div>
                    <h1 class="brand-text">FarmPro</h1>
                </div>
                <p class="tagline">Smart farming solutions for modern agriculture</p>
            </div>

            <div class="profile-content">
                <div class="profile-avatar">
                    <div class="avatar">👤</div>
                </div>

                <form id="profileForm" method="POST" action="../process/updateProfile.php">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-user"></i> Full Name</label>
                        <input type="text" class="form-input" id="fullName" name="fullName" 
                            value="<?php echo htmlspecialchars($fullName); ?>" 
                            placeholder="Enter full name" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-envelope"></i> Email Address</label>
                        <input type="email" class="form-input" id="email" 
                            value="<?php echo htmlspecialchars($email); ?>" 
                            placeholder="Enter email" required disabled>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-phone"></i> Contact Info</label>
                        <input type="text" class="form-input" id="contactInfo" name="contactInfo" 
                            value="<?php echo htmlspecialchars($contact_info); ?>" 
                            placeholder="Enter contact number or info">
                    </div>

                    <div class="button-group">
                        <button type="submit" class="save-btn"><i class="fas fa-save"></i> Save Changes</button>

                        <a href="../process/logout.php" class="save-btn logout-btn">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>

<?php ob_end_flush(); ?>