<?php

session_start();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FarmPro Navbar Component</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #e2e8f0;
            min-height: 100vh;
        }

        /* Navbar Component Styles */
        .navbar {
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(15px);
            border-bottom: 1px solid rgba(34, 197, 94, 0.2);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .navbar.scrolled {
            background: rgba(15, 23, 42, 0.98);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }

        .navbar-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        /* Brand Section */
        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 1.5rem;
            font-weight: bold;
            color: #22c55e;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .navbar-brand:hover {
            transform: scale(1.05);
        }

        .brand-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            box-shadow: 0 4px 15px rgba(34, 197, 94, 0.3);
            transition: all 0.3s ease;
        }

        .brand-icon:hover {
            box-shadow: 0 4px 15px rgba(34, 197, 94, 0.4);
            transform: scale(1.05);
        }

        .brand-text {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Navigation Menu */
        .navbar-menu {
            display: flex;
            align-items: center;
        }

        .nav-links {
            display: flex;
            gap: 0.5rem;
            list-style: none;
            align-items: center;
        }

        .nav-links li {
            position: relative;
        }

        .nav-links a {
            color: #cbd5e1;
            text-decoration: none;
            padding: 0.75rem 1.25rem;
            border-radius: 10px;
            transition: all 0.3s ease;
            font-weight: 500;
            font-size: 0.95rem;
            position: relative;
            display: block;
        }

        .nav-links a:hover {
            background: rgba(34, 197, 94, 0.1);
            color: #22c55e;
        }

        .nav-links a.active {
            background: rgba(34, 197, 94, 0.15);
            color: #22c55e;
            border: 1px solid rgba(34, 197, 94, 0.3);
        }

        /* Mobile Menu Toggle */
        .mobile-menu-toggle {
            display: none;
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(34, 197, 94, 0.2);
            color: #e2e8f0;
            padding: 0.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.2rem;
            transition: all 0.3s ease;
        }

        .mobile-menu-toggle:hover {
            background: rgba(34, 197, 94, 0.1);
            border-color: #22c55e;
        }

        /* Dropdown Menu */
        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(34, 197, 94, 0.2);
            border-radius: 12px;
            padding: 0.5rem 0;
            margin-top: 0.5rem;
            min-width: 200px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }

        .dropdown-menu.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-item {
            display: block;
            padding: 0.75rem 1rem;
            color: #cbd5e1;
            text-decoration: none;
            transition: all 0.3s ease;
            border-bottom: 1px solid rgba(30, 41, 59, 0.5);
        }

        .dropdown-item:last-child {
            border-bottom: none;
        }

        .dropdown-item:hover {
            background: rgba(34, 197, 94, 0.1);
            color: #22c55e;
            padding-left: 1.25rem;
        }

        /* Mobile Responsive Design */
        @media (max-width: 768px) {
            .navbar-container {
                padding: 0 1rem;
            }

            .nav-links {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: rgba(15, 23, 42, 0.98);
                backdrop-filter: blur(15px);
                flex-direction: column;
                padding: 1rem;
                border-top: 1px solid rgba(34, 197, 94, 0.2);
                gap: 0;
            }

            .nav-links.mobile-open {
                display: flex;
            }

            .nav-links li {
                width: 100%;
            }

            .nav-links a {
                display: block;
                width: 100%;
                padding: 1rem;
                border-radius: 8px;
                margin-bottom: 0.5rem;
                text-align: center;
            }

            .nav-links a:hover {
                background: rgba(34, 197, 94, 0.1);
                color: #22c55e;
            }

            .nav-links a.active {
                background: rgba(34, 197, 94, 0.15);
                color: #22c55e;
                border: 1px solid rgba(34, 197, 94, 0.4);
            }

            .mobile-menu-toggle {
                display: block;
            }
        }

        @media (max-width: 480px) {
            .navbar-brand {
                font-size: 1.2rem;
            }

            .brand-icon {
                width: 35px;
                height: 35px;
                font-size: 1.2rem;
            }
        }

        /* Demo Content */
        .demo-content {
            padding: 2rem;
            text-align: center;
            color: #94a3b8;
        }

        .demo-content h2 {
            color: #22c55e;
            margin-bottom: 1rem;
        }

        /* Modern Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(8px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .modal-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .modal-container {
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(34, 197, 94, 0.3);
            border-radius: 16px;
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            transform: scale(0.9) translateY(20px);
            transition: all 0.3s ease;
        }

        .modal-overlay.show .modal-container {
            transform: scale(1) translateY(0);
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(34, 197, 94, 0.2);
        }

        .modal-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .modal-icon.success {
            background: rgba(34, 197, 94, 0.2);
            color: #22c55e;
        }

        .modal-icon.error {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }

        .modal-icon.info {
            background: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
        }

        .modal-icon.warning {
            background: rgba(251, 191, 36, 0.2);
            color: #fbbf24;
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: bold;
            color: #e2e8f0;
            margin-bottom: 0.5rem;
        }

        .modal-body {
            color: #cbd5e1;
            line-height: 1.6;
            margin-bottom: 1.5rem;
            font-size: 1rem;
        }

        .modal-close-btn {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            box-shadow: 0 4px 15px rgba(34, 197, 94, 0.3);
        }

        .modal-close-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(34, 197, 94, 0.4);
        }

        .modal-close-btn:active {
            transform: translateY(0);
        }

        .close-icon {
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(34, 197, 94, 0.2);
            color: #cbd5e1;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1.2rem;
        }

        .close-icon:hover {
            background: rgba(34, 197, 94, 0.1);
            color: #22c55e;
            border-color: #22c55e;
        }
    </style>
</head>
<body>
    <!-- Modal Component -->
    <div class="modal-overlay" id="messageModal">
        <div class="modal-container">
            <div class="modal-header">
                <div>
                    <div class="modal-icon" id="modalIcon">✓</div>
                    <h3 class="modal-title" id="modalTitle">Notification</h3>
                </div>
                <button class="close-icon" id="closeModalBtn">✕</button>
            </div>
            <div class="modal-body" id="modalMessage">
                Your message will appear here
            </div>
            <button class="modal-close-btn" id="modalActionBtn">Close</button>
        </div>
    </div>

    <!-- Navbar Component -->
    <nav class="navbar" id="navbar">
        <div class="navbar-container">
            <!-- Brand Section -->
            <a href="#" class="navbar-brand">
                <div class="brand-icon">🌱</div>
                <span class="brand-text">FarmPro</span>
            </a>
            
            <!-- Main Navigation Menu -->
            <div class="navbar-menu">
                <!-- Navigation Links -->
                <ul class="nav-links" id="navLinks">
                    <li><a href="../views/admin_dashboard.php" class="<?php if($page=='admin_dashboard') echo "active"; ?>">Dashboard</a></li>
                    <li><a href="../views/costing_dashboard.php" class="<?php if($page=='costing') echo "active"; ?>">Costing</a></li>
                    <li><a href="../views/farm_dashboard.php" class="<?php if($page=='farm') echo "active"; ?>">Farm</a></li>
                    <li><a href="../views/analytics_dashboard.php"  class="<?php if($page=='analytics') echo "active"; ?>">Analytics</a></li>
                    <li><a href="../views/reports.php" class="<?php if($page=='reports') echo "active"; ?>">Reports</a></li>
                    <li><a href="../views/transactions.php" class="<?php if($page=='transactions') echo "active"; ?>">Transactions</a></li>
                    <li><a href="../views/settings.php" class="<?php if($page=='settings') echo "active"; ?>">Settings</a></li>
                    <li><a href="../views/audit_logs.php" class="<?php if($page=='audit_logs') echo "active"; ?>">Audit Logs</a></li>
                    <li><a href=
                    "
                    <?php if(isset($_SESSION['user'])) echo "../views/profile.php";  ?>
                    <?php if(!isset($_SESSION['user'])) echo "../views/login.php";  ?>
                    " 
                    class="<?php if($page=='login/register' || $page =='profile') echo "active"; ?>">
                    <?php if(isset($_SESSION['user'])) echo "Profile"; ?>
                    <?php if(!isset($_SESSION['user'])) echo "Login/Register"; ?>
                    </a></li>
                </ul>
                
                <!-- Mobile Menu Toggle -->
                <button class="mobile-menu-toggle" id="mobileMenuToggle">☰</button>
            </div>
        </div>
    </nav>


    <script>
        // Modal functionality
        class MessageModal {
            constructor() {
                this.modal = document.getElementById('messageModal');
                this.modalIcon = document.getElementById('modalIcon');
                this.modalTitle = document.getElementById('modalTitle');
                this.modalMessage = document.getElementById('modalMessage');
                this.closeBtn = document.getElementById('closeModalBtn');
                this.actionBtn = document.getElementById('modalActionBtn');
                
                this.init();
            }

            init() {
                this.bindEvents();
                this.checkURLParams();
            }

            bindEvents() {
                // Close modal on button click
                this.closeBtn.addEventListener('click', () => this.closeModal());
                this.actionBtn.addEventListener('click', () => this.closeModal());

                // Close modal on overlay click
                this.modal.addEventListener('click', (e) => {
                    if (e.target === this.modal) {
                        this.closeModal();
                    }
                });

                // Close modal on Escape key
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape' && this.modal.classList.contains('show')) {
                        this.closeModal();
                    }
                });
            }

            checkURLParams() {
                const urlParams = new URLSearchParams(window.location.search);
                const status = urlParams.get('status');
                const message = urlParams.get('msg');

                if (status && message) {
                    this.showModal(status, decodeURIComponent(message));
                    // Clean URL after showing modal
                    this.cleanURL();
                }
            }

            showModal(status, message) {
                // Set icon and title based on status
                const statusConfig = {
                    'success': {
                        icon: '✓',
                        title: 'Success',
                        class: 'success'
                    },
                    'error': {
                        icon: '✕',
                        title: 'Error',
                        class: 'error'
                    },
                    'warning': {
                        icon: '⚠',
                        title: 'Warning',
                        class: 'warning'
                    },
                    'info': {
                        icon: 'ℹ',
                        title: 'Information',
                        class: 'info'
                    }
                };

                const config = statusConfig[status] || statusConfig['info'];

                // Reset classes
                this.modalIcon.className = 'modal-icon ' + config.class;
                this.modalIcon.textContent = config.icon;
                this.modalTitle.textContent = config.title;
                this.modalMessage.textContent = message;

                // Show modal
                setTimeout(() => {
                    this.modal.classList.add('show');
                }, 100);
            }

            closeModal() {
                this.modal.classList.remove('show');
            }

            cleanURL() {
                const url = new URL(window.location);
                url.searchParams.delete('status');
                url.searchParams.delete('msg');
                window.history.replaceState({}, document.title, url);
            }
        }

        // Navbar functionality
        class FarmProNavbar {
            constructor() {
                this.navbar = document.getElementById('navbar');
                this.mobileMenuToggle = document.getElementById('mobileMenuToggle');
                this.navLinks = document.getElementById('navLinks');
                
                this.init();
            }

            init() {
                this.bindEvents();
                this.handleScroll();
            }

            bindEvents() {
                // Mobile menu toggle
                this.mobileMenuToggle.addEventListener('click', () => {
                    this.toggleMobileMenu();
                });

                // Navigation links - Remove preventDefault to allow normal navigation
                document.querySelectorAll('.nav-links a').forEach(link => {
                    link.addEventListener('click', (e) => {
                        // Don't prevent default - allow normal navigation
                        this.setActiveLink(link);
                        this.closeMobileMenu();
                        console.log('Navigating to:', link.getAttribute('href'));
                    });
                });

                // Scroll effects
                window.addEventListener('scroll', () => {
                    this.handleScroll();
                });

                // Keyboard accessibility
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') {
                        this.closeMobileMenu();
                    }
                });
            }

            toggleMobileMenu() {
                this.navLinks.classList.toggle('mobile-open');
                const isOpen = this.navLinks.classList.contains('mobile-open');
                this.mobileMenuToggle.textContent = isOpen ? '✕' : '☰';
            }

            closeMobileMenu() {
                this.navLinks.classList.remove('mobile-open');
                this.mobileMenuToggle.textContent = '☰';
            }

            setActiveLink(activeLink) {
                document.querySelectorAll('.nav-links a').forEach(link => {
                    link.classList.remove('active');
                });
                activeLink.classList.add('active');
            }

            handleScroll() {
                const scrolled = window.scrollY > 50;
                this.navbar.classList.toggle('scrolled', scrolled);
            }

            // Public method to set active navigation item
            setActiveNavigation(href) {
                const targetLink = document.querySelector(`.nav-links a[href="${href}"]`);
                if (targetLink) {
                    this.setActiveLink(targetLink);
                }
            }
        }

        // Initialize navbar and modal when DOM is loaded
        document.addEventListener('DOMContentLoaded', () => {
            window.farmProNavbar = new FarmProNavbar();
            window.messageModal = new MessageModal();
        });
    </script>
</body>
</html>