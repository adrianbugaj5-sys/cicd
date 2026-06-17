<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Model\Task;
use App\Repository\InMemoryTaskRepository;
use App\Service\TaskService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class TaskServiceTest extends TestCase
{
    private TaskService $service;

    protected function setUp(): void
    {
        Task::resetIdCounter();
        $this->service = new TaskService(new InMemoryTaskRepository());
    }

    public function testCreatesAndStoresTask(): void
    {
        $task = $this->service->createTask('Deploy app');

        self::assertSame('Deploy app', $task->getTitle());
        self::assertCount(1, $this->service->listPending());
    }

    public function testCreateTaskThrowsOnEmptyTitle(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->createTask('');
    }

    public function testCompletesTask(): void
    {
        $task = $this->service->createTask('Write docs');
        $this->service->completeTask($task->getId());

        self::assertCount(0, $this->service->listPending());
        self::assertCount(1, $this->service->listCompleted());
    }

    public function testCompleteUnknownTaskThrows(): void
    {
        $this->expectException(RuntimeException::class);

        $this->service->completeTask(999);
    }

    public function testRenamesTask(): void
    {
        $task = $this->service->createTask('Old name');
        $this->service->renameTask($task->getId(), 'New name');

        self::assertSame('New name', $task->getTitle());
    }

    public function testDeletesTask(): void
    {
        $task = $this->service->createTask('To delete');
        $this->service->deleteTask($task->getId());

        self::assertCount(0, $this->service->listPending());
    }

    public function testDeleteUnknownTaskThrows(): void
    {
        $this->expectException(RuntimeException::class);

        $this->service->deleteTask(42);
    }

    public function testListPendingReturnsOnlyIncompleteTasks(): void
    {
        $t1 = $this->service->createTask('Task 1');
        $t2 = $this->service->createTask('Task 2');
        $this->service->completeTask($t1->getId());

        $pending = $this->service->listPending();

        self::assertCount(1, $pending);
        self::assertSame($t2->getId(), $pending[0]->getId());
    }

    public function testListCompletedReturnsOnlyFinishedTasks(): void
    {
        $t1 = $this->service->createTask('Task A');
        $this->service->createTask('Task B');
        $this->service->completeTask($t1->getId());

        $completed = $this->service->listCompleted();

        self::assertCount(1, $completed);
        self::assertSame($t1->getId(), $completed[0]->getId());
    }
}
