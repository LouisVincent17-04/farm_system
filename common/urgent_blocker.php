<style>
    /* ─────────────────────────────────────────
       urgent_blocker.php  — inline include
       ───────────────────────────────────────── */
    #notifBlocker {
        display: none;
        position: fixed; inset: 0;
        z-index: 2147483647;
        background: rgba(15, 23, 42, 0.98);
        backdrop-filter: blur(15px);
        align-items: center; justify-content: center;
        padding: 1rem;
        opacity: 0; transition: opacity 0.3s ease;
    }
    #notifBlocker.active { display: flex !important; opacity: 1 !important; }

    /* Card */
    .notif-card {
        background: #1e293b;
        width: 100%; max-width: 640px;
        border-radius: 20px;
        box-shadow: 0 0 0 100vmax rgba(0,0,0,0.55), 0 25px 50px -12px rgba(0,0,0,0.7);
        border: 1px solid #475569;
        overflow: hidden;
        display: flex; flex-direction: column;
        transform: scale(0.95); transition: transform 0.3s ease;
        max-height: 90vh;
    }
    #notifBlocker.active .notif-card { transform: scale(1); }

    /* Header */
    .notif-header {
        background: linear-gradient(135deg, #f43f5e, #e11d48);
        padding: 1.5rem; text-align: center; color: white;
        border-bottom: 1px solid rgba(255,255,255,0.1);
        flex-shrink: 0;
    }
    .notif-bell {
        font-size: 2.5rem; display: block; margin-bottom: 0.5rem;
        animation: bellShake 2s infinite;
    }
    @keyframes bellShake {
        0%,50%  { transform: rotate(0); }
        5%,15%,25%,35%,45%  { transform: rotate(10deg); }
        10%,20%,30%,40%     { transform: rotate(-10deg); }
    }
    .notif-title    { font-size: 1.4rem; font-weight: 800; margin: 0; letter-spacing: -0.025em; }
    .notif-subtitle { opacity: .9; margin-top: .5rem; font-size: .95rem; }

    /* Scrollable list */
    .notif-list {
        overflow-y: auto; padding: 0; margin: 0; list-style: none;
        background: #1e293b; flex: 1;
    }
    .notif-list::-webkit-scrollbar        { width: 8px; }
    .notif-list::-webkit-scrollbar-track  { background: #0f172a; }
    .notif-list::-webkit-scrollbar-thumb  { background: #334155; border-radius: 4px; }

    /* Each batch row */
    .notif-item {
        display: flex; align-items: center; justify-content: space-between;
        padding: 1.1rem 1.5rem; border-bottom: 1px solid #334155;
        color: #f1f5f9; transition: background .2s;
        gap: 1rem;
    }
    .notif-item:hover { background: rgba(255,255,255,.03); }
    .notif-item:last-child { border-bottom: none; }

    .item-info { flex: 1; min-width: 0; }
    .item-header { display: flex; align-items: center; gap: 10px; margin-bottom: 6px; }
    .item-title  { font-weight: 700; font-size: 1rem; color: #f1f5f9; }
    .item-meta   { font-size: .85rem; color: #94a3b8; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }

    .tag-badge {
        background: rgba(255,255,255,.08); padding: 2px 8px;
        border-radius: 4px; font-family: monospace; color: #cbd5e1;
        border: 1px solid rgba(255,255,255,.1);
    }
    .overdue-badge {
        background: rgba(239,68,68,.15); color: #f87171;
        padding: 2px 8px; border-radius: 4px; font-size: .75rem;
        border: 1px solid rgba(239,68,68,.25); font-weight: 700;
    }

    /* Resolve button */
    .btn-resolve {
        background: transparent; border: 1px solid #3b82f6; color: #3b82f6;
        padding: 8px 16px; border-radius: 8px; font-weight: 600; font-size: .85rem;
        cursor: pointer; transition: all .2s; white-space: nowrap;
        display: inline-flex; align-items: center; gap: 6px; text-decoration: none;
        flex-shrink: 0;
    }
    .btn-resolve:hover { background: #3b82f6; color: white; box-shadow: 0 4px 12px rgba(59,130,246,.3); }
    .btn-resolve:active { transform: scale(.95); }

    /* Footer */
    .notif-footer {
        padding: 1.25rem 1.5rem; background: #0f172a; text-align: center;
        border-top: 1px solid #334155;
        display: flex; flex-direction: column; align-items: center; gap: 10px;
        flex-shrink: 0;
    }
    .footer-text { font-size: .85rem; color: #64748b; }

    .btn-scheduler {
        display: inline-flex; align-items: center; gap: 8px;
        background: #334155; color: #e2e8f0; text-decoration: none;
        padding: 10px 20px; border-radius: 8px; font-weight: 600; font-size: .9rem;
        transition: background .2s; border: 1px solid #475569;
    }
    .btn-scheduler:hover { background: #475569; color: white; }

    /* Empty state */
    .notif-empty {
        padding: 3rem 2rem; text-align: center; color: #64748b;
    }
</style>

<div id="notifBlocker">
    <div class="notif-card">

        <div class="notif-header">
            <span class="notif-bell">🔔</span>
            <h2 class="notif-title">Attention Requiresssd</h2>
            <p class="notif-subtitle">
                <strong id="overdueGroupCount">0</strong> overdue task batch(es) —
                <strong id="overdueCount">0</strong> animal(s) affected.
            </p>
        </div>

        <div class="notif-list" id="notifList">
            <div class="notif-empty">Checking schedule…</div>
        </div>

        <div class="notif-footer">
            <span class="footer-text">
                🔒 System access is restricted until these items are addressed.
            </span>
            <a href="events_scheduler.php" class="btn-scheduler">
                📅 View All in Event Scheduler
            </a>
        </div>

    </div>
</div>

<script>
/* ─────────────────────────────────────────────────────────────────
   URGENT BLOCKER  –  Smart grouping by Event Type + Pen
   ───────────────────────────────────────────────────────────────── */
window.isOverdue = false;

document.addEventListener('DOMContentLoaded', () => {
    fetchOverdueEvents();

    /* ── Anti-tamper: re-activate blocker if DOM is mutated while active ── */
    const observer = new MutationObserver(() => {
        if (!window.isOverdue) return;
        const blocker = document.getElementById('notifBlocker');
        const body    = document.body;
        if (!blocker) { location.reload(); return; }
        if (!blocker.classList.contains('active')) blocker.classList.add('active');
        if (body.style.overflow !== 'hidden')       body.style.overflow = 'hidden';
    });
    observer.observe(document.body, { childList: true, subtree: true, attributes: true });
});

async function fetchOverdueEvents() {
    try {
        const response = await fetch('../process/getOverdue.php');
        const events   = await response.json();

        const modal         = document.getElementById('notifBlocker');
        const list          = document.getElementById('notifList');
        const countSpan     = document.getElementById('overdueCount');
        const groupCountSpan = document.getElementById('overdueGroupCount');

        /* ── Whitelisted pages — do NOT block ── */
        const currentPage   = window.location.pathname.split('/').pop();
        const allowedPages  = [
            'events_scheduler.php',
            'group_medication.php',
            'group_vitamins.php',
            'group_vaccination.php',
            'group_checkup.php'
        ];
        if (allowedPages.includes(currentPage)) return;

        if (!Array.isArray(events) || events.length === 0) return;

        /* ── Activate blocker ── */
        window.isOverdue          = true;
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        list.innerHTML               = '';
        countSpan.innerText          = events.length;

        /* ════════════════════════════════════════════════════════
           GROUP  by  EVENT_TYPE  +  PEN_ID  +  ITEM_ID
           This ensures each distinct task+location+supply combo
           becomes its own batch card with a direct resolve link.
           ════════════════════════════════════════════════════════ */
        const groups = {};

        events.forEach(ev => {
            const key = `${ev.EVENT_TYPE}|${ev.PEN_ID}|${ev.ITEM_ID ?? 'none'}`;

            if (!groups[key]) {
                groups[key] = {
                    type       : ev.EVENT_TYPE,
                    itemId     : ev.ITEM_ID,
                    itemName   : ev.ITEM_NAME || '—',
                    penId      : ev.PEN_ID,
                    penName    : ev.PEN_NAME,
                    buildingName: ev.BUILDING_NAME,
                    locationName: ev.LOCATION_NAME,
                    icon       : getIcon(ev.EVENT_TYPE),
                    targetPage : getTargetPage(ev.EVENT_TYPE),
                    ids        : [],
                    tags       : [],
                    earliestDue: ev.END_DATE
                };
            }

            groups[key].ids.push(ev.EVENT_ID);
            groups[key].tags.push(ev.TAG_NO);

            // Track earliest deadline within group
            if (ev.END_DATE < groups[key].earliestDue) {
                groups[key].earliestDue = ev.END_DATE;
            }
        });

        const groupList = Object.values(groups);
        groupCountSpan.innerText = groupList.length;

        /* ── Sort: most overdue first ── */
        groupList.sort((a, b) => a.earliestDue.localeCompare(b.earliestDue));

        /* ── Render ── */
        groupList.forEach(group => {
            const DISPLAY_LIMIT = 4;
            const tagPreview    = group.tags.slice(0, DISPLAY_LIMIT).join(', ');
            const extraCount    = group.tags.length - DISPLAY_LIMIT;
            const extraLabel    = extraCount > 0
                ? `<span style="color:#f43f5e; font-weight:700;">+${extraCount} more</span>`
                : '';

            // Calculate days overdue
            const daysOverdue = Math.floor(
                (Date.now() - new Date(group.earliestDue).getTime()) / 86400000
            );
            const overdueText = daysOverdue > 0
                ? `<span class="overdue-badge">⏰ ${daysOverdue}d overdue</span>`
                : `<span class="overdue-badge">Due now</span>`;

            const row = document.createElement('div');
            row.className = 'notif-item';
            row.innerHTML = `
                <div class="item-info">
                    <div class="item-header">
                        <span style="font-size:1.4rem;">${group.icon}</span>
                        <span class="item-title">${group.type} Batch</span>
                        ${overdueText}
                    </div>
                    <div class="item-meta">
                        <span class="tag-badge">🐷 ${group.tags.length} animal(s)</span>
                        <span>🏠 ${group.penName}</span>
                        <span style="color:#64748b;">📍 ${group.buildingName}</span>
                        ${group.itemName !== '—' && group.itemName !== 'N/A'
                            ? `<span style="color:#a78bfa;">💊 ${group.itemName}</span>`
                            : ''}
                    </div>
                    <div style="margin-top:6px; font-size:0.78rem; color:#64748b;">
                        Tags: ${tagPreview}${extraLabel ? ', ' + extraLabel : ''}
                    </div>
                </div>
                <a href="${group.targetPage}?event_ids=${group.ids.join(',')}"
                   class="btn-resolve">
                    Resolve ➜
                </a>
            `;
            list.appendChild(row);
        });

    } catch (error) {
        console.error('Error checking overdue schedule:', error);
        document.getElementById('notifList').innerHTML =
            '<div class="notif-empty">Unable to check schedule. Please refresh.</div>';
    }
}

function getIcon(type) {
    return { Medication: '💊', Vitamins: '🍃', Vaccination: '💉', Checkup: '🩺' }[type] || '📅';
}
function getTargetPage(type) {
    return {
        Medication : 'group_medication.php',
        Vitamins   : 'group_vitamins.php',
        Vaccination: 'group_vaccination.php',
        Checkup    : 'group_checkup.php'
    }[type] || 'events_scheduler.php';
}
</script>