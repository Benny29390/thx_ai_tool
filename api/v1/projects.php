<?php
/**
 * Projects API
 */

use Core\Auth;
use Core\Response;

global $db, $method, $input;

$id = $_GET['id'] ?? null;
$customerId = Auth::isAdmin() ? ($input['customer_id'] ?? null) : Auth::customerId();

switch ($method) {
    case 'GET':
        if ($id) {
            // Einzelnes Projekt
            $project = $db->queryOne(
                "SELECT p.*, c.name as customer_name, u.name as author_name
                 FROM projects p
                 JOIN customers c ON p.customer_id = c.id
                 JOIN users u ON p.created_by = u.id
                 WHERE p.id = ?",
                [$id]
            );

            if (!$project) {
                Response::notFound('Projekt nicht gefunden');
            }

            if (!Auth::canAccessCustomer($project['customer_id'])) {
                Response::forbidden();
            }

            // Aktuelle Version laden
            $project['version'] = $db->queryOne(
                "SELECT * FROM article_versions WHERE project_id = ? ORDER BY version_number DESC LIMIT 1",
                [$id]
            );

            // Abschnitte laden
            if ($project['version']) {
                $project['sections'] = $db->query(
                    "SELECT * FROM article_sections WHERE article_version_id = ? ORDER BY section_order",
                    [$project['version']['id']]
                );
            }

            Response::success($project);
        } else {
            // Liste
            $where = Auth::isAdmin() ? '' : 'WHERE p.customer_id = ?';
            $params = Auth::isAdmin() ? [] : [$customerId];

            $projects = $db->query(
                "SELECT p.*, c.name as customer_name, u.name as author_name
                 FROM projects p
                 JOIN customers c ON p.customer_id = c.id
                 JOIN users u ON p.created_by = u.id
                 $where
                 ORDER BY p.updated_at DESC",
                $params
            );

            Response::success($projects);
        }
        break;

    case 'POST':
        // Neues Projekt
        // Für Admins ohne customer_id: ersten verfügbaren Kunden nutzen
        if (!$customerId && Auth::isAdmin()) {
            $customerId = $input['customer_id'] ?? null;
            if (!$customerId) {
                $defaultCustomer = $db->queryOne("SELECT id FROM customers WHERE is_active = 1 ORDER BY id LIMIT 1");
                $customerId = $defaultCustomer['id'] ?? null;
            }
        }

        if (!$customerId) {
            Response::error('Kunde erforderlich');
        }

        $projectId = $db->insert('projects', [
            'customer_id' => $customerId,
            'created_by' => Auth::id(),
            'title' => $input['title'] ?? 'Neues Projekt',
            'target_website' => $input['target_website'] ?? null,
            'target_word_count' => $input['target_word_count'] ?? 1000,
            'status' => 'draft'
        ]);

        // Erste Version erstellen
        $versionId = $db->insert('article_versions', [
            'project_id' => $projectId,
            'version_number' => 1,
            'content' => json_encode(['sections' => $input['sections'] ?? []]),
            'word_count' => 0
        ]);

        // Abschnitte speichern
        $totalWords = 0;
        if (!empty($input['sections'])) {
            foreach ($input['sections'] as $section) {
                $wordCount = str_word_count($section['content'] ?? '');
                $totalWords += $wordCount;
                $db->insert('article_sections', [
                    'article_version_id' => $versionId,
                    'section_order' => $section['order'] ?? 1,
                    'heading_level' => $section['heading_level'] ?? 'h2',
                    'heading_text' => $section['heading_text'] ?? '',
                    'content' => $section['content'] ?? '',
                    'word_count' => $wordCount,
                    'model_used' => $section['model_used'] ?? null
                ]);
            }
            // Wortanzahl aktualisieren
            $db->update('article_versions', ['word_count' => $totalWords], 'id = ?', [$versionId]);
        }

        // Projekt-Regeln speichern
        if (!empty($input['rule_ids']) && is_array($input['rule_ids'])) {
            foreach ($input['rule_ids'] as $ruleId) {
                $db->insert('project_rules', [
                    'project_id' => $projectId,
                    'rule_id' => (int) $ruleId
                ]);
            }
        }

        // Projekt-Wissen speichern
        if (!empty($input['knowledge_ids']) && is_array($input['knowledge_ids'])) {
            foreach ($input['knowledge_ids'] as $knowledgeId) {
                $db->insert('project_knowledge', [
                    'project_id' => $projectId,
                    'knowledge_entry_id' => (int) $knowledgeId
                ]);
            }
        }

        Response::success(['id' => $projectId], 'Projekt erstellt');
        break;

    case 'PUT':
        if (!$id) {
            Response::error('ID erforderlich');
        }

        $project = $db->queryOne("SELECT * FROM projects WHERE id = ?", [$id]);
        if (!$project || !Auth::canAccessCustomer($project['customer_id'])) {
            Response::forbidden();
        }

        $updates = [];
        if (isset($input['title'])) $updates['title'] = $input['title'];
        if (isset($input['target_website'])) $updates['target_website'] = $input['target_website'];
        if (isset($input['target_word_count'])) $updates['target_word_count'] = $input['target_word_count'];
        if (isset($input['status'])) $updates['status'] = $input['status'];

        // Customer-ID nur für Admins änderbar
        if (isset($input['customer_id']) && Auth::isAdmin()) {
            $updates['customer_id'] = (int) $input['customer_id'];
        }

        // Generator-Einstellungen in metadata speichern
        $metadata = json_decode($project['metadata'] ?? '{}', true) ?: [];
        if (isset($input['topic'])) $metadata['topic'] = $input['topic'];
        if (isset($input['style'])) $metadata['style'] = $input['style'];
        if (isset($input['model'])) $metadata['model'] = $input['model'];
        if (isset($input['sections_count'])) $metadata['sections_count'] = (int) $input['sections_count'];
        $updates['metadata'] = json_encode($metadata);

        $updates['updated_at'] = date('Y-m-d H:i:s');

        if (!empty($updates)) {
            $db->update('projects', $updates, 'id = ?', [$id]);
        }

        // Abschnitte aktualisieren wenn vorhanden - NEUE VERSION erstellen
        if (isset($input['sections'])) {
            // Aktuelle Version holen
            $currentVersion = $db->queryOne(
                "SELECT * FROM article_versions WHERE project_id = ? ORDER BY version_number DESC LIMIT 1",
                [$id]
            );

            // Berechne neuen Inhalt
            $totalWords = 0;
            $newSections = [];
            foreach ($input['sections'] as $index => $section) {
                $wordCount = str_word_count($section['content'] ?? '');
                $totalWords += $wordCount;
                $newSections[] = [
                    'order' => $section['order'] ?? ($index + 1),
                    'heading_level' => $section['heading_level'] ?? 'h2',
                    'heading_text' => $section['heading_text'] ?? '',
                    'content' => $section['content'] ?? '',
                    'word_count' => $wordCount,
                    'model_used' => $section['model_used'] ?? null
                ];
            }

            // Prüfe ob sich Inhalt geändert hat (um unnötige Versionen zu vermeiden)
            $newContentHash = md5(json_encode($newSections));
            $createNewVersion = true;

            if ($currentVersion) {
                $oldSections = $db->query(
                    "SELECT section_order, heading_level, heading_text, content, word_count FROM article_sections WHERE article_version_id = ? ORDER BY section_order",
                    [$currentVersion['id']]
                );
                $oldContentHash = md5(json_encode(array_map(function($s) {
                    return [
                        'order' => $s['section_order'],
                        'heading_level' => $s['heading_level'],
                        'heading_text' => $s['heading_text'],
                        'content' => $s['content'],
                        'word_count' => $s['word_count'],
                        'model_used' => null
                    ];
                }, $oldSections)));

                // Nur neue Version wenn Inhalt sich geändert hat
                if ($newContentHash === $oldContentHash) {
                    $createNewVersion = false;
                }
            }

            if ($createNewVersion) {
                // Neue Versionsnummer
                $newVersionNumber = ($currentVersion['version_number'] ?? 0) + 1;

                // Neue Version erstellen
                $versionId = $db->insert('article_versions', [
                    'project_id' => $id,
                    'version_number' => $newVersionNumber,
                    'content' => json_encode(['sections' => $newSections]),
                    'word_count' => $totalWords
                ]);

                // Neue Abschnitte einfügen
                foreach ($newSections as $section) {
                    $db->insert('article_sections', [
                        'article_version_id' => $versionId,
                        'section_order' => $section['order'],
                        'heading_level' => $section['heading_level'],
                        'heading_text' => $section['heading_text'],
                        'content' => $section['content'],
                        'word_count' => $section['word_count'],
                        'model_used' => $section['model_used']
                    ]);
                }
            }
        }

        // Aktuelle Version für Response ermitteln
        $latestVersion = $db->queryOne(
            "SELECT version_number FROM article_versions WHERE project_id = ? ORDER BY version_number DESC LIMIT 1",
            [$id]
        );
        $responseVersionNumber = $latestVersion['version_number'] ?? 1;

        // Projekt-Regeln aktualisieren
        if (isset($input['rule_ids'])) {
            $db->delete('project_rules', 'project_id = ?', [$id]);
            if (is_array($input['rule_ids'])) {
                foreach ($input['rule_ids'] as $ruleId) {
                    $db->insert('project_rules', [
                        'project_id' => $id,
                        'rule_id' => (int) $ruleId
                    ]);
                }
            }
        }

        // Projekt-Wissen aktualisieren
        if (isset($input['knowledge_ids'])) {
            $db->delete('project_knowledge', 'project_id = ?', [$id]);
            if (is_array($input['knowledge_ids'])) {
                foreach ($input['knowledge_ids'] as $knowledgeId) {
                    $db->insert('project_knowledge', [
                        'project_id' => $id,
                        'knowledge_entry_id' => (int) $knowledgeId
                    ]);
                }
            }
        }

        Response::success(['version_number' => $responseVersionNumber], 'Projekt aktualisiert');
        break;

    case 'DELETE':
        if (!$id) {
            Response::error('ID erforderlich');
        }

        $project = $db->queryOne("SELECT * FROM projects WHERE id = ?", [$id]);
        if (!$project || !Auth::canAccessCustomer($project['customer_id'])) {
            Response::forbidden();
        }

        $db->delete('projects', 'id = ?', [$id]);
        Response::success(null, 'Projekt gelöscht');
        break;

    default:
        Response::error('Method not allowed', 405);
}
