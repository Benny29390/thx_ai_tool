<?php
/**
 * Users API (Admin only)
 * Unterstützt Multi-Kunden-Zuweisung
 */

use Core\Auth;
use Core\Response;

global $db, $method, $input;

$id = $_GET['id'] ?? null;

// Hilfsfunktion: Kunden-IDs eines Benutzers speichern
function saveUserCustomers($db, int $userId, array $customerIds): void
{
    // Vorher-Stand fuer Audit
    $before = array_map('intval', array_column(
        $db->query("SELECT customer_id FROM user_customers WHERE user_id = ?", [$userId]),
        'customer_id'
    ));

    // Alte Zuweisungen löschen
    $db->delete('user_customers', 'user_id = ?', [$userId]);

    $clean = [];
    foreach ($customerIds as $i => $customerId) {
        $customerId = (int) $customerId;
        if ($customerId > 0) {
            $db->insert('user_customers', [
                'user_id' => $userId,
                'customer_id' => $customerId,
                'is_default' => ($i === 0) ? 1 : 0 // Erster Kunde ist Standard
            ]);
            $clean[] = $customerId;
        }
    }

    sort($before); $cleanSorted = $clean; sort($cleanSorted);
    if ($before !== $cleanSorted) {
        \Core\AuditLog::record(
            \Core\AuditLog::TARGET_USER,
            (string)$userId,
            \Core\AuditLog::ACTION_CUSTOMERS_CHANGED,
            ['before' => $before, 'after' => $cleanSorted]
        );
    }
}

switch ($method) {
    case 'GET':
        if ($id) {
            $user = $db->queryOne(
                "SELECT u.id, u.email, u.name, u.abbreviation, u.role, u.is_active, u.last_login, u.last_activity, u.created_at, u.asana_user_gid
                 FROM users u
                 WHERE u.id = ?",
                [$id]
            );
            if (!$user) {
                Response::notFound('Benutzer nicht gefunden');
            }
            // Kunden laden
            $user['customer_ids'] = array_column(
                $db->query("SELECT customer_id FROM user_customers WHERE user_id = ?", [$id]),
                'customer_id'
            );
            Response::success($user);
        } else {
            $users = $db->query(
                "SELECT u.id, u.email, u.name, u.abbreviation, u.role, u.is_active, u.last_login, u.last_activity, u.created_at, u.asana_user_gid,
                        (SELECT COALESCE(MAX(t.is_active), 0) FROM pp_team_members t WHERE t.user_id = u.id) AS pp_team_active
                 FROM users u
                 ORDER BY u.name"
            );
            // Kunden pro Benutzer laden
            foreach ($users as &$user) {
                $customerData = $db->query(
                    "SELECT c.id, c.name FROM customers c
                     JOIN user_customers uc ON c.id = uc.customer_id
                     WHERE uc.user_id = ?
                     ORDER BY uc.is_default DESC, c.name",
                    [$user['id']]
                );
                $user['customer_ids'] = array_column($customerData, 'id');
                $user['customer_names'] = implode(', ', array_column($customerData, 'name'));
                $user['customer_count'] = count($customerData);
            }
            Response::success($users);
        }
        break;

    case 'POST':
        $email = trim($input['email'] ?? '');
        $name = trim($input['name'] ?? '');
        $password = $input['password'] ?? '';
        $role = $input['role'] ?? ROLE_USER;
        $sendInvite = !empty($input['send_invite']);

        // Multi-Kunden Support: customer_ids Array oder einzelne customer_id
        $customerIds = [];
        if (!empty($input['customer_ids']) && is_array($input['customer_ids'])) {
            $customerIds = array_map('intval', $input['customer_ids']);
            $customerIds = array_filter($customerIds, fn($id) => $id > 0);
        } elseif (!empty($input['customer_id'])) {
            $customerIds = [(int) $input['customer_id']];
        }

        // Validierung
        if (empty($email) || empty($name)) {
            Response::error('E-Mail und Name erforderlich');
        }

        // Bei Einladung ist Passwort optional (wird vom User selbst gesetzt)
        if (!$sendInvite && empty($password)) {
            Response::error('Passwort erforderlich (oder Einladung per E-Mail senden)');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('Ungueltige E-Mail-Adresse');
        }

        if (!$sendInvite && strlen($password) < 8) {
            Response::error('Passwort muss mindestens 8 Zeichen haben');
        }

        if (!in_array($role, [ROLE_ADMIN, ROLE_MANAGER, ROLE_USER, ROLE_GUEST], true)) {
            Response::error('Ungueltige Rolle');
        }

        // Kunden-Zuordnung ist optional

        // E-Mail eindeutig?
        $existing = $db->queryOne("SELECT id FROM users WHERE email = ?", [$email]);
        if ($existing) {
            Response::error('E-Mail bereits registriert');
        }

        // Bei Einladung: Zufalls-Token statt Passwort
        $inviteToken = null;
        if ($sendInvite) {
            $inviteToken = bin2hex(random_bytes(32));
            $passwordHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT); // Zufalls-Passwort (nicht nutzbar)
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        }

        $userData = [
            'email' => $email,
            'password_hash' => $passwordHash,
            'name' => $name,
            'role' => $role,
            'is_active' => 1,
        ];

        $asanaUserGid = trim($input['asana_user_gid'] ?? '');
        if ($asanaUserGid !== '') $userData['asana_user_gid'] = $asanaUserGid;

        $abbr = mb_strtoupper(trim($input['abbreviation'] ?? ''));
        if ($abbr !== '') {
            if (mb_strlen($abbr) > 5) Response::error('Kuerzel max. 5 Zeichen');
            $userData['abbreviation'] = $abbr;
        }

        if ($inviteToken) {
            $userData['invite_token'] = $inviteToken;
            $userData['invite_expires_at'] = date('Y-m-d H:i:s', strtotime('+7 days'));
        }

        $userId = $db->insert('users', $userData);

        \Core\AuditLog::record(
            \Core\AuditLog::TARGET_USER,
            (string)$userId,
            \Core\AuditLog::ACTION_USER_CREATED,
            ['email' => $email, 'role' => $role, 'invite' => (bool)$sendInvite]
        );

        // Capabilities setzen: explizit aus Request oder Default fuer die Rolle
        $caps = isset($input['capabilities']) && is_array($input['capabilities'])
            ? array_values(array_filter($input['capabilities'], 'is_string'))
            : Auth::defaultCapsFor($role);
        Auth::setCapabilities((int)$userId, $caps, Auth::id());

        // Einladungs-Mail senden
        $inviteSent = false;
        if ($sendInvite && $inviteToken) {
            try {
                require_once SERVICES_PATH . '/EmailService.php';
                $emailService = \Services\EmailService::fromSettings($db);
                if (!$emailService->isConfigured()) {
                    error_log("SMTP nicht konfiguriert — Einladung fuer {$email} nicht gesendet");
                } else {
                    $inviterName = Auth::user()['name'] ?? 'Administrator';
                    $inviteSent = $emailService->sendInvitation($email, $name, $inviteToken, $inviterName);
                }
            } catch (\Exception $e) {
                error_log("Einladungs-Mail fehlgeschlagen: " . $e->getMessage());
            }
        }

        // Kunden zuweisen
        if (!empty($customerIds)) {
            saveUserCustomers($db, $userId, $customerIds);
        }

        // Nutzer-Event: Relevante Artefakte fuer zugewiesene Kunden identifizieren
        if (!empty($customerIds)) {
            try {
                $customerNames = [];
                foreach ($customerIds as $cid) {
                    $c = $db->queryOne("SELECT name FROM customers WHERE id = ?", [$cid]);
                    if ($c) $customerNames[] = $c['name'];
                }
                if (!empty($customerNames)) {
                    // Artefakte in den Namespaces der Kunden zaehlen
                    $totalRelevant = 0;
                    foreach ($customerNames as $cn) {
                        $count = (int)$db->queryValue(
                            "SELECT COUNT(*) FROM artifacts WHERE JSON_UNQUOTE(JSON_EXTRACT(meta, '$.namespace')) LIKE ? AND (JSON_EXTRACT(meta, '$.is_active') IS NULL OR JSON_EXTRACT(meta, '$.is_active') != false)",
                            ["%{$cn}%"]
                        );
                        $totalRelevant += $count;
                    }
                    if ($totalRelevant > 0) {
                        require_once SERVICES_PATH . '/ArtifactEventService.php';
                        $eventSvc = new \Services\ArtifactEventService($db);
                        // Event an Artefakt-ID 0 (systemweit)
                        $eventSvc->createEvent(0, 'review_needed',
                            "Neuer Nutzer \"{$name}\" — {$totalRelevant} Artefakte in " . implode(', ', $customerNames) . " relevant", [
                            'user_id' => $userId,
                            'user_name' => $name,
                            'customers' => $customerNames,
                            'artifact_count' => $totalRelevant,
                        ], Auth::id());
                    }
                }
            } catch (\Exception $e) {
                error_log("User artifact event failed: " . $e->getMessage());
            }
        }

        $msg = 'Benutzer erstellt';
        if ($sendInvite) {
            $msg = $inviteSent ? 'Benutzer erstellt und Einladung gesendet' : 'Benutzer erstellt, aber E-Mail konnte nicht gesendet werden';
        }
        Response::success(['id' => $userId, 'invite_sent' => $inviteSent], $msg);
        break;

    case 'PUT':
        if (!$id) {
            Response::error('ID erforderlich');
        }

        $user = $db->queryOne("SELECT * FROM users WHERE id = ?", [$id]);
        if (!$user) {
            Response::notFound('Benutzer nicht gefunden');
        }

        $updates = [];
        if (isset($input['name'])) $updates['name'] = trim($input['name']);
        if (isset($input['email'])) {
            $email = trim($input['email']);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Response::error('Ungültige E-Mail-Adresse');
            }
            $existing = $db->queryOne("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $id]);
            if ($existing) {
                Response::error('E-Mail bereits registriert');
            }
            $updates['email'] = $email;
        }
        if (isset($input['role'])) {
            if (!in_array($input['role'], [ROLE_ADMIN, ROLE_MANAGER, ROLE_USER, ROLE_GUEST], true)) {
                Response::error('Ungültige Rolle');
            }
            $updates['role'] = $input['role'];
            // Audit-Log: Rollen-Wechsel
            if ($input['role'] !== ($user['role'] ?? '')) {
                \Core\AuditLog::record(
                    \Core\AuditLog::TARGET_USER,
                    (string)$id,
                    \Core\AuditLog::ACTION_ROLE_CHANGED,
                    ['before' => $user['role'], 'after' => $input['role']]
                );
            }
        }
        if (isset($input['is_active'])) {
            $updates['is_active'] = (int) $input['is_active'];
        }
        if (!empty($input['password'])) {
            if (strlen($input['password']) < 8) {
                Response::error('Passwort muss mindestens 8 Zeichen haben');
            }
            $updates['password_hash'] = password_hash($input['password'], PASSWORD_DEFAULT);
        }
        if (array_key_exists('abbreviation', $input)) {
            $abbr = mb_strtoupper(trim((string) $input['abbreviation']));
            if (mb_strlen($abbr) > 5) Response::error('Kuerzel max. 5 Zeichen');
            $updates['abbreviation'] = $abbr ?: null;
        }
        if (array_key_exists('nickname', $input)) {
            $nick = trim((string) $input['nickname']);
            if (mb_strlen($nick) > 50) Response::error('Spitzname max. 50 Zeichen');
            $updates['nickname'] = $nick ?: null;
        }
        if (array_key_exists('asana_user_gid', $input)) {
            $newGid = trim((string) $input['asana_user_gid']) ?: null;
            $updates['asana_user_gid'] = $newGid;
            // Asana-User-Details (Email/Name) nachladen oder loeschen
            if ($newGid) {
                $pat = $db->queryValue("SELECT setting_value FROM settings WHERE setting_key = 'asana_pat'");
                if ($pat) {
                    $workspaceGid = $db->queryValue("SELECT setting_value FROM settings WHERE setting_key = 'asana_workspace_gid'");
                    require_once SERVICES_PATH . '/AsanaService.php';
                    try {
                        $asana = new \Services\AsanaService($pat);
                        if (empty($workspaceGid)) {
                            $ws = $asana->listWorkspaces();
                            if (!empty($ws)) $workspaceGid = $ws[0]['gid'];
                        }
                        if ($workspaceGid) {
                            foreach ($asana->listUsers($workspaceGid) as $au) {
                                if ((string)($au['gid'] ?? '') === $newGid) {
                                    $updates['asana_user_email'] = $au['email'] ?? null;
                                    $updates['asana_user_name'] = $au['name'] ?? null;
                                    break;
                                }
                            }
                        }
                    } catch (\Exception $e) {}
                }
            } else {
                $updates['asana_user_email'] = null;
                $updates['asana_user_name'] = null;
            }
        }

        if (!empty($updates)) {
            $db->update('users', $updates, 'id = ?', [$id]);
        }

        // --- Projektplanner-Team-Felder synchron halten (pp_team_members per user_id) ---
        $ppFieldsTouched = array_key_exists('pp_capacity_hours', $input)
                        || array_key_exists('pp_hex_color', $input)
                        || array_key_exists('pp_team_active', $input);
        $userMetaTouched = isset($updates['name']) || isset($updates['abbreviation']) || isset($updates['is_active']);
        if ($ppFieldsTouched || $userMetaTouched) {
            $teamRow = $db->queryOne("SELECT id FROM pp_team_members WHERE user_id = ?", [(int)$id]);
            $teamUpdate = [];
            if (array_key_exists('pp_capacity_hours', $input)) {
                $teamUpdate['capacity_hours'] = max(0, (int)$input['pp_capacity_hours']);
            }
            if (array_key_exists('pp_hex_color', $input)) {
                $c = trim((string)$input['pp_hex_color']);
                $teamUpdate['hex_color'] = preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? $c : null;
            }
            // pp_team_active: explizit, ODER user wurde deaktiviert → PP-Team auch deaktivieren
            if (array_key_exists('pp_team_active', $input)) {
                $teamUpdate['is_active'] = (int)$input['pp_team_active'];
            } elseif (isset($updates['is_active']) && (int)$updates['is_active'] === 0) {
                $teamUpdate['is_active'] = 0;
            }
            // Name/Kürzel synchron mit users-Tabelle halten
            $effectiveName = $updates['name'] ?? $user['name'];
            $effectiveAbbr = $updates['abbreviation'] ?? $user['abbreviation'];
            if (isset($updates['name'])) $teamUpdate['name'] = $effectiveName;
            if (array_key_exists('abbreviation', $updates)) $teamUpdate['abbreviation'] = $effectiveAbbr;

            if ($teamRow) {
                if ($teamUpdate) {
                    $db->update('pp_team_members', $teamUpdate, 'id = ?', [(int)$teamRow['id']]);
                    // Name-Propagation in alle Plan-Zeilen
                    if (isset($teamUpdate['name']) && trim((string)$user['name']) !== trim((string)$effectiveName)) {
                        require_once SERVICES_PATH . '/PpTeamService.php';
                        (new \Services\PpTeamService($db))->renamePerson((string)$user['name'], (string)$effectiveName, (int)$teamRow['id']);
                    }
                }
            } elseif ($ppFieldsTouched) {
                // Erstmaliger Insert beim ersten Speichern PP-spezifischer Felder
                $maxOrder = (int)($db->queryValue("SELECT COALESCE(MAX(sort_order), 0) FROM pp_team_members") ?? 0);
                $db->insert('pp_team_members', array_merge([
                    'user_id'        => (int)$id,
                    'name'           => $effectiveName,
                    'abbreviation'   => $effectiveAbbr,
                    'capacity_hours' => 160,
                    'hex_color'      => null,
                    'sort_order'     => $maxOrder + 1,
                    'is_active'      => 1,
                ], $teamUpdate));
            }
        }

        // Kunden aktualisieren (wenn im Request enthalten) — keine harte Pflicht mehr,
        // ein User ohne Kunden sieht halt nichts; das ist OK fuers Anlegen-Setup.
        if (isset($input['customer_ids'])) {
            $customerIds = [];
            if (is_array($input['customer_ids'])) {
                $customerIds = array_map('intval', $input['customer_ids']);
                $customerIds = array_filter($customerIds, fn($cid) => $cid > 0);
            }
            saveUserCustomers($db, (int) $id, $customerIds);
        }

        // Capabilities aktualisieren — wenn `capabilities`-Array im Request,
        // werden die Caps komplett ersetzt (sonst bleiben sie unberuehrt).
        if (isset($input['capabilities']) && is_array($input['capabilities'])) {
            $caps = array_values(array_filter($input['capabilities'], 'is_string'));
            Auth::setCapabilities((int)$id, $caps, Auth::id());
        } elseif (isset($input['role']) && ($input['role'] !== $user['role'])) {
            // Rollen-Wechsel ohne explizite Caps: Defaults der neuen Rolle uebernehmen
            Auth::setCapabilities((int)$id, Auth::defaultCapsFor($input['role']), Auth::id());
        }

        Response::success(null, 'Benutzer aktualisiert');
        break;

    case 'DELETE':
        if (!$id) {
            Response::error('ID erforderlich');
        }

        // Sich selbst nicht löschen
        if ((int) $id === Auth::id()) {
            Response::error('Du kannst dich nicht selbst löschen');
        }

        $user = $db->queryOne("SELECT id, name, email, is_active FROM users WHERE id = ?", [$id]);
        if (!$user) {
            Response::notFound('Benutzer nicht gefunden');
        }

        // Schutzlogik gegen versehentliches Loeschen:
        // 1) User muss vorher deaktiviert sein (is_active = 0)
        // 2) Der Admin muss die E-Mail-Adresse zur Bestaetigung mitsenden
        if ((int)$user['is_active'] === 1) {
            Response::error(
                'Bitte den User erst deaktivieren. Daten und Verknüpfungen bleiben dabei erhalten — Loeschen ist endgültig und nicht rückgängig.',
                409
            );
        }
        $confirmInput = trim((string)($_GET['confirm_email'] ?? $input['confirm_email'] ?? ''));
        if (mb_strtolower($confirmInput) !== mb_strtolower($user['email'])) {
            Response::error(
                'Bestaetigung fehlt: Bitte die exakte E-Mail-Adresse des Users mitsenden (Feld confirm_email).',
                412
            );
        }

        // Referenzen aufräumen (SET NULL wo möglich, Junction-Tables löschen)
        $db->execute("UPDATE orders SET created_by = NULL WHERE created_by = ?", [$id]);
        $db->execute("UPDATE contexts SET created_by = NULL WHERE created_by = ?", [$id]);
        $db->execute("UPDATE projects SET created_by = NULL WHERE created_by = ?", [$id]);
        $db->execute("UPDATE rules SET approved_by = NULL WHERE approved_by = ?", [$id]);
        $db->execute("UPDATE internal_feedback SET user_id = NULL WHERE user_id = ?", [$id]);
        $db->execute("UPDATE internal_feedback SET resolved_by = NULL WHERE resolved_by = ?", [$id]);
        $db->execute("UPDATE section_feedback SET user_id = NULL WHERE user_id = ?", [$id]);
        $db->execute("UPDATE article_feedback SET user_id = NULL WHERE user_id = ?", [$id]);
        $db->execute("UPDATE usage_logs SET user_id = NULL WHERE user_id = ?", [$id]);
        $db->execute("UPDATE generation_jobs SET user_id = NULL WHERE user_id = ?", [$id]);
        $db->execute("DELETE FROM user_customers WHERE user_id = ?", [$id]);
        $db->execute("DELETE FROM sessions WHERE user_id = ?", [$id]);
        $db->execute("DELETE FROM daily_motivations WHERE user_id = ?", [$id]);

        $db->delete('users', 'id = ?', [$id]);
        Response::success(null, 'Benutzer gelöscht');
        break;

    default:
        Response::error('Method not allowed', 405);
}
