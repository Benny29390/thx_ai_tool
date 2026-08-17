<?php
/**
 * KiAvatarService — generiert ein Avatar-Bild für einen KI-Mitarbeiter über die
 * OpenAI-Bild-API (gpt-image-1). Speichert das Bild unter uploads/ki-avatars/
 * und setzt profile.avatar_image.
 *
 * Bewusst Illustrations-/Vektor-Stil (kein Foto einer realen Person), passend
 * für einen virtuellen Mitarbeiter.
 */

namespace Services;

class KiAvatarService
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?: \Core\Database::getInstance();
    }

    /** @return string relativer Bildpfad (mit Cache-Buster) */
    public function generate(int $employeeId, int $actorId): string
    {
        require_once SERVICES_PATH . '/KiMitarbeiterService.php';
        $svc = new KiMitarbeiterService($this->db);
        $e = $svc->get($employeeId);
        if (!$e) throw new \RuntimeException('KI-Mitarbeiter nicht gefunden.');

        $key = (string) \Core\Settings::get('openai_api_key');
        if ($key === '') throw new \RuntimeException('Kein OpenAI-Schlüssel konfiguriert (Einstellungen → KI-Modelle).');
        $model = (string) (\Core\Settings::get('image_model') ?: 'gpt-image-1');

        $p = $e['profile'] ?? [];
        $persona = $p['persona'] ?? [];
        $prompt = $this->buildPrompt($e['name'] ?? 'Assistent', $p['role_title'] ?? ($e['role_title'] ?? ''), $persona);

        $b64 = $this->callOpenAiImage($key, $model, $prompt);
        $bin = base64_decode($b64);
        if ($bin === false || strlen($bin) < 100) throw new \RuntimeException('Bild konnte nicht erzeugt werden.');

        $dir = ROOT_PATH . '/uploads/ki-avatars';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $file = $dir . '/' . $employeeId . '.png';
        if (@file_put_contents($file, $bin) === false) {
            throw new \RuntimeException('Bild konnte nicht gespeichert werden (Schreibrechte uploads/ki-avatars).');
        }
        @chmod($file, 0664);

        $path = '/uploads/ki-avatars/' . $employeeId . '.png?v=' . time();
        $svc->patchProfile($employeeId, ['avatar_image' => $path], $actorId);
        \Core\AuditLog::record('ai_employee', (string) $employeeId, 'avatar_generated', null, $actorId);
        return $path;
    }

    private function buildPrompt(string $name, string $role, array $persona): string
    {
        $bits = ["Freundliches, professionelles Avatar-Portrait (Kopf und Schultern) einer virtuellen Mitarbeiter-Figur"];
        if ($role) $bits[] = "Rolle: $role";
        if (!empty($persona['age'])) $bits[] = "wirkt etwa " . $persona['age'] . " Jahre";
        if (!empty($persona['traits']) && is_array($persona['traits'])) $bits[] = "Ausstrahlung: " . implode(', ', array_slice($persona['traits'], 0, 3));
        $bits[] = "moderner, klarer Flat-/Vektor-Illustrationsstil, weiche Farben, neutraler Hintergrund, sympathisch, keine Schrift im Bild";
        return implode('. ', $bits) . '.';
    }

    private function callOpenAiImage(string $key, string $model, string $prompt): string
    {
        $payload = ['model' => $model, 'prompt' => $prompt, 'size' => '1024x1024', 'n' => 1];
        $ch = curl_init('https://api.openai.com/v1/images/generations');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key, 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 120,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($resp === false) throw new \RuntimeException('Bild-API nicht erreichbar: ' . $err);
        $data = json_decode($resp, true);
        if ($code >= 400) {
            $msg = $data['error']['message'] ?? ('HTTP ' . $code);
            throw new \RuntimeException('Bild-API-Fehler: ' . $msg);
        }
        $b64 = $data['data'][0]['b64_json'] ?? '';
        if ($b64 === '') {
            // Manche Antworten liefern eine URL statt b64 — dann laden.
            $url = $data['data'][0]['url'] ?? '';
            if ($url) { $img = @file_get_contents($url); if ($img !== false) return base64_encode($img); }
            throw new \RuntimeException('Bild-API lieferte kein Bild.');
        }
        return $b64;
    }
}
