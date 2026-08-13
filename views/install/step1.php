<h2>Voraussetzungen prüfen</h2>
<p>Das System prüft, ob alle Voraussetzungen erfüllt sind.</p>

<?php
$requirements = [
    'PHP Version >= 8.1' => version_compare(PHP_VERSION, '8.1.0', '>='),
    'PDO Extension' => extension_loaded('pdo'),
    'PDO MySQL Extension' => extension_loaded('pdo_mysql'),
    'PDO SQLite Extension' => extension_loaded('pdo_sqlite'),
    'cURL Extension' => extension_loaded('curl'),
    'JSON Extension' => extension_loaded('json'),
    'mbstring Extension' => extension_loaded('mbstring'),
    'config/ beschreibbar' => is_writable(CONFIG_PATH),
    'storage/ beschreibbar' => is_writable(STORAGE_PATH)
];

$allOk = !in_array(false, $requirements, true);
?>

<div class="requirements-list">
    <?php foreach ($requirements as $name => $ok): ?>
        <div class="requirement <?= $ok ? 'ok' : 'fail' ?>">
            <span class="icon"><?= $ok ? '&#10003;' : '&#10007;' ?></span>
            <span class="name"><?= htmlspecialchars($name) ?></span>
            <span class="status"><?= $ok ? 'OK' : 'Fehlt' ?></span>
        </div>
    <?php endforeach; ?>
</div>

<form method="post" class="install-form">
    <?php if ($allOk): ?>
        <button type="submit" class="btn btn-primary">Weiter</button>
    <?php else: ?>
        <p class="text-error">Bitte behebe die fehlenden Voraussetzungen und lade die Seite neu.</p>
        <button type="button" class="btn btn-secondary" onclick="location.reload()">Erneut prüfen</button>
    <?php endif; ?>
</form>
