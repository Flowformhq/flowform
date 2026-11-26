<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

class WebhooksPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bolt';

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?string $title = 'Webhooks';

    protected static ?string $navigationLabel = 'Webhooks';

    protected static ?string $slug = 'webhooks';

    protected static ?int $navigationSort = 12;

    protected string $view = 'filament.pages.webhooks';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => WebhookSubscription::query()
                ->visibleTo(auth()->user())
                ->with('form')
                ->withMax([
                    'deliveries as last_succeeded_at' => fn (Builder $query): Builder => $query->where('status', WebhookDelivery::STATUS_SUCCEEDED),
                ], 'attempted_at')
                ->latest('id'))
            ->columns([
                TextColumn::make('source')
                    ->label('Source')
                    ->badge()
                    ->color('info')
                    ->placeholder('api'),

                TextColumn::make('target_host')
                    ->label('Target Host')
                    ->state(fn (WebhookSubscription $record): string => $record->targetHost()),

                TextColumn::make('form.name')
                    ->label('Form')
                    ->placeholder('All forms'),

                TextColumn::make('last_succeeded_at')
                    ->label('Last Success')
                    ->dateTime()
                    ->placeholder('Never')
                    ->sortable(),

                TextColumn::make('failure_count')
                    ->label('Failed Attempts')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('State')
                    ->badge()
                    ->state(fn (WebhookSubscription $record): string => (! $record->is_active || $record->disabled_at !== null) ? 'Disabled' : 'Active')
                    ->color(fn (string $state): string => $state === 'Active' ? 'success' : 'danger'),
            ])
            ->recordActions([
                Action::make('disable')
                    ->label('Disable')
                    ->icon('heroicon-o-pause-circle')
                    ->color('warning')
                    ->visible(fn (WebhookSubscription $record): bool => $record->is_active && $record->disabled_at === null)
                    ->action(function (WebhookSubscription $record): void {
                        $subscription = WebhookSubscription::query()
                            ->visibleTo(auth()->user())
                            ->whereKey($record->getKey())
                            ->first();

                        if (! $subscription) {
                            return;
                        }

                        $subscription->forceFill([
                            'is_active' => false,
                            'disabled_at' => now(),
                        ])->save();

                        Notification::make()
                            ->title('Webhook subscription disabled')
                            ->success()
                            ->send();
                    }),

                Action::make('enable')
                    ->label('Re-enable')
                    ->icon('heroicon-o-play-circle')
                    ->color('success')
                    ->visible(fn (WebhookSubscription $record): bool => ! $record->is_active || $record->disabled_at !== null)
                    ->action(function (WebhookSubscription $record): void {
                        /** @var User|null $user */
                        $user = auth()->user();
                        $subscription = WebhookSubscription::query()
                            ->visibleTo($user)
                            ->whereKey($record->getKey())
                            ->first();

                        if (! $subscription) {
                            return;
                        }

                        $activeCount = WebhookSubscription::active()
                            ->where('user_id', $subscription->user_id)
                            ->whereKeyNot($subscription->getKey())
                            ->count();

                        if ($activeCount >= WebhookSubscription::MAX_ACTIVE_PER_USER) {
                            Notification::make()
                                ->title('Active webhook subscription limit reached ('.WebhookSubscription::MAX_ACTIVE_PER_USER.').')
                                ->danger()
                                ->send();

                            return;
                        }

                        $subscription->forceFill([
                            'is_active' => true,
                            'disabled_at' => null,
                            'failure_count' => 0,
                        ])->save();

                        Notification::make()
                            ->title('Webhook subscription re-enabled')
                            ->success()
                            ->send();
                    }),

                Action::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (WebhookSubscription $record): void {
                        $deleted = WebhookSubscription::query()
                            ->visibleTo(auth()->user())
                            ->whereKey($record->getKey())
                            ->delete();

                        if ($deleted > 0) {
                            Notification::make()
                                ->title('Webhook subscription deleted')
                                ->success()
                                ->send();
                        }
                    }),
            ])
            ->emptyStateHeading('No webhook subscriptions yet')
            ->emptyStateDescription('Create REST hook subscriptions via /api/v1/webhooks or connect Zapier, n8n, or Make.');
    }

    /**
     * @return Collection<int, WebhookDelivery>
     */
    #[Computed]
    public function getRecentDeliveriesProperty(): Collection
    {
        return WebhookDelivery::query()
            ->visibleTo(auth()->user())
            ->with(['form', 'subscription'])
            ->latest('id')
            ->limit(50)
            ->get();
    }
}
