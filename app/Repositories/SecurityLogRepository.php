<?php
declare(strict_types=1);
namespace Vigilancia\Repositories;

use PDO;

final class SecurityLogRepository
{
    public function __construct(private PDO $pdo) {}

    public function record(?int $userId, string $event, string $ip, string $userAgent, array $context = []): void
    {
        $module=explode('.',$event,2)[0]?:'system';$recordId=null;foreach(['record_id','event_id','round_id','supervision_id','visit_id','assignment_id','session_id','user_id','client_id']as$key)if(isset($context[$key])&&is_numeric($context[$key])){$recordId=(int)$context[$key];break;}$old=$context['old_values']??null;$new=$context['new_values']??null;
        $statement = $this->pdo->prepare(
            "INSERT INTO security_logs(user_id,event_type,module_name,record_type,record_id,ip_address,user_agent,context_json,old_values_json,new_values_json,occurred_at)
             VALUES(?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP())"
        );
        $statement->execute([
            $userId,
            $event,
            $module,
            $context['record_type']??$module,
            $recordId,
            $ip,
            $userAgent,
            $context === [] ? null : json_encode($context, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $old===null?null:json_encode($old,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
            $new===null?null:json_encode($new,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
        ]);
    }
}
