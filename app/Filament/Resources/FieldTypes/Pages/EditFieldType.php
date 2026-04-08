<?php

namespace App\Filament\Resources\FieldTypes\Pages;

use App\Filament\Resources\FieldTypes\FieldTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFieldType extends EditRecord
{
    protected static string $resource = FieldTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
