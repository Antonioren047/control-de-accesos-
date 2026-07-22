<?php
declare(strict_types=1);

namespace Vigilancia\Repositories;

use PDO;

final class UserAdminRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function administrativeUsers(int $companyId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT u.id,u.full_name,u.email,u.is_active,u.force_password_change,u.last_login_at,
                    r.code role_code,r.name role_name,
                    COALESCE((SELECT GROUP_CONCAT(c.id ORDER BY c.name) FROM user_client_scopes ucs JOIN clients c ON c.id=ucs.client_id WHERE ucs.user_id=u.id AND ucs.is_active=1),'') client_ids,
                    COALESCE((SELECT GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ', ') FROM user_client_scopes ucs JOIN clients c ON c.id=ucs.client_id WHERE ucs.user_id=u.id AND ucs.is_active=1),'') clients,
                    COALESCE((SELECT GROUP_CONCAT(l.id ORDER BY l.name) FROM user_location_scopes uls JOIN locations l ON l.id=uls.location_id WHERE uls.user_id=u.id AND uls.is_active=1),'') location_ids,
                    COALESCE((SELECT GROUP_CONCAT(l.name ORDER BY l.name SEPARATOR ', ') FROM user_location_scopes uls JOIN locations l ON l.id=uls.location_id WHERE uls.user_id=u.id AND uls.is_active=1),'') locations
             FROM users u JOIN roles r ON r.id=u.role_id
             WHERE u.surveillance_company_id=? AND r.code IN ('superadmin','admin','supervisor')
             ORDER BY FIELD(r.code,'superadmin','admin','supervisor'),u.full_name"
        );
        $statement->execute([$companyId]);
        return $statement->fetchAll();
    }

    public function catalog(int $companyId): array
    {
        $roles = $this->pdo->query(
            "SELECT code,name FROM roles WHERE code IN ('superadmin','admin','supervisor') AND is_active=1 ORDER BY FIELD(code,'superadmin','admin','supervisor')"
        )->fetchAll();
        $clients = $this->query(
            'SELECT id,name FROM clients WHERE surveillance_company_id=? AND is_active=1 ORDER BY name',
            [$companyId]
        );
        $locations = $this->query(
            'SELECT l.id,l.name,c.id client_id,c.name client_name FROM locations l JOIN clients c ON c.id=l.client_id WHERE c.surveillance_company_id=? AND l.is_active=1 AND c.is_active=1 ORDER BY c.name,l.name',
            [$companyId]
        );
        return ['roles' => $roles, 'clients' => $clients, 'locations' => $locations];
    }

    public function create(array $data, int $companyId): int
    {
        $roleId = $this->roleId($data['role_code']);
        $statement = $this->pdo->prepare(
            "INSERT INTO users(surveillance_company_id,role_id,full_name,email,password_hash,password_changed_at,is_active,theme_preference,force_password_change,created_at,updated_at)
             VALUES(?,?,?,?,?,UTC_TIMESTAMP(),1,'auto',1,UTC_TIMESTAMP(),UTC_TIMESTAMP())"
        );
        $statement->execute([$companyId, $roleId, $data['full_name'], $data['email'], $data['password_hash']]);
        $id = (int) $this->pdo->lastInsertId();
        $this->syncScopes($id, $data['client_ids'], $data['location_ids'], $data['actor_id']);
        return $id;
    }

    public function update(int $id, array $data): void
    {
        $roleId = $this->roleId($data['role_code']);
        $sql = 'UPDATE users SET role_id=?,full_name=?,email=?,updated_at=UTC_TIMESTAMP()';
        $params = [$roleId, $data['full_name'], $data['email']];
        if ($data['password_hash'] !== null) {
            $sql .= ',password_hash=?,password_changed_at=UTC_TIMESTAMP(),force_password_change=1';
            $params[] = $data['password_hash'];
        }
        $sql .= ' WHERE id=?';
        $params[] = $id;
        $this->pdo->prepare($sql)->execute($params);
        $this->syncScopes($id, $data['client_ids'], $data['location_ids'], $data['actor_id']);
    }

    public function findAdministrative(int $id, int $companyId): ?array
    {
        $statement = $this->pdo->prepare(
            "SELECT u.id,u.is_active,r.code role_code FROM users u JOIN roles r ON r.id=u.role_id
             WHERE u.id=? AND u.surveillance_company_id=? AND r.code IN ('superadmin','admin','supervisor') LIMIT 1"
        );
        $statement->execute([$id, $companyId]);
        return $statement->fetch() ?: null;
    }

    public function setActive(int $id, bool $active): void
    {
        $this->pdo->prepare('UPDATE users SET is_active=?,updated_at=UTC_TIMESTAMP() WHERE id=?')
            ->execute([$active ? 1 : 0, $id]);
        if (!$active) {
            $this->pdo->prepare('UPDATE user_sessions SET revoked_at=UTC_TIMESTAMP() WHERE user_id=? AND revoked_at IS NULL')
                ->execute([$id]);
        }
    }

    public function activeGlobalCount(int $companyId): int
    {
        $statement = $this->pdo->prepare(
            "SELECT COUNT(*) FROM users u JOIN roles r ON r.id=u.role_id WHERE u.surveillance_company_id=? AND r.code='superadmin' AND u.is_active=1"
        );
        $statement->execute([$companyId]);
        return (int) $statement->fetchColumn();
    }

    public function scopesBelongToCompany(array $clientIds, array $locationIds, int $companyId): bool
    {
        foreach ($clientIds as $id) {
            $statement = $this->pdo->prepare('SELECT COUNT(*) FROM clients WHERE id=? AND surveillance_company_id=?');
            $statement->execute([$id, $companyId]);
            if ((int) $statement->fetchColumn() === 0) return false;
        }
        foreach ($locationIds as $id) {
            $statement = $this->pdo->prepare('SELECT COUNT(*) FROM locations l JOIN clients c ON c.id=l.client_id WHERE l.id=? AND c.surveillance_company_id=?');
            $statement->execute([$id, $companyId]);
            if ((int) $statement->fetchColumn() === 0) return false;
        }
        return true;
    }

    private function syncScopes(int $userId, array $clientIds, array $locationIds, int $actorId): void
    {
        $this->pdo->prepare('UPDATE user_client_scopes SET is_active=0,updated_at=UTC_TIMESTAMP() WHERE user_id=?')->execute([$userId]);
        $this->pdo->prepare('UPDATE user_location_scopes SET is_active=0,updated_at=UTC_TIMESTAMP() WHERE user_id=?')->execute([$userId]);
        $client = $this->pdo->prepare(
            'INSERT INTO user_client_scopes(user_id,client_id,is_active,created_by,created_at,updated_at) VALUES(?,?,1,?,UTC_TIMESTAMP(),UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE is_active=1,created_by=VALUES(created_by),updated_at=UTC_TIMESTAMP()'
        );
        foreach ($clientIds as $id) $client->execute([$userId, $id, $actorId]);
        $location = $this->pdo->prepare(
            'INSERT INTO user_location_scopes(user_id,location_id,is_active,created_by,created_at,updated_at) VALUES(?,?,1,?,UTC_TIMESTAMP(),UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE is_active=1,created_by=VALUES(created_by),updated_at=UTC_TIMESTAMP()'
        );
        foreach ($locationIds as $id) $location->execute([$userId, $id, $actorId]);
    }

    private function roleId(string $code): int
    {
        $statement = $this->pdo->prepare("SELECT id FROM roles WHERE code=? AND code IN ('superadmin','admin','supervisor') AND is_active=1");
        $statement->execute([$code]);
        return (int) $statement->fetchColumn();
    }

    private function query(string $sql, array $params): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }
}
