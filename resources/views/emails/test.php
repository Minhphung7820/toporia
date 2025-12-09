<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Test Email</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #4F46E5; color: white; padding: 20px; text-align: center; }
        .content { background: #f4f4f4; padding: 20px; }
        .footer { text-align: center; padding: 10px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 Test Email from Toporia</h1>
        </div>
        <div class="content">
            <h2>Hello, <?= htmlspecialchars($name) ?>!</h2>
            <p><?= nl2br(htmlspecialchars($content)) ?></p>
            <hr>
            <p><strong>Framework:</strong> <?= htmlspecialchars($framework) ?></p>
            <p><strong>Timestamp:</strong> <?= htmlspecialchars($timestamp) ?></p>
        </div>
        <div class="footer">
            <p>© 2025 Toporia Framework - Sent via <?= htmlspecialchars($framework) ?></p>
        </div>
    </div>
</body>
</html>

