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

        return [
            ...$resendActions,
            DeleteAction::make(),
        ];
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
