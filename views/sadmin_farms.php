<?php
// views/farm_page.php

$page = "farm";
include '../config/SadminConnection.php';

$sql = "SELECT * FROM farms";
$stmt = $conn->prepare($sql);
$stmt->execute();
$farms_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Farm | FarmPro</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600&display=swap');

        :root {
            --bg:        #0f172a;
            --bg2:       #1e293b;
            --border:    #334155;
            --text:      #e2e8f0;
            --muted:     #64748b;
            --farm1:     #f472b6;
            --farm1-dim: rgba(244,114,182,0.12);
            --farm2:     #34d399;
            --farm2-dim: rgba(52,211,153,0.12);
            --gold:      #facc15;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ── Animated background grid ── */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(rgba(148,163,184,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(148,163,184,0.03) 1px, transparent 1px);
            background-size: 48px 48px;
            pointer-events: none;
            z-index: 0;
        }

        /* ── Radial glow blobs ── */
        .blob {
            position: fixed;
            border-radius: 50%;
            filter: blur(90px);
            pointer-events: none;
            z-index: 0;
            animation: drift 12s ease-in-out infinite alternate;
        }
        .blob-1 { width: 500px; height: 500px; background: rgba(244,114,182,0.07); top: -100px; left: -100px; animation-delay: 0s; }
        .blob-2 { width: 400px; height: 400px; background: rgba(52,211,153,0.07); bottom: -80px; right: -80px; animation-delay: -6s; }

        @keyframes drift {
            from { transform: translate(0, 0) scale(1); }
            to   { transform: translate(40px, 30px) scale(1.05); }
        }

        /* ── Page wrapper ── */
        .page-wrap {
            position: relative;
            z-index: 1;
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            gap: 2.5rem;
        }

        /* ── Header ── */
        .page-header {
            text-align: center;
            animation: fadeUp 0.6s ease both;
        }
        .page-header .eyebrow {
            font-size: 0.78rem;
            font-weight: 600;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 0.5rem;
        }
        .page-header h1 {
            font-family: 'Bebas Neue', sans-serif;
            font-size: clamp(2.8rem, 6vw, 4.5rem);
            letter-spacing: 0.04em;
            line-height: 1;
            color: #fff;
        }
        .page-header h1 span {
            color: var(--gold);
        }
        .page-header p {
            margin-top: 0.75rem;
            font-size: 0.95rem;
            color: var(--muted);
            font-weight: 300;
        }

        /* ── Farm cards grid ── */
        .farm-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            width: 100%;
            max-width: 780px;
            animation: fadeUp 0.6s ease 0.15s both;
        }

        .farm-card {
            position: relative;
            background: rgba(30,41,59,0.6);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 2.5rem 2rem;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1.25rem;
            overflow: hidden;
            cursor: pointer;
            transition: transform 0.25s ease, border-color 0.25s ease, box-shadow 0.25s ease;
            backdrop-filter: blur(8px);
        }

        /* Hover glow border per farm */
        .farm-card.farm-1 { --c: var(--farm1); --c-dim: var(--farm1-dim); }
        .farm-card.farm-2 { --c: var(--farm2); --c-dim: var(--farm2-dim); }

        .farm-card::before {
            content: '';
            position: absolute;
            inset: 0;
            background: var(--c-dim);
            opacity: 0;
            transition: opacity 0.3s ease;
            border-radius: inherit;
        }
        .farm-card:hover::before { opacity: 1; }
        .farm-card:hover {
            border-color: var(--c);
            transform: translateY(-6px);
            box-shadow: 0 20px 48px -12px color-mix(in srgb, var(--c) 30%, transparent);
        }

        /* Top accent bar */
        .farm-card::after {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: var(--c);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.3s ease;
            border-radius: 20px 20px 0 0;
        }
        .farm-card:hover::after { transform: scaleX(1); }

        /* Icon badge */
        .farm-icon {
            position: relative;
            z-index: 1;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--c-dim);
            border: 1.5px solid color-mix(in srgb, var(--c) 40%, transparent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            transition: transform 0.25s ease;
        }
        .farm-card:hover .farm-icon { transform: scale(1.1) rotate(-4deg); }

        /* Text */
        .farm-label {
            position: relative;
            z-index: 1;
            text-align: center;
        }
        .farm-label .number {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 2.4rem;
            letter-spacing: 0.06em;
            color: var(--c);
            line-height: 1;
        }
        .farm-label .name {
            font-size: 1rem;
            font-weight: 600;
            color: #fff;
            margin-top: 2px;
        }
        .farm-label .desc {
            font-size: 0.8rem;
            color: var(--muted);
            margin-top: 4px;
            font-weight: 300;
        }

        /* Arrow chip */
        .farm-arrow {
            position: relative;
            z-index: 1;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--c);
            background: var(--c-dim);
            border: 1px solid color-mix(in srgb, var(--c) 30%, transparent);
            padding: 5px 14px;
            border-radius: 100px;
            opacity: 0;
            transform: translateY(6px);
            transition: opacity 0.2s ease, transform 0.2s ease;
        }
        .farm-card:hover .farm-arrow {
            opacity: 1;
            transform: translateY(0);
        }

        /* Divider */
        .or-divider {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--muted);
            font-size: 0.8rem;
            font-weight: 500;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            writing-mode: vertical-lr;
            user-select: none;
        }
        .or-divider::before,
        .or-divider::after {
            content: '';
            flex: 1;
            width: 1px;
            background: var(--border);
        }

        /* Footer note */
        .page-footer {
            font-size: 0.8rem;
            color: var(--muted);
            text-align: center;
            animation: fadeUp 0.6s ease 0.3s both;
        }

        /* Animations */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* Active press */
        .farm-card:active { transform: translateY(-2px) scale(0.99); }

        /* ── Mobile ── */
        @media (max-width: 600px) {
            .farm-grid {
                grid-template-columns: 1fr;
                max-width: 400px;
            }
            .or-divider { writing-mode: horizontal-tb; }
            .or-divider::before,
            .or-divider::after { height: 1px; width: auto; }
        }
    </style>
</head>
<body>

    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>

    <div class="page-wrap">

        <div class="page-header">
            <div class="eyebrow">Farm Management System</div>
            <h1>SELECT <span>FARM</span></h1>
            <p>Choose a farm location to view its dashboard and manage operations.</p>
        </div>

        <div class="farm-grid">

            <!-- Farm 1 -->
            <a href="farm_dashboard.php?location=1" class="farm-card farm-1">
                <div class="farm-icon">🌾</div>
                <div class="farm-label">
                    <div class="number">FARM 1</div>
                    <div class="name">Main Farm</div>
                    <div class="desc">Primary livestock operations</div>
                </div>
                <div class="farm-arrow">
                    Enter &rarr;
                </div>
            </a>

            <!-- Farm 2 -->
            <a href="farm_dashboard.php?location=2" class="farm-card farm-2">
                <div class="farm-icon">🐖</div>
                <div class="farm-label">
                    <div class="number">FARM 2</div>
                    <div class="name">Secondary Farm</div>
                    <div class="desc">Breeding & grow-out units</div>
                </div>
                <div class="farm-arrow">
                    Enter &rarr;
                </div>
            </a>

        </div>

        <div class="page-footer">
            Select a farm to proceed &mdash; your access level applies to the chosen location.
        </div>

    </div>

</body>
</html>