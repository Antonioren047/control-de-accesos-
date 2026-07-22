<?php
declare(strict_types=1);
namespace Vigilancia\Repositories;

use PDO;

final class OrganizationRepository
{
    public function __construct(private PDO $pdo) {}

    public function clients(array $actor): array
    {
        $sql = "SELECT DISTINCT c.id,c.code,c.name,c.legal_name,c.timezone,c.storage_limit_gb,c.is_active FROM clients c";
        $params = [$actor['surveillance_company_id']];
        if ($actor['role_code'] === 'admin') {
            $sql .= ' JOIN user_client_scopes s ON s.client_id=c.id AND s.user_id=? AND s.is_active=1';
            array_unshift($params, $actor['id']);
        }
        $sql .= ' WHERE c.surveillance_company_id=? ORDER BY c.name';
        return $this->fetchAll($sql, $params);
    }

    public function locations(array $actor): array
    {
        $base = "SELECT DISTINCT l.id,l.client_id,c.name AS client_name,l.code,l.name,l.address_line,l.city,l.state,l.postal_code,l.timezone,l.is_active FROM locations l JOIN clients c ON c.id=l.client_id";
        $params = [];
        if ($actor['role_code'] === 'superadmin') {
            $sql = $base . ' WHERE c.surveillance_company_id=?'; $params = [$actor['surveillance_company_id']];
        } elseif ($actor['role_code'] === 'admin') {
            $sql = $base . ' LEFT JOIN user_client_scopes cs ON cs.client_id=c.id AND cs.user_id=? AND cs.is_active=1 LEFT JOIN user_location_scopes ls ON ls.location_id=l.id AND ls.user_id=? AND ls.is_active=1 WHERE c.surveillance_company_id=? AND (cs.user_id IS NOT NULL OR ls.user_id IS NOT NULL)';
            $params = [$actor['id'], $actor['id'], $actor['surveillance_company_id']];
        } else {
            $sql = $base . ' JOIN user_location_scopes ls ON ls.location_id=l.id AND ls.user_id=? AND ls.is_active=1 WHERE c.surveillance_company_id=?';
            $params = [$actor['id'], $actor['surveillance_company_id']];
        }
        return $this->fetchAll($sql . ' ORDER BY c.name,l.name', $params);
    }

    public function accessPoints(array $actor): array
    {
        $visibleLocations = array_column($this->locations($actor), 'id');
        if ($visibleLocations === []) return [];
        $placeholders = implode(',', array_fill(0, count($visibleLocations), '?'));
        return $this->fetchAll(
            "SELECT p.id,p.location_id,l.name AS location_name,p.code,p.name,p.point_type,p.is_active FROM access_points p JOIN locations l ON l.id=p.location_id WHERE p.location_id IN ($placeholders) ORDER BY l.name,p.name",
            $visibleLocations
        );
    }

    public function units(array $actor): array
    {
        if ($actor['role_code'] === 'resident') {
            return $this->fetchAll(
                "SELECT u.id,u.location_id,l.name AS location_name,c.name AS client_name,u.code,u.name,u.unit_type,u.is_active,ru.is_primary FROM resident_units ru JOIN units u ON u.id=ru.unit_id JOIN locations l ON l.id=u.location_id JOIN clients c ON c.id=l.client_id WHERE ru.resident_user_id=? AND ru.is_active=1 AND u.is_active=1 ORDER BY c.name,l.name,u.name",
                [$actor['id']]
            );
        }
        $visibleLocations = array_column($this->locations($actor), 'id');
        if ($visibleLocations === []) return [];
        $placeholders = implode(',', array_fill(0, count($visibleLocations), '?'));
        return $this->fetchAll(
            "SELECT u.id,u.location_id,l.name AS location_name,c.name AS client_name,u.code,u.name,u.unit_type,u.is_active FROM units u JOIN locations l ON l.id=u.location_id JOIN clients c ON c.id=l.client_id WHERE u.location_id IN ($placeholders) ORDER BY c.name,l.name,u.name",
            $visibleLocations
        );
    }

    public function residents(array $actor): array
    {
        $units = array_column($this->units($actor), 'id');
        if ($units === []) return [];
        $placeholders = implode(',', array_fill(0, count($units), '?'));
        return $this->fetchAll(
            "SELECT u.id,u.full_name,u.email,rp.phone,u.is_active,MAX(CASE WHEN ru.is_primary=1 THEN un.id END) AS unit_id,GROUP_CONCAT(DISTINCT un.name ORDER BY un.name SEPARATOR ', ') AS units FROM users u JOIN roles r ON r.id=u.role_id AND r.code='resident' LEFT JOIN resident_profiles rp ON rp.user_id=u.id JOIN resident_units ru ON ru.resident_user_id=u.id AND ru.is_active=1 JOIN units un ON un.id=ru.unit_id WHERE un.id IN ($placeholders) GROUP BY u.id,u.full_name,u.email,rp.phone,u.is_active ORDER BY u.full_name",
            $units
        );
    }

    public function createClient(array $data, int $companyId, int $actorId): int
    {
        $s=$this->pdo->prepare("INSERT INTO clients(surveillance_company_id,code,name,legal_name,timezone,storage_limit_gb,is_active,created_by,updated_by,created_at,updated_at) VALUES(?,?,?,?,?,10,1,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
        $s->execute([$companyId,$data['code'],$data['name'],$data['legal_name'] ?: null,$data['timezone'],$actorId,$actorId]);
        $id=(int)$this->pdo->lastInsertId();
        $this->pdo->prepare('INSERT IGNORE INTO access_policies(client_id,created_at,updated_at) VALUES(?,UTC_TIMESTAMP(),UTC_TIMESTAMP())')->execute([$id]);
        return $id;
    }

    public function createLocation(array $data, int $actorId): int
    {
        $s=$this->pdo->prepare("INSERT INTO locations(client_id,code,name,address_line,city,state,postal_code,timezone,is_active,created_by,updated_by,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,1,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
        $s->execute([$data['client_id'],$data['code'],$data['name'],$data['address_line'],$data['city'] ?: null,$data['state'] ?: null,$data['postal_code'] ?: null,$data['timezone'],$actorId,$actorId]);
        return (int)$this->pdo->lastInsertId();
    }

    public function createAccessPoint(array $data, int $actorId): int
    {
        $s=$this->pdo->prepare("INSERT INTO access_points(location_id,code,name,point_type,is_active,created_by,updated_by,created_at,updated_at) VALUES(?,?,?,?,1,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
        $s->execute([$data['location_id'],$data['code'],$data['name'],$data['point_type'],$actorId,$actorId]);
        return (int)$this->pdo->lastInsertId();
    }

    public function createUnit(array $data, int $actorId): int
    {
        $s=$this->pdo->prepare("INSERT INTO units(location_id,code,name,unit_type,is_active,created_by,updated_by,created_at,updated_at) VALUES(?,?,?,?,1,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
        $s->execute([$data['location_id'],$data['code'],$data['name'],$data['unit_type'],$actorId,$actorId]);
        return (int)$this->pdo->lastInsertId();
    }

    public function createResident(array $data, int $companyId, int $actorId): int
    {
        $roleId=(int)$this->pdo->query("SELECT id FROM roles WHERE code='resident'")->fetchColumn();
        $s=$this->pdo->prepare("INSERT INTO users(surveillance_company_id,role_id,full_name,email,password_hash,password_changed_at,is_active,theme_preference,force_password_change,created_at,updated_at) VALUES(?,?,?,?,?,UTC_TIMESTAMP(),1,'auto',1,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
        $s->execute([$companyId,$roleId,$data['full_name'],$data['email'],$data['password_hash']]);
        $id=(int)$this->pdo->lastInsertId();
        $this->pdo->prepare("INSERT INTO resident_profiles(user_id,phone,is_active,created_at,updated_at) VALUES(?,?,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())")->execute([$id,$data['phone'] ?: null]);
        $this->linkResident($id,(int)$data['unit_id'],$actorId,true);
        return $id;
    }

    public function linkResident(int $residentId, int $unitId, int $actorId, bool $primary=false): void
    {
        $this->pdo->prepare("INSERT INTO resident_units(resident_user_id,unit_id,is_primary,is_active,created_by,created_at,updated_at) VALUES(?,?,?,1,?,UTC_TIMESTAMP(),UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE is_primary=VALUES(is_primary),is_active=1,updated_at=UTC_TIMESTAMP()")
            ->execute([$residentId,$unitId,$primary?1:0,$actorId]);
    }

    public function updateClient(int $id,array $d,int $actorId):void{$this->pdo->prepare('UPDATE clients SET code=?,name=?,legal_name=?,timezone=?,updated_by=?,updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$d['code'],$d['name'],$d['legal_name']?:null,$d['timezone'],$actorId,$id]);}
    public function updateLocation(int $id,array $d,int $actorId):void{$this->pdo->prepare('UPDATE locations SET client_id=?,code=?,name=?,address_line=?,city=?,state=?,postal_code=?,timezone=?,updated_by=?,updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$d['client_id'],$d['code'],$d['name'],$d['address_line'],$d['city']?:null,$d['state']?:null,$d['postal_code']?:null,$d['timezone'],$actorId,$id]);}
    public function updateAccessPoint(int $id,array $d,int $actorId):void{$this->pdo->prepare('UPDATE access_points SET location_id=?,code=?,name=?,point_type=?,updated_by=?,updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$d['location_id'],$d['code'],$d['name'],$d['point_type'],$actorId,$id]);}
    public function updateUnit(int $id,array $d,int $actorId):void{$this->pdo->prepare('UPDATE units SET location_id=?,code=?,name=?,unit_type=?,updated_by=?,updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$d['location_id'],$d['code'],$d['name'],$d['unit_type'],$actorId,$id]);}
    public function updateResident(int $id,array $d,int $actorId):void
    {
        $sql='UPDATE users SET full_name=?,email=?,updated_at=UTC_TIMESTAMP()';$params=[$d['full_name'],$d['email']];
        if(!empty($d['password_hash'])){$sql.=',password_hash=?,password_changed_at=UTC_TIMESTAMP(),force_password_change=1';$params[]=$d['password_hash'];}
        $sql.=' WHERE id=?';$params[]=$id;$this->pdo->prepare($sql)->execute($params);
        $this->pdo->prepare('UPDATE resident_profiles SET phone=?,updated_at=UTC_TIMESTAMP() WHERE user_id=?')->execute([$d['phone']?:null,$id]);
        $this->pdo->prepare('UPDATE resident_units SET is_primary=0,updated_at=UTC_TIMESTAMP() WHERE resident_user_id=?')->execute([$id]);
        $this->linkResident($id,(int)$d['unit_id'],$actorId,true);
    }

    public function setActive(string $entity, int $id, bool $active, int $actorId): void
    {
        if($entity==='resident'){$this->pdo->prepare('UPDATE users SET is_active=?,updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$active?1:0,$id]);$this->pdo->prepare('UPDATE resident_profiles SET is_active=?,updated_at=UTC_TIMESTAMP() WHERE user_id=?')->execute([$active?1:0,$id]);return;}
        $tables=['client'=>'clients','location'=>'locations','access_point'=>'access_points','unit'=>'units'];
        $table=$tables[$entity]??null;
        if(!$table) return;
        $this->pdo->prepare("UPDATE $table SET is_active=?,updated_by=?,updated_at=UTC_TIMESTAMP() WHERE id=?")->execute([$active?1:0,$actorId,$id]);
    }

    private function fetchAll(string $sql, array $parameters): array
    {
        $statement=$this->pdo->prepare($sql);$statement->execute($parameters);return $statement->fetchAll();
    }
}
