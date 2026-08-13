<div class="thx-page-header" style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;">
    <div>
        <div style="font-size:var(--d-fs-xs);margin-bottom:4px;"><a href="/admin/customers" style="color:var(--thoxan-700);text-decoration:none;">&larr; Zurück zur Übersicht</a></div>
        <h1 class="thx-page-title"><?= $customer ? 'Kunde bearbeiten' : 'Neuer Kunde' ?></h1>
        <?php if ($customer): ?>
        <div class="thx-page-subtitle"><?= htmlspecialchars($customer['name'] ?? '') ?></div>
        <?php endif; ?>
    </div>
    <?php if ($customer): ?>
    <button type="button" class="thx-btn thx-btn-secondary" onclick="customerDelete(<?= (int) $customer['id'] ?>, <?= json_encode($customer['name']) ?>)"
            style="color:var(--rose-700);border-color:var(--rose-300);">
        <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;">delete</span>
        Kunde löschen
    </button>
    <?php endif; ?>
</div>

<form id="customer-form" class="form-page">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

    <!-- Tabs -->
    <div class="thx-tabs">
        <button type="button" class="thx-tab is-active" onclick="switchTab('info')">Stammdaten</button>
        <?php if ($customer): ?>
        <button type="button" class="thx-tab" onclick="switchTab('rules')">Regeln</button>
        <button type="button" class="thx-tab" onclick="switchTab('knowledge')">Wissen</button>
        <button type="button" class="thx-tab" onclick="switchTab('asana')">Asana</button>
        <?php endif; ?>
    </div>

    <!-- Tab: Stammdaten -->
    <div class="tab-content active" id="tab-info">
        <div class="thx-card">
            <h3 class="thx-card-title">Grunddaten</h3>
            <div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="customer-name">Firmenname *</label>
                        <input type="text" id="customer-name" name="name" required
                               value="<?= htmlspecialchars($customer['name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="customer-slug">Slug *</label>
                        <input type="text" id="customer-slug" name="slug" required
                               pattern="[a-z0-9-]+" title="Nur Kleinbuchstaben, Zahlen und Bindestriche"
                               value="<?= htmlspecialchars($customer['slug'] ?? '') ?>">
                        <small>Wird für URLs und Dateinamen verwendet</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="customer-abbreviation">Kürzel</label>
                        <input type="text" id="customer-abbreviation" name="abbreviation"
                               maxlength="10"
                               title="Großbuchstaben (inkl. Ä Ö Ü ẞ), Zahlen und & · . - + @ /"
                               placeholder="z.B. TXN, WTÜK, B&Q"
                               value="<?= htmlspecialchars($customer['abbreviation'] ?? '') ?>">
                        <small>Kurzes Kürzel für die Projektansicht (max. 10 Zeichen)</small>
                    </div>
                    <div class="form-group"></div>
                </div>

                <div class="form-row">
                    <div class="form-group" style="position:relative;">
                        <label for="crm-firma-suche">Verknüpfte CRM-Firma</label>
                        <input type="hidden" name="crm_firma_id" id="crm-firma-id"
                               value="<?= htmlspecialchars((string)($customer['crm_firma_id'] ?? '')) ?>">

                        <div id="crm-firma-current"
                             style="display:<?= !empty($crmFirmaLinked) ? 'flex' : 'none' ?>;align-items:center;gap:8px;padding:8px 10px;background:var(--emerald-50);border:1px solid var(--emerald-200);border-radius:6px;margin-bottom:6px;">
                            <span class="material-symbols-rounded" style="font-size:18px;color:var(--emerald-700);">link</span>
                            <span style="flex:1;font-size:0.85rem;">
                                <strong id="crm-firma-current-name"><?= htmlspecialchars($crmFirmaLinked['firmenname'] ?? '') ?></strong>
                                <?php if (!empty($crmFirmaLinked['branche'])): ?>
                                <span style="color:var(--slate-500);" id="crm-firma-current-meta"> · <?= htmlspecialchars($crmFirmaLinked['branche']) ?></span>
                                <?php else: ?>
                                <span style="color:var(--slate-500);" id="crm-firma-current-meta"></span>
                                <?php endif; ?>
                            </span>
                            <?php if (!empty($crmFirmaLinked['id'])): ?>
                            <a href="/crm/firmen/<?= (int)$crmFirmaLinked['id'] ?>" target="_blank"
                               class="thx-btn thx-btn-secondary thx-btn-small" id="crm-firma-current-link" title="In neuem Tab öffnen">
                                <span class="material-symbols-rounded" style="font-size:14px;">open_in_new</span>
                            </a>
                            <?php else: ?>
                            <a href="#" target="_blank" class="thx-btn thx-btn-secondary thx-btn-small" id="crm-firma-current-link" style="display:none;" title="In neuem Tab öffnen">
                                <span class="material-symbols-rounded" style="font-size:14px;">open_in_new</span>
                            </a>
                            <?php endif; ?>
                            <button type="button" class="thx-btn thx-btn-secondary thx-btn-small" onclick="crmFirmaUnlink()" style="color:var(--rose-700);" title="Verknüpfung lösen">
                                <span class="material-symbols-rounded" style="font-size:14px;">link_off</span>
                            </button>
                        </div>

                        <input type="text" id="crm-firma-suche" autocomplete="off"
                               placeholder="<?= !empty($crmFirmaLinked) ? 'Andere Firma suchen …' : 'Firma im CRM suchen (Name, Branche, Website) …' ?>"
                               oninput="crmFirmaSuche()">
                        <div id="crm-firma-results" style="display:none;position:absolute;left:0;right:0;top:100%;z-index:50;max-height:280px;overflow:auto;background:#fff;border:1px solid var(--slate-300);border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,0.08);margin-top:2px;"></div>
                        <small>Sucht in der CRM-Firmen-Tabelle. Bestimmt, in welchen Wissensbucket CRM-Kontakte dieser Firma synchronisiert werden.</small>
                    </div>
                    <div class="form-group"></div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="customer-website">Website</label>
                        <input type="url" id="customer-website" name="website" placeholder="https://..."
                               value="<?= htmlspecialchars($customer['website'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="customer-industry">Branche</label>
                        <input type="text" id="customer-industry" name="industry"
                               placeholder="z.B. IT, Gesundheit, Einzelhandel"
                               value="<?= htmlspecialchars($customer['industry'] ?? '') ?>">
                    </div>
                </div>

                <?php if ($customer): ?>
                <div class="form-group">
                    <label for="customer-status">Status</label>
                    <select id="customer-status" name="is_active">
                        <option value="1" <?= ($customer['is_active'] ?? 1) ? 'selected' : '' ?>>Aktiv</option>
                        <option value="0" <?= !($customer['is_active'] ?? 1) ? 'selected' : '' ?>>Inaktiv</option>
                    </select>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="thx-card" style="background:linear-gradient(135deg,var(--thoxan-100),var(--thoxan-50));border:1px solid var(--thoxan-200);">
            <h3 class="thx-card-title" style="display:flex;align-items:center;gap:0.5rem;color:var(--thoxan-700);">
                <span class="material-symbols-rounded" style="font-size:22px;">auto_awesome</span>
                KI-Assistent: Profil automatisch befuellen
            </h3>
            <div class="thx-card-sub">Gib eine Website-URL, Dokumente oder Text an — die KI fuellt Beschreibung, Zielgruppe, USPs usw. automatisch aus.</div>
            <div>
                <div class="form-group">
                    <label>Quelle</label>
                    <div style="display:flex;gap:0.5rem;margin-bottom:0.75rem;">
                        <button type="button" class="thx-btn thx-btn-secondary thx-btn-small" onclick="ca_switchSource('url')" id="ca-btn-url" style="background:var(--thoxan-700);color:#fff;border-color:var(--thoxan-700);">
                            <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">link</span> URL
                        </button>
                        <button type="button" class="thx-btn thx-btn-secondary thx-btn-small" onclick="ca_switchSource('file')" id="ca-btn-file">
                            <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">upload_file</span> Dokument
                        </button>
                        <button type="button" class="thx-btn thx-btn-secondary thx-btn-small" onclick="ca_switchSource('text')" id="ca-btn-text">
                            <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">edit_note</span> Text
                        </button>
                    </div>
                </div>

                <div id="ca-source-url">
                    <div class="form-group">
                        <label>Website-URL</label>
                        <input type="url" id="ca-url" placeholder="https://firma.de" style="width:100%;">
                        <small>Die KI crawlt die Startseite und ggf. "Ueber uns"-Seiten.</small>
                    </div>
                </div>

                <div id="ca-source-file" style="display:none;">
                    <div class="form-group">
                        <label>Dokument hochladen</label>
                        <input type="file" id="ca-file" accept=".pdf,.docx,.txt,.md,.html,.htm" style="width:100%;">
                        <small>PDF, Word, Text, Markdown oder HTML.</small>
                    </div>
                </div>

                <div id="ca-source-text" style="display:none;">
                    <div class="form-group">
                        <label>Text</label>
                        <textarea id="ca-text" rows="5" placeholder="Firmenbeschreibung, Infos vom Kick-off, Angebotstext..." style="width:100%;"></textarea>
                    </div>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:0.5rem;">
                    <button type="button" class="thx-btn thx-btn-primary" id="ca-analyze-btn" onclick="ca_analyze()" style="background:linear-gradient(135deg,var(--thoxan-700),#1976d2);">
                        <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;">auto_awesome</span>
                        Profil erstellen
                    </button>
                </div>

                <div id="ca-status" style="margin-top:1rem;display:none;"></div>
            </div>
        </div>

        <div class="thx-card">
            <h3 class="thx-card-title">Unternehmensprofil</h3>
            <div class="thx-card-sub">Diese Informationen helfen der KI, passende Texte zu generieren</div>
            <div>
                <div class="form-group">
                    <label for="customer-description">Firmenbeschreibung</label>
                    <textarea id="customer-description" name="description" rows="3"
                              placeholder="Was macht das Unternehmen? Kurze Beschreibung der Tätigkeit..."><?= htmlspecialchars($customer['description'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label for="customer-target-audience">Zielgruppe</label>
                    <textarea id="customer-target-audience" name="target_audience" rows="2"
                              placeholder="z.B. B2B-Kunden im Mittelstand, technikaffine Endverbraucher 25-45 Jahre..."><?= htmlspecialchars($customer['target_audience'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label for="customer-products">Produkte / Dienstleistungen</label>
                    <textarea id="customer-products" name="products_services" rows="2"
                              placeholder="Die wichtigsten Angebote des Unternehmens..."><?= htmlspecialchars($customer['products_services'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label for="customer-usps">Alleinstellungsmerkmale (USPs)</label>
                    <textarea id="customer-usps" name="unique_selling_points" rows="2"
                              placeholder="Was unterscheidet das Unternehmen von der Konkurrenz?"><?= htmlspecialchars($customer['unique_selling_points'] ?? '') ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="customer-tone">Tonalität / Ansprache</label>
                        <select id="customer-tone" name="tone_of_voice">
                            <option value="">-- Bitte wählen --</option>
                            <option value="formal" <?= ($customer['tone_of_voice'] ?? '') === 'formal' ? 'selected' : '' ?>>Formal / Sie-Form</option>
                            <option value="informal" <?= ($customer['tone_of_voice'] ?? '') === 'informal' ? 'selected' : '' ?>>Informal / Du-Form</option>
                            <option value="professional" <?= ($customer['tone_of_voice'] ?? '') === 'professional' ? 'selected' : '' ?>>Professionell-sachlich</option>
                            <option value="friendly" <?= ($customer['tone_of_voice'] ?? '') === 'friendly' ? 'selected' : '' ?>>Freundlich-nahbar</option>
                            <option value="technical" <?= ($customer['tone_of_voice'] ?? '') === 'technical' ? 'selected' : '' ?>>Technisch-fachlich</option>
                            <option value="casual" <?= ($customer['tone_of_voice'] ?? '') === 'casual' ? 'selected' : '' ?>>Locker-umgangssprachlich</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="customer-values">Markenwerte</label>
                        <input type="text" id="customer-values" name="brand_values"
                               placeholder="z.B. Innovation, Nachhaltigkeit, Qualität..."
                               value="<?= htmlspecialchars($customer['brand_values'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($customer): ?>
    <!-- Tab: Regeln -->
    <div class="tab-content" id="tab-rules">
        <div class="thx-card">
            <div class="card-header-with-actions">
                <div>
                    <h3 class="thx-card-title">Zugewiesene Regeln</h3>
                    <div class="thx-card-sub">Wähle die Regeln, die für diesen Kunden gelten sollen</div>
                </div>
                <div class="bulk-actions">
                    <button type="button" class="thx-btn thx-btn-secondary thx-btn-small" onclick="selectAllRules()">Alle</button>
                    <button type="button" class="thx-btn thx-btn-secondary thx-btn-small" onclick="deselectAllRules()">Keine</button>
                </div>
            </div>
            <div>
                <?php if (!empty($rulesGrouped)): ?>
                    <?php foreach ($rulesGrouped as $category): ?>
                    <div class="rule-category-group" data-category="<?= htmlspecialchars($category['slug']) ?>">
                        <div class="category-header" style="border-left-color: <?= htmlspecialchars($category['color']) ?>;">
                            <div class="category-info">
                                <span class="material-symbols-rounded category-icon" style="color: <?= htmlspecialchars($category['color']) ?>;">
                                    <?= htmlspecialchars($category['icon']) ?>
                                </span>
                                <span class="category-name"><?= htmlspecialchars($category['name']) ?></span>
                                <span class="category-count">(<?= count($category['rules']) ?>)</span>
                            </div>
                            <div class="category-actions">
                                <button type="button" class="btn btn-tiny btn-outline"
                                        onclick="selectCategoryRules('<?= htmlspecialchars($category['slug']) ?>')">Alle</button>
                                <button type="button" class="btn btn-tiny btn-outline"
                                        onclick="deselectCategoryRules('<?= htmlspecialchars($category['slug']) ?>')">Keine</button>
                            </div>
                        </div>
                        <div class="category-rules">
                            <?php foreach ($category['rules'] as $rule): ?>
                            <label class="checkbox-card">
                                <input type="checkbox" name="rule_ids[]" value="<?= $rule['id'] ?>"
                                       data-category="<?= htmlspecialchars($category['slug']) ?>"
                                       <?= in_array($rule['id'], $customerRuleIds ?? []) ? 'checked' : '' ?>>
                                <div class="checkbox-card-content">
                                    <strong><?= htmlspecialchars($rule['name']) ?></strong>
                                    <?php if ($rule['type_name']): ?>
                                    <span class="badge-small" style="background: <?= htmlspecialchars($rule['type_color'] ?? '#6b7280') ?>20; color: <?= htmlspecialchars($rule['type_color'] ?? '#6b7280') ?>;">
                                        <?= htmlspecialchars($rule['type_name']) ?>
                                    </span>
                                    <?php endif; ?>
                                    <?php if ($rule['description']): ?>
                                        <small><?= htmlspecialchars($rule['description']) ?></small>
                                    <?php endif; ?>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php elseif (!empty($allRules)): ?>
                    <!-- Fallback für alte Struktur ohne Kategorien -->
                    <div class="checkbox-grid" id="rules-list">
                        <?php foreach ($allRules as $rule): ?>
                        <label class="checkbox-card">
                            <input type="checkbox" name="rule_ids[]" value="<?= $rule['id'] ?>"
                                   <?= in_array($rule['id'], $customerRuleIds ?? []) ? 'checked' : '' ?>>
                            <div class="checkbox-card-content">
                                <strong><?= htmlspecialchars($rule['name']) ?></strong>
                                <span class="badge-small"><?= $rule['rule_type'] ?></span>
                                <?php if ($rule['description']): ?>
                                    <small><?= htmlspecialchars($rule['description']) ?></small>
                                <?php endif; ?>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted">Keine Regeln vorhanden. <a href="/rules">Regeln erstellen</a></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Tab: Wissen -->
    <div class="tab-content" id="tab-knowledge">
        <div class="thx-card">
            <div class="card-header-with-actions">
                <div>
                    <h3 class="thx-card-title">Wissen dieses Kunden</h3>
                    <div class="thx-card-sub">Dokumente, URLs und Texte, die bei Chats mit diesem Kunden automatisch via RAG eingebunden werden</div>
                </div>
                <div>
                    <a href="/wissen/neu?customer_id=<?= $customer['id'] ?>" class="thx-btn thx-btn-primary thx-btn-small">
                        <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">add</span>
                        Neues Wissen
                    </a>
                </div>
            </div>
            <div>
                <?php if (!empty($customerKnowledge ?? [])): ?>
                    <div style="display:flex;flex-direction:column;gap:0.5rem;">
                        <?php foreach ($customerKnowledge as $doc):
                            $tags = [];
                            if (!empty($doc['tags'])) {
                                $decoded = is_array($doc['tags']) ? $doc['tags'] : json_decode($doc['tags'], true);
                                if (is_array($decoded)) $tags = $decoded;
                            }
                            $iconMap = ['upload' => 'description', 'url' => 'link', 'text' => 'edit_note', 'chat' => 'chat'];
                            $icon = $iconMap[$doc['source_type']] ?? 'article';
                        ?>
                        <div style="display:flex;align-items:flex-start;gap:0.75rem;padding:0.75rem;background:white;border:1px solid var(--color-gray-200);border-radius:var(--radius-md);">
                            <span class="material-symbols-rounded" style="color:var(--thoxan-700);font-size:22px;margin-top:2px;"><?= $icon ?></span>
                            <div style="flex:1;min-width:0;">
                                <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;">
                                    <a href="/wissen/<?= $doc['id'] ?>" style="font-weight:600;color:var(--color-black);text-decoration:none;">
                                        <?= htmlspecialchars($doc['title']) ?>
                                    </a>
                                    <?php if ($doc['category']): ?>
                                        <span class="badge-small" style="background:#e6f0fa;color:var(--thoxan-900);"><?= htmlspecialchars($doc['category']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!$doc['is_active']): ?>
                                        <span class="badge-small" style="background:var(--rose-50);color:var(--rose-600);">inaktiv</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($doc['description']): ?>
                                    <div style="font-size: var(--d-fs-sm);color:var(--color-gray-600);margin-top:2px;">
                                        <?= htmlspecialchars(mb_substr($doc['description'], 0, 200)) ?><?= mb_strlen($doc['description']) > 200 ? '…' : '' ?>
                                    </div>
                                <?php endif; ?>
                                <div style="display:flex;gap:1rem;font-size: var(--d-fs-xs);color:var(--color-gray-500);margin-top:4px;flex-wrap:wrap;">
                                    <span><?= (int)$doc['chunk_count'] ?> Chunks</span>
                                    <?php if ((int)$doc['usage_count'] > 0): ?>
                                        <span><?= (int)$doc['usage_count'] ?>x genutzt</span>
                                    <?php endif; ?>
                                    <?php if (!empty($tags)): ?>
                                        <span><?= htmlspecialchars(implode(', ', array_map(fn($t) => '#' . $t, array_slice($tags, 0, 4)))) ?></span>
                                    <?php endif; ?>
                                    <span><?= htmlspecialchars(date('d.m.Y', strtotime($doc['created_at']))) ?></span>
                                </div>
                            </div>
                            <a href="/wissen/<?= $doc['id'] ?>" class="btn btn-tiny btn-outline" title="Bearbeiten">
                                <span class="material-symbols-rounded" style="font-size:14px;">edit</span>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align:center;padding:2rem;color:var(--color-gray-500);">
                        <span class="material-symbols-rounded" style="font-size:48px;opacity:0.3;">library_books</span>
                        <p style="margin-top:0.5rem;">Noch kein Wissen fuer diesen Kunden hinterlegt.</p>
                        <p style="font-size: var(--d-fs-sm);">
                            <a href="/wissen/neu?customer_id=<?= $customer['id'] ?>">Wissen hinzufuegen</a> —
                            Upload, URL oder Text. Die KI nutzt es automatisch bei passenden Fragen im Chat.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Tab: Asana -->
    <?php
    $_settingsJson = json_decode($customer['settings'] ?? '{}', true) ?: [];
    $_asanaCfg = $_settingsJson['asana'] ?? [];
    $_projectGids = $_asanaCfg['project_gids'] ?? [];
    ?>
    <div class="tab-content" id="tab-asana">
        <div class="thx-card">
            <h3 class="thx-card-title" style="display:flex;align-items:center;gap:0.5rem;">
                <span class="material-symbols-rounded" style="font-size:22px;color:#f97316;">task_alt</span>
                Asana-Integration
                <small style="font-weight:normal;color:#94a3b8;">nur Lesezugriff</small>
            </h3>
            <div class="thx-card-sub">Verbindet Asana-Projekte mit dem Wissen-System dieses Kunden — Tasks, Comments, Attachments und Custom Fields werden automatisch ins Wissen verarbeitet.</div>
            <div>
                <div id="ax-state" style="margin-bottom:1rem;color:#64748b;font-size: var(--d-fs-sm);">
                    <span class="ax-spinner"></span>Lade Asana-Projekte...
                </div>

                <div style="display:flex;gap:0.4rem;margin-bottom:0.5rem;flex-wrap:wrap;">
                    <input type="text" id="ax-search" placeholder="Projekt-Name suchen..." oninput="axFilter()" style="flex:1;min-width:220px;padding:0.5rem 0.75rem;border:1px solid var(--color-gray-200);border-radius:6px;font-size: var(--d-fs-sm);">
                    <select id="ax-workspace-filter" onchange="axFilter()" style="padding:0.5rem;border:1px solid var(--color-gray-200);border-radius:6px;font-size: var(--d-fs-sm);display:none;">
                        <option value="">Alle Workspaces</option>
                    </select>
                    <button type="button" class="thx-btn thx-btn-secondary thx-btn-small" onclick="axShowSelectedOnly()" id="ax-show-selected">
                        <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">checklist</span>
                        Nur ausgewaehlte
                    </button>
                </div>

                <div id="ax-projects" style="max-height:420px;overflow-y:auto;margin-bottom:1rem;"></div>

                <!-- Synchronisiertes Wissen aus Asana -->
                <div style="margin-top:1.5rem;padding-top:1rem;border-top:1px solid var(--color-gray-200);">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;">
                        <strong style="font-size: var(--d-fs-sm);">
                            <span class="material-symbols-rounded" style="font-size:18px;vertical-align:middle;color:#10b981;">library_books</span>
                            Aus Asana synchronisiertes Wissen
                            <small id="ax-kb-count" style="font-weight:normal;color:#94a3b8;margin-left:0.4rem;"></small>
                        </strong>
                        <a href="/wissen?customer_id=<?= $customer['id'] ?>&source_type=asana" target="_blank" class="thx-btn thx-btn-secondary thx-btn-small">In Wissen-Liste oeffnen →</a>
                    </div>
                    <div id="ax-kb-list" style="max-height:280px;overflow-y:auto;"></div>
                </div>

                <div style="display:flex;gap:1.5rem;flex-wrap:wrap;align-items:center;padding:0.75rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:1rem;font-size: var(--d-fs-sm);">
                    <label style="display:flex;align-items:center;gap:0.4rem;cursor:pointer;">
                        <input type="checkbox" id="ax-sync-enabled" <?= !empty($_asanaCfg['sync_enabled']) ? 'checked' : '' ?>>
                        Auto-Sync aktivieren
                    </label>
                    <label style="display:flex;align-items:center;gap:0.4rem;">
                        Intervall:
                        <select id="ax-sync-interval" style="padding:0.3rem;border:1px solid #e2e8f0;border-radius:6px;">
                            <?php foreach ([1=>'1 Stunde', 4=>'4 Stunden', 12=>'12 Stunden', 24=>'Täglich', 72=>'Alle 3 Tage', 168=>'Wöchentlich'] as $v => $lbl): ?>
                                <option value="<?= $v ?>" <?= ((int)($_asanaCfg['sync_interval_hours'] ?? 72) === $v) ? 'selected' : '' ?>><?= $lbl ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <?php if (!empty($_asanaCfg['last_sync_at'])): ?>
                        <span style="color:#94a3b8;">Letzter Sync: <?= htmlspecialchars(date('d.m.Y H:i', strtotime($_asanaCfg['last_sync_at']))) ?></span>
                    <?php endif; ?>
                </div>

                <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                    <button type="button" class="thx-btn thx-btn-primary" onclick="axSave(true)" id="ax-save-sync-btn">
                        <span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle;">sync</span>
                        Speichern &amp; Sync starten
                    </button>
                    <button type="button" class="thx-btn thx-btn-secondary" onclick="axSave(false)" id="ax-save-btn">Nur speichern</button>
                    <span id="ax-status" style="margin-left:0.5rem;font-size: var(--d-fs-sm);color:#64748b;align-self:center;"></span>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Actions -->
    <div class="form-actions-sticky">
        <a href="/admin/customers" class="thx-btn thx-btn-secondary">Abbrechen</a>
        <button type="submit" class="thx-btn thx-btn-primary">
            <?= $customer ? 'Änderungen speichern' : 'Kunde anlegen' ?>
        </button>
    </div>
</form>

<style>
.page-header-left {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-xs);
}
.back-link {
    font-size: var(--d-fs-sm);
    color: var(--color-gray-500);
}
.back-link:hover {
    color: var(--color-black);
}
.form-page {
    max-width: 900px;
}
.tab-content {
    display: none;
}
.tab-content.active {
    display: block;
}
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--spacing-md);
}
@media (max-width: 600px) {
    .form-row {
        grid-template-columns: 1fr;
    }
}
.checkbox-grid {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-sm);
}
.checkbox-grid-header {
    font-size: var(--d-fs-xs);
    font-weight: 600;
    color: var(--color-gray-500);
    text-transform: uppercase;
    padding: var(--spacing-sm) 0;
    margin-top: var(--spacing-sm);
    border-bottom: 1px solid var(--color-gray-200);
}
.checkbox-grid-header:first-child {
    margin-top: 0;
}
.checkbox-card {
    display: flex;
    align-items: flex-start;
    gap: var(--spacing-sm);
    padding: var(--spacing-md);
    background: var(--color-gray-50);
    border: 1px solid var(--color-gray-200);
    border-radius: var(--radius-md);
    cursor: pointer;
    transition: all 0.15s ease;
}
.checkbox-card:hover {
    border-color: var(--color-gray-400);
}
.checkbox-card:has(input:checked) {
    background: var(--emerald-50);
    border-color: var(--emerald-500);
}
.checkbox-card input {
    margin-top: 2px;
}
.checkbox-card-content {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.checkbox-card-content strong {
    font-size: var(--d-fs-sm);
}
.checkbox-card-content small {
    font-size: var(--d-fs-sm);
    color: var(--color-gray-500);
}
.badge-small {
    display: inline-block;
    font-size: var(--d-fs-xs);
    padding: 1px 6px;
    border-radius: 3px;
    background: var(--color-gray-200);
    color: var(--color-gray-600);
    width: fit-content;
}
.card-header-with-actions {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}
.bulk-actions {
    display: flex;
    gap: var(--spacing-xs);
}
.rule-category-group {
    margin-bottom: var(--spacing-lg);
}
.rule-category-group:last-child {
    margin-bottom: 0;
}
.category-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--spacing-sm) var(--spacing-md);
    background: var(--color-gray-50);
    border-radius: var(--radius-md);
    margin-bottom: var(--spacing-sm);
    border-left: 3px solid var(--color-gray-400);
}
.category-info {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
}
.category-icon {
    font-size: 20px;
}
.category-name {
    font-weight: 600;
    font-size: var(--d-fs-sm);
}
.category-count {
    font-size: var(--d-fs-sm);
    color: var(--color-gray-500);
}
.category-actions {
    display: flex;
    gap: var(--spacing-xs);
}
.category-rules {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-sm);
    padding-left: var(--spacing-md);
}
.btn-tiny {
    padding: 2px 8px;
    font-size: var(--d-fs-xs);
}
.btn-outline {
    background: transparent;
    border: 1px solid var(--color-gray-300);
    color: var(--color-gray-600);
}
.btn-outline:hover {
    background: var(--color-gray-100);
    border-color: var(--color-gray-400);
}
.form-actions-sticky {
    position: sticky;
    bottom: 0;
    background: white;
    padding: var(--spacing-lg);
    border-top: 1px solid var(--color-gray-200);
    margin: var(--spacing-lg) calc(-1 * var(--spacing-xl));
    margin-bottom: calc(-1 * var(--spacing-xl));
    display: flex;
    justify-content: flex-end;
    gap: var(--spacing-md);
}
textarea {
    width: 100%;
    border: 1px solid var(--color-gray-300);
    border-radius: var(--radius-sm);
    padding: var(--spacing-sm);
    font-family: inherit;
    font-size: var(--d-fs-sm);
    resize: vertical;
}
textarea:focus {
    outline: none;
    border-color: var(--color-black);
}
@keyframes ca-spin {
    to { transform: rotate(360deg); }
}
.spinning {
    display: inline-block;
    animation: ca-spin 1s linear infinite;
}
.ax-spinner {
    display: inline-block;
    width: 14px;
    height: 14px;
    border: 2px solid rgba(0, 76, 155,0.3);
    border-top-color: var(--thoxan-700);
    border-radius: 50%;
    animation: ca-spin 1s linear infinite;
    vertical-align: middle;
    margin-right: 6px;
}
.ax-project {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    padding: 0.6rem 0.7rem;
    border: 1px solid var(--color-gray-200);
    border-radius: 8px;
    margin-bottom: 0.4rem;
    cursor: pointer;
    transition: all 0.15s;
}
.ax-project:hover { border-color: var(--thoxan-700); background: #fafbff; }
.ax-project.selected { border-color: var(--thoxan-700); background: #e6f0fa; }
.ax-project input { margin-top: 3px; }
.ax-project-info { flex: 1; min-width: 0; }
.ax-project-name { font-weight: 600; font-size: var(--d-fs-sm); }
.ax-project-meta { font-size: var(--d-fs-xs); color: #94a3b8; margin-top: 2px; }
</style>

<script>
const customerId = <?= $customer['id'] ?? 'null' ?>;

/** Kunde löschen — mit Live-Dependency-Check + zweistufiger Bestätigung. */
async function customerDelete(id, name) {
    // 1) Dependencies live abfragen
    let info = null;
    try {
        const r = await fetch('/api/v1/admin/customers/' + id + '/dependencies');
        const j = await r.json();
        if (j.success) info = j.data;
    } catch (e) {}

    let warnung = '';
    if (info) {
        const items = Object.entries(info.counts || {}).filter(([k, v]) => v > 0);
        if (items.length > 0) {
            warnung = '\n\n⚠ Folgendes hängt am Kunden:\n' +
                items.map(([k, v]) => '  • ' + v + 'x ' + k).join('\n') +
                '\n\nBeim Löschen werden Referenzen entweder genullt (Pläne, Knowledge, …) oder mit-gelöscht (User-Zuordnungen, Junctions). Daten werden NICHT in den Papierkorb verschoben — der Löschvorgang ist sofort und endgültig.';
        }
    }

    if (!confirm('Kunde „' + name + '" wirklich löschen?' + warnung)) return;
    // 2) Zweite Bestätigung — Namen tippen
    const eingabe = prompt('Zur Sicherheit: Tippe den Kundennamen „' + name + '" exakt ein, um zu bestätigen:');
    if (eingabe === null) return;
    if (eingabe.trim() !== name) { alert('Name stimmt nicht überein — Löschvorgang abgebrochen.'); return; }

    // 3) Löschen
    try {
        const csrfMeta = document.querySelector('input[name="csrf_token"]');
        const r = await fetch('/api/v1/admin/customers/' + id, {
            method: 'DELETE',
            headers: { 'X-CSRF-Token': csrfMeta ? csrfMeta.value : '' },
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message);
        alert('Kunde „' + name + '" wurde gelöscht.');
        window.location.href = '/admin/customers';
    } catch (e) {
        alert('Fehler beim Löschen: ' + e.message);
    }
}

function switchTab(tabName) {
    document.querySelectorAll('.thx-tab').forEach(t => t.classList.remove('is-active'));
    document.querySelectorAll('.tab-content').forEach(p => p.classList.remove('active'));
    document.querySelector(`[onclick="switchTab('${tabName}')"]`).classList.add('is-active');
    document.getElementById('tab-' + tabName).classList.add('active');
}

// Bulk-Selection für Regeln
function selectAllRules() {
    document.querySelectorAll('input[name="rule_ids[]"]').forEach(cb => cb.checked = true);
}

function deselectAllRules() {
    document.querySelectorAll('input[name="rule_ids[]"]').forEach(cb => cb.checked = false);
}

function selectCategoryRules(categorySlug) {
    document.querySelectorAll(`input[data-category="${categorySlug}"]`).forEach(cb => cb.checked = true);
}

function deselectCategoryRules(categorySlug) {
    document.querySelectorAll(`input[data-category="${categorySlug}"]`).forEach(cb => cb.checked = false);
}

// Slug aus Name generieren (nur bei neuem Kunden)
<?php if (!$customer): ?>
document.getElementById('customer-name')?.addEventListener('input', function() {
    const slugField = document.getElementById('customer-slug');
    if (!slugField.dataset.userEdited) {
        const slug = this.value.toLowerCase()
            .replace(/[äöüß]/g, m => ({ä:'ae',ö:'oe',ü:'ue',ß:'ss'}[m]))
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-|-$/g, '');
        slugField.value = slug;
    }
});

document.getElementById('customer-slug')?.addEventListener('input', function() {
    this.dataset.userEdited = 'true';
});
<?php endif; ?>

// ===== KI-Assistent: Profil automatisch befuellen =====
let ca_currentSource = 'url';

function ca_switchSource(source) {
    ca_currentSource = source;
    ['url', 'file', 'text'].forEach(s => {
        const btn = document.getElementById('ca-btn-' + s);
        const panel = document.getElementById('ca-source-' + s);
        if (s === source) {
            btn.style.background = 'var(--thoxan-700)';
            btn.style.color = '#fff';
            btn.style.borderColor = 'var(--thoxan-700)';
            panel.style.display = '';
        } else {
            btn.style.background = '';
            btn.style.color = '';
            btn.style.borderColor = '';
            panel.style.display = 'none';
        }
    });
}

async function ca_analyze() {
    const btn = document.getElementById('ca-analyze-btn');
    const status = document.getElementById('ca-status');

    btn.disabled = true;
    const origLabel = btn.innerHTML;
    btn.innerHTML = '<span class="material-symbols-rounded spinning" style="font-size:16px;vertical-align:middle;">progress_activity</span> Analysiere…';
    status.style.display = 'block';
    status.innerHTML = '<div style="padding:1rem;background:#f8fafc;border-radius:6px;color:#64748b;"><span class="material-symbols-rounded spinning" style="font-size:16px;vertical-align:middle;">autorenew</span> Die KI liest die Quelle und erstellt Vorschlaege…</div>';

    try {
        let response;
        if (ca_currentSource === 'file') {
            const fileInput = document.getElementById('ca-file');
            if (!fileInput.files[0]) throw new Error('Bitte Datei auswaehlen');
            const fd = new FormData();
            fd.append('source', 'file');
            fd.append('file', fileInput.files[0]);
            const res = await fetch('/api/v1/admin/customer-profile-suggest', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            response = await res.json();
            if (!response.success) throw new Error(response.message || 'Fehler');
        } else if (ca_currentSource === 'url') {
            const url = document.getElementById('ca-url').value.trim();
            if (!url) throw new Error('Bitte URL eingeben');
            response = await App.post('/admin/customer-profile-suggest', { source: 'url', url: url });
        } else {
            const text = document.getElementById('ca-text').value.trim();
            if (text.length < 50) throw new Error('Bitte mindestens 50 Zeichen eingeben');
            response = await App.post('/admin/customer-profile-suggest', { source: 'text', text: text });
        }

        const s = response.data.suggestion;
        ca_renderSuggestion(s);
    } catch (err) {
        status.innerHTML = '<div style="padding:0.75rem;background:var(--rose-50);color:var(--rose-600);border-radius:6px;border:1px solid var(--rose-200);"><strong>Fehler:</strong> ' + (err.message || err) + '</div>';
    } finally {
        btn.disabled = false;
        btn.innerHTML = origLabel;
    }
}

function ca_renderSuggestion(s) {
    const status = document.getElementById('ca-status');
    const rows = [
        { key: 'name', label: 'Firmenname', target: 'customer-name', type: 'input' },
        { key: 'industry', label: 'Branche', target: 'customer-industry', type: 'input' },
        { key: 'description', label: 'Firmenbeschreibung', target: 'customer-description', type: 'textarea' },
        { key: 'target_audience', label: 'Zielgruppe', target: 'customer-target-audience', type: 'textarea' },
        { key: 'products_services', label: 'Produkte / Dienstleistungen', target: 'customer-products', type: 'textarea' },
        { key: 'unique_selling_points', label: 'Alleinstellungsmerkmale', target: 'customer-usps', type: 'textarea' },
        { key: 'tone_of_voice', label: 'Tonalitaet', target: 'customer-tone', type: 'select' },
        { key: 'brand_values', label: 'Markenwerte', target: 'customer-values', type: 'input' },
    ];

    let html = '<div style="padding:1rem;background:var(--emerald-50);border:1px solid #bbf7d0;border-radius:8px;">';
    html += '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.75rem;">';
    html += '<strong style="color:#15803d;"><span class="material-symbols-rounded" style="font-size:18px;vertical-align:middle;">check_circle</span> KI-Vorschlag</strong>';
    html += '<button type="button" class="thx-btn thx-btn-primary thx-btn-small" onclick="ca_applyAll()" style="background:var(--emerald-600);border-color:var(--emerald-600);">Alle uebernehmen</button>';
    html += '</div>';
    html += '<div style="display:flex;flex-direction:column;gap:0.5rem;">';

    rows.forEach(row => {
        const val = s[row.key] || '';
        if (!val) return;
        const escaped = String(val).replace(/</g, '&lt;').replace(/>/g, '&gt;');
        html += `
            <div style="display:flex;gap:0.5rem;align-items:flex-start;padding:0.5rem;background:white;border-radius:4px;">
                <div style="flex:1;min-width:0;">
                    <div style="font-size: var(--d-fs-xs);font-weight:600;color:#64748b;margin-bottom:2px;">${row.label}</div>
                    <div style="font-size: var(--d-fs-sm);white-space:pre-wrap;word-break:break-word;">${escaped}</div>
                </div>
                <button type="button" class="btn btn-tiny btn-outline" onclick="ca_applyField('${row.target}', '${row.type}', this)" data-value='${JSON.stringify(val).replace(/'/g, "&apos;")}'>Uebernehmen</button>
            </div>`;
    });

    html += '</div></div>';
    status.innerHTML = html;

    // Werte fuer "Alle uebernehmen"
    window.__ca_lastSuggestion = { rows: rows, data: s };
}

function ca_applyField(targetId, type, btn) {
    let val = btn.getAttribute('data-value');
    try { val = JSON.parse(val); } catch(e) {}
    const el = document.getElementById(targetId);
    if (!el) return;
    if (type === 'select') {
        el.value = val;
    } else {
        el.value = val;
    }
    el.dispatchEvent(new Event('input', { bubbles: true }));
    btn.textContent = 'Uebernommen';
    btn.disabled = true;
    btn.style.background = 'var(--emerald-50)';
    btn.style.color = '#15803d';
    btn.style.borderColor = '#bbf7d0';
}

function ca_applyAll() {
    const ctx = window.__ca_lastSuggestion;
    if (!ctx) return;
    ctx.rows.forEach(row => {
        const val = ctx.data[row.key] || '';
        if (!val) return;
        const el = document.getElementById(row.target);
        if (el) {
            el.value = val;
            el.dispatchEvent(new Event('input', { bubbles: true }));
        }
    });
    App.showNotification('Alle Vorschlaege uebernommen', 'success');
}

// ===== Asana-Tab =====
<?php if ($customer): ?>
let axSelected = new Set(<?= json_encode(array_map('strval', $_projectGids)) ?>);
let axProjects = [];
let axShowSelectedOnlyMode = false;
let axLoaded = false;

async function axInit() {
    if (axLoaded) return;
    axLoaded = true;
    axLoadProjects();
    axLoadKnowledge();
}

async function axLoadProjects() {
    const state = document.getElementById('ax-state');
    state.innerHTML = '<span class="ax-spinner"></span>Lade Asana-Projekte...';

    try {
        const r = await App.get('/admin/asana-projects');
        if (!r.success) {
            state.innerHTML = '<span style="color:var(--rose-600);"><strong>Asana nicht erreichbar:</strong> ' +
                escapeHtml(r.message || 'unbekannter Fehler') + '</span><br>' +
                '<a href="/admin/settings" target="_blank" style="font-size: var(--d-fs-sm);">→ in System-Einstellungen einrichten</a>';
            axLoaded = false;
            return;
        }
        axProjects = r.data?.projects || [];
        const workspaces = r.data?.workspaces || [];

        // Workspace-Filter zeigen wenn mehrere Workspaces
        if (workspaces.length > 1) {
            const select = document.getElementById('ax-workspace-filter');
            select.style.display = '';
            select.innerHTML = '<option value="">Alle Workspaces (' + workspaces.length + ')</option>' +
                workspaces.map(w => `<option value="${escapeHtml(w.gid)}">${escapeHtml(w.name || w.gid)}</option>`).join('');
        }

        if (axProjects.length === 0) {
            state.innerHTML = '<span>Keine Projekte gefunden.</span>';
            return;
        }
        const selCount = axProjects.filter(p => axSelected.has(String(p.gid))).length;
        state.innerHTML = '<strong>' + axProjects.length + '</strong> Projekte verfuegbar' +
            (selCount > 0 ? ', <strong style="color:var(--emerald-600);">' + selCount + '</strong> ausgewaehlt' : '') +
            '. Auswaehlen welche fuer diesen Kunden synchronisiert werden:';
        axRender();
    } catch (e) {
        state.innerHTML = '<span style="color:var(--rose-600);">Verbindungsfehler: ' + escapeHtml(e.message || '') + '</span>';
        axLoaded = false;
    }
}

function axRender() {
    const list = document.getElementById('ax-projects');
    const search = (document.getElementById('ax-search')?.value || '').toLowerCase().trim();
    const wsFilter = document.getElementById('ax-workspace-filter')?.value || '';

    let filtered = axProjects.filter(p => {
        if (axShowSelectedOnlyMode && !axSelected.has(String(p.gid))) return false;
        if (wsFilter && (p.workspace?.gid !== wsFilter)) return false;
        if (search && !(p.name || '').toLowerCase().includes(search)) return false;
        return true;
    });

    if (filtered.length === 0) {
        list.innerHTML = '<div style="text-align:center;padding:1.5rem;color:#94a3b8;">Keine Projekte passen zum Filter.</div>';
        return;
    }

    // Nach Workspace gruppieren wenn vorhanden
    const groups = new Map();
    filtered.forEach(p => {
        const wsName = p.workspace?.name || '—';
        if (!groups.has(wsName)) groups.set(wsName, []);
        groups.get(wsName).push(p);
    });

    let html = '';
    const showWsHeaders = groups.size > 1;
    for (const [wsName, projects] of groups) {
        if (showWsHeaders) {
            html += `<div style="font-size: var(--d-fs-xs);font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;padding:0.5rem 0 0.3rem;">${escapeHtml(wsName)}</div>`;
        }
        html += projects.map(p => {
            const sel = axSelected.has(String(p.gid));
            const meta = [
                p.team?.name ? escapeHtml(p.team.name) : '',
                p.archived ? '<span style="color:var(--rose-600);">archiviert</span>' : '',
                p.modified_at ? 'Geaendert: ' + p.modified_at.substring(0,10) : '',
            ].filter(Boolean).join(' · ');
            return `
                <label class="ax-project ${sel ? 'selected' : ''}">
                    <input type="checkbox" value="${p.gid}" ${sel ? 'checked' : ''} onchange="axToggle(this, '${p.gid}')">
                    <div class="ax-project-info">
                        <div class="ax-project-name">${escapeHtml(p.name || '?')}</div>
                        <div class="ax-project-meta">${meta}</div>
                    </div>
                </label>
            `;
        }).join('');
    }
    list.innerHTML = html;
}

function axFilter() { axRender(); }

function axShowSelectedOnly() {
    axShowSelectedOnlyMode = !axShowSelectedOnlyMode;
    const btn = document.getElementById('ax-show-selected');
    if (axShowSelectedOnlyMode) {
        btn.classList.remove('thx-btn-secondary');
        btn.classList.add('thx-btn-primary');
    } else {
        btn.classList.add('thx-btn-secondary');
        btn.classList.remove('thx-btn-primary');
    }
    axRender();
}

function axToggle(cb, gid) {
    const id = String(gid);
    if (cb.checked) axSelected.add(id);
    else axSelected.delete(id);
    cb.closest('.ax-project').classList.toggle('selected', cb.checked);

    // Counter im Status-Text aktualisieren
    const state = document.getElementById('ax-state');
    if (state && axProjects.length) {
        const selCount = axProjects.filter(p => axSelected.has(String(p.gid))).length;
        state.innerHTML = '<strong>' + axProjects.length + '</strong> Projekte verfuegbar' +
            (selCount > 0 ? ', <strong style="color:var(--emerald-600);">' + selCount + '</strong> ausgewaehlt' : '') +
            '. Auswaehlen welche fuer diesen Kunden synchronisiert werden:';
    }
}

async function axLoadKnowledge() {
    const list = document.getElementById('ax-kb-list');
    const counter = document.getElementById('ax-kb-count');
    list.innerHTML = '<div style="text-align:center;padding:1rem;color:#94a3b8;font-size: var(--d-fs-sm);"><span class="ax-spinner"></span>Lade synchronisiertes Wissen...</div>';
    try {
        const r = await App.get('/knowledge/documents?customer_id=' + customerId + '&source_type=asana&limit=500');
        if (!r.success) throw new Error(r.message || 'Fehler');
        const docs = r.data.items || [];
        counter.textContent = docs.length + ' Eintraege';

        if (docs.length === 0) {
            list.innerHTML = '<div style="text-align:center;padding:1rem;color:#94a3b8;font-size: var(--d-fs-sm);">Noch nichts synchronisiert. Waehle Projekte oben aus und klick "Speichern &amp; Sync starten".</div>';
            return;
        }

        // Nach external_id-Praefix gruppieren (project: vs task:)
        const projectDocs = docs.filter(d => (d.external_id || '').startsWith('project:'));
        const taskDocs = docs.filter(d => (d.external_id || '').startsWith('task:'));
        const others = docs.filter(d => !(d.external_id || '').match(/^(project|task):/));

        let html = '';
        if (projectDocs.length) {
            html += `<div style="font-size: var(--d-fs-xs);font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;padding:0.5rem 0 0.3rem;">Projekt-Overviews (${projectDocs.length})</div>`;
            html += projectDocs.map(axRenderKbRow).join('');
        }
        if (taskDocs.length) {
            html += `<div style="font-size: var(--d-fs-xs);font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;padding:0.5rem 0 0.3rem;">Tasks (${taskDocs.length})</div>`;
            html += taskDocs.slice(0, 50).map(axRenderKbRow).join('');
            if (taskDocs.length > 50) {
                html += `<div style="text-align:center;padding:0.5rem;color:#94a3b8;font-size: var(--d-fs-xs);">+ ${taskDocs.length - 50} weitere — <a href="/wissen?customer_id=${customerId}&source_type=asana" target="_blank">in Wissen-Liste anzeigen</a></div>`;
            }
        }
        if (others.length) {
            html += others.map(axRenderKbRow).join('');
        }
        list.innerHTML = html;
    } catch (e) {
        list.innerHTML = '<div style="color:var(--rose-600);font-size: var(--d-fs-sm);">Fehler: ' + escapeHtml(e.message || '') + '</div>';
    }
}

function axRenderKbRow(d) {
    const date = d.updated_at ? new Date(d.updated_at).toLocaleDateString('de-DE', {day:'2-digit', month:'2-digit'}) : '-';
    const isTask = (d.external_id || '').startsWith('task:');
    return `
        <div style="display:flex;gap:0.5rem;padding:0.4rem 0.5rem;border-bottom:1px solid #f1f5f9;align-items:center;font-size: var(--d-fs-sm);">
            <span class="material-symbols-rounded" style="color:${isTask ? '#f97316' : 'var(--thoxan-700)'};font-size:16px;">${isTask ? 'task_alt' : 'folder'}</span>
            <a href="/wissen/${d.id}" target="_blank" style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:inherit;text-decoration:none;">${escapeHtml(d.title || '?')}</a>
            ${d.source_ref ? `<a href="${escapeHtml(d.source_ref)}" target="_blank" title="In Asana oeffnen" style="color:#94a3b8;"><span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;">open_in_new</span></a>` : ''}
            <span style="color:#94a3b8;font-size: var(--d-fs-xs);white-space:nowrap;">${date}</span>
        </div>
    `;
}

async function axSave(triggerSync) {
    const btn = document.getElementById(triggerSync ? 'ax-save-sync-btn' : 'ax-save-btn');
    const status = document.getElementById('ax-status');
    btn.disabled = true;
    const orig = btn.innerHTML;
    btn.innerHTML = '<span class="ax-spinner"></span>Speichere...';
    status.textContent = '';

    try {
        const r = await App.post('/admin/customers/' + customerId + '/asana-config', {
            project_gids: Array.from(axSelected),
            sync_enabled: document.getElementById('ax-sync-enabled').checked,
            sync_interval_hours: parseInt(document.getElementById('ax-sync-interval').value) || 72,
            trigger_sync: triggerSync && axSelected.size > 0,
        });
        if (!r.success) throw new Error(r.message || 'Fehler');
        const msg = 'Konfiguration gespeichert' +
            (r.data.sync_job_id ? ' — Sync-Job #' + r.data.sync_job_id + ' gestartet' : '');
        App.showNotification(msg, 'success');
        status.style.color = 'var(--emerald-600)';
        status.textContent = msg;
    } catch (e) {
        App.showNotification(e.message || 'Fehler', 'error');
        status.style.color = 'var(--rose-600)';
        status.textContent = e.message || 'Fehler';
    }
    btn.disabled = false;
    btn.innerHTML = orig;
}

function escapeHtml(s) {
    const div = document.createElement('div');
    div.textContent = s ?? '';
    return div.innerHTML;
}

// Asana-Tab beim ersten Aufruf laden
const _origSwitchTab = window.switchTab;
window.switchTab = function(tabName) {
    _origSwitchTab(tabName);
    if (tabName === 'asana') axInit();
};
<?php endif; ?>

// Form Submit
document.getElementById('customer-form').addEventListener('submit', async function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const data = {};

    // Normale Felder
    for (const [key, value] of formData.entries()) {
        if (!key.endsWith('[]')) {
            data[key] = value;
        }
    }

    // Arrays (Checkboxen)
    data.rule_ids = Array.from(formData.getAll('rule_ids[]')).map(Number);
    data.knowledge_ids = Array.from(formData.getAll('knowledge_ids[]')).map(Number);

    try {
        if (customerId) {
            await App.put('/admin/customers/' + customerId, data);
            App.showNotification('Kunde gespeichert', 'success');
        } else {
            const response = await App.post('/admin/customers', data);
            App.showNotification('Kunde angelegt', 'success');
            // Zur Bearbeitungsseite weiterleiten
            if (response.data?.id) {
                window.location.href = '/admin/customers/' + response.data.id + '/edit';
            } else {
                window.location.href = '/admin/customers';
            }
        }
    } catch (error) {
        App.showNotification(error.message, 'error');
    }
});

// ─────────────── CRM-Firma-Verknüpfung (Suche + Auswahl) ───────────────
let crmFirmaSucheTimer = null;
function crmFirmaSuche() {
    clearTimeout(crmFirmaSucheTimer);
    const input = document.getElementById('crm-firma-suche');
    const results = document.getElementById('crm-firma-results');
    const q = (input.value || '').trim();
    if (q.length < 2) { results.style.display = 'none'; results.innerHTML = ''; return; }

    crmFirmaSucheTimer = setTimeout(async () => {
        try {
            const r = await fetch('/api/v1/crm/firmen?suche=' + encodeURIComponent(q) + '&limit=15', { credentials: 'same-origin' });
            const j = await r.json();
            const eintraege = j?.data?.eintraege || [];
            if (!eintraege.length) {
                results.innerHTML = '<div style="padding:10px 12px;color:var(--slate-500);font-size:0.85rem;">Keine Treffer für „' + q.replace(/[<>&]/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[c])) + '"</div>';
                results.style.display = 'block';
                return;
            }
            results.innerHTML = eintraege.map(f => {
                const meta = [f.branche, f.website].filter(Boolean).join(' · ');
                const kontakte = f.anzahl_kontakte > 0 ? f.anzahl_kontakte + ' Kontakt' + (f.anzahl_kontakte === 1 ? '' : 'e') : 'keine Kontakte';
                return '<div class="crm-firma-result" onclick="crmFirmaAuswaehlen(' + f.id + ', this)" '
                    + 'data-name="' + (f.firmenname || '').replace(/"/g, '&quot;') + '" '
                    + 'data-meta="' + (meta || '').replace(/"/g, '&quot;') + '" '
                    + 'style="padding:8px 12px;cursor:pointer;border-bottom:1px solid var(--slate-100);">'
                    + '<div style="font-weight:600;font-size:0.88rem;">' + (f.firmenname || '').replace(/[<>&]/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[c])) + '</div>'
                    + '<div style="font-size:0.75rem;color:var(--slate-500);">' + meta + (meta ? ' · ' : '') + kontakte + '</div>'
                    + '</div>';
            }).join('');
            results.style.display = 'block';
        } catch (e) {
            results.innerHTML = '<div style="padding:10px 12px;color:var(--rose-700);">Fehler: ' + (e.message || 'unbekannt') + '</div>';
            results.style.display = 'block';
        }
    }, 250);
}

function crmFirmaAuswaehlen(id, el) {
    const name = el.dataset.name || '';
    const meta = el.dataset.meta || '';
    document.getElementById('crm-firma-id').value = id;
    document.getElementById('crm-firma-current-name').textContent = name;
    document.getElementById('crm-firma-current-meta').textContent = meta ? ' · ' + meta : '';
    const linkBtn = document.getElementById('crm-firma-current-link');
    linkBtn.href = '/crm/firmen/' + id;
    linkBtn.style.display = '';
    document.getElementById('crm-firma-current').style.display = 'flex';
    document.getElementById('crm-firma-suche').value = '';
    document.getElementById('crm-firma-suche').placeholder = 'Andere Firma suchen …';
    document.getElementById('crm-firma-results').style.display = 'none';
}

function crmFirmaUnlink() {
    if (!confirm('Verknüpfung zur CRM-Firma wirklich lösen?')) return;
    document.getElementById('crm-firma-id').value = '';
    document.getElementById('crm-firma-current').style.display = 'none';
    document.getElementById('crm-firma-suche').placeholder = 'Firma im CRM suchen (Name, Branche, Website) …';
}

// Click ausserhalb der Suche → Dropdown schließen
document.addEventListener('click', (e) => {
    if (!e.target.closest('#crm-firma-suche') && !e.target.closest('#crm-firma-results')) {
        const r = document.getElementById('crm-firma-results');
        if (r) r.style.display = 'none';
    }
});

// Hover-Highlight für Suchergebnisse
document.addEventListener('mouseover', (e) => {
    if (e.target.closest('.crm-firma-result')) {
        const el = e.target.closest('.crm-firma-result');
        el.style.background = 'var(--thoxan-50)';
    }
});
document.addEventListener('mouseout', (e) => {
    if (e.target.closest('.crm-firma-result')) {
        e.target.closest('.crm-firma-result').style.background = '';
    }
});
</script>
