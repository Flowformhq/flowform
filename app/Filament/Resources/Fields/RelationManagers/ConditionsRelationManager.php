<?php

declare(strict_types=1);

namespace App\Filament\Resources\Fields\RelationManagers;

use App\Models\Field;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ConditionsRelationManager extends RelationManager
{
    protected static string $relationship = 'conditions';

    protected static ?string $title = 'Conditions';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('depends_on_field_id')
                    ->label('Depends On Field')
                    ->options(function () {
                        $ownerId = $this->ownerRecord->id;
                        $formId = $this->ownerRecord->form_id;

                        return Field::query()
                            ->where('form_id', $formId)
                            ->where('id', '!=', $ownerId)
                            ->pluck('label', 'id');
                    })
                    ->required()
                    ->searchable(),
                Select::make('operator')
                    ->options([
                        'equals' => 'Equals',
                        'not_equals' => 'Not Equals',
                        'contains' => 'Contains',
                        'greater_than' => 'Greater Than',
                        'less_than' => 'Less Than',
                        'in' => 'In (comma-separated)',
                        'not_in' => 'Not In (comma-separated)',
                        'empty' => 'Is Empty',
                        'not_empty' => 'Is Not Empty',
                    ])
                    ->required()
                    ->live(),
                TextInput::make('value')
                    ->label('Value')
                    ->hidden(fn (callable $get): bool => in_array($get('operator'), ['empty', 'not_empty'])),
                Select::make('action')
                    ->options([
                        'show' => 'Show',
                        'hide' => 'Hide',
                        'require' => 'Require',
                    ])
                    ->required()
                    ->default('show'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('dependsOnField.label')
                    ->label('Depends On')
                    ->searchable(),
                TextColumn::make('operator'),
                TextColumn::make('value')
                    ->placeholder('—'),
                TextColumn::make('action')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'show' => 'success',
                        'hide' => 'danger',
                        'require' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->recordActions([
                DeleteAction::make(),
            ]);
    }
}
