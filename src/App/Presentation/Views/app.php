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
        <!-- Loading fallback -->
        <div style="text-align: center; padding: 50px; font-family: Arial, sans-serif;">
            <p>Loading Vue application...</p>
        </div>
    </div>
    <!-- Vite JavaScript -->
    <?= vite('resources/js/app.js') ?>

    <!-- Debug: Check if script loaded -->
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

    <!-- Fallback if Vue fails to load -->
    <noscript>
        <div style="text-align: center; padding: 50px;">
            <h1>JavaScript Required</h1>
            <p>Please enable JavaScript to view this application.</p>
        </div>
    </noscript>
</body>

</html>