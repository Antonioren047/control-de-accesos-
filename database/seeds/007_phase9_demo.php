<?php
declare(strict_types=1);
return static function(PDO$pdo,bool$demo=false):void{
    if(!$demo)return;
    $pin=password_hash('102938',PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE users u JOIN roles r ON r.id=u.role_id SET u.confirmation_pin_hash=? WHERE r.code IN ('superadmin','admin','supervisor') AND u.confirmation_pin_hash IS NULL")->execute([$pin]);
    $exists=(int)$pdo->query('SELECT COUNT(*) FROM supervision_schedules')->fetchColumn();if($exists)return;
    $row=$pdo->query("SELECT l.id location_id,l.client_id,ap.id point_id,u.id creator FROM locations l JOIN access_points ap ON ap.location_id=l.id JOIN users u JOIN roles r ON r.id=u.role_id AND r.code IN ('superadmin','admin') WHERE l.is_active=1 AND ap.is_active=1 ORDER BY l.id,ap.id LIMIT 1")->fetch();if(!$row)return;
    $s=$pdo->prepare("INSERT INTO supervision_schedules(client_id,location_id,frequency,scheduled_at,status,created_by,created_at,updated_at) VALUES(?,?,'weekly',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY),'pending',?,UTC_TIMESTAMP(),UTC_TIMESTAMP())");$s->execute([$row['client_id'],$row['location_id'],$row['creator']]);$id=(int)$pdo->lastInsertId();$pdo->prepare('INSERT INTO supervision_schedule_points(schedule_id,access_point_id) VALUES(?,?)')->execute([$id,$row['point_id']]);
};
