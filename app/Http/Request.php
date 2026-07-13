<?php
declare(strict_types=1); namespace Vigilancia\Http;
final class Request
{
    public function __construct(
        public string $method,
        public string $path,
        public array $query,
        public array $body,
        public array $headers = []
    ) {}

    public static function capture(): self
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        if ($base && str_starts_with($path, $base)) $path = substr($path, strlen($base)) ?: '/';
        $raw = file_get_contents('php://input') ?: '';
        $json = json_decode($raw, true);
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[str_replace('_', '-', substr($key, 5))] = $value;
            }
        }
        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            '/' . ltrim($path, '/'),
            $_GET,
            is_array($json) ? $json : $_POST,
            array_change_key_case($headers, CASE_LOWER)
        );
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }
}
