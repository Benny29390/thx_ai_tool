<?php
/**
 * CrmKnowledgeSyncQueue — statischer Helper zum Markieren von CRM-Entities
 * für den nachgelagerten Sync-Worker. Vermeidet Circular Dependencies zwischen
 * den CRM-Services und dem CrmKnowledgeSyncService.
 *
 * Wird aus den Mutation-Methoden (anlegen, aktualisieren, ...) aufgerufen.
 * Der Worker (cli/crm-knowledge-worker.php) liest die Queue mit Debounce ab.
 */

namespace Services;

use Core\Database;

class CrmKnowledgeSyncQueue
{
    public static function enqueueKontakt(int $kontaktId): void
    {
        if ($kontaktId <= 0) return;
        try {
            $db = Database::getInstance();
            $db->execute(
                "INSERT INTO crm_sync_queue (entity_typ, entity_id, last_change_at)
                 VALUES ('kontakt', ?, NOW())
                 ON DUPLICATE KEY UPDATE last_change_at = NOW(), attempts = 0, last_error = NULL",
                [$kontaktId]
            );
        } catch (\Throwable $e) {
            error_log('CrmKnowledgeSyncQueue::enqueueKontakt(' . $kontaktId . '): ' . $e->getMessage());
        }
    }

    public static function enqueueFirma(int $firmaId): void
    {
        if ($firmaId <= 0) return;
        try {
            $db = Database::getInstance();
            $db->execute(
                "INSERT INTO crm_sync_queue (entity_typ, entity_id, last_change_at)
                 VALUES ('firma', ?, NOW())
                 ON DUPLICATE KEY UPDATE last_change_at = NOW(), attempts = 0, last_error = NULL",
                [$firmaId]
            );
        } catch (\Throwable $e) {
            error_log('CrmKnowledgeSyncQueue::enqueueFirma(' . $firmaId . '): ' . $e->getMessage());
        }
    }

    /**
     * Bei Wechsel von customers.crm_firma_id: alte+neue Firma re-enqueuen.
     * Im Sync werden dann auch alle Kontakte beider Firmen umgezogen.
     */
    public static function enqueueAfterCustomerLinkChange(?int $oldFirmaId, ?int $newFirmaId): void
    {
        if ($oldFirmaId && $oldFirmaId > 0) self::enqueueFirma($oldFirmaId);
        if ($newFirmaId && $newFirmaId > 0 && $newFirmaId !== $oldFirmaId) self::enqueueFirma($newFirmaId);
    }
}
