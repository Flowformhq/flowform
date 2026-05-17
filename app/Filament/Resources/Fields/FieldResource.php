<?php

declare(strict_types=1);

namespace App\Filament\Resources\Fields;

use App\Filament\Resources\Fields\Pages\CreateField;
use App\Filament\Resources\Fields\Pages\EditField;
use App\Filament\Resources\Fields\Pages\ListFields;
use App\Filament\Resources\Fields\RelationManagers\ConditionsRelationManager;
use App\Filament\Resources\Fields\Schemas\FieldForm;
use App\Filament\Resources\Fields\Tables\FieldsTable;
use App\Models\Field;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FieldResource extends Resource
{
    protected static ?string $model = Field::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedVariable;

    protected static \UnitEnum|string|null $navigationGroup = 'Forms';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return FieldForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FieldsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ConditionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFields::route('/'),
            'create' => CreateField::route('/create'),
            'edit' => EditField::route('/{record}/edit'),
        ];
    }
}
