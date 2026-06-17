<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Task;
use App\Repository\TaskRepositoryInterface;
use RuntimeException;

class TaskService
{
    public function __construct(private readonly TaskRepositoryInterface $repository) {}

    public function createTask(string $title): Task
    {
        $task = new Task($title);
        $this->repository->save($task);

        return $task;
    }

    public function completeTask(int $id): Task
    {
        $task = $this->getOrFail($id);
        $task->complete();

        return $task;
    }

    public function renameTask(int $id, string $newTitle): Task
    {
        $task = $this->getOrFail($id);
        $task->rename($newTitle);

        return $task;
    }

    public function deleteTask(int $id): void
    {
        $this->getOrFail($id);
        $this->repository->delete($id);
    }

    /** @return Task[] */
    public function listPending(): array
    {
        return array_values(
            array_filter($this->repository->findAll(), fn(Task $t) => !$t->isCompleted())
        );
    }

    /** @return Task[] */
    public function listCompleted(): array
    {
        return array_values(
            array_filter($this->repository->findAll(), fn(Task $t) => $t->isCompleted())
        );
    }

    private function getOrFail(int $id): Task
    {
        $task = $this->repository->findById($id);

        if ($task === null) {
            throw new RuntimeException("Task #{$id} not found.");
        }

        return $task;
    }
}
