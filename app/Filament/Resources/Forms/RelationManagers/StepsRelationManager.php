<?php

declare(strict_types=1);

namespace App\Filament\Resources\Forms\RelationManagers;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StepsRelationManager extends RelationManager
{
    protected static string $relationship = 'steps';

    protected static ?string $title = 'Steps';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('step_number')
                    ->required()
                    ->numeric()
                    ->minValue(1),
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description'),
                Toggle::make('is_visible')
                    ->default(true),
                TextInput::make('meta.icon')
                    ->label('Icon')
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('step_number')
                    ->label('Step #')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('is_visible')
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Visible' : 'Hidden'),
                TextColumn::make('fields_count')
                    ->counts('fields')
                    ->label('Fields')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->reorderable('step_number')
            ->defaultSort('step_number')
            ->filters([
                //
            ]);
    }
}
