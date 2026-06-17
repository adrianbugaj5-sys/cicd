<?php

declare(strict_types=1);

namespace App\Model;

use InvalidArgumentException;

class Task
{
    private static int $nextId = 1;

    private int $id;
    private string $title;
    private bool $completed;
    private Priority $priority;

    public function __construct(string $title, Priority $priority = Priority::Medium)
    {
        if (trim($title) === '') {
            throw new InvalidArgumentException('Task title cannot be empty.');
        }

        $this->id       = self::$nextId++;
        $this->title    = trim($title);
        $this->completed = false;
        $this->priority  = $priority;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function isCompleted(): bool
    {
        return $this->completed;
    }

    public function getPriority(): Priority
    {
        return $this->priority;
    }

    public function complete(): void
    {
        $this->completed = true;
    }

    public function rename(string $title): void
    {
        if (trim($title) === '') {
            throw new InvalidArgumentException('Task title cannot be empty.');
        }

        $this->title = trim($title);
    }

    public function changePriority(Priority $priority): void
    {
        $this->priority = $priority;
    }

    public static function reconstruct(int $id, string $title, bool $completed, Priority $priority = Priority::Medium): self
    {
        $task = new self($title, $priority);
        $task->id        = $id;
        $task->completed = $completed;
        if ($id >= self::$nextId) {
            self::$nextId = $id + 1;
        }
        return $task;
    }

    public static function resetIdCounter(int $startFrom = 1): void
    {
        self::$nextId = $startFrom;
    }
}
