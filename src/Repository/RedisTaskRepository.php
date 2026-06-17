<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Priority;
use App\Model\Task;
use Predis\Client;

class RedisTaskRepository implements TaskRepositoryInterface
{
    private const PREFIX  = 'task_manager:task:';
    private const NEXT_ID = 'task_manager:next_id';

    private Client $redis;

    public function __construct(string $host = '127.0.0.1', int $port = 6379)
    {
        $this->redis = new Client(['host' => $host, 'port' => $port]);

        $current = (int) $this->redis->get(self::NEXT_ID);
        Task::resetIdCounter($current > 0 ? $current : 1);
    }

    public function save(Task $task): void
    {
        $this->redis->set(self::PREFIX . $task->getId(), json_encode([
            'id'        => $task->getId(),
            'title'     => $task->getTitle(),
            'completed' => $task->isCompleted(),
            'priority'  => $task->getPriority()->value,
        ]));

        $this->redis->set(self::NEXT_ID, max(
            (int) $this->redis->get(self::NEXT_ID),
            $task->getId() + 1
        ));
    }

    public function findById(int $id): ?Task
    {
        $json = $this->redis->get(self::PREFIX . $id);
        if ($json === null) {
            return null;
        }

        $d = json_decode($json, true);
        return Task::reconstruct($d['id'], $d['title'], $d['completed'], Priority::from($d['priority']));
    }

    public function findAll(): array
    {
        $keys = $this->redis->keys(self::PREFIX . '*');
        if (empty($keys)) {
            return [];
        }

        $tasks = [];
        foreach ($keys as $key) {
            $d = json_decode($this->redis->get($key), true);
            $tasks[$d['id']] = Task::reconstruct($d['id'], $d['title'], $d['completed'], Priority::from($d['priority']));
        }

        ksort($tasks);
        return array_values($tasks);
    }

    public function delete(int $id): void
    {
        $this->redis->del(self::PREFIX . $id);
    }
}
