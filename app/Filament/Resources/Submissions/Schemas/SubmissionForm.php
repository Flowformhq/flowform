<?php

declare(strict_types=1);

namespace App\Filament\Resources\Submissions\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SubmissionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('uuid')
                    ->label('UUID')
                    ->disabled(),
                TextInput::make('form.name')
                    ->label('Form')
                    ->disabled(),
                TextInput::make('status')
                    ->disabled(),
                KeyValue::make('data')
                    ->label('Submitted Payload Data')
                    ->disabled()
                    ->columnSpanFull()
                    ->visible(fn ($record) => ! empty($record?->data)),
                KeyValue::make('meta')
                    ->label('Submission Metadata')
                    ->disabled()
                    ->columnSpanFull()
                    ->visible(fn ($record) => ! empty($record?->meta)),
            ]);
    }
}
