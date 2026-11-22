<x-mail::message>
# Confirm Form Notifications

Someone added this email address (`{{ $recipient->email }}`) to receive form submissions for **{{ $form->name ?? 'a form' }}** on FlowForm.

Please confirm that you want to receive submission alert emails by clicking the button below:

<x-mail::button :url="$verificationUrl">
Confirm Notification Email
</x-mail::button>

This verification link will expire in 7 days. If you did not request this, you can safely ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
