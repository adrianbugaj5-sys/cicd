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

    public function __construct(string $title)
    {
        if (trim($title) === '') {
            throw new InvalidArgumentException('Task title cannot be empty.');
        }

        $this->id = self::$nextId++;
        $this->title = trim($title);
        $this->completed = false;
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

    // Resets the auto-increment counter — useful in tests.
    public static function resetIdCounter(): void
    {
        self::$nextId = 1;
    }
}
