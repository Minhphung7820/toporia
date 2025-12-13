<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password</title>
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
            margin: 0 auto;
            background-color: #ffffff;
        }
        .email-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 2rem;
            text-align: center;
        }
        .email-header h1 {
            color: #ffffff;
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .email-body {
            padding: 2.5rem;
        }
        .email-body h2 {
            color: #2c3e50;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        .email-body p {
            color: #666;
            margin-bottom: 1.5rem;
            font-size: 1rem;
        }
        .button-container {
            text-align: center;
            margin: 2rem 0;
        }
        .button {
            display: inline-block;
            padding: 1rem 2.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            transition: transform 0.3s;
        }
        .button:hover {
            transform: translateY(-2px);
        }
        .email-footer {
            background-color: #f8f9fa;
            padding: 1.5rem;
            text-align: center;
            border-top: 1px solid #e0e0e0;
        }
        .email-footer p {
            color: #999;
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }
        .email-footer a {
            color: #667eea;
            text-decoration: none;
        }
        .warning-box {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 1rem;
            margin: 1.5rem 0;
        }
        .warning-box p {
            color: #856404;
            margin: 0;
            font-size: 0.875rem;
        }
        .token-info {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin: 1.5rem 0;
            font-family: monospace;
            font-size: 0.875rem;
            color: #666;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="email-header">
            <h1><?= htmlspecialchars($appName ?? 'Toporia Framework') ?></h1>
            <p style="color: rgba(255, 255, 255, 0.9); margin: 0;">Password Reset Request</p>
        </div>

        <!-- Body -->
        <div class="email-body">
            <h2>Hello!</h2>

            <p>You are receiving this email because we received a password reset request for your account.</p>

            <div class="button-container">
                <a href="<?= htmlspecialchars($resetUrl) ?>" class="button">Reset Password</a>
            </div>

            <p>If you did not request a password reset, no further action is required.</p>

            <div class="warning-box">
                <p><strong>⚠️ Security Notice:</strong> This link will expire in 1 hour. If you didn't request this, please ignore this email.</p>
            </div>

            <p style="color: #999; font-size: 0.875rem; margin-top: 2rem;">
                If the button doesn't work, copy and paste this link into your browser:
            </p>
            <div class="token-info">
                <?= htmlspecialchars($resetUrl) ?>
            </div>
        </div>

        <!-- Footer -->
        <div class="email-footer">
            <p>This is an automated message, please do not reply to this email.</p>
            <p>
                &copy; <?= now()->format('Y') ?> <?= htmlspecialchars($appName ?? 'Toporia Framework') ?>. All rights reserved.
            </p>
            <p>
                <a href="<?= htmlspecialchars($appUrl ?? '#') ?>">Visit our website</a>
            </p>
        </div>
    </div>
</body>
</html>

