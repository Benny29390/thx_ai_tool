<div class="error-page">
    <h1><?= htmlspecialchars($status ?? 500) ?></h1>
    <p class="error-message"><?= htmlspecialchars($message ?? 'Ein Fehler ist aufgetreten') ?></p>
    <a href="/" class="btn btn-primary">Zur Startseite</a>
</div>
