<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Priority;
use App\Model\Task;

class SessionTaskRepository implements TaskRepositoryInterface
{
    private const KEY = 'task_manager_tasks';

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = [];
        }

        $ids = array_column($_SESSION[self::KEY], 'id');
        Task::resetIdCounter($ids ? max($ids) + 1 : 1);
    }

    public function save(Task $task): void
    {
        $_SESSION[self::KEY][$task->getId()] = [
            'id'        => $task->getId(),
            'title'     => $task->getTitle(),
            'completed' => $task->isCompleted(),
            'priority'  => $task->getPriority()->value,
        ];
    }

    public function findById(int $id): ?Task
    {
        $d = $_SESSION[self::KEY][$id] ?? null;
        return $d ? Task::reconstruct($d['id'], $d['title'], $d['completed'], Priority::from($d['priority'])) : null;
    }

    public function findAll(): array
    {
        return array_map(
            fn (array $d) => Task::reconstruct($d['id'], $d['title'], $d['completed'], Priority::from($d['priority'])),
            $_SESSION[self::KEY]
        );
    }

    public function delete(int $id): void
    {
        unset($_SESSION[self::KEY][$id]);
    }
}
