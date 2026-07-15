<?php
declare(strict_types=1);

$script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
$basePath = rtrim(dirname($script), '/.');

header('Location: ' . ($basePath === '' ? '' : $basePath) . '/public/', true, 302);
exit;
