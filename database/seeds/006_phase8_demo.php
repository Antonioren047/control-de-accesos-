<?php
declare(strict_types=1);
return static function(PDO$pdo,bool$demo=false):void{
    if(!$demo)return;$exists=(int)$pdo->query("SELECT COUNT(*) FROM events e JOIN event_types et ON et.id=e.event_type_id WHERE et.code='incident' AND e.description='Incidencia demostrativa de la Fase 8'")->fetchColumn();if($exists)return;
    $row=$pdo->query("SELECT os.id session_id,os.client_id,os.location_id,os.access_point_id,os.guard_user_id,et.id type_id FROM operational_sessions os JOIN event_types et ON et.code='incident' ORDER BY os.id DESC LIMIT 1")->fetch();if(!$row)return;
    $s=$pdo->prepare("INSERT INTO events(event_type_id,operational_session_id,client_id,location_id,access_point_id,guard_user_id,description,priority,status,occurred_at,created_by,created_at,updated_at) VALUES(?,?,?,?,?,?,'Incidencia demostrativa de la Fase 8','important','submitted',UTC_TIMESTAMP(),?,UTC_TIMESTAMP(),UTC_TIMESTAMP())");$s->execute([$row['type_id'],$row['session_id'],$row['client_id'],$row['location_id'],$row['access_point_id'],$row['guard_user_id'],$row['guard_user_id']]);
};
