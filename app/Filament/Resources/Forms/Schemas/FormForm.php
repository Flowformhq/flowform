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
                    ->label('Form Name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g. Portfolio Contact Form')
                    ->live(onBlur: true),
                Select::make('type')
                    ->label('Form Mode')
                    ->options([
                        'endpoint' => 'Mode 1: Headless Endpoint (HTML / Web3Forms Alternative)',
                        'wizard' => 'Mode 2: Multi-Step Wizard (Schema Branching)',
                    ])
                    ->default('endpoint')
                    ->required()
                    ->live(),
                TextInput::make('endpoint_url')
                    ->label('Your Live Form Endpoint')
                    ->formatStateUsing(function ($record) {
                        if (! $record) {
                            return 'Save form to generate unique endpoint URL';
                        }
                        $id = $record->access_key ?: $record->slug;
                        return url("/f/{$id}");
                    })
                    ->readOnly()
                    ->copyable()
                    ->dehydrated(false)
                    ->helperText('Copy this endpoint into your HTML <form action="..."> or fetch() request.')
                    ->visible(fn ($get) => $get('type') === 'endpoint'),
                TextInput::make('notification_email')
                    ->label('Notification Email(s)')
                    ->placeholder('e.g. you@company.com')
                    ->helperText('Comma-separated email addresses where form submissions will be forwarded.')
                    ->visible(fn ($get) => $get('type') === 'endpoint'),
                TextInput::make('redirect_url')
                    ->label('Custom Redirect URL')
                    ->placeholder('https://yourdomain.com/thanks')
                    ->helperText('Optional page to redirect visitors after HTML form submission.')
                    ->visible(fn ($get) => $get('type') === 'endpoint'),
                TextInput::make('allowed_domains')
                    ->label('Allowed Domain Origins (Spam & Hijack Protection)')
                    ->placeholder('e.g. yourdomain.com, *.yourdomain.com')
                    ->helperText('Optional comma-separated domains allowed to submit. Leave blank to accept from any origin.')
                    ->visible(fn ($get) => $get('type') === 'endpoint'),
                TextInput::make('access_key')
                    ->label('Unique Access Key')
                    ->readOnly()
                    ->copyable()
                    ->dehydrated(false)
                    ->visible(fn ($record) => ! empty($record?->access_key)),
                TextInput::make('slug')
                    ->label('Internal Slug')
                    ->maxLength(255)
                    ->readonly()
                    ->visible(fn () => auth()->user()?->is_admin),
                Textarea::make('description')
                    ->label('Notes / Description')
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Active (Accepting Submissions)')
                    ->default(true),
            ]);
    }
}
