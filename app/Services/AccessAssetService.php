<?php
declare(strict_types=1);

namespace Vigilancia\Services;

use RuntimeException;
use Vigilancia\Exceptions\HttpException;

final class AccessAssetService
{
    public function __construct(private string $root) {}

    public function saveImage(?string $dataUrl, string $category, bool $required = true): ?string
    {
        if (!$dataUrl) {
            if ($required) throw new HttpException('Debes tomar las fotografías requeridas desde la cámara.', 422);
            return null;
        }
        if (!preg_match('#^data:image/(jpeg|png|webp);base64,(.+)$#s', $dataUrl, $match)) {
            throw new HttpException('La evidencia debe ser una fotografía JPEG, PNG o WebP.', 422);
        }
        $binary = base64_decode($match[2], true);
        if ($binary === false || strlen($binary) > 8 * 1024 * 1024) throw new HttpException('La fotografía no es válida o supera 8 MB.', 422);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($binary);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($extensions[$mime])) throw new HttpException('El contenido real de la fotografía no es válido.', 422);
        $directory = "storage/access/$category/" . gmdate('Y/m');
        if (!is_dir($this->root . '/' . $directory) && !mkdir($this->root . '/' . $directory, 0775, true) && !is_dir($this->root . '/' . $directory)) throw new RuntimeException('No fue posible preparar el almacenamiento privado.');
        $relative = $directory . '/' . bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
        if (file_put_contents($this->root . '/' . $relative, $binary, LOCK_EX) === false) throw new RuntimeException('No fue posible guardar la fotografía.');
        return $relative;
    }

    public function createQr(string $token, string $kind, int $id): ?string
    {
        if (!class_exists(\Endroid\QrCode\QrCode::class)) return null;
        $directory = $this->root . '/storage/access/qr';
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) throw new RuntimeException('No fue posible preparar los códigos QR.');
        try {
            $qr = new \Endroid\QrCode\QrCode(data: $token, size: 420, margin: 18);
            $png = extension_loaded('gd') && class_exists(\Endroid\QrCode\Writer\PngWriter::class);
            $relative = 'storage/access/qr/' . $kind . '-' . $id . '-' . bin2hex(random_bytes(6)) . ($png ? '.png' : '.svg');
            $writer = $png ? new \Endroid\QrCode\Writer\PngWriter() : new \Endroid\QrCode\Writer\SvgWriter();
            $writer->write($qr)->saveToFile($this->root . '/' . $relative);
            return is_file($this->root . '/' . $relative) ? $relative : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
