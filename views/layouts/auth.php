<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Login') ?> - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1><?= APP_NAME ?></h1>
            </div>

            <?php if (!empty($flashMessages)): ?>
                <div class="flash-messages">
                    <?php foreach ($flashMessages as $type => $messages): ?>
                        <?php foreach ($messages as $message): ?>
                            <div class="alert alert-<?= htmlspecialchars($type) ?>">
                                <?= htmlspecialchars($message) ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?= $content ?>
        </div>
    </div>
</body>
</html>
