<?php

declare(strict_types=1);

namespace App\Filament\Resources\Forms\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FormsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Form Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('access_key')
                    ->label('Access Key / Endpoint')
                    ->state(fn ($record) => $record->access_key ?: $record->slug)
                    ->copyable()
                    ->tooltip('Click to copy access key')
                    ->fontFamily('mono')
                    ->searchable(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'endpoint' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'endpoint' => 'Mode 1 (Endpoint)',
                        default => 'Mode 2 (Wizard)',
                    })
                    ->sortable(),
                TextColumn::make('submissions_count')
                    ->counts('submissions')
                    ->label('Submissions')
                    ->badge()
                    ->color('primary')
                    ->sortable(),
                TextColumn::make('notification_email')
                    ->label('Recipient')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('is_active')
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
