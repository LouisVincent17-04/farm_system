<?php
ob_start();
$page = 'login/register';
include '../common/navbar.php';

if (isset($_SESSION['user'])) {
    header("Location: ../views/profile.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>GATZ SmartFarm — Sign In</title>
    <script src="https://accounts.google.com/gsi/client" async defer></script>


    <style>
        /* ── VARIABLES ─────────────────────────────────────── */
        :root {
            --green-400: #4ade80;
            --green-500: #22c55e;
            --green-600: #16a34a;
            --green-900: #14532d;
            --slate-700: #334155;
            --slate-800: #1e293b;
            --slate-900: #0f172a;
            --slate-950: #020617;
            --text-primary: #f1f5f9;
            --text-muted: #94a3b8;
            --text-dim: #64748b;
            --border: rgba(255, 255, 255, 0.08);
            --border-focus: rgba(34, 197, 94, 0.5);
            --card-bg: rgba(15, 23, 42, 0.75);
            --input-bg: rgba(2, 6, 23, 0.5);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 18px;
            --transition: 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* ── RESET ─────────────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { -webkit-text-size-adjust: 100%; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--slate-950);
            color: var(--text-primary);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* ── ANIMATED BACKGROUND ───────────────────────────── */
        .lp-bg {
            position: fixed;
            inset: 0;
            z-index: 0;
            overflow: hidden;
        }

        .lp-bg__grid {
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(34, 197, 94, 0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(34, 197, 94, 0.04) 1px, transparent 1px);
            background-size: 48px 48px;
            mask-image: radial-gradient(ellipse 80% 80% at 50% 50%, black 30%, transparent 100%);
        }

        .lp-bg__orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(100px);
            animation: orbFloat 8s ease-in-out infinite;
        }
        .lp-bg__orb--1 {
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(34, 197, 94, 0.15), transparent 70%);
            top: -200px; left: -150px;
            animation-delay: 0s;
        }
        .lp-bg__orb--2 {
            width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.1), transparent 70%);
            bottom: -250px; right: -200px;
            animation-delay: -4s;
        }
        .lp-bg__orb--3 {
            width: 300px; height: 300px;
            background: radial-gradient(circle, rgba(34, 197, 94, 0.08), transparent 70%);
            top: 40%; left: 30%;
            animation-delay: -2s;
        }

        @keyframes orbFloat {
            0%, 100% { transform: translateY(0px) scale(1); }
            50% { transform: translateY(-30px) scale(1.05); }
        }

        /* ── LAYOUT ────────────────────────────────────────── */
        .lp-page {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
        }

        /* ── CARD ──────────────────────────────────────────── */
        .lp-card {
            width: 100%;
            max-width: 440px;
            background: var(--card-bg);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 36px 32px 32px;
            box-shadow:
                0 0 0 1px rgba(34, 197, 94, 0.05),
                0 32px 64px -16px rgba(0, 0, 0, 0.7),
                inset 0 1px 0 rgba(255, 255, 255, 0.06);
            animation: cardReveal 0.5s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }

        @keyframes cardReveal {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── BRAND HEADER ──────────────────────────────────── */
        .lp-brand {
            text-align: center;
            margin-bottom: 28px;
        }

        .lp-brand__icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 52px; height: 52px;
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.2), rgba(16, 185, 129, 0.1));
            border: 1px solid rgba(34, 197, 94, 0.25);
            border-radius: 14px;
            font-size: 24px;
            margin-bottom: 14px;
            box-shadow: 0 0 24px rgba(34, 197, 94, 0.1);
        }

        .lp-brand__name {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-weight: bolder;
            font-size: 22px;
            color: var(--text-primary);
            letter-spacing: -0.3px;
            margin-bottom: 5px;
        }

        .lp-brand__name span {
            background: linear-gradient(135deg, #4ade80, #22c55e);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .lp-brand__tagline {
            font-size: 13px;
            color: var(--text-dim);
            font-weight: 400;
            letter-spacing: 0.1px;
        }

        /* ── TABS ──────────────────────────────────────────── */
        .lp-tabs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: rgba(2, 6, 23, 0.5);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 3px;
            margin-bottom: 24px;
        }

        .lp-tab-btn {
            padding: 9px 12px;
            border: none;
            background: transparent;
            color: var(--text-dim);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 13.5px;
            font-weight: 500;
            border-radius: 6px;
            cursor: pointer;
            transition: all var(--transition);
            letter-spacing: 0.1px;
        }

        .lp-tab-btn.is-active {
            background: linear-gradient(135deg, #166534, #15803d);
            color: #dcfce7;
            box-shadow: 0 1px 8px rgba(34, 197, 94, 0.25), inset 0 1px 0 rgba(255,255,255,0.1);
        }

        /* ── FORM PANELS ───────────────────────────────────── */
        .lp-panel { display: none; }
        .lp-panel.is-active {
            display: block;
            animation: panelIn 0.3s ease forwards;
        }

        @keyframes panelIn {
            from { opacity: 0; transform: translateY(6px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── GOOGLE BUTTON ─────────────────────────────────── */
        .lp-google-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 11px 16px;
            background: #ffffff;
            color: #1f2937;
            border: none;
            border-radius: var(--radius-sm);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all var(--transition);
            letter-spacing: 0.1px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.3);
            margin-bottom: 20px;
        }

        .lp-google-btn:hover {
            background: #f9fafb;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
            transform: translateY(-1px);
        }

        .lp-google-btn:active { transform: translateY(0); box-shadow: 0 1px 3px rgba(0,0,0,0.3); }

        .lp-google-btn__logo {
            display: flex;
            align-items: center;
            flex-shrink: 0;
        }

        /* ── DIVIDER ───────────────────────────────────────── */
        .lp-divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            color: var(--text-dim);
            font-size: 12px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .lp-divider::before, .lp-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        /* ── FORM FIELDS ───────────────────────────────────── */
        .lp-field { position: relative; margin-bottom: 16px; }

        .lp-field__label {
            display: block;
            font-size: 12px;
            font-weight: 500;
            color: var(--text-muted);
            margin-bottom: 6px;
            letter-spacing: 0.3px;
        }

        .lp-field__input {
            width: 100%;
            padding: 11px 14px;
            background: var(--input-bg);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-sm);
            color: var(--text-primary);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 14px;
            outline: none;
            transition: border-color var(--transition), box-shadow var(--transition), background var(--transition);
            -webkit-appearance: none;
        }

        .lp-field__input::placeholder { color: var(--text-dim); }

        .lp-field__input:focus {
            border-color: var(--border-focus);
            background: rgba(2, 6, 23, 0.7);
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1);
        }

        .lp-field__input.is-invalid {
            border-color: rgba(239, 68, 68, 0.6);
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
        }

        /* Password toggle */
        .lp-field__eye {
            position: absolute;
            right: 12px;
            bottom: 11px;
            cursor: pointer;
            color: var(--text-dim);
            transition: color var(--transition);
            line-height: 1;
            user-select: none;
            font-size: 15px;
        }
        .lp-field__eye:hover { color: var(--text-muted); }

        /* Inline error */
        .lp-field__error {
            display: flex;
            align-items: center;
            gap: 5px;
            margin-top: 5px;
            font-size: 11.5px;
            color: #f87171;
            opacity: 0;
            max-height: 0;
            overflow: hidden;
            transition: all 0.2s ease;
        }
        .lp-field__error.is-visible { opacity: 1; max-height: 32px; }
        .lp-field__error::before {
            content: '';
            display: inline-block;
            width: 4px; height: 4px;
            border-radius: 50%;
            background: #f87171;
            flex-shrink: 0;
        }

        /* ── PASSWORD STRENGTH ─────────────────────────────── */
        .lp-strength {
            margin-top: 8px;
            display: none;
        }
        .lp-strength.is-visible { display: block; }
        .lp-strength__track {
            height: 3px;
            background: rgba(255,255,255,0.08);
            border-radius: 99px;
            overflow: hidden;
        }
        .lp-strength__bar {
            height: 100%;
            width: 0%;
            border-radius: 99px;
            transition: width 0.3s ease, background 0.3s ease;
        }
        .lp-strength__label {
            font-size: 11px;
            color: var(--text-dim);
            margin-top: 4px;
            text-align: right;
        }

        /* ── FORM OPTIONS ──────────────────────────────────── */
        .lp-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            gap: 12px;
        }

        .lp-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-size: 13px;
            color: var(--text-muted);
            user-select: none;
        }
        .lp-checkbox input[type="checkbox"] {
            width: 15px; height: 15px;
            accent-color: var(--green-500);
            cursor: pointer;
            flex-shrink: 0;
        }

        .lp-forgot {
            font-size: 13px;
            color: var(--green-400);
            text-decoration: none;
            font-weight: 500;
            white-space: nowrap;
            transition: color var(--transition);
        }
        .lp-forgot:hover { color: #86efac; text-decoration: underline; }

        /* ── SUBMIT BUTTON ─────────────────────────────────── */
        .lp-submit {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: var(--radius-sm);
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: #f0fdf4;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 14.5px;
            font-weight: 600;
            cursor: pointer;
            letter-spacing: 0.2px;
            transition: all var(--transition);
            box-shadow: 0 4px 14px rgba(22, 163, 74, 0.35), inset 0 1px 0 rgba(255,255,255,0.12);
            position: relative;
            overflow: hidden;
        }
        .lp-submit::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, transparent 40%, rgba(255,255,255,0.08));
            opacity: 0;
            transition: opacity var(--transition);
        }
        .lp-submit:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(22, 163, 74, 0.45), inset 0 1px 0 rgba(255,255,255,0.12);
        }
        .lp-submit:hover::after { opacity: 1; }
        .lp-submit:active:not(:disabled) { transform: translateY(0); }
        .lp-submit:disabled { opacity: 0.55; cursor: not-allowed; transform: none; box-shadow: none; }

        /* ── FOOTER LINK ───────────────────────────────────── */
        .lp-footer-note {
            text-align: center;
            margin-top: 18px;
            font-size: 13px;
            color: var(--text-dim);
        }
        .lp-footer-note a {
            color: var(--green-400);
            text-decoration: none;
            font-weight: 500;
            transition: color var(--transition);
        }
        .lp-footer-note a:hover { color: #86efac; }

        /* ── TOAST ─────────────────────────────────────────── */
        .lp-toast {
            max-height: 0;
            overflow: hidden;
            opacity: 0;
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            margin-bottom: 0;
        }
        .lp-toast.is-visible { max-height: 100px; opacity: 1; margin-bottom: 16px; }

        .lp-toast__inner {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 11px 14px;
            border-radius: var(--radius-sm);
            border: 1px solid transparent;
        }
        .lp-toast--error .lp-toast__inner {
            background: rgba(239, 68, 68, 0.1);
            border-color: rgba(239, 68, 68, 0.25);
        }
        .lp-toast--success .lp-toast__inner {
            background: rgba(34, 197, 94, 0.1);
            border-color: rgba(34, 197, 94, 0.25);
        }

        .lp-toast__icon {
            font-size: 15px;
            flex-shrink: 0;
            margin-top: 1px;
        }
        .lp-toast--error .lp-toast__icon { color: #f87171; }
        .lp-toast--success .lp-toast__icon { color: #4ade80; }

        .lp-toast__body { flex: 1; min-width: 0; }
        .lp-toast__title { font-size: 12.5px; font-weight: 600; margin-bottom: 1px; }
        .lp-toast--error .lp-toast__title  { color: #fca5a5; }
        .lp-toast--success .lp-toast__title { color: #86efac; }
        .lp-toast__msg { font-size: 12px; color: var(--text-muted); line-height: 1.4; }

        .lp-toast__close {
            background: none;
            border: none;
            color: var(--text-dim);
            cursor: pointer;
            font-size: 14px;
            padding: 0;
            flex-shrink: 0;
            transition: color var(--transition);
            line-height: 1;
            margin-top: 1px;
        }
        .lp-toast__close:hover { color: var(--text-primary); }

        /* ── TERMS ERROR ───────────────────────────────────── */
        .lp-terms-error {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 11.5px;
            color: #f87171;
            margin-top: -8px;
            margin-bottom: 14px;
            opacity: 0;
            max-height: 0;
            overflow: hidden;
            transition: all 0.2s ease;
        }
        .lp-terms-error.is-visible { opacity: 1; max-height: 24px; }
        .lp-terms-error::before {
            content: '';
            display: inline-block;
            width: 4px; height: 4px;
            border-radius: 50%;
            background: #f87171;
            flex-shrink: 0;
        }

        /* ── MOBILE ────────────────────────────────────────── */
        @media (max-width: 480px) {
            .lp-card {
                padding: 28px 20px 24px;
                border-radius: 16px;
                /* Remove backdrop on very small screens for perf */
                backdrop-filter: blur(12px);
            }
            .lp-brand__name { font-size: 20px; }
            .lp-submit { padding: 14px; } /* Larger tap target */
            .lp-google-btn { padding: 13px 16px; } /* Larger tap target */
            .lp-options { flex-wrap: wrap; gap: 8px; }
            .lp-forgot { margin-left: auto; }
        }

        @media (max-width: 360px) {
            .lp-page { padding: 16px 12px; }
            .lp-card { padding: 24px 16px 20px; }
        }
    </style>
</head>
<body>

<!-- Animated background -->
<div class="lp-bg" aria-hidden="true">
    <div class="lp-bg__grid"></div>
    <div class="lp-bg__orb lp-bg__orb--1"></div>
    <div class="lp-bg__orb lp-bg__orb--2"></div>
    <div class="lp-bg__orb lp-bg__orb--3"></div>
</div>

<div class="lp-page">
    <div class="lp-card">

        <!-- Brand -->
        <div class="lp-brand">
            <div class="lp-brand__icon">🌱</div>
            <div class="lp-brand__name">GATZ <span>SmartFarm</span></div>
            <div class="lp-brand__tagline">Smart farming solutions for modern agriculture</div>
        </div>

        <!-- Tabs -->
        <div class="lp-tabs" role="tablist">
            <button class="lp-tab-btn is-active" onclick="switchTab('signin')" role="tab" aria-selected="true">Sign In</button>
            <button class="lp-tab-btn" onclick="switchTab('signup')" role="tab" aria-selected="false">Sign Up</button>
        </div>

        <!-- ═══ SIGN IN PANEL ═══ -->
        <div class="lp-panel is-active" id="signinForm" role="tabpanel">

            <!-- Toast -->
            <div class="lp-toast" id="signinToast" role="alert" aria-live="polite">
                <div class="lp-toast__inner">
                    <span class="lp-toast__icon" id="signinToastIcon"></span>
                    <div class="lp-toast__body">
                        <div class="lp-toast__title" id="signinToastTitle"></div>
                        <div class="lp-toast__msg" id="signinToastMsg"></div>
                    </div>
                    <button class="lp-toast__close" onclick="hideToast('signin')" aria-label="Dismiss">✕</button>
                </div>
            </div>

            <!-- Google -->
            <button class="lp-google-btn" onclick="handleGoogleAuth()" type="button">
                <span class="lp-google-btn__logo">
                    <svg width="18" height="18" viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg">
                        <g fill="none" fill-rule="evenodd">
                            <path d="M17.64 9.205c0-.639-.057-1.252-.164-1.841H9v3.481h4.844a4.14 4.14 0 0 1-1.796 2.716v2.259h2.908c1.702-1.567 2.684-3.875 2.684-6.615z" fill="#4285F4"/>
                            <path d="M9 18c2.43 0 4.467-.806 5.956-2.18l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 0 0 9 18z" fill="#34A853"/>
                            <path d="M3.964 10.71A5.41 5.41 0 0 1 3.682 9c0-.593.102-1.17.282-1.71V4.958H.957A8.996 8.996 0 0 0 0 9c0 1.452.348 2.827.957 4.042l3.007-2.332z" fill="#FBBC05"/>
                            <path d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 0 0 .957 4.958L3.964 7.29C4.672 5.163 6.656 3.58 9 3.58z" fill="#EA4335"/>
                        </g>
                    </svg>
                </span>
                <span>Continue with Google</span>
            </button>

            <div class="lp-divider">or continue with email</div>

            <form id="signinFormEl" onsubmit="return handleSigninSubmit(event)" novalidate>
                <div class="lp-field">
                    <label class="lp-field__label" for="signinEmail">Email address</label>
                    <input class="lp-field__input" type="email" id="signinEmail" name="email"
                           placeholder="you@example.com" autocomplete="email">
                    <div class="lp-field__error" id="signinEmailErr">Please enter a valid email</div>
                </div>

                <div class="lp-field">
                    <label class="lp-field__label" for="signinPassword">Password</label>
                    <input class="lp-field__input" type="password" id="signinPassword" name="password"
                           placeholder="••••••••" autocomplete="current-password" style="padding-right:38px;">
                    <span class="lp-field__eye" onclick="togglePw('signinPassword', this)" aria-label="Toggle">👁</span>
                    <div class="lp-field__error" id="signinPasswordErr">Password is required</div>
                </div>

                <div class="lp-options">
                    <label class="lp-checkbox">
                        <input type="checkbox" id="remember">
                        Remember me
                    </label>
                    <a href="forgot_password.php" class="lp-forgot">Forgot password?</a>
                </div>

                <button type="submit" class="lp-submit" id="signinBtn">Sign In</button>
            </form>

            <div class="lp-footer-note">
                No account yet? <a href="#" onclick="switchTab('signup'); return false;">Create one</a>
            </div>
        </div>

        <!-- ═══ SIGN UP PANEL ═══ -->
        <div class="lp-panel" id="signupForm" role="tabpanel">

            <div class="lp-toast" id="signupToast" role="alert" aria-live="polite">
                <div class="lp-toast__inner">
                    <span class="lp-toast__icon" id="signupToastIcon"></span>
                    <div class="lp-toast__body">
                        <div class="lp-toast__title" id="signupToastTitle"></div>
                        <div class="lp-toast__msg" id="signupToastMsg"></div>
                    </div>
                    <button class="lp-toast__close" onclick="hideToast('signup')" aria-label="Dismiss">✕</button>
                </div>
            </div>

            <button class="lp-google-btn" onclick="handleGoogleAuth()" type="button">
                <span class="lp-google-btn__logo">
                    <svg width="18" height="18" viewBox="0 0 18 18" xmlns="http://www.w3.org/2000/svg">
                        <g fill="none" fill-rule="evenodd">
                            <path d="M17.64 9.205c0-.639-.057-1.252-.164-1.841H9v3.481h4.844a4.14 4.14 0 0 1-1.796 2.716v2.259h2.908c1.702-1.567 2.684-3.875 2.684-6.615z" fill="#4285F4"/>
                            <path d="M9 18c2.43 0 4.467-.806 5.956-2.18l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 0 0 9 18z" fill="#34A853"/>
                            <path d="M3.964 10.71A5.41 5.41 0 0 1 3.682 9c0-.593.102-1.17.282-1.71V4.958H.957A8.996 8.996 0 0 0 0 9c0 1.452.348 2.827.957 4.042l3.007-2.332z" fill="#FBBC05"/>
                            <path d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 0 0 .957 4.958L3.964 7.29C4.672 5.163 6.656 3.58 9 3.58z" fill="#EA4335"/>
                        </g>
                    </svg>
                </span>
                <span>Continue with Google</span>
            </button>

            <div class="lp-divider">or sign up with email</div>

            <form id="signupFormEl" onsubmit="return handleSignupSubmit(event)" novalidate>
                <div class="lp-field">
                    <label class="lp-field__label" for="signupName">Full name</label>
                    <input class="lp-field__input" type="text" id="signupName" name="fullname"
                           placeholder="John Dela Cruz" autocomplete="name">
                    <div class="lp-field__error" id="signupNameErr">Please enter your full name</div>
                </div>

                <div class="lp-field">
                    <label class="lp-field__label" for="signupEmail">Email address</label>
                    <input class="lp-field__input" type="email" id="signupEmail" name="email"
                           placeholder="you@example.com" autocomplete="email">
                    <div class="lp-field__error" id="signupEmailErr">Please enter a valid email</div>
                </div>

                <div class="lp-field">
                    <label class="lp-field__label" for="signupPassword">Password</label>
                    <input class="lp-field__input" type="password" id="signupPassword" name="password"
                           placeholder="Min. 8 characters" autocomplete="new-password" style="padding-right:38px;">
                    <span class="lp-field__eye" onclick="togglePw('signupPassword', this)" aria-label="Toggle">👁</span>
                    <div class="lp-strength" id="pwStrengthWrap">
                        <div class="lp-strength__track">
                            <div class="lp-strength__bar" id="pwStrengthBar"></div>
                        </div>
                        <div class="lp-strength__label" id="pwStrengthLabel"></div>
                    </div>
                    <div class="lp-field__error" id="signupPasswordErr">Password must be at least 8 characters</div>
                </div>

                <div class="lp-options">
                    <label class="lp-checkbox">
                        <input type="checkbox" id="terms">
                        I agree to the terms &amp; conditions
                    </label>
                </div>
                <div class="lp-terms-error" id="signupTermsErr">You must agree to the terms to continue</div>

                <button type="submit" class="lp-submit" id="signupBtn">Create Account</button>
            </form>

            <div class="lp-footer-note">
                Already have an account? <a href="#" onclick="switchTab('signin'); return false;">Sign in</a>
            </div>
        </div>

    </div>
</div>

<script>
// ── TOAST ────────────────────────────────────────────────
function showToast(form, title, msg, type) {
    var el = document.getElementById(form + 'Toast');
    document.getElementById(form + 'ToastIcon').textContent  = type === 'success' ? '✓' : '✕';
    document.getElementById(form + 'ToastTitle').textContent = title;
    document.getElementById(form + 'ToastMsg').textContent   = msg;
    el.className = 'lp-toast lp-toast--' + type;
    void el.offsetHeight;
    el.classList.add('is-visible');
    if (type === 'success') setTimeout(function() { hideToast(form); }, 4000);
}
function hideToast(form) {
    document.getElementById(form + 'Toast').classList.remove('is-visible');
}

// ── TABS ─────────────────────────────────────────────────
function switchTab(tab) {
    var isSignin = tab === 'signin';
    document.getElementById('signinForm').classList.toggle('is-active', isSignin);
    document.getElementById('signupForm').classList.toggle('is-active', !isSignin);
    var btns = document.querySelectorAll('.lp-tab-btn');
    btns[0].classList.toggle('is-active', isSignin);
    btns[0].setAttribute('aria-selected', isSignin);
    btns[1].classList.toggle('is-active', !isSignin);
    btns[1].setAttribute('aria-selected', !isSignin);
    hideToast('signin'); hideToast('signup');
}

// ── PASSWORD TOGGLE ───────────────────────────────────────
function togglePw(id, btn) {
    var inp = document.getElementById(id);
    var hide = inp.type === 'password';
    inp.type = hide ? 'text' : 'password';
    btn.textContent = hide ? '🙈' : '👁';
}

// ── VALIDATION HELPERS ────────────────────────────────────
function isValidEmail(v) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v); }

function setErr(inputEl, errEl, show) {
    inputEl.classList.toggle('is-invalid', show);
    errEl.classList.toggle('is-visible', show);
}

function validateSignin() {
    var em = document.getElementById('signinEmail'),  emErr = document.getElementById('signinEmailErr');
    var pw = document.getElementById('signinPassword'), pwErr = document.getElementById('signinPasswordErr');
    var ok = true;
    var emOk = em.value.trim() && isValidEmail(em.value.trim());
    setErr(em, emErr, !emOk); if (!emOk) ok = false;
    var pwOk = pw.value.length > 0;
    setErr(pw, pwErr, !pwOk); if (!pwOk) ok = false;
    return ok;
}

function validateSignup() {
    var nm = document.getElementById('signupName'),   nmErr = document.getElementById('signupNameErr');
    var em = document.getElementById('signupEmail'),  emErr = document.getElementById('signupEmailErr');
    var pw = document.getElementById('signupPassword'), pwErr = document.getElementById('signupPasswordErr');
    var tr = document.getElementById('terms'), trErr = document.getElementById('signupTermsErr');
    var ok = true;
    var nmOk = nm.value.trim().length > 0;
    setErr(nm, nmErr, !nmOk); if (!nmOk) ok = false;
    var emOk = em.value.trim() && isValidEmail(em.value.trim());
    setErr(em, emErr, !emOk); if (!emOk) ok = false;
    var pwOk = pw.value.length >= 8;
    setErr(pw, pwErr, !pwOk); if (!pwOk) ok = false;
    var trOk = tr.checked;
    trErr.classList.toggle('is-visible', !trOk); if (!trOk) ok = false;
    return ok;
}

// ── INIT ─────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    // Clear field errors on input
    document.querySelectorAll('.lp-field__input').forEach(function(inp) {
        inp.addEventListener('input', function() {
            this.classList.remove('is-invalid');
            var err = document.getElementById(this.id + 'Err');
            if (err) err.classList.remove('is-visible');
        });
    });
    document.getElementById('terms').addEventListener('change', function() {
        document.getElementById('signupTermsErr').classList.remove('is-visible');
    });

    // Password strength
    var pwInp   = document.getElementById('signupPassword');
    var pwWrap  = document.getElementById('pwStrengthWrap');
    var pwBar   = document.getElementById('pwStrengthBar');
    var pwLbl   = document.getElementById('pwStrengthLabel');
    var levels  = [
        { max: 1, pct: '20%',  color: '#ef4444', label: 'Very weak' },
        { max: 2, pct: '40%',  color: '#f97316', label: 'Weak' },
        { max: 3, pct: '60%',  color: '#eab308', label: 'Fair' },
        { max: 4, pct: '80%',  color: '#3b82f6', label: 'Good' },
        { max: 5, pct: '100%', color: '#22c55e', label: 'Strong' }
    ];
    pwInp.addEventListener('input', function() {
        var v = this.value;
        if (!v.length) { pwWrap.classList.remove('is-visible'); return; }
        pwWrap.classList.add('is-visible');
        var score = 0;
        if (v.length >= 8)  score++;
        if (v.length >= 12) score++;
        if (/[a-z]/.test(v) && /[A-Z]/.test(v)) score++;
        if (/[0-9]/.test(v)) score++;
        if (/[^a-zA-Z0-9]/.test(v)) score++;
        var step = levels.find(function(s) { return score <= s.max; }) || levels[levels.length - 1];
        pwBar.style.width = step.pct;
        pwBar.style.backgroundColor = step.color;
        pwLbl.textContent = step.label;
        pwLbl.style.color = step.color;
    });
});

// ── FETCH WRAPPER ─────────────────────────────────────────
function postForm(url, formData) {
    return fetch(url, { method: 'POST', body: formData })
        .then(function(res) {
            if (!res.ok) throw new Error('Server error ' + res.status);
            return res.text();
        })
        .then(function(text) {
            try { return JSON.parse(text); }
            catch(e) { console.error('[GATZ] Raw response:', text); throw new Error('Unexpected server response.'); }
        });
}

// ── SIGN IN ───────────────────────────────────────────────
function handleSigninSubmit(event) {
    event.preventDefault();
    hideToast('signin');
    if (!validateSignin()) return false;
    var btn = document.getElementById('signinBtn'), orig = btn.textContent;
    btn.disabled = true; btn.textContent = 'Signing in…';
    postForm('../process/validateLogin.php', new FormData(event.target))
        .then(function(data) {
            if (data.success === true) {
                showToast('signin', 'Welcome back!', 'Login successful. Redirecting…', 'success');
                setTimeout(function() { window.location.href = '../views/admin_dashboard.php'; }, 1200);
            } else {
                showToast('signin', 'Login Failed', data.message || 'Invalid credentials.', 'error');
                btn.disabled = false; btn.textContent = orig;
            }
        })
        .catch(function(err) {
            showToast('signin', 'System Error', err.message, 'error');
            btn.disabled = false; btn.textContent = orig;
        });
    return false;
}

// ── SIGN UP ───────────────────────────────────────────────
function handleSignupSubmit(event) {
    event.preventDefault();
    hideToast('signup');
    if (!validateSignup()) return false;
    var btn = document.getElementById('signupBtn'), orig = btn.textContent;
    btn.disabled = true; btn.textContent = 'Creating account…';
    postForm('../process/validateRegistration.php', new FormData(event.target))
        .then(function(data) {
            if (data.success === true) {
                showToast('signup', 'Account Created!', 'Registration successful! Redirecting…', 'success');
                setTimeout(function() {
                    event.target.reset();
                    document.getElementById('pwStrengthWrap').classList.remove('is-visible');
                    switchTab('signin');
                }, 1500);
            } else {
                showToast('signup', 'Registration Failed', data.message || 'Error creating account.', 'error');
            }
            btn.disabled = false; btn.textContent = orig;
        })
        .catch(function(err) {
            showToast('signup', 'System Error', err.message, 'error');
            btn.disabled = false; btn.textContent = orig;
        });
    return false;
}

// ── GOOGLE AUTH ───────────────────────────────────────────
function handleGoogleAuth() {
    var client = google.accounts.oauth2.initTokenClient({
        client_id: '791478894702-qsmtnl2j9hnrbgfh4r0uo5gpqiur2db4.apps.googleusercontent.com',
        scope: 'email profile',
        callback: function(res) { if (res.access_token) verifyGoogle(res.access_token); }
    });
    client.requestAccessToken();
}

function verifyGoogle(token) {
    fetch('../process/googleLogin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ access_token: token })
    })
    .then(function(res) { return res.text(); })
    .then(function(text) {
        var data;
        try { data = JSON.parse(text); } catch(e) { throw new Error('Invalid server response.'); }
        if (data.success === true) {
            showToast('signin', 'Welcome!', 'Logged in via Google. Redirecting…', 'success');
            setTimeout(function() { window.location.href = '../views/admin_dashboard.php'; }, 1200);
        } else {
            showToast('signin', 'Google Login Failed', data.message || 'Google auth failed.', 'error');
        }
    })
    .catch(function(err) {
        showToast('signin', 'System Error', err.message, 'error');
    });
}
</script>
</body>
</html>
<?php ob_end_flush(); ?>