<?php
declare(strict_types=1);

return static function(PDO $pdo,bool $demo=false):void{
    if(!$demo)return;
    $companyId=(int)$pdo->query('SELECT id FROM surveillance_companies ORDER BY id LIMIT 1')->fetchColumn();
    if($companyId<=0)return;
    $client=$pdo->prepare("INSERT INTO clients(surveillance_company_id,code,name,legal_name,timezone,storage_limit_gb,is_active,created_at,updated_at) VALUES(?,'DEMO-01','Residencial Vértice','Residencial Vértice A.C.','America/Mexico_City',10,1,UTC_TIMESTAMP(),UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE name=VALUES(name),is_active=1,updated_at=UTC_TIMESTAMP()");$client->execute([$companyId]);
    $clientId=(int)$pdo->query("SELECT id FROM clients WHERE surveillance_company_id=$companyId AND code='DEMO-01'")->fetchColumn();
    $location=$pdo->prepare("INSERT INTO locations(client_id,code,name,address_line,city,state,postal_code,timezone,is_active,created_at,updated_at) VALUES(?,?,?,?,?,'Nuevo León','64000','America/Mexico_City',1,UTC_TIMESTAMP(),UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE name=VALUES(name),address_line=VALUES(address_line),is_active=1,updated_at=UTC_TIMESTAMP()");
    $location->execute([$clientId,'NORTE','Residencial Norte','Av. Seguridad 100','Monterrey']);
    $location->execute([$clientId,'SUR','Parque Industrial Sur','Carretera Industrial 250','Monterrey']);
    $north=(int)$pdo->query("SELECT id FROM locations WHERE client_id=$clientId AND code='NORTE'")->fetchColumn();
    $south=(int)$pdo->query("SELECT id FROM locations WHERE client_id=$clientId AND code='SUR'")->fetchColumn();
    $point=$pdo->prepare("INSERT INTO access_points(location_id,code,name,point_type,is_active,created_at,updated_at) VALUES(?,?,?,?,1,UTC_TIMESTAMP(),UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE name=VALUES(name),is_active=1,updated_at=UTC_TIMESTAMP()");
    $point->execute([$north,'PRINCIPAL','Caseta principal','main']);$point->execute([$south,'CARGA','Acceso de carga','vehicle']);
    $unit=$pdo->prepare("INSERT INTO units(location_id,code,name,unit_type,is_active,created_at,updated_at) VALUES(?,?,?,?,1,UTC_TIMESTAMP(),UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE name=VALUES(name),is_active=1,updated_at=UTC_TIMESTAMP()");
    $unit->execute([$north,'CASA-101','Casa 101','house']);$unit->execute([$north,'DEPTO-A2','Departamento A-2','apartment']);$unit->execute([$south,'NAVE-4','Nave industrial 4','warehouse']);
    $unitId=(int)$pdo->query("SELECT id FROM units WHERE location_id=$north AND code='CASA-101'")->fetchColumn();
    $users=$pdo->query("SELECT u.id,r.code FROM users u JOIN roles r ON r.id=u.role_id WHERE u.surveillance_company_id=$companyId")->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach($users as $userId=>$role){
        if($role==='admin')$pdo->prepare("INSERT INTO user_client_scopes(user_id,client_id,is_active,created_at,updated_at) VALUES(?,?,1,UTC_TIMESTAMP(),UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE is_active=1,updated_at=UTC_TIMESTAMP()")->execute([$userId,$clientId]);
        if($role==='supervisor')foreach([$north,$south] as $locationId)$pdo->prepare("INSERT INTO user_location_scopes(user_id,location_id,is_active,created_at,updated_at) VALUES(?,?,1,UTC_TIMESTAMP(),UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE is_active=1,updated_at=UTC_TIMESTAMP()")->execute([$userId,$locationId]);
        if($role==='guard'){$pointId=(int)$pdo->query("SELECT id FROM access_points WHERE location_id=$north AND code='PRINCIPAL'")->fetchColumn();$pdo->prepare("INSERT INTO user_access_point_scopes(user_id,access_point_id,is_active,created_at,updated_at) VALUES(?,?,1,UTC_TIMESTAMP(),UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE is_active=1,updated_at=UTC_TIMESTAMP()")->execute([$userId,$pointId]);}
        if($role==='resident'){$pdo->prepare("INSERT INTO resident_profiles(user_id,phone,is_active,created_at,updated_at) VALUES(?,'8110000000',1,UTC_TIMESTAMP(),UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE is_active=1,updated_at=UTC_TIMESTAMP()")->execute([$userId]);$pdo->prepare("INSERT INTO resident_units(resident_user_id,unit_id,is_primary,is_active,created_at,updated_at) VALUES(?,?,1,1,UTC_TIMESTAMP(),UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE is_primary=1,is_active=1,updated_at=UTC_TIMESTAMP()")->execute([$userId,$unitId]);}
    }
};
