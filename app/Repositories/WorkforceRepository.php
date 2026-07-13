<?php
declare(strict_types=1);
namespace Vigilancia\Repositories;

use PDO;

final class WorkforceRepository
{
    public function __construct(private PDO $pdo){}
    public function guards(array $actor):array
    {
        $sql="SELECT DISTINCT u.id,u.full_name,NULLIF(u.email,CONCAT('guard.',gp.employee_number,'@local.invalid')) AS email,gp.employee_number,gp.photo_path,gp.phone,gp.curp,gp.address_line,gp.hire_date,gp.emergency_contact_name,gp.emergency_contact_phone,gp.status,u.is_active,(SELECT gc.id FROM guard_credentials gc WHERE gc.guard_user_id=u.id AND gc.status='active' ORDER BY gc.id DESC LIMIT 1) AS credential_id FROM users u JOIN roles r ON r.id=u.role_id AND r.code='guard' JOIN guard_profiles gp ON gp.user_id=u.id";
        $params=[];
        if($actor['role_code']==='supervisor'){$sql.=" JOIN guard_assignments ga ON ga.guard_user_id=u.id AND ga.status='active' JOIN user_location_scopes ls ON ls.location_id=ga.location_id AND ls.user_id=? AND ls.is_active=1";$params[]=$actor['id'];}
        $sql.=" WHERE u.surveillance_company_id=? ORDER BY u.full_name";$params[]=$actor['surveillance_company_id'];return $this->fetch($sql,$params);
    }
    public function shifts(array $actor):array
    {
        $sql="SELECT DISTINCT s.id,s.name,TIME_FORMAT(s.start_time,'%H:%i') start_time,TIME_FORMAT(s.end_time,'%H:%i') end_time,s.crosses_midnight,s.tolerance_minutes,s.early_departure_tolerance_minutes,s.overtime_after_minutes,s.applicable_days,s.is_active,GROUP_CONCAT(DISTINCT l.name ORDER BY l.name SEPARATOR ', ') locations FROM shifts s LEFT JOIN shift_locations sl ON sl.shift_id=s.id AND sl.is_active=1 LEFT JOIN locations l ON l.id=sl.location_id";$params=[];
        if($actor['role_code']==='supervisor'){$sql.=" JOIN user_location_scopes ls ON ls.location_id=l.id AND ls.user_id=? AND ls.is_active=1";$params[]=$actor['id'];}
        elseif($actor['role_code']==='guard'){$sql.=" JOIN guard_assignments ga ON ga.shift_id=s.id AND ga.guard_user_id=? AND ga.status='active'";$params[]=$actor['id'];}
        $sql.=" WHERE s.surveillance_company_id=? GROUP BY s.id ORDER BY s.name";$params[]=$actor['surveillance_company_id'];return $this->fetch($sql,$params);
    }
    public function assignments(array $actor):array
    {
        $sql="SELECT ga.id,ga.guard_user_id,u.full_name guard_name,gp.employee_number,c.name client_name,l.name location_name,ap.name access_point_name,s.name shift_name,ga.start_date,ga.end_date,ga.applicable_days,ga.assignment_type,ga.rotation_pattern,ga.status FROM guard_assignments ga JOIN users u ON u.id=ga.guard_user_id JOIN guard_profiles gp ON gp.user_id=u.id JOIN clients c ON c.id=ga.client_id JOIN locations l ON l.id=ga.location_id JOIN access_points ap ON ap.id=ga.access_point_id JOIN shifts s ON s.id=ga.shift_id";$params=[];$where=["u.surveillance_company_id=?"];$params[]=$actor['surveillance_company_id'];
        if($actor['role_code']==='supervisor'){$sql.=" JOIN user_location_scopes ls ON ls.location_id=ga.location_id AND ls.user_id=? AND ls.is_active=1";array_unshift($params,$actor['id']);}
        if($actor['role_code']==='guard')$where[]='ga.guard_user_id='.(int)$actor['id'];$sql.=' WHERE '.implode(' AND ',$where).' ORDER BY ga.start_date DESC,ga.id DESC';return $this->fetch($sql,$params);
    }
    public function createGuard(array $data,int $companyId,int $actorId):array
    {
        $role=(int)$this->pdo->query("SELECT id FROM roles WHERE code='guard'")->fetchColumn();$email=$data['email']?:'guard.'.$data['employee_number'].'@local.invalid';
        $s=$this->pdo->prepare("INSERT INTO users(surveillance_company_id,role_id,full_name,email,password_hash,password_changed_at,is_active,theme_preference,force_password_change,created_at,updated_at) VALUES(?,?,?,?,?,UTC_TIMESTAMP(),1,'auto',0,UTC_TIMESTAMP(),UTC_TIMESTAMP())");$s->execute([$companyId,$role,$data['full_name'],strtolower($email),password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT)]);$id=(int)$this->pdo->lastInsertId();
        $this->pdo->prepare("INSERT INTO guard_profiles(user_id,employee_number,photo_path,phone,curp,address_line,hire_date,emergency_contact_name,emergency_contact_phone,pin_hash,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?, 'active',UTC_TIMESTAMP(),UTC_TIMESTAMP())")->execute([$id,$data['employee_number'],$data['photo_path'],$data['phone'],$data['curp']?:null,$data['address_line'],$data['hire_date'],$data['emergency_contact_name'],$data['emergency_contact_phone'],$data['pin_hash']]);
        return ['id'=>$id,'email'=>$email];
    }
    public function createCredential(int $guardId,string $tokenHash,string $reference,?int $previousId=null):int
    {$s=$this->pdo->prepare("INSERT INTO guard_credentials(guard_user_id,token_hash,token_reference,status,issued_at,regenerated_from_id,created_at) VALUES(?,?,?,'active',UTC_TIMESTAMP(),?,UTC_TIMESTAMP())");$s->execute([$guardId,$tokenHash,$reference,$previousId]);return(int)$this->pdo->lastInsertId();}
    public function setQrPath(int $id,string $path):void{$this->pdo->prepare('UPDATE guard_credentials SET qr_asset_path=? WHERE id=?')->execute([$path,$id]);}
    public function activeCredentialId(int $guardId):?int{$s=$this->pdo->prepare("SELECT id FROM guard_credentials WHERE guard_user_id=? AND status='active' ORDER BY id DESC LIMIT 1");$s->execute([$guardId]);$id=$s->fetchColumn();return$id===false?null:(int)$id;}
    public function revokeCredentials(int $guardId,int $actorId):void{$this->pdo->prepare("UPDATE guard_credentials SET status='revoked',revoked_at=UTC_TIMESTAMP(),revoked_by=? WHERE guard_user_id=? AND status='active'")->execute([$actorId,$guardId]);}
    public function resetPin(int $guardId,string $hash):void{$this->pdo->prepare("UPDATE guard_profiles SET pin_hash=?,pin_failed_attempts=0,pin_blocked_until=NULL,updated_at=UTC_TIMESTAMP() WHERE user_id=?")->execute([$hash,$guardId]);}
    public function createShift(array $d,int $companyId,int $actorId):int{$s=$this->pdo->prepare("INSERT INTO shifts(surveillance_company_id,name,start_time,end_time,crosses_midnight,tolerance_minutes,early_departure_tolerance_minutes,overtime_after_minutes,applicable_days,is_active,created_by,updated_by,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,1,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())");$s->execute([$companyId,$d['name'],$d['start_time'],$d['end_time'],$d['crosses_midnight']?1:0,$d['tolerance_minutes'],$d['early_tolerance'],$d['overtime_minutes'],json_encode($d['days']),$actorId,$actorId]);$id=(int)$this->pdo->lastInsertId();foreach($d['location_ids'] as $locationId)$this->pdo->prepare("INSERT INTO shift_locations(shift_id,location_id,is_active,created_at) VALUES(?,?,1,UTC_TIMESTAMP())")->execute([$id,$locationId]);return$id;}
    public function overlappingAssignments(int $guardId,string $start,?string $end,array $days):array{$end=$end?:'9999-12-31';$s=$this->pdo->prepare("SELECT id,applicable_days FROM guard_assignments WHERE guard_user_id=? AND status='active' AND start_date<=? AND COALESCE(end_date,'9999-12-31')>=?");$s->execute([$guardId,$end,$start]);return array_values(array_filter($s->fetchAll(),fn($row)=>array_intersect($days,json_decode($row['applicable_days'],true)?:[])!==[]));}
    public function createAssignment(array $d,int $actorId):int{$s=$this->pdo->prepare("INSERT INTO guard_assignments(guard_user_id,client_id,location_id,access_point_id,shift_id,start_date,end_date,applicable_days,assignment_type,rotation_pattern,replaces_assignment_id,status,created_by,updated_by,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,'active',?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())");$s->execute([$d['guard_user_id'],$d['client_id'],$d['location_id'],$d['access_point_id'],$d['shift_id'],$d['start_date'],$d['end_date']?:null,json_encode($d['days']),$d['assignment_type'],$d['rotation_pattern']?:null,$d['replaces_assignment_id']?:null,$actorId,$actorId]);$id=(int)$this->pdo->lastInsertId();$this->history($id,'created',$actorId);return$id;}
    public function cancelAssignment(int $id,int $actorId):void{$this->history($id,'cancelled',$actorId);$this->pdo->prepare("UPDATE guard_assignments SET status='cancelled',updated_by=?,updated_at=UTC_TIMESTAMP() WHERE id=?")->execute([$actorId,$id]);}
    public function requestAssignmentChange(int $id,int $actorId,string $type,string $comment):int{$s=$this->pdo->prepare("INSERT INTO assignment_change_requests(assignment_id,requested_by,request_type,comment,status,created_at,updated_at) VALUES(?,?,?,?,'pending',UTC_TIMESTAMP(),UTC_TIMESTAMP())");$s->execute([$id,$actorId,$type,$comment]);return(int)$this->pdo->lastInsertId();}
    public function assignmentInScope(int $id,array $actor):bool{$sql="SELECT COUNT(*) FROM guard_assignments ga JOIN users u ON u.id=ga.guard_user_id WHERE ga.id=? AND u.surveillance_company_id=?";$p=[$id,$actor['surveillance_company_id']];if($actor['role_code']==='supervisor'){$sql.=" AND EXISTS(SELECT 1 FROM user_location_scopes ls WHERE ls.user_id=? AND ls.location_id=ga.location_id AND ls.is_active=1)";$p[]=$actor['id'];}$s=$this->pdo->prepare($sql);$s->execute($p);return(int)$s->fetchColumn()>0;}
    public function guardInCompany(int $id,int $companyId):bool{$s=$this->pdo->prepare("SELECT COUNT(*) FROM users u JOIN roles r ON r.id=u.role_id AND r.code='guard' JOIN guard_profiles gp ON gp.user_id=u.id WHERE u.id=? AND u.surveillance_company_id=? AND u.is_active=1 AND gp.status='active'");$s->execute([$id,$companyId]);return(int)$s->fetchColumn()>0;}
    public function assignmentRelations(int $clientId,int $locationId,int $pointId,int $shiftId):bool{$s=$this->pdo->prepare("SELECT COUNT(*) FROM locations l JOIN access_points ap ON ap.location_id=l.id JOIN shift_locations sl ON sl.location_id=l.id AND sl.is_active=1 JOIN shifts sh ON sh.id=sl.shift_id AND sh.is_active=1 WHERE l.id=? AND l.client_id=? AND ap.id=? AND sh.id=?");$s->execute([$locationId,$clientId,$pointId,$shiftId]);return(int)$s->fetchColumn()>0;}
    public function history(int $id,string $action,int $actorId):void{$s=$this->pdo->prepare('SELECT * FROM guard_assignments WHERE id=?');$s->execute([$id]);$row=$s->fetch();if($row)$this->pdo->prepare("INSERT INTO assignment_history(assignment_id,action,snapshot_json,performed_by,occurred_at) VALUES(?,?,?,?,UTC_TIMESTAMP())")->execute([$id,$action,json_encode($row,JSON_UNESCAPED_UNICODE),$actorId]);}
    public function credential(int $id):?array{$s=$this->pdo->prepare("SELECT gc.*,u.full_name,gp.employee_number,gp.photo_path,gp.status guard_status FROM guard_credentials gc JOIN users u ON u.id=gc.guard_user_id JOIN guard_profiles gp ON gp.user_id=u.id WHERE gc.id=? LIMIT 1");$s->execute([$id]);return$s->fetch()?:null;}
    private function fetch(string $sql,array $params):array{$s=$this->pdo->prepare($sql);$s->execute($params);return$s->fetchAll();}
}
