<?php
session_start();

if (isset($_SESSION['user']) && ($_SESSION['user']['USER_TYPE'] > 1)) {
    header("Location: ../views/admin_dashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Verification Pending</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            padding: 1rem;
        }

        /* Glass Card Container */
        .verification-card {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 3rem;
            border-radius: 24px;
            text-align: center;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Icon Styling */
        .icon-wrapper {
            width: 80px;
            height: 80px;
            background: rgba(245, 158, 11, 0.15); /* Amber background */
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem auto;
            box-shadow: 0 0 0 8px rgba(245, 158, 11, 0.05);
            color: #fbbf24;
        }

        h1 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #fff, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        p {
            color: #94a3b8;
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        .status-badge {
            display: inline-block;
            background: rgba(245, 158, 11, 0.1);
            color: #fbbf24;
            padding: 0.5rem 1rem;
            border-radius: 99px;
            font-size: 0.85rem;
            font-weight: 600;
            border: 1px solid rgba(245, 158, 11, 0.2);
            margin-bottom: 2rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.875rem 2rem;
            background: rgba(255, 255, 255, 0.05);
            color: #cbd5e1;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 500;
            transition: all 0.2s;
            border: 1px solid rgba(255, 255, 255, 0.1);
            width: 100%;
        }

        .btn:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-color: rgba(255, 255, 255, 0.2);
        }

    </style>
</head>
<body>

    <div class="verification-card">
        <div class="icon-wrapper">
            <svg width="40" height="40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
            </svg>
        </div>

        <div class="status-badge">Status: Pending Verification</div>

        <h1>Verification Required</h1>

        <p>
            Your account has been successfully created but requires administrative approval before you can access the company dashboard.
            <br><br>
            Please contact your System Administrator or IT Department to verify your identity and activate your account privileges.
        </p>

        <a href="../views/login.php" class="btn">
            Return to Login
        </a>
    </div>

</body>
</html>