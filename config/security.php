<?php
declare(strict_types=1);use Vigilancia\Support\Env;return ['password_min_length'=>12,'session_max_minutes'=>(int)Env::get('SESSION_LIFETIME',1440),'csrf'=>true,'same_site'=>'Lax'];
