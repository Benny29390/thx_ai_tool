<?php
/**
 * Helper: server-seitiger Permission-Check pro Plan.
 *
 * Verwendung in plan-spezifischen Endpoints:
 *   require __DIR__ . '/_pp_perm.php';
 *   pp_require($planId, 'read'|'edit'|'write');
 *
 * Regel:
 *  - Admin und Manager-mit-CAP_PROJEKTPLANNER haben implizit 'owner' (Vollzugriff)
 *    auf alle Pläne (Übergangslösung).
 *  - Plan-Owner (created_by) = 'owner'
 *  - Über `pp_plan_shares` direkt freigegeben: 'read'/'edit'/'write'
 *
 * Stufen:
 *  - 'read'  → owner, write, edit, read
 *  - 'edit'  → owner, write, edit (nur is_done, ist_hours, actual_hours, notes editierbar)
 *  - 'write' → owner, write
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Services\ProjektplannerService;

if (!function_exists('pp_require')) {
    function pp_require(int $planId, string $need = 'read'): string {
        require_once SERVICES_PATH . '/ProjektplannerService.php';
        $svc = new ProjektplannerService(Database::getInstance());
        $user = Auth::user();
        $uid = (int) ($user['id'] ?? 0);
        $isMgr = Auth::isAdmin() || Auth::isManager();
        $perm = $svc->getPermission($planId, $uid, $isMgr);
        if (!$perm) Response::forbidden('Kein Zugriff auf diesen Plan');
        $ok = match ($need) {
            'read' => true,
            'edit' => in_array($perm, ['owner','write','edit'], true),
            'write' => in_array($perm, ['owner','write'], true),
            default => false,
        };
        if (!$ok) Response::forbidden('Nicht ausreichend berechtigt');
        return $perm;
    }

    /**
     * Field-Whitelist für „edit"-Permission (alles andere wird stillschweigend ignoriert).
     * Tallyr-Konvention: nur Status (is_done), Ist-Stunden, Aufwand-Notiz, Bemerkungen.
     */
    function pp_filter_edit_fields(array $payload): array {
        $allowed = ['is_done', 'ist_hours', 'actual_hours', 'notes'];
        return array_intersect_key($payload, array_flip($allowed));
    }
}
