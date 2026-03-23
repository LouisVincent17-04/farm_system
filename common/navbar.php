<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GATZ SmartFarm Navbar</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #e2e8f0; min-height: 100vh; }
        .navbar { background: rgba(15,23,42,0.95); backdrop-filter: blur(15px); border-bottom: 1px solid rgba(34,197,94,0.2); padding: 1rem 0; position: sticky; top: 0; z-index: 1000; transition: all 0.3s ease; }
        .navbar.scrolled { background: rgba(15,23,42,0.98); box-shadow: 0 10px 40px rgba(0,0,0,0.3); }
        .navbar-container { display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; padding: 0 2rem; }
        .navbar-brand { display: flex; align-items: center; gap: 1rem; font-size: 1.5rem; font-weight: bold; color: #22c55e; text-decoration: none; transition: all 0.3s ease; }
        .navbar-brand:hover { transform: scale(1.05); }
        .brand-icon { width: 45px; height: 45px; background: linear-gradient(135deg, #22c55e, #16a34a); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem; box-shadow: 0 4px 15px rgba(34,197,94,0.3); transition: all 0.3s ease; }
        .brand-text { background: linear-gradient(135deg, #22c55e, #16a34a); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .navbar-menu { display: flex; align-items: center; }
        .nav-links { display: flex; gap: 0.5rem; list-style: none; align-items: center; }
        .nav-links a { color: #cbd5e1; text-decoration: none; padding: 0.75rem 1.25rem; border-radius: 10px; transition: all 0.3s ease; font-weight: 500; font-size: 0.95rem; display: block; }
        .nav-links a:hover { background: rgba(34,197,94,0.1); color: #22c55e; }
        .nav-links a.active { background: rgba(34,197,94,0.15); color: #22c55e; border: 1px solid rgba(34,197,94,0.3); }
        .mobile-menu-toggle { display: none; background: rgba(30,41,59,0.6); border: 1px solid rgba(34,197,94,0.2); color: #e2e8f0; padding: 0.5rem; border-radius: 8px; cursor: pointer; font-size: 1.2rem; transition: all 0.3s ease; }
        .mobile-menu-toggle:hover { background: rgba(34,197,94,0.1); border-color: #22c55e; }
        @media (max-width: 1350px) {
            .navbar-container { padding: 0 1rem; }
            .nav-links { display: none; position: absolute; top: 100%; left: 0; right: 0; background: rgba(15,23,42,0.98); backdrop-filter: blur(15px); flex-direction: column; padding: 1rem; border-top: 1px solid rgba(34,197,94,0.2); gap: 0; }
            .nav-links.mobile-open { display: flex; }
            .nav-links li { width: 100%; }
            .nav-links a { width: 100%; padding: 1rem; margin-bottom: 0.5rem; text-align: center; }
            .mobile-menu-toggle { display: block; }
        }
        .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(8px); display: flex; align-items: center; justify-content: center; z-index: 2000; opacity: 0; visibility: hidden; transition: all 0.3s ease; }
        .modal-overlay.show { opacity: 1; visibility: visible; }
        .modal-container { background: rgba(15,23,42,0.95); backdrop-filter: blur(20px); border: 1px solid rgba(34,197,94,0.3); border-radius: 16px; padding: 2rem; max-width: 500px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.5); transform: scale(0.9) translateY(20px); transition: all 0.3s ease; }
        .modal-overlay.show .modal-container { transform: scale(1) translateY(0); }
        .modal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid rgba(34,197,94,0.2); }
        .modal-icon { width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 1rem; }
        .modal-icon.success { background: rgba(34,197,94,0.2); color: #22c55e; }
        .modal-icon.error   { background: rgba(239,68,68,0.2);  color: #ef4444; }
        .modal-title  { font-size: 1.5rem; font-weight: bold; color: #e2e8f0; margin-bottom: 0.5rem; }
        .modal-body   { color: #cbd5e1; line-height: 1.6; margin-bottom: 1.5rem; font-size: 1rem; }
        .modal-close-btn { background: linear-gradient(135deg, #22c55e, #16a34a); color: white; border: none; padding: 0.75rem 2rem; border-radius: 10px; font-size: 1rem; font-weight: 600; cursor: pointer; width: 100%; box-shadow: 0 4px 15px rgba(34,197,94,0.3); }
        .close-icon { background: rgba(30,41,59,0.6); border: 1px solid rgba(34,197,94,0.2); color: #cbd5e1; width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 1.2rem; }
        #notifBlocker { display: none; position: fixed; inset: 0; z-index: 2147483647; background: rgba(15,23,42,0.98); backdrop-filter: blur(15px); align-items: center; justify-content: center; padding: 1rem; opacity: 0; transition: opacity 0.3s ease; }
        #notifBlocker.active { display: flex !important; opacity: 1 !important; }
        .notif-card { background: #1e293b; width: 100%; max-width: 640px; border-radius: 20px; box-shadow: 0 0 0 100vmax rgba(0,0,0,0.55), 0 25px 50px -12px rgba(0,0,0,0.7); border: 1px solid #475569; overflow: hidden; display: flex; flex-direction: column; transform: scale(0.95); transition: transform 0.3s ease; max-height: 90vh; }
        #notifBlocker.active .notif-card { transform: scale(1); }
        .notif-header { background: linear-gradient(135deg, #f43f5e, #e11d48); padding: 1.5rem; text-align: center; color: white; border-bottom: 1px solid rgba(255,255,255,0.1); flex-shrink: 0; }
        .notif-bell { font-size: 2.5rem; display: block; margin-bottom: 0.5rem; animation: bellShake 2s infinite; }
        @keyframes bellShake { 0%,50% { transform: rotate(0); } 5%,15%,25%,35%,45% { transform: rotate(10deg); } 10%,20%,30%,40% { transform: rotate(-10deg); } }
        .notif-title    { font-size: 1.4rem; font-weight: 800; margin: 0; }
        .notif-subtitle { opacity: .9; margin-top: .5rem; font-size: .95rem; }
        .notif-list { overflow-y: auto; padding: 0; margin: 0; list-style: none; background: #1e293b; flex: 1; }
        .notif-list::-webkit-scrollbar { width: 8px; }
        .notif-list::-webkit-scrollbar-track { background: #0f172a; }
        .notif-list::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
        .notif-item { display: flex; align-items: center; justify-content: space-between; padding: 1.1rem 1.5rem; border-bottom: 1px solid #334155; color: #f1f5f9; gap: 1rem; }
        .notif-item:hover { background: rgba(255,255,255,.03); }
        .notif-item:last-child { border-bottom: none; }
        .item-info { flex: 1; min-width: 0; }
        .item-header { display: flex; align-items: center; gap: 10px; margin-bottom: 6px; }
        .item-title  { font-weight: 700; font-size: 1rem; color: #f1f5f9; }
        .item-meta   { font-size: .85rem; color: #94a3b8; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        .tag-badge { background: rgba(255,255,255,.08); padding: 2px 8px; border-radius: 4px; font-family: monospace; color: #cbd5e1; border: 1px solid rgba(255,255,255,.1); }
        .overdue-badge { background: rgba(239,68,68,.15); color: #f87171; padding: 2px 8px; border-radius: 4px; font-size: .75rem; border: 1px solid rgba(239,68,68,.25); font-weight: 700; }
        .btn-resolve { background: transparent; border: 1px solid #3b82f6; color: #3b82f6; padding: 8px 16px; border-radius: 8px; font-weight: 600; font-size: .85rem; cursor: pointer; transition: all .2s; white-space: nowrap; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; flex-shrink: 0; }
        .btn-resolve:hover { background: #3b82f6; color: white; box-shadow: 0 4px 12px rgba(59,130,246,.3); }
        .btn-resolve:active { transform: scale(.95); }
        .notif-footer { padding: 1.25rem 1.5rem; background: #0f172a; text-align: center; border-top: 1px solid #334155; display: flex; flex-direction: column; align-items: center; gap: 10px; flex-shrink: 0; }
        .footer-text { font-size: .85rem; color: #64748b; }
        .btn-scheduler { display: inline-flex; align-items: center; gap: 8px; background: #334155; color: #e2e8f0; text-decoration: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; font-size: .9rem; transition: background .2s; border: 1px solid #475569; }
        .btn-scheduler:hover { background: #475569; color: white; }
        .notif-empty { padding: 3rem 2rem; text-align: center; color: #64748b; }
    </style>
</head>
<body>

    <div class="modal-overlay" id="messageModal">
        <div class="modal-container">
            <div class="modal-header">
                <div>
                    <div class="modal-icon" id="modalIcon">&#10003;</div>
                    <h3 class="modal-title" id="modalTitle">Notification</h3>
                </div>
                <button class="close-icon" id="closeModalBtn">&#x2715;</button>
            </div>
            <div class="modal-body" id="modalMessage">Your message will appear here</div>
            <button class="modal-close-btn" id="modalActionBtn">Close</button>
        </div>
    </div>

    <?php if(isset($_SESSION['user'])): ?>
    <div id="notifBlocker">
        <div class="notif-card">
            <div class="notif-header">
                <span class="notif-bell">&#128276;</span>
                <h2 class="notif-title">Attention Required</h2>
                <p class="notif-subtitle">
                    <strong id="overdueGroupCount">0</strong> overdue task batch(es) &mdash;
                    <strong id="overdueCount">0</strong> animal(s) affected.
                </p>
            </div>
            <div class="notif-list" id="notifList">
                <div class="notif-empty">Checking schedule&hellip;</div>
            </div>
            <div class="notif-footer">
                <span class="footer-text">&#128274; System access is restricted until these items are addressed.</span>
                <a href="events_scheduler.php" class="btn-scheduler">&#128197; View All in Event Scheduler</a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <nav class="navbar" id="navbar">
        <div class="navbar-container">
            <a href="#" class="navbar-brand">
                <div class="brand-icon">&#127807;</div>
                <span class="brand-text">GATZ SmartFarm</span>
            </a>
            <div class="navbar-menu">
                <ul class="nav-links" id="navLinks">
                    <?php  if(isset($_SESSION['user'])): ?>
                        <li><a href="../views/admin_dashboard.php"     class="<?php if($page=='admin_dashboard') echo 'active'; ?>">Dashboard</a></li>
                        <?php if($_SESSION['user']['USER_TYPE'] > 3): ?>
                        <li><a href="../views/costing_dashboard.php"   class="<?php if($page=='costing')         echo 'active'; ?>">Costing</a></li>
                        <?php endif; ?>
                        <li><a href="../views/farm_dashboard.php"      class="<?php if($page=='farm')            echo 'active'; ?>">Farm</a></li>
                        <?php if($_SESSION['user']['USER_TYPE'] > 3): ?>
                        <li><a href="../views/analytics_dashboard.php" class="<?php if($page=='analytics')       echo 'active'; ?>">Analytics</a></li>
                        <li><a href="../views/reports.php"             class="<?php if($page=='reports')         echo 'active'; ?>">Reports</a></li>
                        <?php endif; ?>
                        <li><a href="../views/transactions.php"        class="<?php if($page=='transactions')    echo 'active'; ?>">Transactions</a></li>
                        <?php if($_SESSION['user']['USER_TYPE'] == 4): ?>
                        <li><a href="../views/settings.php"            class="<?php if($page=='settings')        echo 'active'; ?>">Settings</a></li>
                        <li><a href="../views/audit_logs.php"          class="<?php if($page=='audit_logs')      echo 'active'; ?>">Audit Logs</a></li>
                    <?php endif; ?>
                    
                        <li><a href="<?php echo isset($_SESSION['user']) ? '../views/profile.php' : '../globalxadminzportal/login.php'; ?>"
                                class="<?php if($page=='login/register' || $page=='profile') echo 'active'; ?>">
                                <?php echo isset($_SESSION['user']) ? 'Profile' : 'Login/Register'; ?>
                            </a>
                        </li>
                    <?php endif; ?>    
                </ul>
                <button class="mobile-menu-toggle" id="mobileMenuToggle">&#9776;</button>
            </div>
        </div>
    </nav>

    <script>
        /* Message Modal */
        class MessageModal {
            constructor() {
                this.modal        = document.getElementById('messageModal');
                this.modalIcon    = document.getElementById('modalIcon');
                this.modalTitle   = document.getElementById('modalTitle');
                this.modalMessage = document.getElementById('modalMessage');
                this.closeBtn     = document.getElementById('closeModalBtn');
                this.actionBtn    = document.getElementById('modalActionBtn');
                this.init();
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
                const cfg = { success:{icon:'V',title:'Success',cls:'success'}, error:{icon:'X',title:'Error',cls:'error'}, warning:{icon:'!',title:'Warning',cls:'warning'}, info:{icon:'i',title:'Information',cls:'info'} }[status] || {icon:'i',title:'Information',cls:'info'};
                this.modalIcon.className    = 'modal-icon ' + cfg.cls;
                this.modalIcon.textContent  = cfg.icon;
                this.modalTitle.textContent = cfg.title;
                this.modalMessage.textContent = message;
                setTimeout(() => this.modal.classList.add('show'), 100);
            }
            closeModal() { this.modal.classList.remove('show'); }
            cleanURL() { const url = new URL(window.location); url.searchParams.delete('status'); url.searchParams.delete('msg'); window.history.replaceState({}, document.title, url); }
        }

        /* Navbar */
        class FarmProNavbar {
            constructor() {
                this.navbar           = document.getElementById('navbar');
                this.mobileMenuToggle = document.getElementById('mobileMenuToggle');
                this.navLinks         = document.getElementById('navLinks');
                this.init();
            }
            init() { this.bindEvents(); this.handleScroll(); }
            bindEvents() {
                this.mobileMenuToggle.addEventListener('click', () => this.toggleMobileMenu());
                document.querySelectorAll('.nav-links a').forEach(link => link.addEventListener('click', () => { this.setActiveLink(link); this.closeMobileMenu(); }));
                window.addEventListener('scroll', () => this.handleScroll());
                document.addEventListener('keydown', e => { if (e.key === 'Escape') this.closeMobileMenu(); });
            }
            toggleMobileMenu() { this.navLinks.classList.toggle('mobile-open'); this.mobileMenuToggle.textContent = this.navLinks.classList.contains('mobile-open') ? 'X' : '='; }
            closeMobileMenu()  { this.navLinks.classList.remove('mobile-open'); this.mobileMenuToggle.textContent = '='; }
            setActiveLink(link){ document.querySelectorAll('.nav-links a').forEach(l => l.classList.remove('active')); link.classList.add('active'); }
            handleScroll()     { this.navbar.classList.toggle('scrolled', window.scrollY > 50); }
        }

        <?php if(isset($_SESSION['user'])): ?>
        /* Overdue Blocker */
        window.isOverdue = false;

        document.addEventListener('DOMContentLoaded', () => {
            fetchOverdueEvents();
            const observer = new MutationObserver(() => {
                if (!window.isOverdue) return;
                const blocker = document.getElementById('notifBlocker');
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

                const modal          = document.getElementById('notifBlocker');
                const list           = document.getElementById('notifList');
                const countSpan      = document.getElementById('overdueCount');
                const groupCountSpan = document.getElementById('overdueGroupCount');

                // Never block these pages - they are the fix pages
                const currentPage  = window.location.pathname.split('/').pop();
                const allowedPages = ['events_scheduler.php','group_medication.php','group_vitamins.php','group_vaccination.php','group_checkup.php'];
                if (allowedPages.includes(currentPage)) return;

                if (!Array.isArray(events) || events.length === 0) return;

                // Activate blocker
                window.isOverdue             = true;
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
                list.innerHTML               = '';
                countSpan.innerText          = events.length;

                // GROUP by EVENT_TYPE + PEN_ID + ITEM_ID
                // Each unique combo = one batch row with its own Fix link carrying all event IDs
                const groups = {};
                events.forEach(ev => {
                    const key = ev.EVENT_TYPE + '|' + ev.PEN_ID + '|' + (ev.ITEM_ID || 'none');
                    if (!groups[key]) {
                        groups[key] = {
                            type        : ev.EVENT_TYPE,
                            itemName    : ev.ITEM_NAME || 'Checkup',
                            penName     : ev.PEN_NAME,
                            buildingName: ev.BUILDING_NAME,
                            icon        : getIcon(ev.EVENT_TYPE),
                            targetPage  : getTargetPage(ev.EVENT_TYPE),
                            ids         : [],
                            tags        : [],
                            earliestDue : ev.END_DATE || ev.START_DATE
                        };
                    }
                    // CRITICAL: collect every EVENT_ID into the group
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
                    const extraHtml  = extra > 0 ? ' <span style="color:#f43f5e;font-weight:700;">+' + extra + ' more</span>' : '';

                    const daysOverdue = Math.floor((Date.now() - new Date(group.earliestDue).getTime()) / 86400000);
                    const overdueHtml = daysOverdue > 0
                        ? '<span class="overdue-badge">&#9200; ' + daysOverdue + 'd overdue</span>'
                        : '<span class="overdue-badge">Due now</span>';

                    // THE KEY FIX: ?event_ids= carries ALL IDs for this batch
                    const resolveUrl = group.targetPage + '?event_ids=' + group.ids.join(',');

                    const showItem = group.itemName !== 'Checkup' && group.itemName !== 'N/A' && group.itemName !== 'none'
                        ? '<span style="color:#a78bfa;">&#128138; ' + group.itemName + '</span>'
                        : '';

                    const row = document.createElement('div');
                    row.className = 'notif-item';
                    row.innerHTML =
                        '<div class="item-info">' +
                            '<div class="item-header">' +
                                '<span style="font-size:1.4rem;">' + group.icon + '</span>' +
                                '<span class="item-title">' + group.type + ' Batch</span>' +
                                overdueHtml +
                            '</div>' +
                            '<div class="item-meta">' +
                                '<span class="tag-badge">&#128055; ' + group.tags.length + ' animal(s)</span>' +
                                '<span>&#127968; ' + group.penName + '</span>' +
                                '<span style="color:#64748b;">&#128205; ' + group.buildingName + '</span>' +
                                showItem +
                            '</div>' +
                            '<div style="margin-top:6px;font-size:0.78rem;color:#64748b;">Tags: ' + tagPreview + extraHtml + '</div>' +
                        '</div>' +
                        '<a href="' + resolveUrl + '" class="btn-resolve">Fix &#10151;</a>';
                    list.appendChild(row);
                });

            } catch (err) {
                console.error('Scheduler blocker error:', err);
                document.getElementById('notifList').innerHTML = '<div class="notif-empty">Unable to check schedule. Please refresh.</div>';
            }
        }

        function getIcon(type) {
            var icons = { Medication:'&#128138;', Vitamins:'&#127807;', Vaccination:'&#128137;', Checkup:'&#129514;' };
            return icons[type] || '&#128197;';
        }
        function getTargetPage(type) {
            var pages = { Medication:'group_medication.php', Vitamins:'group_vitamins.php', Vaccination:'group_vaccination.php', Checkup:'group_checkup.php' };
            return pages[type] || 'events_scheduler.php';
        }
        <?php endif; ?>

        document.addEventListener('DOMContentLoaded', () => {
            window.farmProNavbar = new FarmProNavbar();
            window.messageModal  = new MessageModal();
        });
    </script>
</body>
</html>