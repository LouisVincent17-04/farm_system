<div id="fp-global-loader" class="fp-loader-overlay">
    <div class="fp-loader-content">
        <div class="fp-spinner-ring"></div>
        <div class="fp-brand-icon">
            <img src="../common/tab-icon1.ico" alt="Logo" style="width: 40px; height: 40px; object-fit: contain;">
        </div>
    </div>
    <div class="fp-loader-text">Loading...</div>
</div>

<style>
    /* Prevent scrolling while loading */
    body.fp-loading {
        overflow: hidden;
    }

    .fp-loader-overlay {
        position: fixed;
        inset: 0; /* top: 0, left: 0, right: 0, bottom: 0 */
        background: #080f1a; /* FarmPro Dark Background */
        z-index: 999999; /* Stay above absolutely everything */
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        transition: opacity 0.5s ease-out, visibility 0.5s ease-out;
    }

    /* The class added by JS to fade it out */
    .fp-loader-overlay.hidden {
        opacity: 0;
        visibility: hidden;
    }

    .fp-loader-content {
        position: relative;
        width: 80px;
        height: 80px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1.5rem;
    }

    .fp-spinner-ring {
        position: absolute;
        width: 100%;
        height: 100%;
        border: 3px solid rgba(16, 185, 129, 0.1); /* Dim Emerald */
        border-top-color: #10b981; /* Bright Emerald */
        border-radius: 50%;
        animation: fp-spin 1s cubic-bezier(0.6, 0.2, 0.4, 0.8) infinite;
    }

    .fp-brand-icon {
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        animation: fp-pulse 2s ease-in-out infinite;
    }

    .fp-loader-text {
        color: #94a3b8; /* Slate text */
        font-family: 'DM Sans', sans-serif;
        font-size: 0.95rem;
        font-weight: 600;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        animation: fp-pulse 2s ease-in-out infinite;
    }

    @keyframes fp-spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    @keyframes fp-pulse {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.5; transform: scale(0.95); }
    }
</style>

<script>
    // Add loading class to body immediately so user can't scroll while loading
    document.body.classList.add('fp-loading');

    // Wait for the entire page (images, CSS, scripts) to finish loading
    window.addEventListener('load', function() {
        const loader = document.getElementById('fp-global-loader');
        
        if (loader) {
            // Add the hidden class to trigger the CSS fade-out transition
            loader.classList.add('hidden');
            
            // Allow scrolling again
            document.body.classList.remove('fp-loading');
            
            // Wait for the fade transition to finish (500ms), then completely remove it from the DOM
            setTimeout(() => {
                loader.remove();
            }, 500);
        }
    });
</script>