<?php
declare(strict_types=1);
namespace Vigilancia\Services;

use PDO;

final class ScopeService
{
    public function __construct(private PDO $pdo) {}

    public function client(array $actor, int $clientId): bool
    {
        if ($actor['role_code'] === 'superadmin') {
            return $this->exists('SELECT COUNT(*) FROM clients WHERE id=? AND surveillance_company_id=?', [$clientId, $actor['surveillance_company_id']]);
        }
        return $this->exists(
            'SELECT COUNT(*) FROM user_client_scopes s JOIN clients c ON c.id=s.client_id WHERE s.user_id=? AND s.client_id=? AND s.is_active=1 AND c.surveillance_company_id=?',
            [$actor['id'], $clientId, $actor['surveillance_company_id']]
        );
    }

    public function location(array $actor, int $locationId): bool
    {
        if ($actor['role_code'] === 'superadmin') {
            return $this->exists('SELECT COUNT(*) FROM locations l JOIN clients c ON c.id=l.client_id WHERE l.id=? AND c.surveillance_company_id=?', [$locationId, $actor['surveillance_company_id']]);
        }
        if ($actor['role_code'] === 'admin') {
            return $this->exists(
                'SELECT COUNT(*) FROM locations l JOIN clients c ON c.id=l.client_id LEFT JOIN user_client_scopes cs ON cs.client_id=c.id AND cs.user_id=? AND cs.is_active=1 LEFT JOIN user_location_scopes ls ON ls.location_id=l.id AND ls.user_id=? AND ls.is_active=1 WHERE l.id=? AND c.surveillance_company_id=? AND (cs.user_id IS NOT NULL OR ls.user_id IS NOT NULL)',
                [$actor['id'], $actor['id'], $locationId, $actor['surveillance_company_id']]
            );
        }
        if ($actor['role_code'] === 'supervisor') {
            return $this->exists('SELECT COUNT(*) FROM user_location_scopes WHERE user_id=? AND location_id=? AND is_active=1', [$actor['id'], $locationId]);
        }
        return false;
    }

    public function unit(array $actor, int $unitId): bool
    {
        if ($actor['role_code'] === 'resident') {
            return $this->exists('SELECT COUNT(*) FROM resident_units WHERE resident_user_id=? AND unit_id=? AND is_active=1', [$actor['id'], $unitId]);
        }
        $statement = $this->pdo->prepare('SELECT location_id FROM units WHERE id=? LIMIT 1');
        $statement->execute([$unitId]);
        $locationId = $statement->fetchColumn();
        return $locationId !== false && $this->location($actor, (int) $locationId);
    }

    public function accessPoint(array $actor, int $pointId): bool
    {
        $statement = $this->pdo->prepare('SELECT location_id FROM access_points WHERE id=? LIMIT 1');
        $statement->execute([$pointId]);
        $locationId = $statement->fetchColumn();
        return $locationId !== false && $this->location($actor, (int) $locationId);
    }

    private function exists(string $sql, array $parameters): bool
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        return (int) $statement->fetchColumn() > 0;
    }
}
