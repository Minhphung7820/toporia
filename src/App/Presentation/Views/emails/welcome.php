<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Toporia</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f5f5f5;
        }

        .email-container {
            max-width: 600px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .email-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }

        .email-header h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .email-header p {
            font-size: 16px;
            opacity: 0.9;
        }

        .email-body {
            padding: 40px 30px;
        }

        .email-body h2 {
            font-size: 24px;
            color: #111827;
            margin-bottom: 20px;
        }

        .email-body p {
            font-size: 16px;
            color: #6b7280;
            margin-bottom: 20px;
            line-height: 1.8;
        }

        .email-button {
            display: inline-block;
            padding: 14px 32px;
            background: #000;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin: 20px 0;
            transition: background 0.2s;
        }

        .email-button:hover {
            background: #1f1f1f;
        }

        .email-footer {
            background-color: #f9fafb;
            padding: 30px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }

        .email-footer p {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 10px;
        }

        .email-footer a {
            color: #667eea;
            text-decoration: none;
        }
    </style>
</head>

<body>
    <div class="email-container">
        <div class="email-header">
            <h1>TOPORIA</h1>
            <p>Welcome to the Framework</p>
        </div>
        <div class="email-body">
            <h2>Welcome, <?= htmlspecialchars($name ?? 'User') ?>!</h2>
            <p>Thank you for joining <?= htmlspecialchars(config('app.name', 'Toporia')) ?>. We're excited to have you on board!</p>
            <p>You can now start building amazing applications with our framework.</p>
            <div style="text-align: center;">
                <a href="<?= config('app.url', 'http://localhost:8000') ?>" class="email-button">Get Started</a>
            </div>
            <p>If you have any questions, feel free to reach out to our support team.</p>
        </div>
        <div class="email-footer">
            <p>&copy; <?= date('Y') ?> <?= htmlspecialchars(config('app.name', 'Toporia')) ?>. All rights reserved.</p>
            <p><a href="<?= config('app.url', 'http://localhost:8000') ?>">Visit our website</a></p>
        </div>
    </div>
</body>

</html>