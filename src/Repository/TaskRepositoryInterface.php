<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Task;

interface TaskRepositoryInterface
{
    public function save(Task $task): void;

    public function findById(int $id): ?Task;

    /** @return Task[] */
    public function findAll(): array;

    public function delete(int $id): void;
}
