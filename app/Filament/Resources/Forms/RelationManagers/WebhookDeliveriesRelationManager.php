<?php

declare(strict_types=1);

namespace App\Filament\Resources\Forms\RelationManagers;

use App\Models\Form;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Services\WebhookDispatcher;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\HtmlString;

class WebhookDeliveriesRelationManager extends RelationManager
{
    protected static string $relationship = 'webhookDeliveries';

    protected static ?string $title = 'Webhook Deliveries';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        if ($user->is_admin) {
            return true;
        }

        return $ownerRecord instanceof Form && $ownerRecord->user_id === $user->id;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => WebhookDelivery::applyVisibleTo($query, auth()->user())
                ->with(['subscription', 'form'])
                ->latest('id'))
            ->columns([
                TextColumn::make('attempted_at')
                    ->label('Time')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('target_host')
                    ->label('Target Host')
                    ->state(fn (WebhookDelivery $record): string => $record->targetHost()),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        WebhookDelivery::STATUS_SUCCEEDED => 'success',
                        WebhookDelivery::STATUS_FAILED => 'danger',
                        default => 'warning',
                    }),

                TextColumn::make('response_code')
                    ->label('Response Code')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('attempt')
                    ->label('Attempt')
                    ->sortable(),

                TextColumn::make('duration_ms')
                    ->label('Duration')
                    ->formatStateUsing(fn (?int $state): string => $state !== null ? "{$state} ms" : '—')
                    ->sortable(),

                TextColumn::make('response_body')
                    ->label('Response Body')
                    ->limit(80)
                    ->placeholder('—')
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        WebhookDelivery::STATUS_SUCCEEDED => 'Succeeded',
                        WebhookDelivery::STATUS_FAILED => 'Failed',
                        WebhookDelivery::STATUS_PENDING => 'Pending',
                    ]),
            ])
            ->recordActions([
                Action::make('retry')
                    ->label('Retry')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (WebhookDelivery $record): bool => $record->status === WebhookDelivery::STATUS_FAILED)
                    ->action(function (WebhookDelivery $record): void {
                        /** @var User|null $user */
                        $user = auth()->user();
                        $authorized = WebhookDelivery::query()
                            ->visibleTo($user)
                            ->whereKey($record->getKey())
                            ->first();

                        if (! $authorized || ! $user) {
                            Notification::make()
                                ->title('Delivery not found')
                                ->danger()
                                ->send();

                            return;
                        }

                        $rateKey = 'webhook-retry:'.sha1($authorized->delivery_key);
                        if (RateLimiter::tooManyAttempts($rateKey, 1)) {
                            Notification::make()
                                ->title('Please wait before retrying this delivery again.')
                                ->warning()
                                ->send();

                            return;
                        }

                        RateLimiter::hit($rateKey, 60);

                        $dispatched = app(WebhookDispatcher::class)->retryDelivery($authorized, $user);

                        if ($dispatched) {
                            Notification::make()
                                ->title('Webhook retry enqueued')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Delivery could not be retried (already delivered or target disabled)')
                                ->warning()
                                ->send();
                        }
                    }),

                Action::make('viewResponse')
                    ->label('Response')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->visible(fn (WebhookDelivery $record): bool => filled($record->response_body))
                    ->modalHeading('Response body (truncated to 2 KB)')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(fn (WebhookDelivery $record): HtmlString => new HtmlString(
                        '<pre class="rounded-md bg-gray-100 p-3 text-xs whitespace-pre-wrap break-all dark:bg-gray-800">'
                        .e(mb_strcut((string) $record->response_body, 0, 2048, 'UTF-8'))
                        .'</pre>'
                    )),
            ])
            ->emptyStateHeading('No webhook deliveries yet')
            ->emptyStateDescription('Delivery attempts for this form appear here after submissions trigger an outbound webhook.');
    }
}
