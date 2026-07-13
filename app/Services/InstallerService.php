<?php
declare(strict_types=1); namespace Vigilancia\Services;
use PDO;use RuntimeException;use Vigilancia\Database\Connection;use Vigilancia\Database\MigrationRunner;use Vigilancia\Database\SeederRunner;use Vigilancia\Validation\Validator;
final class InstallerService{
 public function __construct(private string $root){}
 public function install(array $input):array{
  $required=['db_host','db_port','db_name','db_user','company_name','admin_name','admin_email','admin_password','timezone','app_url'];
  $errors=Validator::required($input,$required);
  if(!Validator::email($input['admin_email']??''))$errors['admin_email'][]='El correo no es válido.';
  if(!Validator::strongPassword($input['admin_password']??''))$errors['admin_password'][]='Usa 12 caracteres, mayúscula, minúscula, número y símbolo.';
  if(!preg_match('/^[A-Za-z0-9_]+$/',$input['db_name']??''))$errors['db_name'][]='Usa solo letras, números y guion bajo.';
  if($errors)throw new RuntimeException(json_encode($errors,JSON_UNESCAPED_UNICODE));
  $cfg=['host'=>$input['db_host'],'port'=>(int)$input['db_port'],'database'=>$input['db_name'],'username'=>$input['db_user'],'password'=>$input['db_password']??'','charset'=>'utf8mb4'];
  $server=Connection::make($cfg,false);$server->exec('CREATE DATABASE IF NOT EXISTS '.$cfg['database'].' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
  $pdo=Connection::make($cfg);$env=$this->writeEnv($input);
  $migrations=(new MigrationRunner($pdo,$this->root.'/database/migrations'))->run();
  $seeds=(new SeederRunner($pdo,$this->root.'/database/seeds'))->run(!empty($input['demo']));
  $pdo->beginTransaction();
  try{
   $company=$pdo->prepare("INSERT INTO surveillance_companies(name,timezone,is_active,created_at,updated_at) VALUES(?,?,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())");$company->execute([$input['company_name'],$input['timezone']]);$companyId=(int)$pdo->lastInsertId();
   $roleId=(int)$pdo->query("SELECT id FROM roles WHERE code='superadmin'")->fetchColumn();
   $user=$pdo->prepare("INSERT INTO users(surveillance_company_id,role_id,full_name,email,password_hash,password_changed_at,is_active,created_at,updated_at) VALUES(?,?,?,?,?,UTC_TIMESTAMP(),1,UTC_TIMESTAMP(),UTC_TIMESTAMP())");
   $user->execute([$companyId,$roleId,$input['admin_name'],strtolower($input['admin_email']),password_hash($input['admin_password'],PASSWORD_DEFAULT)]);
   $pdo->exec("INSERT INTO installer_logs(step,status,message,created_at) VALUES('finish','success','Instalación completada',UTC_TIMESTAMP())");$pdo->commit();
  }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
  if(file_put_contents($this->root.'/storage/installed.lock',gmdate('c'),LOCK_EX)===false)throw new RuntimeException('No fue posible bloquear el instalador.');
  return ['env'=>$env,'migrations'=>$migrations,'seeds'=>$seeds];
 }
 private function writeEnv(array $i):string{
  $pairs=['APP_NAME'=>'Sistema de Vigilancia','APP_ENV'=>'production','APP_DEBUG'=>'false','APP_URL'=>$i['app_url'],'APP_TIMEZONE'=>$i['timezone'],'SESSION_NAME'=>'vigilancia_session','SESSION_LIFETIME'=>'1440','DB_HOST'=>$i['db_host'],'DB_PORT'=>$i['db_port'],'DB_DATABASE'=>$i['db_name'],'DB_USERNAME'=>$i['db_user'],'DB_PASSWORD'=>$i['db_password']??'','DB_CHARSET'=>'utf8mb4'];
  $lines=[];foreach($pairs as $k=>$v)$lines[]=$k.'="'.str_replace(['\\','"'],['\\\\','\\"'],(string)$v).'"';$path=$this->root.'/.env';
  if(file_put_contents($path,implode(PHP_EOL,$lines).PHP_EOL,LOCK_EX)===false)throw new RuntimeException('No fue posible escribir .env. Créalo manualmente usando .env.example.');return $path;
 }
}
