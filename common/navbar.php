<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
<link rel="icon" type="image/x-icon" href="../common/tab-icon1.ico">
<style>
    /* ─── GLOBALS & NAMESPACED VARIABLES ─── */
    :root {
        --fp-nav-bg: rgba(15, 23, 42, 0.85);
        --fp-nav-scrolled: rgba(15, 23, 42, 0.98);
        --fp-nav-border: rgba(16, 185, 129, 0.15);
        --fp-emerald: #10b981;
        --fp-emerald-hover: #059669;
        --fp-red: #ef4444;
        --fp-red-hover: #dc2626;
        --fp-text-main: #f1f5f9;
        --fp-text-muted: #94a3b8;
        --fp-font: -apple-system, BlinkMacSystemFont, 'DM Sans', 'Segoe UI', Roboto, sans-serif;
        --fp-transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* Force zero margins on the body so the navbar sits perfectly flush */
    body { 
        margin: 0 !important; 
        padding: 0; 
        box-sizing: border-box; 
    }

    /* ─── NAVBAR ─── */
    .fp-navbar {
        background: var(--fp-nav-bg); 
        backdrop-filter: blur(16px); 
        -webkit-backdrop-filter: blur(16px);
        border-bottom: 1px solid var(--fp-nav-border); 
        padding: 0.85rem 0;
        position: sticky; 
        top: 0; 
        left: 0;
        width: 100%;
        z-index: 9999; /* Keeps navbar above all page content */
        transition: var(--fp-transition);
    }
    
    .fp-navbar.scrolled {
        background: var(--fp-nav-scrolled); 
        box-shadow: 0 10px 40px rgba(0,0,0,0.4); 
        border-bottom-color: rgba(16, 185, 129, 0.3);
    }
    
    .fp-nav-container {
        display: flex; justify-content: space-between; align-items: center;
        max-width: 1560px; margin: 0 auto; padding: 0 2rem;
    }

    .fp-navbar-brand {
        display: flex; align-items: center; gap: 0.75rem; text-decoration: none; transition: var(--fp-transition);
    }
    .fp-navbar-brand:hover { transform: scale(1.02); }
    
    .fp-brand-icon {
        width: 40px; height: 40px; background: linear-gradient(135deg, var(--fp-emerald), #047857);
        border-radius: 10px; display: flex; align-items: center; justify-content: center;
        color: white; font-size: 1.25rem; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
    }
    .fp-brand-text {
        font-size: 1.35rem; font-weight: 700; letter-spacing: -0.02em;
        background: linear-gradient(135deg, #34d399, var(--fp-emerald));
        -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
    }

    /* ─── NAV LINKS ─── */
    .fp-navbar-menu { display: flex; align-items: center; }
    .fp-nav-links { display: flex; gap: 0.25rem; list-style: none; align-items: center; margin: 0; padding: 0; }
    
    .fp-nav-links a {
        color: var(--fp-text-muted); text-decoration: none; padding: 0.65rem 1rem;
        border-radius: 8px; transition: var(--fp-transition); font-weight: 600; font-size: 0.9rem;
        display: flex; align-items: center; gap: 6px; border: 1px solid transparent;
    }
    .fp-nav-links a i { font-size: 0.85rem; opacity: 0.7; transition: opacity 0.2s; }
    
    .fp-nav-links a:hover { background: rgba(16, 185, 129, 0.1); color: var(--fp-emerald); }
    .fp-nav-links a:hover i { opacity: 1; }
    
    .fp-nav-links a.active {
        background: rgba(16, 185, 129, 0.15); color: var(--fp-emerald);
        border: 1px solid rgba(16, 185, 129, 0.25);
    }
    .fp-nav-links a.active i { opacity: 1; }

    .fp-mobile-toggle {
        display: none; background: rgba(30, 41, 59, 0.8); border: 1px solid var(--fp-nav-border);
        color: var(--fp-text-main); width: 40px; height: 40px; border-radius: 8px; cursor: pointer;
        font-size: 1.2rem; transition: var(--fp-transition); align-items: center; justify-content: center;
    }
    .fp-mobile-toggle:hover { background: rgba(16, 185, 129, 0.15); border-color: var(--fp-emerald); color: var(--fp-emerald); }

    /* ─── MOBILE RESPONSIVENESS ─── */
    @media (max-width: 1350px) {
        .fp-nav-container { padding: 0 1.25rem; }
        .fp-mobile-toggle { display: flex; }
        .fp-nav-links {
            display: none; position: absolute; top: 100%; left: 0; right: 0;
            background: var(--fp-nav-scrolled); backdrop-filter: blur(16px);
            flex-direction: column; padding: 1rem; border-top: 1px solid var(--fp-nav-border);
            box-shadow: 0 20px 40px rgba(0,0,0,0.5); gap: 0.25rem;
        }
        .fp-nav-links.mobile-open { display: flex; }
        .fp-nav-links li { width: 100%; }
        .fp-nav-links a { width: 100%; padding: 1rem; justify-content: center; font-size: 1rem; }
    }

    /* ─── MESSAGE MODAL (Generic Alerts) ─── */
    .fp-msg-overlay {
        position: fixed; inset: 0; background: rgba(0,0,0,0.75); backdrop-filter: blur(8px);
        display: flex; align-items: center; justify-content: center; z-index: 2000;
        opacity: 0; visibility: hidden; transition: var(--fp-transition);
    }
    .fp-msg-overlay.show { opacity: 1; visibility: visible; }
    
    .fp-msg-container {
        background: #1e293b; border: 1px solid var(--fp-nav-border); border-radius: 16px;
        padding: 0; max-width: 450px; width: 90%; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.6);
        transform: scale(0.95) translateY(10px); transition: var(--fp-transition); overflow: hidden;
    }
    .fp-msg-overlay.show .fp-msg-container { transform: scale(1) translateY(0); }
    
    .fp-msg-header {
        display: flex; align-items: center; justify-content: space-between; padding: 1.5rem;
        border-bottom: 1px solid rgba(255,255,255,0.05); background: rgba(15,23,42,0.5);
    }
    .fp-msg-header-left { display: flex; align-items: center; gap: 1rem; }
    
    .fp-msg-icon {
        width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;
    }
    .fp-msg-icon.success { background: rgba(16, 185, 129, 0.15); color: var(--fp-emerald); border: 1px solid rgba(16, 185, 129, 0.3); }
    .fp-msg-icon.error   { background: rgba(239, 68, 68, 0.15); color: var(--fp-red); border: 1px solid rgba(239, 68, 68, 0.3); }
    .fp-msg-icon.info    { background: rgba(59, 130, 246, 0.15); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3); }
    .fp-msg-icon.warning { background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); }
    
    .fp-msg-title { font-size: 1.2rem; font-weight: 700; color: #fff; margin: 0; }
    .fp-msg-close-icon { background: transparent; border: none; color: var(--fp-text-muted); cursor: pointer; font-size: 1.25rem; transition: color 0.2s; }
    .fp-msg-close-icon:hover { color: var(--fp-red); }
    
    .fp-msg-body { padding: 1.5rem; color: var(--fp-text-secondary); line-height: 1.6; font-size: 0.95rem; text-align: center; }
    .fp-msg-footer { padding: 1rem 1.5rem; background: rgba(15,23,42,0.5); border-top: 1px solid rgba(255,255,255,0.05); }
    
    .fp-msg-action-btn {
        background: var(--fp-emerald); color: #000; border: none; padding: 10px 0; border-radius: 8px;
        font-size: 0.95rem; font-weight: 700; cursor: pointer; width: 100%; font-family: var(--fp-font);
        transition: all 0.2s; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.25);
    }
    .fp-msg-action-btn:hover { background: #34d399; transform: translateY(-1px); }

    /* ─── NOTIF BLOCKER (OVERDUE TASKS) ─── */
    #fpNotifBlocker {
        display: none; position: fixed; inset: 0; z-index: 2147483647; background: rgba(15,23,42,0.95);
        backdrop-filter: blur(20px); align-items: center; justify-content: center; padding: 1rem;
        opacity: 0; transition: opacity 0.3s ease;
    }
    #fpNotifBlocker.active { display: flex !important; opacity: 1 !important; }
    
    .fp-overdue-card {
        background: #1e293b; width: 100%; max-width: 650px; border-radius: 20px;
        box-shadow: 0 0 0 100vmax rgba(0,0,0,0.6), 0 25px 50px -12px rgba(0,0,0,0.8);
        border: 1px solid #475569; overflow: hidden; display: flex; flex-direction: column;
        transform: scale(0.95); transition: transform 0.3s cubic-bezier(0.4,0,0.2,1); max-height: 90vh;
    }
    #fpNotifBlocker.active .fp-overdue-card { transform: scale(1); }
    
    .fp-overdue-header {
        background: linear-gradient(135deg, var(--fp-red), #991b1b); padding: 2rem 1.5rem; text-align: center;
        color: white; border-bottom: 1px solid rgba(255,255,255,0.1); flex-shrink: 0; position: relative; overflow: hidden;
    }
    .fp-overdue-header::after {
        content: '\f071'; font-family: "Font Awesome 6 Free"; font-weight: 900;
        position: absolute; right: -20px; top: -20px; font-size: 8rem; opacity: 0.1; transform: rotate(15deg);
    }
    .fp-overdue-bell { font-size: 2.5rem; display: block; margin-bottom: 0.75rem; animation: fpBellShake 2s infinite; color: #fca5a5; }
    @keyframes fpBellShake { 0%,50% { transform: rotate(0); } 5%,15%,25%,35%,45% { transform: rotate(10deg); } 10%,20%,30%,40% { transform: rotate(-10deg); } }
    
    .fp-overdue-title { font-size: 1.5rem; font-weight: 800; margin: 0 0 0.5rem 0; letter-spacing: -0.02em; }
    .fp-overdue-subtitle { opacity: 0.9; margin: 0; font-size: 0.95rem; font-weight: 500; }
    
    .fp-overdue-list { overflow-y: auto; padding: 0; margin: 0; list-style: none; background: #0f172a; flex: 1; }
    .fp-overdue-list::-webkit-scrollbar { width: 8px; }
    .fp-overdue-list::-webkit-scrollbar-track { background: #0f172a; }
    .fp-overdue-list::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
    
    .fp-overdue-item {
        display: flex; align-items: center; justify-content: space-between; padding: 1.25rem 1.5rem;
        border-bottom: 1px solid rgba(255,255,255,0.05); color: #f1f5f9; gap: 1rem; transition: background 0.2s;
    }
    .fp-overdue-item:hover { background: rgba(255,255,255,0.02); }
    .fp-overdue-item:last-child { border-bottom: none; }
    
    .fp-item-info { flex: 1; min-width: 0; }
    .fp-item-header { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
    .fp-item-title { font-weight: 700; font-size: 1.05rem; color: #fff; }
    
    .fp-item-meta { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; font-size: 0.85rem; color: var(--fp-text-secondary); }
    .fp-tag-badge { background: #1e293b; padding: 3px 8px; border-radius: 6px; font-family: var(--font-mono); color: #cbd5e1; border: 1px solid #334155; display: inline-flex; align-items: center; gap: 4px;}
    .fp-overdue-badge { background: rgba(239,68,68,0.15); color: #fca5a5; padding: 3px 8px; border-radius: 6px; font-size: 0.75rem; border: 1px solid rgba(239,68,68,0.3); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;}
    
    .fp-btn-resolve {
        background: transparent; border: 1px solid var(--blue); color: #60a5fa; padding: 8px 16px;
        border-radius: 8px; font-weight: 700; font-size: 0.85rem; cursor: pointer; transition: all 0.2s;
        white-space: nowrap; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; flex-shrink: 0;
    }
    .fp-btn-resolve:hover { background: var(--blue); color: white; box-shadow: 0 4px 12px rgba(59,130,246,0.3); transform: translateY(-1px); }
    
    .fp-overdue-footer { padding: 1.25rem 1.5rem; background: #0f172a; text-align: center; border-top: 1px solid rgba(255,255,255,0.05); display: flex; flex-direction: column; align-items: center; gap: 12px; flex-shrink: 0; }
    .fp-footer-text { font-size: 0.85rem; color: var(--fp-text-muted); font-weight: 600; }
    .fp-btn-scheduler {
        display: inline-flex; align-items: center; gap: 8px; background: #1e293b; color: #e2e8f0;
        text-decoration: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; font-size: 0.9rem;
        transition: all 0.2s; border: 1px solid #475569;
    }
    .fp-btn-scheduler:hover { background: #334155; color: white; border-color: #64748b; }
    .fp-notif-empty { padding: 3rem 2rem; text-align: center; color: var(--fp-text-muted); font-style: italic; }
</style>
<?php include '../common/loader.php'; ?>
<div class="fp-msg-overlay" id="fpMessageModal">
    <div class="fp-msg-container">
        <div class="fp-msg-header">
            <div class="fp-msg-header-left">
                <div class="fp-msg-icon" id="fpModalIcon"><i class="fa-solid fa-check"></i></div>
                <h3 class="fp-msg-title" id="fpModalTitle">Notification</h3>
            </div>
            <button class="fp-msg-close-icon" id="fpCloseModalBtn"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="fp-msg-body" id="fpModalMessage">Your message will appear here</div>
        <div class="fp-msg-footer">
            <button class="fp-msg-action-btn" id="fpModalActionBtn">Acknowledge</button>
        </div>
    </div>
</div>

<?php if(isset($_SESSION['user'])): ?>
<div id="fpNotifBlocker">
    <div class="fp-overdue-card">
        <div class="fp-overdue-header">
            <i class="fa-solid fa-bell fp-overdue-bell"></i>
            <h2 class="fp-overdue-title">Attention Required</h2>
            <p class="fp-overdue-subtitle">
                <strong id="fpOverdueGroupCount">0</strong> overdue task batch(es) &mdash;
                <strong id="fpOverdueCount">0</strong> animal(s) affected.
            </p>
        </div>
        <div class="fp-overdue-list" id="fpNotifList">
            <div class="fp-notif-empty"><i class="fa-solid fa-spinner fa-spin me-2"></i> Checking schedule...</div>
        </div>
        <div class="fp-overdue-footer">
            <span class="fp-footer-text"><i class="fa-solid fa-lock" style="color:var(--fp-red);"></i> System access is restricted until these items are addressed.</span>
            <a href="events_scheduler.php" class="fp-btn-scheduler"><i class="fa-solid fa-calendar-days"></i> View All in Event Scheduler</a>
        </div>
    </div>
</div>
<?php endif; ?>

<nav class="fp-navbar" id="fpNavbar">
    <div class="fp-nav-container">
        <a href="admin_dashboard.php" class="fp-navbar-brand">
            <div class="fp-brand-icon" style="background: none; box-shadow: none; padding: 0; width: 40px; height: 40px;">
                <img src="../common/tab-icon1.ico" alt="GATZ SmartFarm Logo" style="width: 40px; height: 40px; object-fit: contain; border-radius: 10px;">
            </div>
            <span class="fp-brand-text">GATZ SmartFarm</span>
        </a>
        <div class="fp-navbar-menu">
            <ul class="fp-nav-links" id="fpNavLinks">
                <?php  if(isset($_SESSION['user'])): ?>
                    <li><a href="../views/admin_dashboard.php" class="<?php if($page=='admin_dashboard') echo 'active'; ?>"><i class="fa-solid fa-house"></i> Dashboard</a></li>
                    <?php if($_SESSION['user']['USER_TYPE'] > 3): ?>
                    <li><a href="../views/costing_dashboard.php" class="<?php if($page=='costing') echo 'active'; ?>"><i class="fa-solid fa-money-bill-trend-up"></i> Costing</a></li>
                    <?php endif; ?>
                    <li><a href="../views/farm_dashboard.php" class="<?php if($page=='farm') echo 'active'; ?>"><i class="fa-solid fa-tractor"></i> Farm</a></li>
                    <?php if($_SESSION['user']['USER_TYPE'] > 3): ?>
                    <li><a href="../views/analytics_dashboard.php" class="<?php if($page=='analytics') echo 'active'; ?>"><i class="fa-solid fa-chart-line"></i> Analytics</a></li>
                    <li><a href="../views/reports.php" class="<?php if($page=='reports') echo 'active'; ?>"><i class="fa-solid fa-file-invoice"></i> Reports</a></li>
                    <?php endif; ?>
                    <li><a href="../views/transactions.php" class="<?php if($page=='transactions') echo 'active'; ?>"><i class="fa-solid fa-clipboard-list"></i> Transactions</a></li>
                    <?php if($_SESSION['user']['USER_TYPE'] == 4): ?>
                    <li><a href="../views/settings.php" class="<?php if($page=='settings') echo 'active'; ?>"><i class="fa-solid fa-gear"></i> Settings</a></li>
                    <li><a href="../views/audit_logs.php" class="<?php if($page=='audit_logs') echo 'active'; ?>"><i class="fa-solid fa-shield-halved"></i> Audit Logs</a></li>
                <?php endif; ?>
                
                    <li><a href="<?php echo isset($_SESSION['user']) ? '../views/profile.php' : '../globalxadminzportal/login.php'; ?>"
                            class="<?php if($page=='login/register' || $page=='profile') echo 'active'; ?>">
                            <i class="fa-solid fa-user-astronaut"></i> <?php echo isset($_SESSION['user']) ? 'Profile' : 'Login'; ?>
                        </a>
                    </li>
                <?php endif; ?>    
            </ul>
            <button class="fp-mobile-toggle" id="fpMobileToggle"><i class="fa-solid fa-bars"></i></button>
        </div>
    </div>
</nav>

<script>
    /* ─── MESSAGE MODAL CLASS ─── */
    class FarmProMessageModal {
        constructor() {
            this.modal        = document.getElementById('fpMessageModal');
            this.modalIcon    = document.getElementById('fpModalIcon');
            this.modalTitle   = document.getElementById('fpModalTitle');
            this.modalMessage = document.getElementById('fpModalMessage');
            this.closeBtn     = document.getElementById('fpCloseModalBtn');
            this.actionBtn    = document.getElementById('fpModalActionBtn');
            if(this.modal) this.init();
        }
        init() { this.bindEvents(); this.checkURLParams(); }
        bindEvents() {
            this.closeBtn.addEventListener('click',  () => this.closeModal());
            this.actionBtn.addEventListener('click', () => this.closeModal());
            this.modal.addEventListener('click', e => { if (e.target === this.modal) this.closeModal(); });
            document.addEventListener('keydown', e => { if (e.key === 'Escape' && this.modal.classList.contains('show')) this.closeModal(); });
        }
        checkURLParams() {
            const p = new URLSearchParams(window.location.search);
            if (p.get('status') && p.get('msg')) {
                this.showModal(p.get('status'), decodeURIComponent(p.get('msg')));
                this.cleanURL();
            }
        }
        showModal(status, message) {
            const cfg = { 
                success: { icon: '<i class="fa-solid fa-check"></i>', title: 'Success', cls: 'success' }, 
                error:   { icon: '<i class="fa-solid fa-xmark"></i>', title: 'Error', cls: 'error' }, 
                warning: { icon: '<i class="fa-solid fa-triangle-exclamation"></i>', title: 'Warning', cls: 'warning' }, 
                info:    { icon: '<i class="fa-solid fa-info"></i>', title: 'Information', cls: 'info' } 
            }[status] || { icon: '<i class="fa-solid fa-info"></i>', title: 'Information', cls: 'info' };
            
            this.modalIcon.className  = 'fp-msg-icon ' + cfg.cls;
            this.modalIcon.innerHTML  = cfg.icon;
            this.modalTitle.textContent = cfg.title;
            this.modalMessage.textContent = message;
            setTimeout(() => this.modal.classList.add('show'), 100);
        }
        closeModal() { this.modal.classList.remove('show'); }
        cleanURL() { const url = new URL(window.location); url.searchParams.delete('status'); url.searchParams.delete('msg'); window.history.replaceState({}, document.title, url); }
    }

    /* ─── NAVBAR CLASS ─── */
    class FarmProNavbar {
        constructor() {
            this.navbar           = document.getElementById('fpNavbar');
            this.mobileMenuToggle = document.getElementById('fpMobileToggle');
            this.navLinks         = document.getElementById('fpNavLinks');
            if(this.navbar) this.init();
        }
        init() { this.bindEvents(); this.handleScroll(); }
        bindEvents() {
            this.mobileMenuToggle.addEventListener('click', () => this.toggleMobileMenu());
            document.querySelectorAll('.fp-nav-links a').forEach(link => link.addEventListener('click', () => { this.setActiveLink(link); this.closeMobileMenu(); }));
            window.addEventListener('scroll', () => this.handleScroll());
            document.addEventListener('keydown', e => { if (e.key === 'Escape') this.closeMobileMenu(); });
        }
        toggleMobileMenu() { 
            this.navLinks.classList.toggle('mobile-open'); 
            this.mobileMenuToggle.innerHTML = this.navLinks.classList.contains('mobile-open') ? '<i class="fa-solid fa-xmark"></i>' : '<i class="fa-solid fa-bars"></i>'; 
        }
        closeMobileMenu()  { this.navLinks.classList.remove('mobile-open'); this.mobileMenuToggle.innerHTML = '<i class="fa-solid fa-bars"></i>'; }
        setActiveLink(link){ document.querySelectorAll('.fp-nav-links a').forEach(l => l.classList.remove('active')); link.classList.add('active'); }
        handleScroll()     { this.navbar.classList.toggle('scrolled', window.scrollY > 50); }
    }

    <?php if(isset($_SESSION['user'])): ?>
    /* ─── OVERDUE TASK BLOCKER LOGIC ─── */
    window.fpIsOverdue = false;

    document.addEventListener('DOMContentLoaded', () => {
        fetchOverdueEvents();
        const observer = new MutationObserver(() => {
            if (!window.fpIsOverdue) return;
            const blocker = document.getElementById('fpNotifBlocker');
            if (!blocker) { location.reload(); return; }
            if (!blocker.classList.contains('active')) blocker.classList.add('active');
            if (document.body.style.overflow !== 'hidden') document.body.style.overflow = 'hidden';
        });
        observer.observe(document.body, { childList: true, subtree: true, attributes: true });
    });

    async function fetchOverdueEvents() {
        try {
            // 1. Auto-sync pending events that already have transaction records
            const syncFd = new FormData();
            syncFd.append('action', 'auto_sync_status');
            await fetch('../process/eventManager.php', { method: 'POST', body: syncFd });

            // 2. Fetch remaining overdue events
            const response = await fetch('../process/getOverdue.php');
            const events   = await response.json();

            const modal          = document.getElementById('fpNotifBlocker');
            const list           = document.getElementById('fpNotifList');
            const countSpan      = document.getElementById('fpOverdueCount');
            const groupCountSpan = document.getElementById('fpOverdueGroupCount');

            // Never block these pages - they are the fix pages
            const currentPage  = window.location.pathname.split('/').pop();
            const allowedPages = ['events_scheduler.php','group_medication.php','group_vitamins.php','group_vaccination.php','group_checkup.php'];
            if (allowedPages.includes(currentPage)) return;

            if (!Array.isArray(events) || events.length === 0) return;

            // Activate blocker
            window.fpIsOverdue = true;
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
            list.innerHTML = '';
            countSpan.innerText = events.length;

            // GROUP by EVENT_TYPE + PEN_ID + ITEM_ID
            const groups = {};
            events.forEach(ev => {
                const key = ev.EVENT_TYPE + '|' + ev.PEN_ID + '|' + (ev.ITEM_ID || 'none');
                if (!groups[key]) {
                    groups[key] = {
                        type        : ev.EVENT_TYPE,
                        itemName    : ev.ITEM_NAME || 'Checkup',
                        penName     : ev.PEN_NAME,
                        buildingName: ev.BUILDING_NAME,
                        icon        : getFpIcon(ev.EVENT_TYPE),
                        targetPage  : getFpTargetPage(ev.EVENT_TYPE),
                        ids         : [],
                        tags        : [],
                        earliestDue : ev.END_DATE || ev.START_DATE
                    };
                }
                groups[key].ids.push(ev.EVENT_ID);
                groups[key].tags.push(ev.TAG_NO);
                const due = ev.END_DATE || ev.START_DATE;
                if (due < groups[key].earliestDue) groups[key].earliestDue = due;
            });

            const groupList = Object.values(groups);
            groupCountSpan.innerText = groupList.length;
            groupList.sort((a, b) => a.earliestDue.localeCompare(b.earliestDue));

            groupList.forEach(group => {
                const LIMIT      = 4;
                const tagPreview = group.tags.slice(0, LIMIT).join(', ');
                const extra      = group.tags.length - LIMIT;
                const extraHtml  = extra > 0 ? ' <span style="color:var(--fp-red);font-weight:700;">+' + extra + ' more</span>' : '';

                const daysOverdue = Math.floor((Date.now() - new Date(group.earliestDue).getTime()) / 86400000);
                const overdueHtml = daysOverdue > 0
                    ? '<span class="fp-overdue-badge"><i class="fa-solid fa-clock"></i> ' + daysOverdue + 'd overdue</span>'
                    : '<span class="fp-overdue-badge">Due now</span>';

                const resolveUrl = group.targetPage + '?event_ids=' + group.ids.join(',');

                const showItem = group.itemName !== 'Checkup' && group.itemName !== 'N/A' && group.itemName !== 'none'
                    ? '<span class="fp-tag-badge" style="color:#a78bfa; border-color: rgba(168,85,247,0.3);"><i class="fa-solid fa-box-open"></i> ' + group.itemName + '</span>'
                    : '';

                const row = document.createElement('div');
                row.className = 'fp-overdue-item';
                row.innerHTML =
                    '<div class="fp-item-info">' +
                        '<div class="fp-item-header">' +
                            '<span style="font-size:1.4rem;">' + group.icon + '</span>' +
                            '<span class="fp-item-title">' + group.type + ' Batch</span>' +
                            overdueHtml +
                        '</div>' +
                        '<div class="fp-item-meta">' +
                            '<span class="fp-tag-badge"><i class="fa-solid fa-paw"></i> ' + group.tags.length + ' animal(s)</span>' +
                            '<span class="fp-tag-badge"><i class="fa-solid fa-layer-group"></i> ' + group.penName + '</span>' +
                            '<span class="fp-tag-badge" style="color:var(--fp-text-muted);"><i class="fa-solid fa-location-dot"></i> ' + group.buildingName + '</span>' +
                            showItem +
                        '</div>' +
                        '<div style="margin-top:8px;font-size:0.8rem;color:var(--fp-text-muted);">Tags: ' + tagPreview + extraHtml + '</div>' +
                    '</div>' +
                    '<a href="' + resolveUrl + '" class="fp-btn-resolve">Fix <i class="fa-solid fa-arrow-right"></i></a>';
                list.appendChild(row);
            });

        } catch (err) {
            console.error('Scheduler blocker error:', err);
            document.getElementById('fpNotifList').innerHTML = '<div class="fp-notif-empty"><i class="fa-solid fa-triangle-exclamation"></i> Unable to check schedule. Please refresh.</div>';
        }
    }

    function getFpIcon(type) {
        var icons = { 
            Medication: '<i class="fa-solid fa-pills" style="color: #3b82f6;"></i>', 
            Vitamins: '<i class="fa-solid fa-flask" style="color: #10b981;"></i>', 
            Vaccination: '<i class="fa-solid fa-syringe" style="color: #a855f7;"></i>', 
            Checkup: '<i class="fa-solid fa-stethoscope" style="color: #f59e0b;"></i>' 
        };
        return icons[type] || '<i class="fa-solid fa-calendar-check" style="color: #64748b;"></i>';
    }
    function getFpTargetPage(type) {
        var pages = { Medication:'group_medication.php', Vitamins:'group_vitamins.php', Vaccination:'group_vaccination.php', Checkup:'group_checkup.php' };
        return pages[type] || 'events_scheduler.php';
    }
    <?php endif; ?>

    document.addEventListener('DOMContentLoaded', () => {
        window.farmProNavbar = new FarmProNavbar();
        window.messageModal  = new FarmProMessageModal();
    });
</script>