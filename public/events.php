<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Repository\RedisTaskRepository;
use App\Service\TaskService;
use Predis\Client;

header('Content-Type: application/json');
header('Cache-Control: no-store');

$emoji     = mb_substr($_GET['emoji'] ?? '👤', 0, 4);
$token     = preg_replace('/[^a-z0-9]/', '', $_GET['token'] ?? '');
$clientSeq = (int) ($_GET['seq'] ?? 0);
$redisHost = getenv('REDIS_HOST') ?: '127.0.0.1';

try {
    $redis      = new Client(['host' => $redisHost]);
    $repository = new RedisTaskRepository($redisHost);
} catch (Throwable) {
    echo json_encode(['seq' => 0, 'pending' => [], 'completed' => [], 'online' => [], 'event' => null]);
    exit;
}

$service   = new TaskService($repository);
$serverSeq = (int) ($redis->get('task_manager:event_seq') ?? 0);

if ($token) {
    $redis->setex("task_manager:online:{$token}", 35, $emoji);
}

$taskToArray = fn ($t) => [
    'id'        => $t->getId(),
    'title'     => $t->getTitle(),
    'completed' => $t->isCompleted(),
    'priority'  => $t->getPriority()->value,
];

$onlineKeys = $redis->keys('task_manager:online:*');
$online     = [];
foreach ($onlineKeys as $key) {
    $val = $redis->get($key);
    if ($val) {
        $online[] = $val;
    }
}

$event = null;
if ($serverSeq > $clientSeq) {
    $raw   = $redis->lindex('task_manager:events', 0);
    $event = $raw ? json_decode($raw, true) : null;
}

echo json_encode([
    'seq'       => $serverSeq,
    'pending'   => array_map($taskToArray, $service->listPending()),
    'completed' => array_map($taskToArray, $service->listCompleted()),
    'online'    => array_values(array_unique($online)),
    'event'     => $event,
]);
