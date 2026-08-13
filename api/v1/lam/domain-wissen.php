<?php
/**
 * GET  /api/v1/lam/domain-wissen
 *   Liste aller Domain-Wissens-Eintraege mit Filter.
 *   Params: suche, linkart[] (multi), confidence[] (multi), nur_konflikte, limit, offset
 *   Returns: { rows: [...], gesamt: N }
 */

use Core\Auth;
use Core\Database;
use Core\Response;
use Services\LamService;

if (!Auth::can(CAP_LAM)) Response::forbidden();

require_once SERVICES_PATH . '/LamService.php';
$svc = new LamService(Database::getInstance());

$filter = [];
if (!empty($_GET['suche']))          $filter['suche']         = (string)$_GET['suche'];
if (!empty($_GET['linkart']))        $filter['linkart']       = is_array($_GET['linkart']) ? $_GET['linkart'] : [$_GET['linkart']];
if (!empty($_GET['confidence']))     $filter['confidence']    = is_array($_GET['confidence']) ? $_GET['confidence'] : [$_GET['confidence']];
if (!empty($_GET['nur_konflikte']))  $filter['nur_konflikte'] = true;
if (isset($_GET['limit']))           $filter['limit']         = (int)$_GET['limit'];
if (isset($_GET['offset']))          $filter['offset']        = (int)$_GET['offset'];

Response::success($svc->listeDomainWissen($filter));
