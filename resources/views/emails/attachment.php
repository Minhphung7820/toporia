<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Email with Attachment</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #10b981; color: white; padding: 20px; text-align: center; }
        .content { background: #f4f4f4; padding: 20px; }
        .attachment { background: white; border: 2px dashed #10b981; padding: 15px; margin: 10px 0; text-align: center; }
        .footer { text-align: center; padding: 10px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📎 Email with Attachment</h1>
        </div>
        <div class="content">
            <h2>Hello, <?= htmlspecialchars($name) ?>!</h2>
            <p>This email contains an attached file for you.</p>

            <div class="attachment">
                <p><strong>📄 Attached File:</strong></p>
                <p><?= htmlspecialchars($filename) ?></p>
            </div>

            <p>Please check the attachment and let us know if you have any questions.</p>
        </div>
        <div class="footer">
            <p>© 2025 Toporia Framework</p>
        </div>
    </div>
</body>
</html>

