<?php
declare(strict_types=1);

namespace Vigilancia\Services;

use PDO;
use RuntimeException;
use Throwable;
use Vigilancia\Database\Connection;
use Vigilancia\Database\MigrationRunner;
use Vigilancia\Database\SeederRunner;
use Vigilancia\Validation\Validator;

final class InstallerService
{
    public function __construct(private string $root)
    {
    }

    public function install(array $input): array
    {
        $required = [
            'db_host',
            'db_port',
            'db_name',
            'db_user',
            'company_name',
            'admin_name',
            'admin_email',
            'admin_password',
            'timezone',
            'app_url',
        ];
        $errors = Validator::required($input, $required);

        if (!Validator::email($input['admin_email'] ?? '')) {
            $errors['admin_email'][] = 'El correo no es válido.';
        }
        if (!Validator::strongPassword($input['admin_password'] ?? '')) {
            $errors['admin_password'][] = 'Usa 12 caracteres, mayúscula, minúscula, número y símbolo.';
        }
        if (!preg_match('/^[A-Za-z0-9_]+$/', $input['db_name'] ?? '')) {
            $errors['db_name'][] = 'Usa solo letras, números y guion bajo.';
        }
        if ($errors) {
            throw new RuntimeException(json_encode($errors, JSON_UNESCAPED_UNICODE));
        }

        $cfg = [
            'host' => $input['db_host'],
            'port' => (int) $input['db_port'],
            'database' => $input['db_name'],
            'username' => $input['db_user'],
            'password' => $input['db_password'] ?? '',
            'charset' => 'utf8mb4',
        ];

        // En cPanel la base y el usuario se crean previamente desde el panel.
        // El usuario MySQL normalmente no tiene permiso CREATE DATABASE.
        $pdo = Connection::make($cfg);
        $this->assertEmptyDatabase($pdo, $cfg['database']);
        $env = $this->writeEnv($input);

        $migrations = (new MigrationRunner($pdo, $this->root . '/database/migrations'))->run();
        // Producción nunca carga los seeds de demostración.
        $seeds = (new SeederRunner($pdo, $this->root . '/database/seeds'))->run(false);

        $pdo->beginTransaction();
        try {
            $company = $pdo->prepare(
                'INSERT INTO surveillance_companies(name,timezone,is_active,created_at,updated_at) '
                . 'VALUES(?,?,1,UTC_TIMESTAMP(),UTC_TIMESTAMP())'
            );
            $company->execute([$input['company_name'], $input['timezone']]);
            $companyId = (int) $pdo->lastInsertId();

            $roleId = (int) $pdo->query("SELECT id FROM roles WHERE code='superadmin'")->fetchColumn();
            if ($roleId < 1) {
                throw new RuntimeException('No fue posible localizar el rol global.');
            }

            $user = $pdo->prepare(
                'INSERT INTO users(surveillance_company_id,role_id,full_name,email,password_hash,password_changed_at,is_active,created_at,updated_at) '
                . 'VALUES(?,?,?,?,?,UTC_TIMESTAMP(),1,UTC_TIMESTAMP(),UTC_TIMESTAMP())'
            );
            $user->execute([
                $companyId,
                $roleId,
                trim((string) $input['admin_name']),
                strtolower(trim((string) $input['admin_email'])),
                password_hash((string) $input['admin_password'], PASSWORD_DEFAULT),
            ]);

            if ((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() !== 1) {
                throw new RuntimeException('La instalación debe finalizar con un único usuario global.');
            }

            $pdo->exec(
                "INSERT INTO installer_logs(step,status,message,created_at) "
                . "VALUES('finish','success','Instalación de producción completada',UTC_TIMESTAMP())"
            );
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        if (file_put_contents($this->root . '/storage/installed.lock', gmdate('c'), LOCK_EX) === false) {
            throw new RuntimeException('No fue posible bloquear el instalador.');
        }

        return ['env' => $env, 'migrations' => $migrations, 'seeds' => $seeds];
    }

    private function assertEmptyDatabase(PDO $pdo, string $database): void
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables '
            . 'WHERE table_schema = ? AND table_type = \'BASE TABLE\''
        );
        $stmt->execute([$database]);
        if ((int) $stmt->fetchColumn() !== 0) {
            throw new RuntimeException(
                'La base de datos debe estar vacía. Crea una base nueva en cPanel para evitar mezclar información previa o de prueba.'
            );
        }
    }

    private function writeEnv(array $input): string
    {
        $pairs = [
            'APP_NAME' => 'Sistema de Vigilancia',
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => rtrim((string) $input['app_url'], '/'),
            'APP_TIMEZONE' => $input['timezone'],
            'SESSION_NAME' => 'vigilancia_session',
            'SESSION_LIFETIME' => '1440',
            'DB_HOST' => $input['db_host'],
            'DB_PORT' => $input['db_port'],
            'DB_DATABASE' => $input['db_name'],
            'DB_USERNAME' => $input['db_user'],
            'DB_PASSWORD' => $input['db_password'] ?? '',
            'DB_CHARSET' => 'utf8mb4',
        ];

        $lines = [];
        foreach ($pairs as $key => $value) {
            $lines[] = $key . '="' . str_replace(['\\', '"'], ['\\\\', '\\"'], (string) $value) . '"';
        }

        $path = $this->root . '/.env';
        if (file_put_contents($path, implode(PHP_EOL, $lines) . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('No fue posible escribir .env. Créalo manualmente usando .env.example.');
        }

        return $path;
    }
}
