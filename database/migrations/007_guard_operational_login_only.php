<?php
declare(strict_types=1);
return static function(PDO $pdo):void{
    $pdo->exec("INSERT INTO system_settings(setting_key,setting_value,value_type,is_public,created_at,updated_at) VALUES('security.guard_web_login_enabled','0','boolean',0,UTC_TIMESTAMP(),UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE setting_value='0',updated_at=UTC_TIMESTAMP()");
    $pdo->exec("UPDATE user_sessions s JOIN users u ON u.id=s.user_id JOIN roles r ON r.id=u.role_id SET s.revoked_at=UTC_TIMESTAMP() WHERE r.code='guard' AND s.revoked_at IS NULL");
};
