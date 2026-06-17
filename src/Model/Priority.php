<?php

declare(strict_types=1);

namespace App\Model;

enum Priority: string
{
    case Low    = 'low';
    case Medium = 'medium';
    case High   = 'high';

    public function label(): string
    {
        return match ($this) {
            Priority::Low    => 'Niski',
            Priority::Medium => 'Średni',
            Priority::High   => 'Wysoki',
        };
    }
}
