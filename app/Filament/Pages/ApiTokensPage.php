<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\User;
use App\Support\TokenAbility;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokensPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?string $title = 'API Tokens';

    protected static ?string $navigationLabel = 'API Tokens';

    protected static ?string $slug = 'api-tokens';

    protected static ?int $navigationSort = 11;

    protected string $view = 'filament.pages.api-tokens';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => PersonalAccessToken::query()
                ->where('tokenable_type', User::class)
                ->where('tokenable_id', (int) auth()->id())
                ->latest('id'))
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('abilities')
                    ->badge()
                    ->color('info'),
                TextColumn::make('last_used_at')
                    ->label('Last Used')
                    ->dateTime()
                    ->placeholder('Never')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('revoke')
                    ->label('Revoke')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Revoke API token?')
                    ->modalDescription('Any integration or script using this token will lose access immediately.')
                    ->action(function (PersonalAccessToken $record): void {
                        $deleted = PersonalAccessToken::query()
                            ->where('tokenable_type', User::class)
                            ->where('tokenable_id', (int) auth()->id())
                            ->whereKey($record->getKey())
                            ->delete();

                        if ($deleted > 0) {
                            Notification::make()
                                ->title('API token revoked')
                                ->success()
                                ->send();
                        }
                    }),
            ])
            ->emptyStateHeading('No API tokens yet')
            ->emptyStateDescription('Create a scoped token to connect automation hubs, MCP clients, or custom apps.');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create_token')
                ->label('Create API Token')
                ->icon('heroicon-o-plus')
                ->schema([
                    TextInput::make('name')
                        ->label('Token Name')
                        ->placeholder('e.g. n8n automation')
                        ->required()
                        ->maxLength(255),
                    CheckboxList::make('abilities')
                        ->label('Abilities')
                        ->options(TokenAbility::options())
                        ->required()
                        ->minItems(1)
                        ->columns(1),
                ])
                ->action(function (array $data): void {
                    /** @var User $user */
                    $user = auth()->user();

                    $abilities = array_values(array_intersect(
                        array_map('strval', (array) ($data['abilities'] ?? [])),
                        TokenAbility::values()
                    ));

                    $token = $user->createToken((string) $data['name'], $abilities);

                    $this->replaceMountedAction('revealToken', [
                        'plainTextToken' => $token->plainTextToken,
                        'tokenName' => (string) $data['name'],
                    ]);
                }),
        ];
    }

    public function revealTokenAction(): Action
    {
        return Action::make('revealToken')
            ->modalHeading('API token created')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Done')
            ->modalContent(function (array $arguments): HtmlString {
                $token = e((string) ($arguments['plainTextToken'] ?? ''));
                $name = e((string) ($arguments['tokenName'] ?? ''));

                return new HtmlString(<<<HTML
                    <div class="space-y-3 text-sm">
                        <p class="font-medium text-amber-600 dark:text-amber-400">
                            Copy the token for <strong>{$name}</strong> now. For your security, it will never be shown again.
                        </p>
                        <div class="rounded-md bg-gray-100 p-3 font-mono text-xs break-all select-all dark:bg-gray-800">
                            {$token}
                        </div>
                        <p class="text-gray-600 dark:text-gray-400">
                            Send it as a bearer header: <code>Authorization: Bearer ...</code>
                        </p>
                    </div>
                HTML);
            });
    }
}
