<?php
declare(strict_types=1);

return static function(PDO $pdo,bool $demo=false):void{
    if(!$demo)return;
    $row=$pdo->query("SELECT ru.resident_user_id,u.id unit_id,u.location_id FROM resident_units ru JOIN units u ON u.id=ru.unit_id WHERE ru.is_active=1 AND u.is_active=1 ORDER BY ru.resident_user_id LIMIT 1")->fetch();
    if(!$row)return;
    $exists=$pdo->prepare("SELECT id FROM visitor_passes WHERE resident_user_id=? AND visitor_name='Visitante Demostración' LIMIT 1");$exists->execute([$row['resident_user_id']]);if($exists->fetchColumn())return;
    $token=bin2hex(random_bytes(32));$scheduled=gmdate('Y-m-d H:i:s',time()+86400);$until=gmdate('Y-m-d H:i:s',time()+93600);
    $insert=$pdo->prepare("INSERT INTO visitor_passes(resident_user_id,unit_id,location_id,visitor_name,phone,host_name,reason,scheduled_at,valid_from,valid_until,qr_token_hash,qr_reference,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?, ?,?,?,?,?,'pending',UTC_TIMESTAMP(),UTC_TIMESTAMP())");
    $reference=substr(hash('sha256',$token.random_bytes(8)),0,12);$insert->execute([$row['resident_user_id'],$row['unit_id'],$row['location_id'],'Visitante Demostración','8115550101','Residente Demo','Visita de demostración',$scheduled,$scheduled,$until,hash('sha256',$token),$reference]);$id=(int)$pdo->lastInsertId();
    if(class_exists(\Vigilancia\Services\AccessAssetService::class)){$path=(new \Vigilancia\Services\AccessAssetService(dirname(__DIR__,2)))->createQr($token,'visit',$id);if($path)$pdo->prepare('UPDATE visitor_passes SET qr_asset_path=? WHERE id=?')->execute([$path,$id]);}
};
