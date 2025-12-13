<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($subject ?? 'Email') ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f4f4f4;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content {
            padding: 30px 20px;
        }
        .message {
            background: #f9f9f9;
            padding: 20px;
            border-left: 4px solid #667eea;
            margin: 20px 0;
        }
        .footer {
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #666;
            background: #f9f9f9;
        }
        .timestamp {
            color: #999;
            font-size: 11px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📧 <?= htmlspecialchars($subject ?? 'Email from Toporia') ?></h1>
        </div>
        <div class="content">
            <div class="message">
                <p><?= nl2br(htmlspecialchars($message ?? 'No message content')) ?></p>
            </div>
            <div class="timestamp">
                Sent at: <?= htmlspecialchars($timestamp ?? date('Y-m-d H:i:s')) ?>
            </div>
        </div>
        <div class="footer">
            <p>🚀 Sent from <strong>Toporia Framework</strong></p>
            <p>Queue + Mail System Test</p>
        </div>
    </div>
</body>
</html>

