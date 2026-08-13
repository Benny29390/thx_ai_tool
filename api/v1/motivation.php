<?php
/**
 * Motivation API - Generiert tägliche Motivationsnachrichten mit KI
 * Inkl. Tagesmotto und persönlicher Ansprache
 */

use Core\{Response, Auth, Database};

require_once SERVICES_PATH . '/AIService.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    Response::error('Methode nicht erlaubt', 405);
}

$db = Database::getInstance();
$userId = Auth::id();
$userName = Auth::user()['name'] ?? 'Nutzer';
$firstName = explode(' ', $userName)[0];

// Datum-Infos
$today = date('Y-m-d');
$dayOfMonth = (int) date('j');
$month = (int) date('n');
$weekdayEn = date('l');

$weekdays = [
    'Sunday' => 'Sonntag',
    'Monday' => 'Montag',
    'Tuesday' => 'Dienstag',
    'Wednesday' => 'Mittwoch',
    'Thursday' => 'Donnerstag',
    'Friday' => 'Freitag',
    'Saturday' => 'Samstag'
];
$weekday = $weekdays[$weekdayEn] ?? $weekdayEn;

// Tagesmotto / Welttage / Besondere Tage
$dailyMottos = [
    // Januar
    '1-1' => 'Neujahr - Der perfekte Tag für einen Neuanfang',
    '1-27' => 'Holocaust-Gedenktag - Ein Tag der Erinnerung',
    // Februar
    '2-2' => 'Murmeltiertag - Zeit alte Gewohnheiten zu überdenken',
    '2-14' => 'Valentinstag - Der Tag der Liebe und Wertschaetzung',
    '2-20' => 'Welttag der sozialen Gerechtigkeit',
    // Maerz
    '3-8' => 'Internationaler Frauentag',
    '3-14' => 'Pi-Tag - Ein Tag für Mathematik und Präzision',
    '3-20' => 'Weltglueckstag - Verbreite Freude',
    '3-21' => 'Welttag der Poesie - Lass Worte fliessen',
    '3-22' => 'Weltwassertag',
    // April
    '4-1' => 'Tag des Spaßes - Lachen ist die beste Medizin',
    '4-7' => 'Weltgesundheitstag',
    '4-22' => 'Tag der Erde - Denke an unseren Planeten',
    '4-23' => 'Welttag des Buches - Perfekt zum Schreiben',
    // Mai
    '5-1' => 'Tag der Arbeit - Feiere deine Leistungen',
    '5-3' => 'Internationaler Tag der Pressefreiheit',
    '5-4' => 'Star Wars Tag - Moege die Macht mit dir sein',
    '5-15' => 'Internationaler Tag der Familie',
    '5-17' => 'Weltfernmeldetag - Kommunikation verbindet',
    // Juni
    '6-5' => 'Weltumwelttag',
    '6-8' => 'Welttag der Ozeane',
    '6-21' => 'Sommeranfang - Die laengsten Tage des Jahres',
    // Juli
    '7-17' => 'Welt-Emoji-Tag',
    '7-30' => 'Internationaler Tag der Freundschaft',
    // August
    '8-8' => 'Internationaler Tag des Bieres',
    '8-12' => 'Internationaler Tag der Jugend',
    '8-19' => 'Welttag der humanitaeren Hilfe',
    // September
    '9-8' => 'Weltbildungstag',
    '9-21' => 'Internationaler Tag des Friedens',
    '9-27' => 'Welttourismustag',
    // Oktober
    '10-1' => 'Internationaler Tag der aelteren Menschen',
    '10-4' => 'Welttierschutztag',
    '10-5' => 'Welttag der Lehrer',
    '10-10' => 'Welttag der psychischen Gesundheit',
    '10-16' => 'Welternaehrungstag',
    '10-31' => 'Halloween - Zeit für Kreativität',
    // November
    '11-3' => 'Weltmaennertag',
    '11-13' => 'Weltnettigkeitstag - Sei freundlich',
    '11-16' => 'Internationaler Tag der Toleranz',
    '11-21' => 'Welttag des Fernsehens',
    // Dezember
    '12-3' => 'Internationaler Tag der Menschen mit Behinderungen',
    '12-5' => 'Internationaler Tag des Ehrenamts',
    '12-10' => 'Tag der Menschenrechte',
    '12-21' => 'Winteranfang - Die kuerzesten Tage des Jahres',
    '12-24' => 'Heiligabend - Zeit für Familie und Besinnlichkeit',
    '12-25' => 'Weihnachten - Frohe Festtage',
    '12-31' => 'Silvester - Das Jahr geht zu Ende',
];

// Wochentags-Mottos als Fallback
$weekdayMottos = [
    'Monday' => 'Montag - Der Tag des Neustarts',
    'Tuesday' => 'Dienstag - Der Tag der Produktivität',
    'Wednesday' => 'Mittwoch - Die Wochenmitte ist erreicht',
    'Thursday' => 'Donnerstag - Auf der Zielgeraden zum Wochenende',
    'Friday' => 'Freitag - Feiere deine Wochenleistung',
    'Saturday' => 'Samstag - Zeit für kreative Projekte',
    'Sunday' => 'Sonntag - Der Tag der Erholung und Inspiration',
];

// Tagesmotto bestimmen
$dateKey = $month . '-' . $dayOfMonth;
$dailyMotto = $dailyMottos[$dateKey] ?? $weekdayMottos[$weekdayEn] ?? "Ein neuer Tag voller Möglichkeiten";

// Bei forceNew wird das Caching übersprungen
$forceNew = isset($_GET['force']) && $_GET['force'] === '1';

if (!$forceNew) {
    $cached = $db->queryOne(
        "SELECT motivation FROM daily_motivations WHERE user_id = ? AND date = ?",
        [$userId, $today]
    );

    if ($cached) {
        Response::success(['motivation' => $cached['motivation']]);
    }
}

// Settings laden
$settings = [];
foreach ($db->query("SELECT setting_key, setting_value FROM settings") as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$settings = \Core\Settings::decryptMap($settings);

// KI-Motivation generieren
try {
    // Schnelles, guenstiges Modell: GPT-3.5 Turbo als sicherer Fallback
    $model = $db->queryOne(
        "SELECT model_id, provider FROM ai_models WHERE is_active = 1 AND model_id IN ('gpt-3.5-turbo', 'gpt-4o-mini', 'claude-3-haiku-20240307') ORDER BY cost_input ASC LIMIT 1"
    );
    $modelId = $model['model_id'] ?? 'gpt-3.5-turbo';
    $provider = $model['provider'] ?? 'openai';

    // API Key holen
    $apiKey = $provider === 'anthropic'
        ? ($settings['anthropic_api_key'] ?? '')
        : ($settings['openai_api_key'] ?? '');

    if (empty($apiKey)) {
        throw new \Exception('Kein API-Key konfiguriert');
    }

    // Tageszeit
    $hour = (int) date('H');
    if ($hour < 12) {
        $timeContext = 'Es ist Morgen.';
    } elseif ($hour < 14) {
        $timeContext = 'Es ist Mittagszeit.';
    } elseif ($hour < 18) {
        $timeContext = 'Es ist Nachmittag.';
    } else {
        $timeContext = 'Es ist Abend.';
    }

    $systemPrompt = "Du bist ein lässiger, smarter Motivator mit Humor.
Generiere einen kurzen Tagesspruch für {$firstName}.

Kontext: {$timeContext} Es ist {$weekday}. Tagesmotto: \"{$dailyMotto}\".

Regeln:
- MAXIMAL 1-2 kurze Sätze (unter 120 Zeichen!)
- Locker, cool, mit einem Augenzwinkern — kein Kitsch
- Beziehe das Tagesmotto ein, wenn es passt, sonst ignoriere es
- Du-Form, direkte Ansprache an {$firstName}
- Deutsch, mit ae/oe/ue statt Umlauten
- Antworte NUR mit dem Spruch, nichts anderes";

    // AI Service erstellen — kurzes Timeout, da nur 2-3 Sätze
    $ai = new \Services\AIService($apiKey, $provider);
    $ai->setModel($modelId);
    $ai->setMaxTokens(150);
    $ai->setTimeout(15);

    // Chat-Anfrage
    $result = $ai->chat(
        [['role' => 'user', 'content' => 'Generiere jetzt die persönliche Tagesmotivation.']],
        $systemPrompt
    );

    $motivation = trim($result['content'] ?? '');

    if (empty($motivation)) {
        throw new \Exception('Leere Antwort von KI');
    }

    // In DB speichern
    $db->execute(
        "INSERT INTO daily_motivations (user_id, date, motivation, model_used, created_at)
         VALUES (?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE motivation = VALUES(motivation), model_used = VALUES(model_used)",
        [$userId, $today, $motivation, $modelId]
    );

    Response::success(['motivation' => $motivation]);

} catch (\Exception $e) {
    // Fallback-Motivationen mit Tagesmotto
    $fallbacks = [
        "{$firstName}, los geht's — die Texte schreiben sich nicht von allein.",
        "Hey {$firstName}, Kaffee steht bereit? Dann ab an die Tasten.",
        "{$firstName}, heute wird's gut. Vertrau mir.",
        "Neuer Tag, neue Texte. Du packst das, {$firstName}.",
        "{$firstName}, deine Tastatur wartet schon ungeduldig.",
        "Weniger denken, mehr schreiben. Du weisst, wie's geht, {$firstName}.",
        "{$firstName}, mach was Grosses draus — oder zumindest was Fertiges.",
        "Plan fuer heute: aufstehen, Texte rocken, fertig. Oder, {$firstName}?",
    ];

    $dayIndex = (int) date('z') + $userId + ($forceNew ? rand(0, count($fallbacks) - 1) : 0);
    $motivation = $fallbacks[$dayIndex % count($fallbacks)];

    Response::success(['motivation' => $motivation]);
}
