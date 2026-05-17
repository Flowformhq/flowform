<?php

declare(strict_types=1);

namespace App\Filament\Resources\FieldTypes;

use App\Filament\Resources\FieldTypes\Pages\CreateFieldType;
use App\Filament\Resources\FieldTypes\Pages\EditFieldType;
use App\Filament\Resources\FieldTypes\Pages\ListFieldTypes;
use App\Filament\Resources\FieldTypes\Schemas\FieldTypeForm;
use App\Filament\Resources\FieldTypes\Tables\FieldTypesTable;
use App\Models\FieldType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FieldTypeResource extends Resource
{
    protected static ?string $model = FieldType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static \UnitEnum|string|null $navigationGroup = 'Forms';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return FieldTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FieldTypesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFieldTypes::route('/'),
            'create' => CreateFieldType::route('/create'),
            'edit' => EditFieldType::route('/{record}/edit'),
        ];
    }
}
