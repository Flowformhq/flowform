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

class EditForm extends EditRecord
{
    protected static string $resource = FormResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [
            DeleteAction::make(),
        ];

        /** @var Form|null $form */
        $form = $this->getRecord();
        if ($form) {
            foreach ($form->recipients as $recipient) {
                $actions[] = Action::make("resend_verification_{$recipient->id}")
                    ->label('Resend verification')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('warning')
                    ->visible(fn () => ! $recipient->fresh()->isVerified())
                    ->action(fn () => $this->triggerResendVerification($recipient));
            }

            $actions[] = Action::make('resend_verification')
                ->label('Resend verification')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->visible(fn () => $form->recipients()->whereNull('verified_at')->exists())
                ->action(function (array $arguments = []) use ($form) {
                    $recipientId = $arguments['recipient'] ?? $arguments['recipient_id'] ?? null;
                    $recipient = $recipientId
                        ? $form->recipients()->find($recipientId)
                        : $form->recipients()->whereNull('verified_at')->first();

                    if ($recipient) {
                        $this->triggerResendVerification($recipient);
                    }
                });

            $actions[] = Action::make('resendVerification')
                ->label('Resend verification')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->visible(fn () => $form->recipients()->whereNull('verified_at')->exists())
                ->action(function (array $arguments = []) use ($form) {
                    $recipientId = $arguments['recipient'] ?? $arguments['recipient_id'] ?? null;
                    $recipient = $recipientId
                        ? $form->recipients()->find($recipientId)
                        : $form->recipients()->whereNull('verified_at')->first();

                    if ($recipient) {
                        $this->triggerResendVerification($recipient);
                    }
                });
        }

        return $actions;
    }

    public function triggerResendVerification(NotificationRecipient $recipient): void
    {
        if (! $recipient->resendVerification()) {
            Notification::make()
                ->title('Rate limit exceeded')
                ->body('Too many verification emails sent for this recipient. Please wait before requesting another.')
                ->danger()
                ->send();

            $this->addError('resend_verification', 'Rate limit exceeded.');

            return;
        }

        Notification::make()
            ->title('Verification email sent')
            ->body("A verification link has been resent to {$recipient->email}.")
            ->success()
            ->send();
    }
}
