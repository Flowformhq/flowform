<?php

namespace App\Services;

readonly class FieldState
{
    public function __construct(
        public bool $isVisible,
        public bool $isRequired,
    ) {}
}
