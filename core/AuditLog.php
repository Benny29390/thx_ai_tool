<?php
namespace Core;

/**
 * Zentrales Audit-Log fuer Berechtigungs-Aenderungen.
 *
 * Tabelle: permission_audit_log (id, occurred_at, actor_user_id, target_type, target_key, action, diff JSON)
 *
 * Verwendung:
 *   AuditLog::record('user', $userId, 'caps_changed', ['before' => [...], 'after' => [...]]);
 *   AuditLog::record('role', 'manager', 'customers_changed', ['before' => [1,2], 'after' => [1,2,3]]);
 */
class AuditLog
{
    public const TARGET_USER = 'user';
    public const TARGET_ROLE = 'role';

    public const ACTION_CAPS_CHANGED           = 'caps_changed';
    public const ACTION_ROLE_CHANGED           = 'role_changed';
    public const ACTION_CUSTOMERS_CHANGED      = 'customers_changed';
    public const ACTION_ROLE_CAPS_CHANGED      = 'role_caps_changed';
    public const ACTION_ROLE_CUSTOMERS_CHANGED = 'role_customers_changed';
    public const ACTION_USER_CREATED           = 'user_created';
    public const ACTION_USER_DEACTIVATED       = 'user_deactivated';

    /**
     * Eintrag schreiben — silent fail, soll nie die eigentliche Operation blockieren.
     */
    public static function record(string $targetType, string $targetKey, string $action, ?array $diff = null, ?int $actorId = null): void
    {
        try {
            $db = Database::getInstance();
            $actorId = $actorId ?? Auth::id();
            $db->insert('permission_audit_log', [
                'actor_user_id' => $actorId,
                'target_type'   => $targetType,
                'target_key'    => $targetKey,
                'action'        => $action,
                'diff'          => $diff !== null ? json_encode($diff, JSON_UNESCAPED_UNICODE) : null,
            ]);
        } catch (\Throwable $e) {
            error_log('AuditLog failed: ' . $e->getMessage());
        }
    }

    /**
     * Eintraege abrufen — mit Filtern fuer die Admin-View.
     *
     * @param array{target_type?:string, target_key?:string, action?:string, actor_id?:int, limit?:int, offset?:int} $filter
     * @return array
     */
    public static function list(array $filter = []): array
    {
        $db = Database::getInstance();
        $where = ['1=1'];
        $params = [];

        if (!empty($filter['target_type'])) {
            $where[] = 'l.target_type = ?';
            $params[] = $filter['target_type'];
        }
        if (!empty($filter['target_key'])) {
            $where[] = 'l.target_key = ?';
            $params[] = (string)$filter['target_key'];
        }
        if (!empty($filter['action'])) {
            $where[] = 'l.action = ?';
            $params[] = $filter['action'];
        }
        if (!empty($filter['actor_id'])) {
            $where[] = 'l.actor_user_id = ?';
            $params[] = (int)$filter['actor_id'];
        }

        $limit  = (int)($filter['limit']  ?? 100);
        $offset = (int)($filter['offset'] ?? 0);

        $sql = "SELECT l.*, u.name AS actor_name, u.email AS actor_email
                FROM permission_audit_log l
                LEFT JOIN users u ON u.id = l.actor_user_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY l.occurred_at DESC
                LIMIT $limit OFFSET $offset";

        $rows = $db->query($sql, $params);
        foreach ($rows as &$r) {
            if (!empty($r['diff'])) {
                $r['diff'] = json_decode($r['diff'], true);
            }
        }
        return $rows;
    }

    public static function count(array $filter = []): int
    {
        $db = Database::getInstance();
        $where = ['1=1'];
        $params = [];
        if (!empty($filter['target_type'])) { $where[] = 'target_type = ?'; $params[] = $filter['target_type']; }
        if (!empty($filter['target_key']))  { $where[] = 'target_key = ?';  $params[] = (string)$filter['target_key']; }
        if (!empty($filter['action']))      { $where[] = 'action = ?';      $params[] = $filter['action']; }
        if (!empty($filter['actor_id']))    { $where[] = 'actor_user_id = ?'; $params[] = (int)$filter['actor_id']; }
        $sql = 'SELECT COUNT(*) FROM permission_audit_log WHERE ' . implode(' AND ', $where);
        return (int)$db->queryValue($sql, $params);
    }
}
