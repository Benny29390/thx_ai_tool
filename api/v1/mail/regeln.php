<?php
/** GET /mail/regeln */
use Core\Auth;
use Core\Database;
use Core\Response;
if (!Auth::isAdmin() && !Auth::isManager()) Response::forbidden();
Response::success(Database::getInstance()->query(
    "SELECT * FROM mail_regeln ORDER BY prioritaet ASC, id ASC"
));
