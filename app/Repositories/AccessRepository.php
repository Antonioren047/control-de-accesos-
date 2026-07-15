<?php
declare(strict_types=1);

namespace Vigilancia\Repositories;

use PDO;

final class AccessRepository
{
    public function __construct(private PDO $pdo) {}

    public function residentUnits(int $residentId): array
    {
        return $this->all("SELECT u.id unit_id,u.name unit_name,l.id location_id,l.name location_name,c.id client_id,c.name client_name,l.timezone,ap.visit_duration_minutes,ap.max_advance_days,ap.max_active_visits_per_resident,ap.minors_identification_exempt,ap.privacy_notice_version FROM resident_units ru JOIN units u ON u.id=ru.unit_id AND u.is_active=1 JOIN locations l ON l.id=u.location_id AND l.is_active=1 JOIN clients c ON c.id=l.client_id AND c.is_active=1 JOIN access_policies ap ON ap.client_id=c.id WHERE ru.resident_user_id=? AND ru.is_active=1 ORDER BY c.name,l.name,u.name", [$residentId]);
    }

    public function unitForResident(int $unitId, int $residentId): ?array
    {
        foreach ($this->residentUnits($residentId) as $unit) if ((int) $unit['unit_id'] === $unitId) return $unit;
        return null;
    }

    public function catalog(array $actor): array
    {
        if ($actor['role_code'] === 'resident') return $this->residentUnits((int) $actor['id']);
        $sql = "SELECT u.id unit_id,u.name unit_name,l.id location_id,l.name location_name,c.id client_id,c.name client_name,l.timezone,ap.visit_duration_minutes,ap.max_advance_days,ap.max_active_visits_per_resident,ap.minors_identification_exempt,ap.privacy_notice_version FROM units u JOIN locations l ON l.id=u.location_id AND l.is_active=1 JOIN clients c ON c.id=l.client_id AND c.is_active=1 JOIN access_policies ap ON ap.client_id=c.id WHERE u.is_active=1 AND c.surveillance_company_id=?";
        $params = [$actor['surveillance_company_id']];
        if ($actor['role_code'] === 'supervisor') { $sql .= ' AND EXISTS(SELECT 1 FROM user_location_scopes s WHERE s.user_id=? AND s.location_id=l.id AND s.is_active=1)'; $params[] = $actor['id']; }
        elseif ($actor['role_code'] === 'admin') { $sql .= ' AND (EXISTS(SELECT 1 FROM user_location_scopes s WHERE s.user_id=? AND s.location_id=l.id AND s.is_active=1) OR EXISTS(SELECT 1 FROM user_client_scopes s WHERE s.user_id=? AND s.client_id=c.id AND s.is_active=1))'; array_push($params,$actor['id'],$actor['id']); }
        return $this->all($sql . ' ORDER BY c.name,l.name,u.name', $params);
    }

    public function expire(): void
    {
        $this->pdo->exec("UPDATE visitor_passes SET status='expired',updated_at=UTC_TIMESTAMP() WHERE status='pending' AND valid_until<UTC_TIMESTAMP()");
    }

    public function policyForLocation(int $locationId): ?array
    {
        return $this->one('SELECT ap.* FROM access_policies ap JOIN locations l ON l.client_id=ap.client_id WHERE l.id=?', [$locationId]);
    }

    public function visits(array $actor): array
    {
        $this->expire();
        $sql = "SELECT vp.*,u.name unit_name,l.name location_name,r.full_name resident_name,va.entry_at,va.exit_at FROM visitor_passes vp JOIN units u ON u.id=vp.unit_id JOIN locations l ON l.id=vp.location_id JOIN users r ON r.id=vp.resident_user_id LEFT JOIN visitor_accesses va ON va.visitor_pass_id=vp.id JOIN clients c ON c.id=l.client_id WHERE c.surveillance_company_id=?";
        $params = [$actor['surveillance_company_id']];
        if ($actor['role_code'] === 'resident') { $sql .= ' AND vp.resident_user_id=?'; $params[] = $actor['id']; }
        elseif ($actor['role_code'] === 'supervisor') { $sql .= ' AND EXISTS(SELECT 1 FROM user_location_scopes s WHERE s.user_id=? AND s.location_id=vp.location_id AND s.is_active=1)'; $params[] = $actor['id']; }
        elseif ($actor['role_code'] === 'admin') { $sql .= ' AND (EXISTS(SELECT 1 FROM user_location_scopes s WHERE s.user_id=? AND s.location_id=vp.location_id AND s.is_active=1) OR EXISTS(SELECT 1 FROM user_client_scopes s WHERE s.user_id=? AND s.client_id=l.client_id AND s.is_active=1))'; array_push($params, $actor['id'], $actor['id']); }
        return $this->all($sql . ' ORDER BY vp.scheduled_at DESC LIMIT 300', $params);
    }

    public function activeVisitCount(int $residentId): int
    {
        $statement = $this->pdo->prepare("SELECT COUNT(*) FROM visitor_passes WHERE resident_user_id=? AND status IN ('pending','inside')");
        $statement->execute([$residentId]); return (int) $statement->fetchColumn();
    }

    public function duplicateVisit(int $residentId, int $locationId, string $name, string $scheduled, int $exclude = 0): bool
    {
        $statement = $this->pdo->prepare("SELECT COUNT(*) FROM visitor_passes WHERE resident_user_id=? AND location_id=? AND LOWER(visitor_name)=LOWER(?) AND ABS(TIMESTAMPDIFF(MINUTE,scheduled_at,?))<120 AND status IN ('pending','inside') AND id<>?");
        $statement->execute([$residentId, $locationId, $name, $scheduled, $exclude]); return (int) $statement->fetchColumn() > 0;
    }

    public function createVisit(array $data): int
    {
        $statement = $this->pdo->prepare("INSERT INTO visitor_passes(resident_user_id,unit_id,location_id,visitor_name,phone,identification_type,identification_number,company,host_name,reason,license_plate,vehicle,scheduled_at,valid_from,valid_until,qr_token_hash,qr_reference,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'pending',UTC_TIMESTAMP(),UTC_TIMESTAMP())");
        $statement->execute([$data['resident_id'],$data['unit_id'],$data['location_id'],$data['visitor_name'],$data['phone']?:null,$data['identification_type']?:null,$data['identification_number']?:null,$data['company']?:null,$data['host_name'],$data['reason'],$data['license_plate']?:null,$data['vehicle']?:null,$data['scheduled_at'],$data['valid_from'],$data['valid_until'],$data['token_hash'],$data['reference']]);
        return (int) $this->pdo->lastInsertId();
    }

    public function setVisitQr(int $id, ?string $path): void { $this->pdo->prepare('UPDATE visitor_passes SET qr_asset_path=?,updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$path,$id]); }
    public function visitById(int $id): ?array { return $this->one('SELECT vp.*,l.client_id FROM visitor_passes vp JOIN locations l ON l.id=vp.location_id WHERE vp.id=?', [$id]); }
    public function visitByToken(string $value): ?array { $reference = strtolower(trim($value)); return preg_match('/^[a-f0-9]{12}$/',$reference) ? $this->one('SELECT vp.*,ap.privacy_notice_version,ap.minors_identification_exempt FROM visitor_passes vp JOIN locations l ON l.id=vp.location_id JOIN access_policies ap ON ap.client_id=l.client_id WHERE vp.qr_reference=?',[$reference]) : $this->one('SELECT vp.*,ap.privacy_notice_version,ap.minors_identification_exempt FROM visitor_passes vp JOIN locations l ON l.id=vp.location_id JOIN access_policies ap ON ap.client_id=l.client_id WHERE vp.qr_token_hash=?',[hash('sha256',$value)]); }
    public function updateVisit(int $id,array $data):void{$this->pdo->prepare("UPDATE visitor_passes SET unit_id=?,location_id=?,visitor_name=?,phone=?,identification_type=?,identification_number=?,company=?,host_name=?,reason=?,license_plate=?,vehicle=?,scheduled_at=?,valid_from=?,valid_until=?,updated_at=UTC_TIMESTAMP() WHERE id=? AND status='pending' AND first_used_at IS NULL")->execute([$data['unit_id'],$data['location_id'],$data['visitor_name'],$data['phone']?:null,$data['identification_type']?:null,$data['identification_number']?:null,$data['company']?:null,$data['host_name'],$data['reason'],$data['license_plate']?:null,$data['vehicle']?:null,$data['scheduled_at'],$data['valid_from'],$data['valid_until'],$id]);}
    public function cancelVisit(int$id,int$actor):void{$this->pdo->prepare("UPDATE visitor_passes SET status='cancelled',cancelled_at=UTC_TIMESTAMP(),cancelled_by=?,updated_at=UTC_TIMESTAMP() WHERE id=? AND status='pending' AND first_used_at IS NULL")->execute([$actor,$id]);}
    public function shareVisit(int$id,int$resident,string$channel,string$ip,string$agent):void{$this->pdo->prepare('INSERT INTO visitor_share_logs(visitor_pass_id,resident_user_id,channel,pressed_at,ip_address,user_agent) VALUES(?,?,?,UTC_TIMESTAMP(),?,?)')->execute([$id,$resident,$channel,$ip,substr($agent,0,255)]);}
    public function checkInVisit(array$d):int{$s=$this->pdo->prepare("INSERT INTO visitor_accesses(visitor_pass_id,operational_session_id,access_point_id,entry_guard_user_id,entry_at,identification_type,identification_number,identification_photo_path,visitor_photo_path,privacy_notice_version,privacy_accepted_at,privacy_ip_address,privacy_device_identifier,created_at,updated_at) VALUES(?,?,?,?,UTC_TIMESTAMP(),?,?,?,?,?,UTC_TIMESTAMP(),?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())");$s->execute([$d['visit_id'],$d['session_id'],$d['point_id'],$d['guard_id'],$d['identification_type'],$d['identification_number'],$d['identification_photo'],$d['visitor_photo'],$d['privacy_version'],$d['ip'],$d['device']]);$this->pdo->prepare("UPDATE visitor_passes SET status='inside',first_used_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP() WHERE id=?")->execute([$d['visit_id']]);return(int)$this->pdo->lastInsertId();}
    public function checkOutVisit(int$id,int$guard,int$point):void{$this->pdo->prepare('UPDATE visitor_accesses SET exit_guard_user_id=?,exit_access_point_id=?,exit_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP() WHERE visitor_pass_id=? AND exit_at IS NULL')->execute([$guard,$point,$id]);$this->pdo->prepare("UPDATE visitor_passes SET status='checked_out',updated_at=UTC_TIMESTAMP() WHERE id=? AND status='inside'")->execute([$id]);}
    public function activeVisits(int$location):array{return$this->all("SELECT vp.id,vp.visitor_name,vp.host_name,vp.qr_reference,u.name unit_name,va.entry_at FROM visitor_passes vp JOIN units u ON u.id=vp.unit_id JOIN visitor_accesses va ON va.visitor_pass_id=vp.id WHERE vp.location_id=? AND vp.status='inside' AND va.exit_at IS NULL ORDER BY va.entry_at DESC",[$location]);}

    public function providers(array$actor):array{$sql="SELECT pa.*,u.name unit_name,l.name location_name,r.full_name resident_name FROM provider_accesses pa LEFT JOIN units u ON u.id=pa.unit_id JOIN locations l ON l.id=pa.location_id LEFT JOIN users r ON r.id=pa.resident_user_id JOIN clients c ON c.id=l.client_id WHERE c.surveillance_company_id=?";$p=[$actor['surveillance_company_id']];if($actor['role_code']==='resident'){$sql.=' AND pa.resident_user_id=?';$p[]=$actor['id'];}elseif($actor['role_code']==='supervisor'){$sql.=' AND EXISTS(SELECT 1 FROM user_location_scopes s WHERE s.user_id=? AND s.location_id=pa.location_id AND s.is_active=1)';$p[]=$actor['id'];}elseif($actor['role_code']==='admin'){$sql.=' AND (EXISTS(SELECT 1 FROM user_location_scopes s WHERE s.user_id=? AND s.location_id=pa.location_id AND s.is_active=1) OR EXISTS(SELECT 1 FROM user_client_scopes s WHERE s.user_id=? AND s.client_id=l.client_id AND s.is_active=1))';array_push($p,$actor['id'],$actor['id']);}return$this->all($sql.' ORDER BY pa.created_at DESC LIMIT 300',$p);}
    public function createProvider(array$d):int{$s=$this->pdo->prepare("INSERT INTO provider_accesses(resident_user_id,created_by,unit_id,location_id,provider_company,service_type,responsible_name,materials,tools,scheduled_at,qr_token_hash,qr_reference,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?, 'pending',UTC_TIMESTAMP(),UTC_TIMESTAMP())");$s->execute([$d['resident_id']?:null,$d['creator'],$d['unit_id']?:null,$d['location_id'],$d['company'],$d['service'],$d['responsible'],$d['materials']?:null,$d['tools']?:null,$d['scheduled_at']?:null,$d['token_hash']?:null,$d['reference']?:null]);return(int)$this->pdo->lastInsertId();}
    public function setProviderQr(int$id,?string$path):void{$this->pdo->prepare('UPDATE provider_accesses SET qr_asset_path=?,updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$path,$id]);}
    public function providerById(int$id):?array{return$this->one('SELECT pa.*,l.client_id FROM provider_accesses pa JOIN locations l ON l.id=pa.location_id WHERE pa.id=?',[$id]);}
    public function providerByToken(string$value):?array{$ref=strtolower(trim($value));return preg_match('/^[a-f0-9]{12}$/',$ref)?$this->one('SELECT * FROM provider_accesses WHERE qr_reference=?',[$ref]):$this->one('SELECT * FROM provider_accesses WHERE qr_token_hash=?',[hash('sha256',$value)]);}
    public function checkInProvider(int$id,array$d):void{$this->pdo->prepare("UPDATE provider_accesses SET operational_session_id=?,access_point_id=?,identification_type=?,identification_number=?,identification_photo_path=?,person_photo_path=?,privacy_notice_version=?,privacy_accepted_at=UTC_TIMESTAMP(),privacy_ip_address=?,status='inside',entry_guard_user_id=?,entry_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP() WHERE id=? AND status='pending'")->execute([$d['session_id'],$d['point_id'],$d['identification_type'],$d['identification_number'],$d['identification_photo'],$d['person_photo'],$d['privacy_version'],$d['ip'],$d['guard_id'],$id]);}
    public function checkOutProvider(int$id,int$guard):void{$this->pdo->prepare("UPDATE provider_accesses SET status='checked_out',exit_guard_user_id=?,exit_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP() WHERE id=? AND status='inside'")->execute([$guard,$id]);}
    public function activeProviders(int$location):array{return$this->all("SELECT id,provider_company,service_type,responsible_name,materials,tools,qr_reference,entry_at FROM provider_accesses WHERE location_id=? AND status='inside' ORDER BY entry_at DESC",[$location]);}
    private function one(string$sql,array$p):?array{$s=$this->pdo->prepare($sql);$s->execute($p);return$s->fetch()?:null;}
    private function all(string$sql,array$p):array{$s=$this->pdo->prepare($sql);$s->execute($p);return$s->fetchAll();}
}
