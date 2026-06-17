<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Task;

class InMemoryTaskRepository implements TaskRepositoryInterface
{
    /** @var array<int, Task> */
    private array $tasks = [];

    public function save(Task $task): void
    {
        $this->tasks[$task->getId()] = $task;
    }

    public function findById(int $id): ?Task
    {
        return $this->tasks[$id] ?? null;
    }

    public function findAll(): array
    {
        return array_values($this->tasks);
    }

    public function delete(int $id): void
    {
        unset($this->tasks[$id]);
    }
}
