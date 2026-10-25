<?php

declare(strict_types=1);

namespace App\Filament\Resources\Forms\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class FormForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->readonly(),
                Select::make('type')
                    ->options([
                        'wizard' => 'Wizard Mode (Multi-Step)',
                        'endpoint' => 'Endpoint Mode (Headless / HTML)',
                    ])
                    ->default('wizard')
                    ->required()
                    ->live(),
                TextInput::make('notification_email')
                    ->label('Notification Email(s)')
                    ->placeholder('e.g. contact@buildeon.solutions')
                    ->helperText('Comma-separated email addresses to notify upon new submissions.')
                    ->visible(fn ($get) => $get('type') === 'endpoint'),
                TextInput::make('redirect_url')
                    ->label('Redirect URL')
                    ->placeholder('https://yourdomain.com/thank-you')
                    ->helperText('Optional fallback URL to redirect visitors after HTML form submission.')
                    ->visible(fn ($get) => $get('type') === 'endpoint'),
                TextInput::make('endpoint_url')
                    ->label('Headless Endpoint URL')
                    ->formatStateUsing(fn ($record) => $record?->slug ? url("/f/{$record->slug}") : 'Save form to generate endpoint URL')
                    ->readOnly()
                    ->copyable()
                    ->dehydrated(false)
                    ->visible(fn ($get) => $get('type') === 'endpoint'),
                Textarea::make('description')
                    ->columnSpanFull(),
                Toggle::make('is_active'),
                TextInput::make('version')
                    ->required()
                    ->numeric()
                    ->default(1)
                    ->minValue(1),
            ]);
    }
}
