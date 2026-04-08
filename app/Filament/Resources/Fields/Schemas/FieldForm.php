<?php

namespace App\Filament\Resources\Fields\Schemas;

use App\Models\FieldType;
use App\Models\Form;
use App\Models\Step;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class FieldForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('form_id')
                    ->label('Form')
                    ->options(Form::query()->pluck('name', 'id'))
                    ->required()
                    ->searchable()
                    ->live(onBlur: true),
                Select::make('step_id')
                    ->label('Step')
                    ->options(function (callable $get) {
                        $formId = $get('form_id');
                        if ($formId) {
                            return Step::query()->where('form_id', $formId)->pluck('title', 'id');
                        }

                        return Step::query()->pluck('title', 'id');
                    })
                    ->required()
                    ->searchable(),
                Select::make('field_type_id')
                    ->label('Field Type')
                    ->options(FieldType::query()->pluck('name', 'id'))
                    ->required()
                    ->searchable(),
                Grid::make(2)
                    ->schema([
                        TextInput::make('code')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('label')
                            ->required()
                            ->maxLength(255),
                    ]),
                TextInput::make('placeholder')
                    ->maxLength(255),
                Textarea::make('description')
                    ->columnSpanFull(),
                Grid::make(3)
                    ->schema([
                        Toggle::make('is_required'),
                        Toggle::make('is_repeatable'),
                        TextInput::make('order')
                            ->required()
                            ->numeric()
                            ->default(0),
                    ]),
                TextInput::make('default_value')
                    ->maxLength(255),
                KeyValue::make('validation_rules')
                    ->columnSpanFull(),
                KeyValue::make('config')
                    ->columnSpanFull(),
            ]);
    }
}
