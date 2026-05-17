<?php

declare(strict_types=1);

namespace App\Filament\Resources\Steps\Pages;

use App\Filament\Resources\Steps\StepResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStep extends CreateRecord
{
    protected static string $resource = StepResource::class;
}
