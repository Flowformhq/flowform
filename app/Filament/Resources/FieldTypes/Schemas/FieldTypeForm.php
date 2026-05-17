<?php

declare(strict_types=1);

namespace App\Filament\Resources\FieldTypes\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class FieldTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('component')
                    ->required()
                    ->maxLength(255),
                KeyValue::make('meta')
                    ->columnSpanFull(),
            ]);
    }
}
