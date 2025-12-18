<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <title>Admin - Toporia</title>
    <?= vite_css('resources/js/admin.js') ?>
    <style>
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        #admin-app {
            min-height: 100vh;
        }
    </style>
</head>
<body>
    <div id="admin-app"></div>
    <?= vite('resources/js/admin.js') ?>
</body>
</html>
