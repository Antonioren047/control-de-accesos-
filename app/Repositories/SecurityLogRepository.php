<?php
declare(strict_types=1);
namespace Vigilancia\Repositories;

use PDO;

final class SecurityLogRepository
{
    public function __construct(private PDO $pdo) {}

    public function record(?int $userId, string $event, string $ip, string $userAgent, array $context = []): void
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO security_logs(user_id,event_type,ip_address,user_agent,context_json,occurred_at)
             VALUES(?,?,?,?,?,UTC_TIMESTAMP())"
        );
        $statement->execute([
            $userId,
            $event,
            $ip,
            $userAgent,
            $context === [] ? null : json_encode($context, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ]);
    }
}
