<?php

declare(strict_types=1);

namespace App\Filament\Resources\Forms\Pages;

use App\Filament\Resources\Forms\FormResource;
use App\Models\Form;
use App\Models\NotificationRecipient;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\HtmlString;

class EditForm extends EditRecord
{
    protected static string $resource = FormResource::class;

    protected function getHeaderActions(): array
    {
        /** @var Form $form */
        $form = $this->getRecord();

        $resendActions = $form->recipients
            ->reject(fn (NotificationRecipient $recipient) => $recipient->isVerified())
            ->map(fn (NotificationRecipient $recipient) => Action::make("resend_verification_{$recipient->id}")
                ->label("Resend verification to {$recipient->email}")
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->action(fn () => $this->resendVerification($recipient)))
            ->values()
            ->all();

        $secretActions = [];

        if ($form->isEndpoint()) {
            $secretActions = [
                Action::make('generate_server_secret')
                    ->label(fn (): string => $this->getEndpointForm()->hasEndpointSecret() ? 'Rotate server secret' : 'Generate server secret')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->requiresConfirmation(fn (): bool => $this->getEndpointForm()->hasEndpointSecret())
                    ->modalHeading('Rotate server secret?')
                    ->modalDescription('Generating a new secret immediately invalidates the previous one.')
                    ->action(function (): void {
                        $secret = $this->getEndpointForm()->generateEndpointSecret();

                        $this->replaceMountedAction('revealServerSecret', ['secret' => $secret]);
                    }),
                Action::make('remove_server_secret')
                    ->label('Remove server secret')
                    ->icon('heroicon-o-shield-exclamation')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (): bool => $this->getEndpointForm()->hasEndpointSecret())
                    ->action(function (): void {
                        $this->getEndpointForm()->removeEndpointSecret();

                        Notification::make()
                            ->title('Server secret removed')
                            ->success()
                            ->send();
                    }),
            ];
        }

        return [
            ...$resendActions,
            ...$secretActions,
            DeleteAction::make(),
        ];
    }

    public function revealServerSecretAction(): Action
    {
        return Action::make('revealServerSecret')
            ->modalHeading('Server secret generated')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Done')
            ->modalContent(function (array $arguments): HtmlString {
                $secret = e((string) ($arguments['secret'] ?? ''));

                return new HtmlString(<<<HTML
                    <div class="space-y-3 text-sm">
                        <p class="font-medium text-amber-600 dark:text-amber-400">
                            Copy this secret now. For your security, it will not be shown again.
                        </p>
                        <div class="rounded-md bg-gray-100 p-3 font-mono text-xs break-all select-all dark:bg-gray-800">
                            {$secret}
                        </div>
                        <p class="text-gray-600 dark:text-gray-400">
                            Send it in the <code>X-FlowForm-Secret</code> request header, or pass <code>?_secret=...</code> in the URL query string for webhook builders that cannot set custom headers.
                        </p>
                    </div>
                HTML);
            });
    }

    protected function getEndpointForm(): Form
    {
        /** @var Form $form */
        $form = $this->getRecord();

        return $form;
    }

    protected function resendVerification(NotificationRecipient $recipient): void
    {
        if (! $recipient->resendVerification()) {
            Notification::make()
                ->title('Rate limit exceeded')
                ->body('Too many verification emails sent for this recipient. Please wait before requesting another.')
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Verification email sent')
            ->body("A verification link has been resent to {$recipient->email}.")
            ->success()
            ->send();
    }
}
