<?php
// ============================================================
// common/chat_support.php
// Include this in any PHP page — just the widget UI + JS
// The actual POST handling is in chat_endpoint.php
// ============================================================
?>
<style>
    /* ===== TOGGLE BUTTON ===== */
    #cb-toggle {
        position: fixed; bottom: 28px; right: 28px;
        width: 58px; height: 58px;
        background: #6366f1; color: #fff;
        border: none; border-radius: 50%;
        font-size: 26px; cursor: pointer;
        box-shadow: 0 6px 18px rgba(99,102,241,.45);
        z-index: 9999; transition: transform .2s;
    }
    #cb-toggle:hover { transform: scale(1.1); }

    /* ===== WINDOW ===== */
    #cb-window {
        display: none; flex-direction: column;
        position: fixed; bottom: 98px; right: 28px;
        width: 350px; height: 520px;
        background: #fff; border-radius: 18px;
        box-shadow: 0 10px 40px rgba(0,0,0,.18);
        z-index: 9999; overflow: hidden;
        font-family: 'Segoe UI', sans-serif;
    }
    #cb-window.open { display: flex; }

    /* ===== HEADER ===== */
    #cb-header {
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        color: #fff; padding: 16px 18px;
        display: flex; align-items: center; gap: 10px;
        flex-shrink: 0;
    }
    #cb-header .cb-avatar {
        width: 36px; height: 36px; border-radius: 50%;
        background: rgba(255,255,255,.25);
        display: flex; align-items: center; justify-content: center;
        font-size: 18px;
    }
    #cb-header .cb-info { flex: 1; }
    #cb-header .cb-info h4 { margin: 0; font-size: 15px; }
    #cb-header .cb-info span { font-size: 11px; opacity: .8; }
    #cb-close { cursor: pointer; font-size: 18px; opacity: .8; }
    #cb-close:hover { opacity: 1; }

    /* ===== MESSAGES ===== */
    #cb-messages {
        flex: 1; padding: 16px; overflow-y: auto;
        display: flex; flex-direction: column; gap: 10px;
        background: #f5f5fb;
    }

    .cb-msg {
        max-width: 92%; padding: 10px 14px;
        border-radius: 14px; font-size: 13.5px; line-height: 1.5;
    }
    .cb-msg.bot {
        background: #fff; color: #1e1b4b;
        align-self: flex-start; border-bottom-left-radius: 3px;
        box-shadow: 0 1px 4px rgba(0,0,0,.08);
        width: 100%;
    }
    .cb-msg.user {
        background: #6366f1; color: #fff;
        align-self: flex-end; border-bottom-right-radius: 3px;
    }

    /* ===== RESULT SECTIONS ===== */
    .cb-top-result { margin-top: 8px; }
    .cb-top-result a {
        display: flex; align-items: center; justify-content: space-between;
        background: linear-gradient(135deg, #eef2ff, #e0e7ff);
        color: #3730a3; text-decoration: none;
        padding: 10px 13px; border-radius: 10px;
        font-size: 13.5px; font-weight: 700;
        border: 1.5px solid #a5b4fc;
        transition: background .15s;
    }
    .cb-top-result a:hover { background: linear-gradient(135deg, #e0e7ff, #c7d2fe); }
    .cb-top-result-badge {
        font-size: 10px; font-weight: 700;
        background: #6366f1; color: #fff;
        padding: 2px 7px; border-radius: 20px;
        margin-bottom: 5px; display: inline-block;
        letter-spacing: 0.5px;
    }

    .cb-section-label {
        font-size: 10.5px; font-weight: 700;
        text-transform: uppercase; letter-spacing: 0.8px;
        color: #9ca3af; margin: 10px 0 5px;
    }

    .cb-related { display: flex; flex-direction: column; gap: 4px; }
    .cb-related a {
        display: flex; align-items: center; justify-content: space-between;
        background: #f9fafb; color: #4338ca;
        text-decoration: none; padding: 7px 11px;
        border-radius: 8px; font-size: 13px; font-weight: 600;
        border: 1px solid #e5e7eb; transition: background .15s;
    }
    .cb-related a:hover { background: #eef2ff; border-color: #c7d2fe; }

    .cb-also { display: flex; flex-direction: column; gap: 4px; }
    .cb-also a {
        display: flex; align-items: center; justify-content: space-between;
        background: #fafafa; color: #6b7280;
        text-decoration: none; padding: 6px 11px;
        border-radius: 8px; font-size: 12.5px; font-weight: 500;
        border: 1px solid #f3f4f6; transition: background .15s;
    }
    .cb-also a:hover { background: #f3f4f6; color: #4338ca; }

    .cb-conf {
        font-size: 10.5px; font-weight: 600;
        color: #818cf8; white-space: nowrap; margin-left: 6px;
    }
    .cb-conf.low { color: #d1d5db; }

    /* ===== TYPING INDICATOR ===== */
    .cb-typing {
        display: flex; gap: 4px; align-items: center;
        padding: 12px 14px;
    }
    .cb-typing span {
        width: 7px; height: 7px; background: #9ca3af;
        border-radius: 50%; animation: cb-bounce .9s infinite;
    }
    .cb-typing span:nth-child(2) { animation-delay: .15s; }
    .cb-typing span:nth-child(3) { animation-delay: .30s; }
    @keyframes cb-bounce {
        0%,60%,100% { transform: translateY(0); }
        30%          { transform: translateY(-6px); }
    }

    /* ===== INPUT AREA ===== */
    #cb-input-area {
        display: flex; padding: 10px 12px; gap: 8px;
        border-top: 1px solid #e5e7eb; background: #fff;
        flex-shrink: 0; align-items: center;
    }
    #cb-input {
        flex: 1; border: 1.5px solid #d1d5db; border-radius: 10px;
        padding: 9px 13px; font-size: 13.5px; outline: none;
        transition: border-color .2s;
    }
    #cb-input:focus { border-color: #6366f1; }
    #cb-input.cb-listening {
        border-color: #ef4444;
        background: #fff5f5;
        animation: cb-pulse-border 1.2s infinite;
    }
    @keyframes cb-pulse-border {
        0%, 100% { border-color: #ef4444; box-shadow: 0 0 0 0 rgba(239,68,68,0); }
        50%       { border-color: #f87171; box-shadow: 0 0 0 3px rgba(239,68,68,.15); }
    }

    #cb-send {
        background: #6366f1; color: #fff; border: none;
        border-radius: 10px; padding: 9px 16px;
        cursor: pointer; font-size: 13px; font-weight: 600;
        transition: background .15s;
    }
    #cb-send:hover { background: #4f46e5; }
    #cb-send:disabled { background: #a5b4fc; cursor: not-allowed; }

    /* ===== MIC BUTTON ===== */
    #cb-mic {
        width: 38px; height: 38px; flex-shrink: 0;
        border: none; border-radius: 50%;
        background: #f3f4f6; color: #6b7280;
        font-size: 16px; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        transition: background .2s, color .2s, transform .15s;
        position: relative;
    }
    #cb-mic:hover { background: #e0e7ff; color: #6366f1; }
    #cb-mic.listening {
        background: #ef4444; color: #fff;
        animation: cb-mic-pulse 1.2s infinite;
    }
    #cb-mic.listening:hover { background: #dc2626; }
    #cb-mic:disabled { opacity: .45; cursor: not-allowed; }
    @keyframes cb-mic-pulse {
        0%, 100% { box-shadow: 0 0 0 0 rgba(239,68,68,.5); }
        50%       { box-shadow: 0 0 0 7px rgba(239,68,68,0); }
    }

    #cb-mic .cb-mic-ring {
        position: absolute; inset: -4px;
        border-radius: 50%; border: 2px solid #ef4444;
        opacity: 0; transform: scale(1);
        transition: none;
    }
    #cb-mic.listening .cb-mic-ring {
        animation: cb-ring-expand 1.2s infinite;
    }
    #cb-mic.listening .cb-mic-ring:nth-child(2) { animation-delay: .4s; }
    @keyframes cb-ring-expand {
        0%   { opacity: .6; transform: scale(1); }
        100% { opacity: 0;  transform: scale(1.9); }
    }

    /* ===== VOICE STATUS TOAST ===== */
    #cb-voice-status {
        display: none;
        font-size: 11.5px; font-weight: 600;
        color: #ef4444; text-align: center;
        padding: 4px 12px; background: #fff5f5;
        border-top: 1px solid #fee2e2;
        flex-shrink: 0;
        letter-spacing: 0.2px;
    }
    #cb-voice-status.active { display: block; }

    /* ===== NO-MIC NOTICE ===== */
    #cb-no-voice {
        display: none;
        font-size: 11px; color: #9ca3af;
        text-align: center; padding: 3px 8px;
        flex-shrink: 0;
    }
</style>

<button id="cb-toggle" title="Chat with Assistant">🤖</button>

<div id="cb-window">
    <div id="cb-header">
        <div class="cb-avatar">🤖</div>
        <div class="cb-info">
            <h4>Mako AI</h4>
            <span>Powered by Python AI</span>
        </div>
        <span id="cb-close">✕</span>
    </div>

    <div id="cb-messages">
        <div class="cb-msg bot">
            Hi! 👋 I can help you navigate the system.<br><br>
            Try asking things like:<br>
            • <em>"I want to go to vaccination"</em><br>
            • <em>"Show me the feeding records"</em><br>
            • <em>"Where is the checkup page?"</em><br><br>
            🎙️ <strong>Tip:</strong> Press the mic button to speak your query!
        </div>
    </div>

    <div id="cb-voice-status">🔴 Listening… speak now</div>

    <div id="cb-input-area">
        <input type="text" id="cb-input" placeholder="Type or speak a message…" />

        <button id="cb-mic" title="Click to speak">
            <span class="cb-mic-ring"></span>
            <span class="cb-mic-ring"></span>
            🎙️
        </button>

        <button id="cb-send">Send</button>
    </div>

    <div id="cb-no-voice">🎙️ Voice not supported in this browser</div>
</div>

<script>
(function () {
    const toggle     = document.getElementById('cb-toggle');
    const cbWindow   = document.getElementById('cb-window');
    const closeBtn   = document.getElementById('cb-close');
    const sendBtn    = document.getElementById('cb-send');
    const input      = document.getElementById('cb-input');
    const messages   = document.getElementById('cb-messages');
    const micBtn     = document.getElementById('cb-mic');
    const voiceStat  = document.getElementById('cb-voice-status');
    const noVoiceEl  = document.getElementById('cb-no-voice');


    // ── Toggle open/close ──────────────────────────────────
    toggle.addEventListener('click', () => cbWindow.classList.toggle('open'));
    closeBtn.addEventListener('click', () => cbWindow.classList.remove('open'));

    // ── Send on button click or Enter ──────────────────────
    sendBtn.addEventListener('click', sendMessage);
    input.addEventListener('keydown', e => { if (e.key === 'Enter') sendMessage(); });

    // ══════════════════════════════════════════════════════
    // VOICE RECOGNITION SETUP
    // ══════════════════════════════════════════════════════
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    let recognition = null;
    let isListening = false;

    if (!SpeechRecognition) {
        micBtn.style.display = 'none';
        noVoiceEl.style.display = 'block';
    } else {
        recognition = new SpeechRecognition();
        recognition.continuous      = false;
        recognition.interimResults  = true;
        recognition.lang            = 'en-US';
        recognition.maxAlternatives = 1;

        recognition.addEventListener('result', e => {
            let transcript = '';
            for (let i = e.resultIndex; i < e.results.length; i++) {
                transcript += e.results[i][0].transcript;
            }
            input.value = transcript;

            if (e.results[e.results.length - 1].isFinal) {
                stopListening();
                if (transcript.trim()) {
                    setTimeout(sendMessage, 300);
                }
            }
        });

        recognition.addEventListener('start', () => {
            isListening = true;
            micBtn.classList.add('listening');
            input.classList.add('cb-listening');
            voiceStat.classList.add('active');
            input.placeholder = 'Listening…';
            sendBtn.disabled  = true;
        });

        recognition.addEventListener('end', () => { stopListening(); });

        recognition.addEventListener('error', e => {
            stopListening();
            const msgs = {
                'not-allowed':   '🚫 Microphone access denied. Please allow mic permissions.',
                'no-speech':     '🔇 No speech detected. Try again.',
                'network':       '🌐 Network error during speech recognition.',
                'audio-capture': '🎤 No microphone found.',
            };
            appendBotHTML(msgs[e.error] || `❌ Voice error: ${e.error}`);
        });

        micBtn.addEventListener('click', () => {
            if (isListening) {
                recognition.stop();
                stopListening();
            } else {
                input.value = '';
                try { recognition.start(); } catch(err) {}
            }
        });
    }

    function stopListening() {
        isListening = false;
        micBtn.classList.remove('listening');
        input.classList.remove('cb-listening');
        voiceStat.classList.remove('active');
        input.placeholder = 'Type or speak a message…';
        sendBtn.disabled  = false;
    }

    // ══════════════════════════════════════════════════════
    // TEST TRIGGER KEYWORDS
    // Add more entries here to support additional test commands
    // ══════════════════════════════════════════════════════
    const TEST_TRIGGERS = {
        'positive test transactions': 'positive_test_transactions',
        // 'positive test employees': 'positive_test_employees',
    };

    // ══════════════════════════════════════════════════════
    // SEND MESSAGE
    // ══════════════════════════════════════════════════════
    function sendMessage() {
        const text = input.value.trim();  // ← always first
        if (!text) return;

        // Stop listening if still active
        if (isListening && recognition) {
            recognition.stop();
            stopListening();
        }

        // ── Check for test trigger BEFORE sending to AI ──
        const textLower   = text.toLowerCase();
        const matchedTest = Object.keys(TEST_TRIGGERS).find(k => textLower.includes(k));

        if (matchedTest) {
            appendMsg(text, 'user');
            input.value = '';
            const typingEl = appendTyping();

            // Fetch registered devices from server, then show picker
            fetch('../common/get_runners.php')
            .then(r => r.json())
            .then(data => {
                typingEl.remove();

                if (!data.devices || data.devices.length === 0) {
                    appendBotHTML('❌ No devices registered. Run <code>test_runner_server.py</code> on a device first.');
                    return;
                }

                // Only one device — run immediately, no need to ask
                if (data.devices.length === 1) {
                    runTestOnDevice(TEST_TRIGGERS[matchedTest], data.devices[0]);
                    return;
                }

                // Multiple devices — show picker buttons
                let html = '🖥️ <strong>Which device should run this test?</strong><br><br>';
                html += '<div style="display:flex;flex-direction:column;gap:6px;">';
                data.devices.forEach(device => {
                    html += `<button onclick="runTestOnDevice('${TEST_TRIGGERS[matchedTest]}','${device}')"
                        style="background:#6366f1;color:white;border:none;padding:8px 12px;
                        border-radius:8px;cursor:pointer;font-weight:600;text-align:left;">
                        🖥️ ${device}
                    </button>`;
                });
                html += '</div>';
                appendBotHTML(html);
            })
            .catch(() => {
                typingEl.remove();
                appendBotHTML('❌ Could not fetch registered devices. Check server.');
            });

            return; // ← stop here, don't send to AI
        }

        // ── Normal AI flow ───────────────────────────────
        appendMsg(text, 'user');
        input.value      = '';
        input.disabled   = true;
        sendBtn.disabled = true;
        if (micBtn) micBtn.disabled = true;

        const typingEl   = appendTyping();
        const controller = new AbortController();
        const timeoutId  = setTimeout(() => controller.abort(), 8000);

        fetch('../chat_endpoint.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ message: text }),
            signal:  controller.signal
        })
        .then(r => {
            if (!r.ok) throw new Error('Server returned ' + r.status);
            return r.json();
        })
        .then(data => {
            clearTimeout(timeoutId);
            typingEl.remove();
            
            // Check for explicit connection errors returned from PHP wrapper
            if (data.status === 'error') {
                appendBotHTML(data.message);
            } else {
                renderBotResponse(data);
            }
        })
        .catch(err => {
            clearTimeout(timeoutId);
            typingEl.remove();
            if (err.name === 'AbortError') {
                appendBotHTML('⏱️ Request timed out. Make sure the Python AI server is running.');
            } else {
                appendBotHTML('❌ Error: ' + err.message);
            }
        })
        .finally(() => {
            input.disabled   = false;
            sendBtn.disabled = false;
            if (micBtn) micBtn.disabled = false;
            input.focus();
        });
    }

    // ── Split links into tiers ─────────────────────────────
    function splitLinks(links) {
        if (!links || links.length === 0) return { top: null, related: [], also: [] };
        const top  = links[0];
        const rest = links.slice(1);
        if (rest.length === 0) return { top, related: [], also: [] };
        const relatedCount = Math.ceil(rest.length / 2);
        return { top, related: rest.slice(0, relatedCount), also: rest.slice(relatedCount) };
    }

    // ── Render bot response ────────────────────────────────
    function renderBotResponse(data) {
        const div = document.createElement('div');
        div.className = 'cb-msg bot';

        if (!data.links || data.links.length === 0) {
            div.innerHTML = `<div>${escHtml(data.message)}</div>`;
            messages.appendChild(div);
            messages.scrollTop = messages.scrollHeight;
            return;
        }

        const { top, related, also } = splitLinks(data.links);
        let html = '';

        html += `<span class="cb-top-result-badge">⭐ Top Result</span>`;
        html += `<div class="cb-top-result">
            <a href="${escHtml(top.url)}">
                <span>📄 ${escHtml(top.label)}</span>
                ${top.confidence ? `<span class="cb-conf">${top.confidence}%</span>` : ''}
            </a>
        </div>`;

        if (related.length > 0) {
            html += `<div class="cb-section-label">🔍 Related Searches</div><div class="cb-related">`;
            related.forEach(link => {
                html += `<a href="${escHtml(link.url)}">
                    <span>📄 ${escHtml(link.label)}</span>
                    ${link.confidence ? `<span class="cb-conf">${link.confidence}%</span>` : ''}
                </a>`;
            });
            html += `</div>`;
        }

        if (also.length > 0) {
            html += `<div class="cb-section-label">💡 You May Also Want</div><div class="cb-also">`;
            also.forEach(link => {
                html += `<a href="${escHtml(link.url)}">
                    <span>📄 ${escHtml(link.label)}</span>
                    ${link.confidence ? `<span class="cb-conf low">${link.confidence}%</span>` : ''}
                </a>`;
            });
            html += `</div>`;
        }

        div.innerHTML = html;
        messages.appendChild(div);
        messages.scrollTop = messages.scrollHeight;
    }

    // ── Helpers ────────────────────────────────────────────
    function appendMsg(text, type) {
        const div = document.createElement('div');
        div.className   = `cb-msg ${type}`;
        div.textContent = text;
        messages.appendChild(div);
        messages.scrollTop = messages.scrollHeight;
    }

    function appendBotHTML(html) {
        const div = document.createElement('div');
        div.className = 'cb-msg bot';
        div.innerHTML = html;
        messages.appendChild(div);
        messages.scrollTop = messages.scrollHeight;
    }

    function appendTyping() {
        const div = document.createElement('div');
        div.className = 'cb-msg bot cb-typing';
        div.innerHTML = '<span></span><span></span><span></span>';
        messages.appendChild(div);
        messages.scrollTop = messages.scrollHeight;
        return div;
    }

    function escHtml(str) {
        if (!str) return '';
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // ── Run test on a specific registered device ───────────
    window.runTestOnDevice = function(testKey, device) {
        const typingEl = appendTyping();
        fetch('../common/chat_run_test.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ test: testKey, device: device })
        })
        .then(r => r.json())
        .then(data => {
            typingEl.remove();
            appendBotHTML(
                data.success
                    ? `🧪 ${data.message}`
                    : `❌ ${data.message}`
            );
        })
        .catch(() => {
            typingEl.remove();
            appendBotHTML(`❌ Could not reach runner on <strong>${device}</strong>.`);
        });
    };
})();
</script>