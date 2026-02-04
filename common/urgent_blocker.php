<style>
    /* --- BLOCKER OVERLAY --- */
    #notifBlocker {
        display: none; 
        position: fixed; inset: 0; 
        z-index: 2147483647; /* Max Z-Index to stay on top */
        background: rgba(15, 23, 42, 0.98); 
        backdrop-filter: blur(15px);
        align-items: center; justify-content: center;
        padding: 1rem;
        opacity: 0; transition: opacity 0.3s ease;
    }
    
    #notifBlocker.active { display: flex !important; opacity: 1 !important; }

    /* --- CARD DESIGN --- */
    .notif-card {
        background: #1e293b; 
        width: 100%; max-width: 600px; 
        border-radius: 16px; 
        box-shadow: 0 0 0 100vmax rgba(0,0,0,0.6), 0 25px 50px -12px rgba(0, 0, 0, 0.7);
        border: 1px solid #475569;
        overflow: hidden;
        display: flex; flex-direction: column;
        transform: scale(0.95); transition: transform 0.3s ease;
    }
    
    #notifBlocker.active .notif-card { transform: scale(1); }

    .notif-header {
        background: linear-gradient(135deg, #f43f5e, #e11d48);
        padding: 1.5rem; text-align: center; color: white;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    
    .notif-bell { font-size: 2.5rem; display: block; margin-bottom: 0.5rem; animation: bellShake 2s infinite; }
    
    @keyframes bellShake {
        0%, 50% { transform: rotate(0); } 
        5%, 15%, 25%, 35%, 45% { transform: rotate(10deg); } 
        10%, 20%, 30%, 40% { transform: rotate(-10deg); } 
    }

    .notif-title { font-size: 1.4rem; font-weight: 800; margin: 0; letter-spacing: -0.025em; }
    .notif-subtitle { opacity: 0.9; margin-top: 0.5rem; font-size: 0.95rem; }

    /* --- SCROLLABLE LIST --- */
    .notif-list {
        max-height: 45vh; overflow-y: auto; padding: 0; margin: 0; list-style: none;
        background: #1e293b;
    }

    /* Scrollbar Styling */
    .notif-list::-webkit-scrollbar { width: 8px; }
    .notif-list::-webkit-scrollbar-track { background: #0f172a; }
    .notif-list::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }

    .notif-item {
        display: flex; align-items: center; justify-content: space-between;
        padding: 1.25rem 1.5rem; border-bottom: 1px solid #334155;
        color: #f1f5f9; transition: background 0.2s;
    }
    .notif-item:hover { background: rgba(255,255,255,0.03); }

    .item-info { flex: 1; margin-right: 1rem; }
    .item-header { display: flex; align-items: center; gap: 10px; margin-bottom: 6px; }
    .item-title { font-weight: 700; font-size: 1rem; color: #f1f5f9; }
    .item-meta { font-size: 0.85rem; color: #94a3b8; display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
    
    .tag-badge { 
        background: rgba(255,255,255,0.08); padding: 2px 8px; 
        border-radius: 4px; font-family: monospace; color: #cbd5e1; border: 1px solid rgba(255,255,255,0.1);
    }
    .date-badge { color: #f43f5e; font-weight: 600; display: flex; align-items: center; gap: 4px; }

    /* --- BUTTONS --- */
    .btn-resolve {
        background: transparent; border: 1px solid #3b82f6; color: #3b82f6;
        padding: 8px 16px; border-radius: 8px; font-weight: 600; font-size: 0.85rem;
        cursor: pointer; transition: all 0.2s; white-space: nowrap;
        display: flex; align-items: center; gap: 6px; text-decoration: none;
    }
    .btn-resolve:hover { background: #3b82f6; color: white; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3); }
    .btn-resolve:active { transform: scale(0.95); }

    .notif-footer {
        padding: 1.5rem; background: #0f172a; text-align: center;
        border-top: 1px solid #334155;
        display: flex; flex-direction: column; align-items: center; gap: 10px;
    }
    .footer-text { font-size: 0.85rem; color: #64748b; }
    
    .btn-scheduler {
        display: inline-flex; align-items: center; gap: 8px;
        background: #334155; color: #e2e8f0; text-decoration: none;
        padding: 10px 20px; border-radius: 8px; font-weight: 600; font-size: 0.9rem;
        transition: background 0.2s; border: 1px solid #475569;
    }
    .btn-scheduler:hover { background: #475569; color: white; }
</style>

<div id="notifBlocker">
    <div class="notif-card">
        <div class="notif-header">
            <span class="notif-bell">🔔</span>
            <h2 class="notif-title">Attention Required</h2>
            <p class="notif-subtitle">You have <strong id="overdueCount">0</strong> overdue events to resolve.</p>
        </div>
        
        <div class="notif-list" id="notifList">
            <div style="padding:2rem; text-align:center; color:#64748b;">Checking schedule...</div>
        </div>

        <div class="notif-footer">
            <span class="footer-text">🔒 System access is restricted until these items are addressed.</span>
            
            <a href="events_scheduler.php" class="btn-scheduler">
                📅 Go to Event Scheduler
            </a>
        </div>
    </div>
</div>

<script>
// Flag to track if the blocker *should* be active
window.isOverdue = false;

document.addEventListener("DOMContentLoaded", () => {
    fetchOverdueEvents();
    
    // ============================================================
    // 🛡️ ANTI-TAMPER PROTECTION (The "Annoying" Part)
    // ============================================================
    const observer = new MutationObserver((mutations) => {
        if (!window.isOverdue) return; // Only protect if actually blocked

        const blocker = document.getElementById('notifBlocker');
        const body = document.body;

        // 1. Check if they deleted the blocker HTML
        if (!blocker) {
            // RELOAD immediately to restore it (and trigger server-side check)
            location.reload(); 
            return;
        }

        // 2. Check if they removed the 'active' class (display:none)
        if (!blocker.classList.contains('active')) {
            blocker.classList.add('active');
        }

        // 3. Check if they re-enabled scrolling on body
        if (body.style.overflow !== 'hidden') {
            body.style.overflow = 'hidden';
        }
    });

    // Start watching the body and the blocker for ANY changes
    observer.observe(document.body, { childList: true, subtree: true, attributes: true });
});

async function fetchOverdueEvents() {
    try {
        // Fetch overdue events
        const response = await fetch('../process/getOverdue.php');
        const events = await response.json();

        const modal = document.getElementById('notifBlocker');
        const list = document.getElementById('notifList');
        const countSpan = document.getElementById('overdueCount');
        
        // Get current page file name
        const currentPage = window.location.pathname.split("/").pop();

        if (Array.isArray(events) && events.length > 0) {
            
            // --- WHITE LISTING ---
            // If user is on a "Fix It" page, do NOT block them.
            // Allowed pages: Scheduler itself, or specific group transaction pages
            const allowedPages = [
                'events_scheduler.php',
                'group_medication.php', 
                'group_vitamins.php', 
                'group_vaccination.php', 
                'group_checkup.php'
            ];
            
            if (allowedPages.includes(currentPage)) {
                return; // Allow access to fix issues
            }

            // --- ACTIVATE BLOCKER ---
            window.isOverdue = true; // Set flag for mutation observer
            modal.classList.add('active');
            document.body.style.overflow = 'hidden'; // Freeze scrolling
            list.innerHTML = '';
            countSpan.innerText = events.length;

            events.forEach(ev => {
                const row = document.createElement('div');
                row.className = 'notif-item';
                
                const icon = getIcon(ev.EVENT_TYPE);
                const targetPage = getTargetPage(ev.EVENT_TYPE);
                
                const rawDate = ev.END_DATE ? ev.END_DATE : ev.START_DATE;
                const displayDate = new Date(rawDate).toLocaleString('en-US', { 
                    month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' 
                });
                
                row.innerHTML = `
                    <div class="item-info">
                        <div class="item-header">
                            <span style="font-size:1.3rem;">${icon}</span>
                            <span class="item-title">${ev.EVENT_TYPE}: ${ev.ITEM_NAME}</span>
                        </div>
                        <div class="item-meta">
                            <span class="tag-badge">${ev.TAG_NO}</span>
                            <span>📍 ${ev.PEN_NAME}</span>
                            <span class="date-badge">📅 Due: ${displayDate}</span>
                        </div>
                    </div>
                    <a href="${targetPage}" class="btn-resolve">
                        Go to Page ➜
                    </a>
                `;
                list.appendChild(row);
            });
        }
    } catch (error) {
        console.error("Error checking schedule:", error);
    }
}

function getIcon(type) {
    const map = { 
        'Medication': '💊', 
        'Vitamins': '🍃', 
        'Vaccination': '💉', 
        'Checkup': '🩺' 
    };
    return map[type] || '📅';
}

function getTargetPage(type) {
    const map = {
        'Medication': 'group_medication.php',
        'Vitamins': 'group_vitamins.php', 
        'Vaccination': 'group_vaccination.php',
        'Checkup': 'group_checkup.php' 
    };
    return map[type] || 'events_scheduler.php';
}
</script>