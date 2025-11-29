<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Toporia Framework - Vue SPA</title>

    <!-- Vite CSS (production only, dev handled by Vite) -->
    <?= vite_css('resources/js/app.js') ?>
</head>

<body>
    <div id="app">
        <!-- Professional Loading Screen -->
        <div class="loading-screen">
            <div class="loading-container">
                <!-- Animated Logo/Brand -->
                <div class="loading-brand">
                    <div class="brand-logo">
                        <div class="logo-circle">
                            <div class="logo-inner"></div>
                        </div>
                    </div>
                    <h1 class="brand-name">TOPORIA</h1>
                    <p class="brand-tagline">Professional PHP Framework</p>
                </div>

                <!-- Loading Spinner -->
                <div class="loading-spinner">
                    <div class="spinner-ring"></div>
                    <div class="spinner-ring"></div>
                    <div class="spinner-ring"></div>
                </div>

                <!-- Loading Text -->
                <div class="loading-text">
                    <span class="loading-dots">
                        <span>Loading</span>
                        <span class="dot dot-1">.</span>
                        <span class="dot dot-2">.</span>
                        <span class="dot dot-3">.</span>
                    </span>
                </div>

                <!-- Progress Bar (Optional) -->
                <div class="loading-progress">
                    <div class="progress-bar"></div>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* Loading Screen Styles */
        .loading-screen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            animation: fadeOut 0.5s ease-out forwards;
            animation-delay: 1s;
            opacity: 1;
        }

        @keyframes fadeOut {
            to {
                opacity: 0;
                visibility: hidden;
            }
        }

        .loading-container {
            text-align: center;
            color: white;
            max-width: 400px;
            padding: 2rem;
        }

        /* Brand Section */
        .loading-brand {
            margin-bottom: 3rem;
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .brand-logo {
            margin-bottom: 1.5rem;
        }

        .logo-circle {
            width: 80px;
            height: 80px;
            margin: 0 auto;
            position: relative;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                transform: scale(1);
                opacity: 1;
            }

            50% {
                transform: scale(1.05);
                opacity: 0.9;
            }
        }

        .logo-inner {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border: 3px solid rgba(255, 255, 255, 0.3);
            position: relative;
            overflow: hidden;
        }

        .logo-inner::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            animation: rotate 3s linear infinite;
        }

        @keyframes rotate {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        .brand-name {
            font-size: 2.5rem;
            font-weight: 800;
            letter-spacing: 0.1em;
            margin: 0 0 0.5rem 0;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            animation: fadeInUp 0.6s ease-out 0.2s both;
        }

        .brand-tagline {
            font-size: 0.9rem;
            opacity: 0.9;
            font-weight: 400;
            letter-spacing: 0.05em;
            margin: 0;
            animation: fadeInUp 0.6s ease-out 0.4s both;
        }

        /* Spinner */
        .loading-spinner {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 2rem 0;
            height: 60px;
            position: relative;
        }

        .spinner-ring {
            position: absolute;
            width: 50px;
            height: 50px;
            border: 4px solid rgba(255, 255, 255, 0.2);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        .spinner-ring:nth-child(1) {
            width: 50px;
            height: 50px;
            animation-duration: 1s;
        }

        .spinner-ring:nth-child(2) {
            width: 40px;
            height: 40px;
            animation-duration: 1.2s;
            animation-direction: reverse;
            border-top-color: rgba(255, 255, 255, 0.8);
        }

        .spinner-ring:nth-child(3) {
            width: 30px;
            height: 30px;
            animation-duration: 0.8s;
            border-top-color: rgba(255, 255, 255, 0.6);
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Loading Text */
        .loading-text {
            margin: 1.5rem 0;
            font-size: 1rem;
            font-weight: 500;
            opacity: 0.9;
        }

        .loading-dots {
            display: inline-block;
        }

        .dot {
            animation: dotPulse 1.4s ease-in-out infinite;
            opacity: 0;
        }

        .dot-1 {
            animation-delay: 0s;
        }

        .dot-2 {
            animation-delay: 0.2s;
        }

        .dot-3 {
            animation-delay: 0.4s;
        }

        @keyframes dotPulse {

            0%,
            80%,
            100% {
                opacity: 0;
            }

            40% {
                opacity: 1;
            }
        }

        /* Progress Bar */
        .loading-progress {
            width: 100%;
            height: 4px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 2px;
            overflow: hidden;
            margin-top: 2rem;
        }

        .progress-bar {
            height: 100%;
            background: white;
            border-radius: 2px;
            width: 0%;
            animation: progress 2s ease-in-out infinite;
        }

        @keyframes progress {
            0% {
                width: 0%;
                transform: translateX(0);
            }

            50% {
                width: 70%;
                transform: translateX(0);
            }

            100% {
                width: 100%;
                transform: translateX(0);
            }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .brand-name {
                font-size: 2rem;
            }

            .logo-circle {
                width: 60px;
                height: 60px;
            }

            .loading-container {
                padding: 1.5rem;
            }
        }

        /* Hide loading screen when Vue app is ready */
        #app:not(:empty) .loading-screen {
            display: none;
        }
    </style>
    <!-- Vite JavaScript -->
    <?= vite('resources/js/app.js') ?>

    <!-- Auto-hide loading screen when Vue app is ready -->
    <script>
        (function() {
            // Hide loading screen when Vue app mounts
            function hideLoadingScreen() {
                const loadingScreen = document.querySelector('.loading-screen');
                if (loadingScreen) {
                    loadingScreen.style.opacity = '0';
                    loadingScreen.style.transition = 'opacity 0.5s ease-out';
                    setTimeout(() => {
                        loadingScreen.style.display = 'none';
                    }, 500);
                }
            }

            // Try to detect when Vue app is ready
            // Method 1: Check if #app content changed (Vue mounted)
            const appElement = document.getElementById('app');
            if (appElement) {
                const observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.addedNodes.length > 0) {
                            // Check if Vue has mounted (app content exists)
                            const appContent = appElement.querySelector('.loading-screen');
                            if (!appContent || appElement.children.length > 1) {
                                hideLoadingScreen();
                                observer.disconnect();
                            }
                        }
                    });
                });

                observer.observe(appElement, {
                    childList: true,
                    subtree: true
                });
            }

            // Method 2: Fallback - hide after max wait time (3 seconds)
            setTimeout(function() {
                hideLoadingScreen();
            }, 3000);

            // Method 3: Hide on window load
            window.addEventListener('load', function() {
                setTimeout(hideLoadingScreen, 500);
            });
        })();
    </script>

    <?php if (env('APP_DEBUG', false)): ?>
        <!-- Debug: Check if script loaded (Development only) -->
        <script>
            window.addEventListener('DOMContentLoaded', function() {
                console.log('✅ DOM loaded');
                const scripts = document.querySelectorAll('script[type="module"]');
                console.log('📜 Module scripts found:', scripts.length);
                scripts.forEach((script, index) => {
                    console.log(`  Script ${index + 1}:`, script.src);
                });

                // Check if #app exists
                const appElement = document.getElementById('app');
                console.log('🎯 #app element:', appElement ? 'found' : 'NOT FOUND');
            });

            window.addEventListener('error', function(e) {
                console.error('❌ Global error:', e.message, e.filename, e.lineno);
            });
        </script>
    <?php endif; ?>

    <!-- Fallback if Vue fails to load -->
    <noscript>
        <div style="
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-align: center;
            padding: 2rem;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        ">
            <div>
                <h1 style="font-size: 2.5rem; margin-bottom: 1rem; font-weight: 700;">JavaScript Required</h1>
                <p style="font-size: 1.1rem; opacity: 0.9;">Please enable JavaScript to view this application.</p>
            </div>
        </div>
    </noscript>
</body>

</html>