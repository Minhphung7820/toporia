<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Welcome to Toporia Framework</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background: #f8f9fa; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: white; padding: 30px; border-radius: 0 0 10px 10px; }
        .feature { padding: 10px; margin: 5px 0; background: #f0f9ff; border-left: 4px solid #0ea5e9; }
        .button { display: inline-block; padding: 12px 24px; background: #4F46E5; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎉 Welcome to Toporia Framework!</h1>
        </div>
        <div class="content">
            <h2>Hello, <?= htmlspecialchars($userName) ?>!</h2>
            <p>We're excited to have you on board. Your account has been successfully created with email: <strong><?= htmlspecialchars($userEmail) ?></strong></p>

            <h3>✨ Features Available:</h3>
            <?php foreach ($features as $feature): ?>
                <div class="feature">✓ <?= htmlspecialchars($feature) ?></div>
            <?php endforeach; ?>

            <p style="text-align: center;">
                <a href="#" class="button">Get Started →</a>
            </p>

            <p><small>Account created at: <?= htmlspecialchars($timestamp) ?></small></p>
        </div>
        <div class="footer">
            <p>© 2025 Toporia Framework. All rights reserved.</p>
            <p>This is an automated email, please do not reply.</p>
        </div>
    </div>
</body>
</html>

