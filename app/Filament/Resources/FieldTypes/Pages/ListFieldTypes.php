<?php

namespace App\Filament\Resources\FieldTypes\Pages;

use App\Filament\Resources\FieldTypes\FieldTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFieldTypes extends ListRecords
{
    protected static string $resource = FieldTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
