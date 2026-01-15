<?php
$page = "reports"; 
include '../common/navbar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coming Soon | FarmPro</title>
    <style>
        /* BASE THEME (Matching your existing UI) */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #e2e8f0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* CENTERED LAYOUT */
        .coming-soon-wrapper {
            flex-grow: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            position: relative;
            overflow: hidden;
        }

        /* DECORATIVE BACKGROUND BLOBS (Adds depth) */
        .blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            z-index: 0;
            opacity: 0.4;
        }
        .blob-1 { width: 300px; height: 300px; background: #2563eb; top: 10%; left: 20%; animation: float 8s infinite ease-in-out; }
        .blob-2 { width: 250px; height: 250px; background: #7c3aed; bottom: 20%; right: 20%; animation: float 10s infinite ease-in-out reverse; }

        @keyframes float {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(20px, -20px); }
        }

        /* GLASS CARD */
        .glass-card {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 4rem 3rem;
            text-align: center;
            max-width: 600px;
            width: 100%;
            z-index: 1;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            position: relative;
        }

        /* ICON STYLING */
        .icon-wrapper {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #3b82f6, #9333ea);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem auto;
            box-shadow: 0 0 30px rgba(59, 130, 246, 0.4);
            animation: pulse 3s infinite;
        }
        .icon-wrapper svg {
            width: 50px;
            height: 50px;
            color: white;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.4); }
            70% { box-shadow: 0 0 0 20px rgba(59, 130, 246, 0); }
            100% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0); }
        }

        /* TYPOGRAPHY */
        h1 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #fff, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        p {
            color: #94a3b8;
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 2.5rem;
        }

        /* PROGRESS BAR (Visual Flair) */
        .progress-container {
            width: 100%;
            background: rgba(255, 255, 255, 0.1);
            height: 6px;
            border-radius: 99px;
            margin-bottom: 2.5rem;
            overflow: hidden;
        }
        .progress-bar {
            height: 100%;
            width: 75%; /* Arbitrary percentage */
            background: linear-gradient(90deg, #3b82f6, #9333ea);
            border-radius: 99px;
            position: relative;
        }
        /* shimmer effect on progress bar */
        .progress-bar::after {
            content: '';
            position: absolute;
            top: 0; left: 0; bottom: 0; right: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            transform: translateX(-100%);
            animation: shimmer 2s infinite;
        }
        @keyframes shimmer {
            100% { transform: translateX(100%); }
        }

        /* BUTTON */
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 0.875rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .back-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }
        
        .tag-pill {
            background: rgba(245, 158, 11, 0.2);
            color: #fbbf24;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 1.5rem;
            display: inline-block;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

    </style>
</head>
<body>

    <div class="coming-soon-wrapper">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>

        <div class="glass-card">
            <div class="tag-pill">Work in Progress</div>
            
            <div class="icon-wrapper">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                </svg>
            </div>

            <h1>Feature Coming Soon</h1>
            
            <p>
                We're currently crafting this feature to help you manage your farm even better. 
                Check back soon for updates!
            </p>

            <div class="progress-container">
                <div class="progress-bar"></div>
            </div>

            <a href="admin_dashboard.php" class="back-btn">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to Dashboard
            </a>
        </div>
    </div>

</body>
</html>