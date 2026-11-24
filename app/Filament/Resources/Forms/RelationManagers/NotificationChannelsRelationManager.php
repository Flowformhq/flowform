<?php

declare(strict_types=1);

namespace App\Filament\Resources\Forms\RelationManagers;

use App\Models\NotificationChannel;
use App\Models\Submission;
use App\Services\ChatNotifications\ChatNotificationManager;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class NotificationChannelsRelationManager extends RelationManager
{
    protected static string $relationship = 'notificationChannels';

    protected static ?string $title = 'Chat Notification Channels';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->label('Channel Type')
                    ->options([
                        NotificationChannel::TYPE_SLACK => 'Slack',
                        NotificationChannel::TYPE_DISCORD => 'Discord',
                        NotificationChannel::TYPE_TELEGRAM => 'Telegram',
                    ])
                    ->required()
                    ->live(),

                TextInput::make('config.webhook_url')
                    ->label('Webhook URL')
                    ->placeholder(fn (callable $get): string => match ($get('type')) {
                        NotificationChannel::TYPE_SLACK => 'https://hooks.slack.com/services/...',
                        NotificationChannel::TYPE_DISCORD => 'https://discord.com/api/webhooks/...',
                        default => 'https://...',
                    })
                    ->visible(fn (callable $get): bool => in_array($get('type'), [NotificationChannel::TYPE_SLACK, NotificationChannel::TYPE_DISCORD], true))
                    ->required(fn (callable $get): bool => in_array($get('type'), [NotificationChannel::TYPE_SLACK, NotificationChannel::TYPE_DISCORD], true))
                    ->url()
                    ->rules([
                        fn (callable $get) => function (string $attribute, mixed $value, \Closure $fail) use ($get) {
                            $type = (string) $get('type');
                            if (empty($value) || ! is_string($value)) {
                                return;
                            }
                            if (! ChatNotificationManager::isAllowedChatHost($type, $value)) {
                                $fail("The webhook URL must be a valid, allowed host for {$type}.");
                            }
                        },
                    ]),

                TextInput::make('config.bot_token')
                    ->label('Bot Token')
                    ->password()
                    ->revealable()
                    ->placeholder('123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ')
                    ->visible(fn (callable $get): bool => $get('type') === NotificationChannel::TYPE_TELEGRAM)
                    ->required(fn (callable $get): bool => $get('type') === NotificationChannel::TYPE_TELEGRAM),

                TextInput::make('config.chat_id')
                    ->label('Chat ID')
                    ->placeholder('-1001234567890')
                    ->visible(fn (callable $get): bool => $get('type') === NotificationChannel::TYPE_TELEGRAM)
                    ->required(fn (callable $get): bool => $get('type') === NotificationChannel::TYPE_TELEGRAM),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        NotificationChannel::TYPE_SLACK => 'Slack',
                        NotificationChannel::TYPE_DISCORD => 'Discord',
                        NotificationChannel::TYPE_TELEGRAM => 'Telegram',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        NotificationChannel::TYPE_SLACK => 'warning',
                        NotificationChannel::TYPE_DISCORD => 'info',
                        NotificationChannel::TYPE_TELEGRAM => 'primary',
                        default => 'gray',
                    }),

                TextColumn::make('destination')
                    ->label('Destination')
                    ->state(function (NotificationChannel $record): string {
                        if ($record->type === NotificationChannel::TYPE_TELEGRAM) {
                            return 'Chat ID: '.($record->config['chat_id'] ?? '—');
                        }

                        $url = (string) ($record->config['webhook_url'] ?? '');
                        if (empty($url)) {
                            return '—';
                        }

                        $host = parse_url($url, PHP_URL_HOST) ?? '';

                        return Str::limit($host.(parse_url($url, PHP_URL_PATH) ?? ''), 35);
                    }),

                TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Disabled'),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                Action::make('sendTest')
                    ->label('Send Test')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->action(function (NotificationChannel $record) {
                        try {
                            $form = $record->form ?? $this->getOwnerRecord();
                            $testSubmission = new Submission([
                                'form_id' => $record->form_id,
                                'status' => 'completed',
                                'data' => [
                                    'test_event' => 'FlowForm chat integration test',
                                    'channel' => $record->type,
                                    'timestamp' => now()->toIso8601String(),
                                ],
                            ]);
                            $testSubmission->uuid = (string) Str::uuid();
                            $testSubmission->setRelation('form', $form);

                            $formatter = ChatNotificationManager::formatterFor($record->type);
                            $formatter->send($record, $testSubmission);

                            Notification::make()
                                ->title('Test message sent successfully')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Failed to send test message')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
