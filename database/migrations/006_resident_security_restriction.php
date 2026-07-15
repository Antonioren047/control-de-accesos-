<?php
declare(strict_types=1);
return static function(PDO $pdo):void{
    $codes="'auth.password.change','auth.sessions.view','auth.sessions.revoke'";
    $pdo->exec("DELETE rp FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.code='resident' AND p.code IN ($codes)");
    $pdo->exec("DELETE up FROM user_permissions up JOIN users u ON u.id=up.user_id JOIN roles r ON r.id=u.role_id JOIN permissions p ON p.id=up.permission_id WHERE r.code='resident' AND p.code IN ($codes)");
};
