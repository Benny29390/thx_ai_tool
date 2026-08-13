<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="/assets/css/install.css">
</head>
<body>
    <div class="install-container">
        <div class="install-card">
            <div class="install-header">
                <h1><?= APP_NAME ?></h1>
                <p class="version">Version <?= APP_VERSION ?></p>
            </div>

            <div class="install-progress">
                <div class="progress-steps">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <div class="step <?= $i < $step ? 'completed' : ($i === $step ? 'active' : '') ?>">
                            <span class="step-number"><?= $i ?></span>
                            <span class="step-label">
                                <?php
                                $labels = [
                                    1 => 'Voraussetzungen',
                                    2 => 'Datenbank',
                                    3 => 'Administrator',
                                    4 => 'API-Keys',
                                    5 => 'Abschluss'
                                ];
                                echo $labels[$i];
                                ?>
                            </span>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?= htmlspecialchars($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="install-content">
                <?php $this->getStepContent(); ?>
            </div>
        </div>
    </div>

    <script src="/assets/js/install.js"></script>
</body>
</html>
