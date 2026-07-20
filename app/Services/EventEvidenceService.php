<?php
declare(strict_types=1);
namespace Vigilancia\Services;

use RuntimeException;
use Vigilancia\Exceptions\HttpException;

final class EventEvidenceService
{
    public function __construct(private string $root) {}

    public function save(string $dataUrl,string $watermark,?float $duration=null):array
    {
        if(!preg_match('#^data:(image/(?:jpeg|png|webp)|video/(?:mp4|webm));base64,(.+)$#s',$dataUrl,$m))throw new HttpException('La evidencia debe capturarse como JPEG, PNG, WebP, MP4 o WebM.',422);
        $binary=base64_decode($m[2],true);if($binary===false||strlen($binary)===0)throw new HttpException('La evidencia capturada no es válida.',422);
        if(strlen($binary)>20*1024*1024)throw new HttpException('La evidencia supera el máximo de 20 MB.',422);
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->buffer($binary);$ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','video/mp4'=>'mp4','video/webm'=>'webm'];
        if(!isset($ext[$mime]))throw new HttpException('El contenido real de la evidencia no corresponde a un formato permitido.',422);
        $media=str_starts_with($mime,'image/')?'photo':'video';if($media==='video'&&($duration===null||$duration<=0||$duration>30.5))throw new HttpException('El video debe durar como máximo 30 segundos.',422);
        if($media==='photo'&&extension_loaded('gd'))$binary=$this->stamp($binary,$mime,$watermark);
        $dir='storage/events/'.gmdate('Y/m');$absolute=$this->root.'/'.$dir;if(!is_dir($absolute)&&!mkdir($absolute,0775,true)&&!is_dir($absolute))throw new RuntimeException('No fue posible preparar el almacenamiento privado.');
        $path=$dir.'/'.bin2hex(random_bytes(18)).'.'.$ext[$mime];if(file_put_contents($this->root.'/'.$path,$binary,LOCK_EX)===false)throw new RuntimeException('No fue posible guardar la evidencia.');
        return['path'=>$path,'mime'=>$mime,'media_type'=>$media,'size'=>strlen($binary),'duration'=>$media==='video'?$duration:null];
    }

    private function stamp(string$binary,string$mime,string$text):string
    {
        $image=@imagecreatefromstring($binary);if(!$image)return$binary;$width=imagesx($image);$height=imagesy($image);$bar=max(42,(int)round($height*.075));
        imagealphablending($image,true);$bg=imagecolorallocatealpha($image,3,25,58,24);imagefilledrectangle($image,0,$height-$bar,$width,$height,$bg);$white=imagecolorallocate($image,255,255,255);
        $label=mb_strimwidth($text,0,max(40,(int)($width/8)-4),'…');imagestring($image,$width>700?4:3,12,$height-$bar+(int)(($bar-16)/2),$label,$white);
        ob_start();if($mime==='image/jpeg')imagejpeg($image,null,86);elseif($mime==='image/png')imagepng($image,null,7);else imagewebp($image,null,86);$result=(string)ob_get_clean();imagedestroy($image);return$result?:$binary;
    }
}
