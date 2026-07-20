<?php
declare(strict_types=1);
namespace Vigilancia\Services;
use RuntimeException;use Vigilancia\Exceptions\HttpException;
final class SupervisionEvidenceService
{
 public function __construct(private string$root){}
 public function save(string$data,string$watermark):array{if(!preg_match('#^data:image/(jpeg|png|webp);base64,(.+)$#s',$data,$m))throw new HttpException('La evidencia debe ser una fotografía capturada.',422);$bin=base64_decode($m[2],true);if($bin===false||!$bin||strlen($bin)>10*1024*1024)throw new HttpException('La fotografía no es válida o supera 10 MB.',422);$mime=(new \finfo(FILEINFO_MIME_TYPE))->buffer($bin);$ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];if(!isset($ext[$mime]))throw new HttpException('El contenido real de la evidencia no es válido.',422);$dir='storage/supervisions/'.gmdate('Y/m');$abs=$this->root.'/'.$dir;if(!is_dir($abs)&&!mkdir($abs,0775,true)&&!is_dir($abs))throw new RuntimeException('No fue posible preparar el almacenamiento.');$path=$dir.'/'.bin2hex(random_bytes(18)).'.'.$ext[$mime];if(file_put_contents($this->root.'/'.$path,$bin,LOCK_EX)===false)throw new RuntimeException('No fue posible guardar la evidencia.');return['path'=>$path,'mime'=>$mime,'size'=>strlen($bin)];}
}
