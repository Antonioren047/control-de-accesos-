<?php
declare(strict_types=1);
namespace Vigilancia\Services;

use RuntimeException;
use Vigilancia\Exceptions\HttpException;

final class CredentialAssetService
{
    public function __construct(private string $root){}
    public function savePhoto(string $dataUrl):string
    {
        if(!preg_match('#^data:image/(jpeg|png|webp);base64,(.+)$#s',$dataUrl,$match))throw new HttpException('La fotografía obligatoria debe ser JPEG, PNG o WebP.',422);
        $binary=base64_decode($match[2],true);if($binary===false||strlen($binary)>5*1024*1024)throw new HttpException('La fotografía no es válida o supera 5 MB.',422);
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->buffer($binary);$extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];if(!isset($extensions[$mime]))throw new HttpException('El contenido real de la fotografía no es válido.',422);
        $directory=$this->root.'/storage/guards/photos';if(!is_dir($directory)&&!mkdir($directory,0775,true)&&!is_dir($directory))throw new RuntimeException('No fue posible preparar fotografías.');
        $relative='storage/guards/photos/'.bin2hex(random_bytes(16)).'.'.$extensions[$mime];if(file_put_contents($this->root.'/'.$relative,$binary,LOCK_EX)===false)throw new RuntimeException('No fue posible guardar la fotografía.');return $relative;
    }
    public function createQr(string $token,int $credentialId):?string
    {
        if(!class_exists(\Endroid\QrCode\QrCode::class))return null;
        $directory=$this->root.'/storage/guards/qr';if(!is_dir($directory)&&!mkdir($directory,0775,true)&&!is_dir($directory))throw new RuntimeException('No fue posible preparar códigos QR.');
        try{
            $qr=new \Endroid\QrCode\QrCode(data:$token,size:320,margin:12);
            $isPng=extension_loaded('gd')&&class_exists(\Endroid\QrCode\Writer\PngWriter::class);
            $extension=$isPng?'png':'svg';$relative='storage/guards/qr/credential-'.$credentialId.'-'.bin2hex(random_bytes(6)).'.'.$extension;
            $writer=$isPng?new \Endroid\QrCode\Writer\PngWriter():new \Endroid\QrCode\Writer\SvgWriter();
            $writer->write($qr)->saveToFile($this->root.'/'.$relative);return$relative;
        }catch(\Throwable){return null;}
    }
}
