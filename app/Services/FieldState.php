<?php

declare(strict_types=1);

namespace App\Services;

readonly class FieldState
{
    public function __construct(
        public bool $isVisible,
        public bool $isRequired,
    ) {}
}
