<?php
declare(strict_types=1);
namespace Vigilancia\Services;

use RuntimeException;
use Vigilancia\Exceptions\HttpException;

final class OperationalPhotoService
{
    public function __construct(private string $root){}
    public function save(string $dataUrl):string
    {
        if(!preg_match('#^data:image/(jpeg|png|webp);base64,(.+)$#s',$dataUrl,$match))throw new HttpException('Debes tomar una fotografía válida desde la cámara.',422);
        $binary=base64_decode($match[2],true);if($binary===false||strlen($binary)>5*1024*1024)throw new HttpException('La fotografía no es válida o supera 5 MB.',422);
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->buffer($binary);$extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];if(!isset($extensions[$mime]))throw new HttpException('El contenido real de la fotografía no es válido.',422);
        $directory=$this->root.'/storage/attendance/'.gmdate('Y/m');if(!is_dir($directory)&&!mkdir($directory,0775,true)&&!is_dir($directory))throw new RuntimeException('No fue posible preparar el almacenamiento de asistencias.');
        $relative='storage/attendance/'.gmdate('Y/m').'/'.bin2hex(random_bytes(20)).'.'.$extensions[$mime];if(file_put_contents($this->root.'/'.$relative,$binary,LOCK_EX)===false)throw new RuntimeException('No fue posible guardar la fotografía de entrada.');return$relative;
    }
}
