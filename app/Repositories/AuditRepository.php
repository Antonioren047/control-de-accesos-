<?php
declare(strict_types=1);
namespace Vigilancia\Repositories;
use PDO;
final class AuditRepository
{
 public function __construct(private PDO$pdo){}
 public function list(array$a,array$f,int$limit=300):array{$sql="SELECT sl.id,sl.event_type,sl.module_name,sl.record_type,sl.record_id,sl.ip_address,sl.user_agent,sl.context_json,sl.old_values_json,sl.new_values_json,sl.occurred_at,u.full_name,u.email FROM security_logs sl LEFT JOIN users u ON u.id=sl.user_id";$where=[];$p=[];if($a['role_code']!=='superadmin'){$where[]='u.surveillance_company_id=?';$p[]=$a['surveillance_company_id'];}$from=(string)($f['date_from']??'');$to=(string)($f['date_to']??'');if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)){$where[]='sl.occurred_at>=?';$p[]=$from.' 00:00:00';}if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)){$where[]='sl.occurred_at<=?';$p[]=$to.' 23:59:59';}if(trim((string)($f['module']??''))!==''){$where[]='sl.module_name=?';$p[]=trim((string)$f['module']);}if((int)($f['user_id']??0)){$where[]='sl.user_id=?';$p[]=(int)$f['user_id'];}if($where)$sql.=' WHERE '.implode(' AND ',$where);$limit=max(1,min(5000,$limit));return$this->all($sql." ORDER BY sl.occurred_at DESC LIMIT $limit",$p);}
 public function catalog(array$a):array{$p=[];$sql='SELECT id,full_name FROM users';if($a['role_code']!=='superadmin'){$sql.=' WHERE surveillance_company_id=?';$p[]=$a['surveillance_company_id'];}$modules=$this->pdo->query("SELECT DISTINCT COALESCE(module_name,SUBSTRING_INDEX(event_type,'.',1)) module FROM security_logs ORDER BY module")->fetchAll(PDO::FETCH_COLUMN);return['users'=>$this->all($sql.' ORDER BY full_name',$p),'modules'=>$modules];}
 private function all(string$q,array$p):array{$s=$this->pdo->prepare($q);$s->execute($p);return$s->fetchAll();}
}
