<?php
declare(strict_types=1);

return static function(PDO$pdo):void{
    $columnExists=static function(string$table,string$column)use($pdo):bool{$s=$pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');$s->execute([$table,$column]);return(int)$s->fetchColumn()>0;};
    if(!$columnExists('event_types','event_scope'))$pdo->exec("ALTER TABLE event_types ADD COLUMN event_scope VARCHAR(20) NOT NULL DEFAULT 'incident' AFTER code");
    $pdo->exec("UPDATE event_types SET event_scope='system' WHERE code IN ('provider_entry','round','visit','shift_novelty')");
    if(!$columnExists('events','title'))$pdo->exec("ALTER TABLE events ADD COLUMN title VARCHAR(180) NULL AFTER guard_user_id");
    $pdo->exec("UPDATE events e JOIN event_types et ON et.id=e.event_type_id SET e.title=CONCAT(et.name,' #',e.id) WHERE e.title IS NULL OR e.title=''");
    $pdo->exec("ALTER TABLE events MODIFY title VARCHAR(180) NOT NULL");
    $s=$pdo->query("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='event_types' AND index_name='idx_event_types_scope'");if((int)$s->fetchColumn()===0)$pdo->exec("CREATE INDEX idx_event_types_scope ON event_types(event_scope,is_active,sort_order)");
};
