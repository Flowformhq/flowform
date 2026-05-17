<?php

declare(strict_types=1);

namespace App\Filament\Resources\Steps\Schemas;

use App\Models\Form;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class StepForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('form_id')
                    ->label('Form')
                    ->options(Form::query()->pluck('name', 'id'))
                    ->required()
                    ->searchable(),
                Grid::make(2)
                    ->schema([
                        TextInput::make('step_number')
                            ->required()
                            ->numeric()
                            ->minValue(1),
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                    ]),
                Textarea::make('description')
                    ->columnSpanFull(),
                Grid::make(2)
                    ->schema([
                        Toggle::make('is_visible')
                            ->default(true),
                        TextInput::make('meta.icon')
                            ->label('Icon')
                            ->maxLength(255),
                    ]),
                KeyValue::make('validation_rules')
                    ->columnSpanFull(),
            ]);
    }
}
