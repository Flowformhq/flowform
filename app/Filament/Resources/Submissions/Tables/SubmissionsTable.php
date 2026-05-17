<?php

declare(strict_types=1);

namespace App\Filament\Resources\Submissions\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SubmissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('uuid')
                    ->label('UUID')
                    ->searchable()
                    ->limit(8),
                TextColumn::make('form.name')
                    ->label('Form')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'warning',
                        'completed' => 'success',
                        'abandoned' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('current_step')
                    ->label('Progress')
                    ->formatStateUsing(function ($state, $record) {
                        $totalSteps = $record->form->stepCount();

                        return $totalSteps > 0
                            ? "Step {$state} of {$totalSteps}"
                            : "Step {$state}";
                    })
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
