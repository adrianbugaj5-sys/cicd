<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Model\Task;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TaskTest extends TestCase
{
    protected function setUp(): void
    {
        Task::resetIdCounter();
    }

    public function testCreatesTaskWithTitle(): void
    {
        $task = new Task('Buy milk');

        self::assertSame('Buy milk', $task->getTitle());
        self::assertFalse($task->isCompleted());
    }

    public function testTrimsWhitespaceFromTitle(): void
    {
        $task = new Task('  Buy milk  ');

        self::assertSame('Buy milk', $task->getTitle());
    }

    public function testThrowsOnEmptyTitle(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Task('   ');
    }

    public function testCompletesTask(): void
    {
        $task = new Task('Write tests');
        $task->complete();

        self::assertTrue($task->isCompleted());
    }

    public function testRenamesTask(): void
    {
        $task = new Task('Old title');
        $task->rename('New title');

        self::assertSame('New title', $task->getTitle());
    }

    public function testRenameThrowsOnEmptyTitle(): void
    {
        $task = new Task('Valid title');

        $this->expectException(InvalidArgumentException::class);

        $task->rename('');
    }

    public function testIdsAreAutoIncremented(): void
    {
        $first  = new Task('First');
        $second = new Task('Second');

        self::assertSame(1, $first->getId());
        self::assertSame(2, $second->getId());
    }
}
