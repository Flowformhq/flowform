<?php

namespace App\Filament\Resources\Submissions\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SubmissionValuesRelationManager extends RelationManager
{
    protected static string $relationship = 'values';

    protected static ?string $title = 'Submission Values';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('field.label')
                    ->label('Field')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('field.code')
                    ->label('Code')
                    ->searchable(),
                TextColumn::make('value')
                    ->limit(50)
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ]);
    }
}
